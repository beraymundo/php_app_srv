<?php
/**
 * Editor de .docx que preserva a formatação original.
 *
 * Como funciona:
 *  - modelo(): lê o XML e gera uma estrutura simples (parágrafos, runs de texto, tabelas)
 *    em que cada item guarda "src" = índice do nó original de onde veio.
 *  - O navegador edita só textos e a estrutura (duplicar/remover parágrafos, linhas, colunas).
 *  - aplicar(): reconstrói o XML clonando os nós originais indicados por "src" e trocando
 *    apenas o texto. Toda a formatação (fontes, bordas, espaçamento...) vem do nó clonado.
 */
const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const W14_NS = 'http://schemas.microsoft.com/office/word/2010/wordml';
const XML_NS = 'http://www.w3.org/XML/1998/namespace';

final class Docx
{
    /** @var array<string,string> */
    public array $arquivos;
    private array $estilos = [];
    private array $cacheEstilo = [];
    private array $padrao = [];
    private ?string $estiloParPadrao = null;
    private string $parteAtual = '';
    private array $usados = [];

    public function __construct(string $caminho)
    {
        $this->arquivos = Zip::ler($caminho);
        if (!isset($this->arquivos['word/document.xml'])) {
            throw new RuntimeException('Este arquivo não parece ser um documento do Word (.docx).');
        }
        // Conserta arquivos salvos por uma versão antiga deste editor, que perdia o prefixo w14:
        foreach ($this->arquivos as $nome => $xml) {
            if (str_starts_with($nome, 'word/') && str_ends_with($nome, '.xml') && str_contains($xml, 'xmlns:w14=')) {
                for ($i = 0; $i < 2; $i++) {
                    $xml = preg_replace('/(<w:[A-Za-z]+\b[^>]*?)\s(paraId|textId)="/', '$1 w14:$2="', $xml);
                }
                $this->arquivos[$nome] = $xml;
            }
        }
        $this->carregarEstilos();
    }

    public function salvar(string $caminho): void
    {
        Zip::gravar($caminho, $this->arquivos);
    }

    // ------------------------------------------------------------------ utilidades XML

    public static function dom(string $xml): DOMDocument
    {
        $d = new DOMDocument();
        $d->preserveWhiteSpace = true;
        if (!$d->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('XML inválido dentro do documento.');
        }
        return $d;
    }

    /** @return DOMElement[] */
    private static function filhos(DOMNode $el): array
    {
        $out = [];
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement) {
                $out[] = $c;
            }
        }
        return $out;
    }

    private static function filho(?DOMElement $el, string $nome): ?DOMElement
    {
        if (!$el) {
            return null;
        }
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === W_NS && $c->localName === $nome) {
                return $c;
            }
        }
        return null;
    }

    private static function eh(DOMElement $el, string $nome): bool
    {
        return $el->namespaceURI === W_NS && $el->localName === $nome;
    }

    private static function val(?DOMElement $el, string $attr = 'val'): ?string
    {
        if (!$el || !$el->hasAttributeNS(W_NS, $attr)) {
            return null;
        }
        return $el->getAttributeNS(W_NS, $attr);
    }

    private static function liga(?DOMElement $el): ?bool
    {
        if (!$el) {
            return null;
        }
        $v = self::val($el);
        return $v === null || !in_array(strtolower($v), ['0', 'false', 'off', 'none'], true);
    }

    // ------------------------------------------------------------------ relações / partes

    /** @return array<string,array{0:string,1:string}> rId => [tipo, caminho] */
    public function relacoes(string $parte): array
    {
        $dir = dirname($parte);
        $relsPath = ($dir === '.' ? '' : $dir . '/') . '_rels/' . basename($parte) . '.rels';
        if (!isset($this->arquivos[$relsPath])) {
            return [];
        }
        $d = self::dom($this->arquivos[$relsPath]);
        $out = [];
        foreach ($d->getElementsByTagName('Relationship') as $r) {
            if ($r->getAttribute('TargetMode') === 'External') {
                continue;
            }
            $alvo = $r->getAttribute('Target');
            $out[$r->getAttribute('Id')] = [$r->getAttribute('Type'), self::resolver($dir, $alvo)];
        }
        return $out;
    }

    private static function resolver(string $dir, string $alvo): string
    {
        if (str_starts_with($alvo, '/')) {
            return ltrim($alvo, '/');
        }
        $partes = [];
        foreach (explode('/', ($dir === '.' ? '' : $dir . '/') . $alvo) as $p) {
            if ($p === '..') {
                array_pop($partes);
            } elseif ($p !== '' && $p !== '.') {
                $partes[] = $p;
            }
        }
        return implode('/', $partes);
    }

    /** @return array<string,string> parte => rótulo */
    public function partes(): array
    {
        $lista = ['word/document.xml' => 'Documento'];
        $h = $f = 0;
        foreach ($this->relacoes('word/document.xml') as [$tipo, $alvo]) {
            if (!isset($this->arquivos[$alvo])) {
                continue;
            }
            if (str_ends_with($tipo, '/header')) {
                $lista[$alvo] = 'Cabeçalho' . (++$h > 1 ? " $h" : '');
            } elseif (str_ends_with($tipo, '/footer')) {
                $lista[$alvo] = 'Rodapé' . (++$f > 1 ? " $f" : '');
            }
        }
        return $lista;
    }

    private static function container(DOMDocument $d, string $parte): DOMElement
    {
        $raiz = $d->documentElement;
        if ($parte === 'word/document.xml') {
            $body = self::filho($raiz, 'body');
            if (!$body) {
                throw new RuntimeException('Documento sem corpo.');
            }
            return $body;
        }
        return $raiz;
    }

    // ------------------------------------------------------------------ estilos (só para exibição)

    private function carregarEstilos(): void
    {
        if (!isset($this->arquivos['word/styles.xml'])) {
            return;
        }
        $d = self::dom($this->arquivos['word/styles.xml']);
        $x = new DOMXPath($d);
        $x->registerNamespace('w', W_NS);
        $rp = $x->query('/w:styles/w:docDefaults/w:rPrDefault/w:rPr')->item(0);
        if ($rp instanceof DOMElement) {
            $this->padrao = self::fmtRun($rp);
        }
        foreach ($x->query('/w:styles/w:style') as $s) {
            /** @var DOMElement $s */
            $id = $s->getAttributeNS(W_NS, 'styleId');
            $pPr = self::filho($s, 'pPr');
            $rPr = self::filho($s, 'rPr');
            $this->estilos[$id] = [
                'base' => self::val(self::filho($s, 'basedOn')),
                'r' => $rPr ? self::fmtRun($rPr) : [],
                'jc' => self::val(self::filho($pPr, 'jc')),
                'num' => (bool)self::filho($pPr, 'numPr'),
            ];
            if ($s->getAttributeNS(W_NS, 'type') === 'paragraph' && self::liga($s->getAttributeNodeNS(W_NS, 'default') ? $s : null) && $s->getAttributeNS(W_NS, 'default') !== '0') {
                $this->estiloParPadrao ??= $id;
            }
        }
    }

    private function estilo(?string $id, int $prof = 0): array
    {
        $vazio = ['r' => [], 'jc' => null, 'num' => false];
        if ($id === null || !isset($this->estilos[$id]) || $prof > 15) {
            return $vazio;
        }
        if (isset($this->cacheEstilo[$id])) {
            return $this->cacheEstilo[$id];
        }
        $e = $this->estilos[$id];
        $b = $e['base'] ? $this->estilo($e['base'], $prof + 1) : $vazio;
        return $this->cacheEstilo[$id] = [
            'r' => array_merge($b['r'], $e['r']),
            'jc' => $e['jc'] ?? $b['jc'],
            'num' => $e['num'] || $b['num'],
        ];
    }

    private static function fmtRun(DOMElement $rPr): array
    {
        $f = [];
        foreach (['b' => 'b', 'i' => 'i', 'strike' => 's', 'caps' => 'caps'] as $tag => $k) {
            $v = self::liga(self::filho($rPr, $tag));
            if ($v !== null) {
                $f[$k] = $v;
            }
        }
        if ($u = self::filho($rPr, 'u')) {
            $f['u'] = self::val($u) !== 'none';
        }
        if (($sz = self::val(self::filho($rPr, 'sz'))) !== null) {
            $f['sz'] = (float)$sz / 2;
        }
        if (($c = self::val(self::filho($rPr, 'color'))) !== null && $c !== 'auto') {
            $f['cor'] = '#' . $c;
        }
        if ($fo = self::filho($rPr, 'rFonts')) {
            $nome = self::val($fo, 'ascii') ?? self::val($fo, 'hAnsi');
            if ($nome) {
                $f['fonte'] = $nome;
            }
        }
        if (($h = self::val(self::filho($rPr, 'highlight'))) !== null && $h !== 'none') {
            $f['fundo'] = $h;
        } elseif (($s = self::filho($rPr, 'shd')) && ($fill = self::val($s, 'fill')) && $fill !== 'auto') {
            $f['fundo'] = '#' . $fill;
        }
        if (($va = self::val(self::filho($rPr, 'vertAlign'))) && $va !== 'baseline') {
            $f['va'] = $va;
        }
        return $f;
    }

    // ------------------------------------------------------------------ leitura -> modelo

    public function modelo(): array
    {
        $partes = [];
        $pagina = null;
        foreach ($this->partes() as $nome => $rotulo) {
            $d = self::dom($this->arquivos[$nome]);
            $cont = self::container($d, $nome);
            $this->parteAtual = $nome;
            $partes[] = ['nome' => $nome, 'rotulo' => $rotulo, 'blocos' => $this->blocos($cont)];
            if ($nome === 'word/document.xml' && ($sp = self::filho($cont, 'sectPr'))) {
                $pg = self::filho($sp, 'pgSz');
                $mg = self::filho($sp, 'pgMar');
                $pagina = [
                    'w' => (float)(self::val($pg, 'w') ?? 11906),
                    'h' => (float)(self::val($pg, 'h') ?? 16838),
                    'me' => (float)(self::val($mg, 'left') ?? 1440),
                    'md' => (float)(self::val($mg, 'right') ?? 1440),
                    'mt' => (float)(self::val($mg, 'top') ?? 1440),
                    'mb' => (float)(self::val($mg, 'bottom') ?? 1440),
                ];
            }
        }
        return ['partes' => $partes, 'pagina' => $pagina ?? ['w' => 11906, 'h' => 16838, 'me' => 1440, 'md' => 1440, 'mt' => 1440, 'mb' => 1440]];
    }

    private function blocos(DOMElement $c): array
    {
        $out = [];
        foreach (self::filhos($c) as $i => $el) {
            $nome = $el->namespaceURI === W_NS ? $el->localName : '';
            if ($nome === 'p') {
                $out[] = $this->paragrafo($el, $i);
                continue;
            }
            if ($nome === 'tbl' && ($t = $this->tabela($el, $i))) {
                $out[] = $t;
                continue;
            }
            $b = ['t' => 'x', 'src' => $i, 'n' => $el->localName];
            if (in_array($el->localName, ['tbl', 'sdt', 'customXml', 'txbxContent'], true)) {
                $b['txt'] = mb_substr(trim(preg_replace('/\s+/', ' ', $el->textContent)), 0, 300);
            }
            $out[] = $b;
        }
        return $out;
    }

    private function paragrafo(DOMElement $p, int $i): array
    {
        $pPr = self::filho($p, 'pPr');
        $estiloId = self::val(self::filho($pPr, 'pStyle')) ?? $this->estiloParPadrao;
        $est = $this->estilo($estiloId);
        $jc = self::val(self::filho($pPr, 'jc')) ?? $est['jc'];
        $jc = ['both' => 'justify', 'distribute' => 'justify', 'start' => 'left', 'end' => 'right'][$jc ?? ''] ?? $jc;
        $base = array_merge($this->padrao, $est['r']);
        $runs = [];
        $editavel = false;
        foreach (self::runs($p) as $j => $r) {
            $m = $this->run($r, $j, $base);
            $editavel = $editavel || empty($m['ro']);
            $runs[] = $m;
        }
        if (!$editavel) {
            $marca = ($mr = self::filho($pPr, 'rPr')) ? self::fmtRun($mr) : [];
            $runs[] = ['src' => -1, 'x' => '', 'f' => array_merge($base, $marca)];
        }
        $b = ['t' => 'p', 'src' => $i, 'r' => $runs];
        if ($jc && $jc !== 'left') {
            $b['jc'] = $jc;
        }
        if (self::filho($pPr, 'numPr') || $est['num']) {
            $b['li'] = 1;
        }
        return $b;
    }

    /** Runs na ordem do documento (inclui runs dentro de hyperlinks, inserções etc.). */
    private static function runs(DOMElement $p): array
    {
        $out = [];
        foreach (self::filhos($p) as $c) {
            if ($c->namespaceURI !== W_NS) {
                continue;
            }
            switch ($c->localName) {
                case 'r':
                    $out[] = $c;
                    break;
                case 'hyperlink':
                case 'ins':
                case 'smartTag':
                case 'customXml':
                case 'fldSimple':
                case 'dir':
                case 'bdo':
                    array_push($out, ...self::runs($c));
                    break;
                case 'sdt':
                    if ($sc = self::filho($c, 'sdtContent')) {
                        array_push($out, ...self::runs($sc));
                    }
                    break;
            }
        }
        return $out;
    }

    /** Lê o texto de um run. $tipo recebe o motivo de ser somente-leitura (img, pb, fld, obj). */
    private static function textoRun(DOMElement $r, ?string &$tipo = null, ?DOMElement &$desenho = null): string
    {
        $txt = '';
        $tipo = null;
        foreach (self::filhos($r) as $c) {
            if ($c->namespaceURI !== W_NS) {
                $tipo ??= 'obj';
                continue;
            }
            switch ($c->localName) {
                case 'rPr':
                case 'lastRenderedPageBreak':
                case 'proofErr':
                case 'softHyphen':
                    break;
                case 't':
                    $txt .= $c->textContent;
                    break;
                case 'tab':
                    $txt .= "\t";
                    break;
                case 'cr':
                    $txt .= "\n";
                    break;
                case 'noBreakHyphen':
                    $txt .= '-';
                    break;
                case 'br':
                    $t = self::val($c, 'type');
                    if ($t === 'page' || $t === 'column') {
                        $tipo = 'pb';
                    } else {
                        $txt .= "\n";
                    }
                    break;
                case 'drawing':
                case 'pict':
                case 'object':
                    $tipo = 'img';
                    $desenho = $c;
                    break;
                case 'fldChar':
                case 'instrText':
                    $tipo ??= 'fld';
                    break;
                default:
                    $tipo ??= 'obj';
            }
        }
        return $txt;
    }

    private function run(DOMElement $r, int $j, array $base): array
    {
        $rPr = self::filho($r, 'rPr');
        $f = $base;
        if ($rPr) {
            if ($rs = self::val(self::filho($rPr, 'rStyle'))) {
                $f = array_merge($f, $this->estilo($rs)['r']);
            }
            $f = array_merge($f, self::fmtRun($rPr));
        }
        $tipo = null;
        $desenho = null;
        $txt = self::textoRun($r, $tipo, $desenho);
        $m = ['src' => $j, 'x' => $txt, 'f' => $f];
        if ($tipo !== null) {
            $m['ro'] = 1;
            $m['k'] = $tipo;
            if ($tipo === 'img' && $desenho && ($img = $this->imagem($desenho))) {
                $m['img'] = $img;
            }
        }
        return $m;
    }

    private function imagem(DOMElement $desenho): ?array
    {
        $blip = $desenho->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip')->item(0);
        $rid = $blip ? $blip->getAttributeNS(R_NS, 'embed') : '';
        if ($rid === '') {
            $id = $desenho->getElementsByTagNameNS('urn:schemas-microsoft-com:vml', 'imagedata')->item(0);
            $rid = $id ? $id->getAttributeNS(R_NS, 'id') : '';
        }
        $rels = $this->relacoes($this->parteAtual);
        if ($rid === '' || !isset($rels[$rid])) {
            return null;
        }
        $ext = $desenho->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'extent')->item(0);
        return [
            'p' => $rels[$rid][1],
            'w' => $ext ? round((int)$ext->getAttribute('cx') / 12700, 1) : null,
            'h' => $ext ? round((int)$ext->getAttribute('cy') / 12700, 1) : null,
        ];
    }

    private function tabela(DOMElement $t, int $i): ?array
    {
        $grid = [];
        foreach (self::filhos(self::filho($t, 'tblGrid') ?? $t) as $g) {
            if (self::eh($g, 'gridCol')) {
                $grid[] = (float)self::val($g, 'w');
            }
        }
        $linhas = [];
        $mesclada = false;
        $ri = 0;
        foreach (self::filhos($t) as $c) {
            if (self::eh($c, 'tr')) {
                $cels = [];
                $ci = 0;
                foreach (self::filhos($c) as $tc) {
                    if (self::eh($tc, 'tc')) {
                        $tcPr = self::filho($tc, 'tcPr');
                        $span = (int)(self::val(self::filho($tcPr, 'gridSpan')) ?? 1);
                        $vmEl = self::filho($tcPr, 'vMerge');
                        $vm = $vmEl ? (self::val($vmEl) ?? 'continue') : null;
                        $cel = ['src' => $ci, 'b' => $this->blocos($tc)];
                        if ($span > 1) {
                            $cel['span'] = $span;
                            $mesclada = true;
                        }
                        if ($vm) {
                            $cel['vm'] = $vm;
                            $mesclada = true;
                        }
                        $shd = self::filho($tcPr, 'shd');
                        if (($fill = self::val($shd, 'fill')) && $fill !== 'auto') {
                            $cel['fundo'] = '#' . $fill;
                        }
                        $cels[] = $cel;
                        $ci++;
                    } elseif (in_array($tc->localName, ['sdt', 'customXml'], true)) {
                        return null;
                    }
                }
                $linhas[] = ['src' => $ri++, 'c' => $cels];
            } elseif (in_array($c->localName, ['sdt', 'customXml'], true)) {
                return null;
            }
        }
        return ['t' => 'tbl', 'src' => $i, 'grid' => $grid, 'm' => $mesclada ? 1 : 0, 'l' => $linhas];
    }

    // ------------------------------------------------------------------ modelo -> XML

    public function aplicar(array $modelo): void
    {
        $partes = $this->partes();
        foreach ($modelo['partes'] ?? [] as $pm) {
            $nome = (string)($pm['nome'] ?? '');
            if (!isset($partes[$nome])) {
                continue;
            }
            $d = self::dom($this->arquivos[$nome]);
            $cont = self::container($d, $nome);
            $this->usados = [];
            $this->montarBlocos($cont, self::filhos($cont), (array)($pm['blocos'] ?? []), $nome);
            $this->arquivos[$nome] = $d->saveXML();
        }
    }

    /** @param DOMElement[] $orig nós originais (fonte dos clones) */
    private function montarBlocos(DOMElement $cont, array $orig, array $blocos, string $chave): bool
    {
        // Atenção: os originais só podem sair do documento DEPOIS de clonados; clonar um nó já
        // removido faz o libxml perder prefixos de atributos (w14:paraId, r:embed...) e o Word recusa o arquivo.
        $antigos = iterator_to_array($cont->childNodes);
        $temP = false;
        foreach ($blocos as $b) {
            $s = (int)($b['src'] ?? -1);
            $src = $orig[$s] ?? null;
            if (!$src) {
                continue;
            }
            $no = $src->cloneNode(true);
            $k = "$chave/$s";
            if (isset($this->usados[$k])) {
                self::limparIds($no);
            }
            $this->usados[$k] = true;
            $t = $b['t'] ?? 'x';
            if ($t === 'p' && self::eh($src, 'p')) {
                $this->montarParagrafo($no, $b);
                $temP = true;
            } elseif ($t === 'tbl' && self::eh($src, 'tbl')) {
                $this->montarTabela($no, $src, $b, $k);
            }
            $cont->appendChild($no);
        }
        foreach ($antigos as $n) {
            $cont->removeChild($n);
        }
        return $temP;
    }

    /** Remove ids que não podem se repetir quando um nó é duplicado. */
    private static function limparIds(DOMElement $no): void
    {
        $lista = [$no];
        foreach ($no->getElementsByTagNameNS('*', '*') as $e) {
            $lista[] = $e;
        }
        foreach ($lista as $e) {
            if ($e->hasAttributeNS(W14_NS, 'paraId')) {
                $e->removeAttributeNS(W14_NS, 'paraId');
            }
            if ($e->hasAttributeNS(W14_NS, 'textId')) {
                $e->removeAttributeNS(W14_NS, 'textId');
            }
        }
        foreach (['bookmarkStart', 'bookmarkEnd'] as $tag) {
            foreach (iterator_to_array($no->getElementsByTagNameNS(W_NS, $tag)) as $bm) {
                $bm->parentNode->removeChild($bm);
            }
        }
    }

    private function montarParagrafo(DOMElement $p, array $b): void
    {
        $runs = self::runs($p);
        $presentes = [];
        foreach ((array)($b['r'] ?? []) as $m) {
            $src = (int)($m['src'] ?? -1);
            if ($src >= 0) {
                $presentes[$src] = true;
            }
            if (!empty($m['ro'])) {
                continue;
            }
            $txt = str_replace(["\r\n", "\r"], "\n", (string)($m['x'] ?? ''));
            if ($src >= 0 && isset($runs[$src])) {
                $tipo = null;
                $atual = self::textoRun($runs[$src], $tipo);
                if ($tipo === null && $atual !== $txt) {
                    self::definirTexto($runs[$src], $txt);
                }
            } elseif ($src < 0 && $txt !== '') {
                $d = $p->ownerDocument;
                $r = $d->createElementNS(W_NS, 'w:r');
                if ($mr = self::filho(self::filho($p, 'pPr'), 'rPr')) {
                    $rPr = $mr->cloneNode(true);
                    foreach (self::filhos($rPr) as $c) {
                        if (in_array($c->localName, ['ins', 'del', 'moveFrom', 'moveTo', 'rPrChange'], true)) {
                            $rPr->removeChild($c);
                        }
                    }
                    $r->appendChild($rPr);
                }
                self::definirTexto($r, $txt);
                $p->appendChild($r);
            }
        }
        // runs que o editor removeu (ex.: imagem que foi para o parágrafo de baixo ao dar Enter)
        foreach ($runs as $j => $r) {
            if (!isset($presentes[$j]) && $r->parentNode) {
                $r->parentNode->removeChild($r);
            }
        }
    }

    private static function definirTexto(DOMElement $r, string $txt): void
    {
        foreach (self::filhos($r) as $c) {
            if (!self::eh($c, 'rPr')) {
                $r->removeChild($c);
            }
        }
        $d = $r->ownerDocument;
        foreach (preg_split('/(\n|\t)/', $txt, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $pedaco) {
            if ($pedaco === "\n") {
                $r->appendChild($d->createElementNS(W_NS, 'w:br'));
            } elseif ($pedaco === "\t") {
                $r->appendChild($d->createElementNS(W_NS, 'w:tab'));
            } else {
                $t = $d->createElementNS(W_NS, 'w:t');
                $t->setAttributeNS(XML_NS, 'xml:space', 'preserve');
                $t->appendChild($d->createTextNode($pedaco));
                $r->appendChild($t);
            }
        }
    }

    private function montarTabela(DOMElement $no, DOMElement $src, array $b, string $chave): void
    {
        $origLinhas = array_values(array_filter(self::filhos($src), fn($e) => self::eh($e, 'tr')));
        foreach (self::filhos($no) as $c) {
            if (self::eh($c, 'tr')) {
                $no->removeChild($c);
            }
        }
        $gridOrig = [];
        foreach (self::filhos(self::filho($src, 'tblGrid') ?? $src) as $g) {
            if (self::eh($g, 'gridCol')) {
                $gridOrig[] = (float)self::val($g, 'w');
            }
        }
        $grid = array_map('floatval', (array)($b['grid'] ?? $gridOrig));
        $mudou = count($grid) !== count($gridOrig);
        foreach ($grid as $i => $w) {
            $mudou = $mudou || abs($w - ($gridOrig[$i] ?? 0)) > 0.5;
        }
        $d = $no->ownerDocument;
        if ($mudou && ($tg = self::filho($no, 'tblGrid'))) {
            while ($tg->firstChild) {
                $tg->removeChild($tg->firstChild);
            }
            foreach ($grid as $w) {
                $gc = $d->createElementNS(W_NS, 'w:gridCol');
                $gc->setAttributeNS(W_NS, 'w:w', (string)(int)round($w));
                $tg->appendChild($gc);
            }
        }
        foreach ((array)($b['l'] ?? []) as $lm) {
            $rs = (int)($lm['src'] ?? -1);
            $or = $origLinhas[$rs] ?? null;
            if (!$or) {
                continue;
            }
            $tr = $or->cloneNode(true);
            foreach (self::filhos($tr) as $c) {
                if (self::eh($c, 'tc')) {
                    $tr->removeChild($c);
                }
            }
            if (isset($this->usados["$chave/r$rs"])) {
                self::limparIds($tr);
            }
            $this->usados["$chave/r$rs"] = true;
            $origCels = array_values(array_filter(self::filhos($or), fn($e) => self::eh($e, 'tc')));
            $col = 0;
            foreach ((array)($lm['c'] ?? []) as $cm) {
                $cs = (int)($cm['src'] ?? -1);
                $oc = $origCels[$cs] ?? null;
                if (!$oc) {
                    continue;
                }
                $tc = $oc->cloneNode(false);
                if (!$this->montarBlocos($tc, self::filhos($oc), (array)($cm['b'] ?? []), "$chave/r$rs/c$cs")) {
                    $tc->appendChild($d->createElementNS(W_NS, 'w:p'));
                }
                if ($mudou) {
                    $span = max(1, (int)($cm['span'] ?? 1));
                    $w = array_sum(array_slice($grid, $col, $span));
                    $col += $span;
                    if ($tw = self::filho(self::filho($tc, 'tcPr'), 'tcW')) {
                        $tw->setAttributeNS(W_NS, 'w:w', (string)(int)round($w));
                        $tw->setAttributeNS(W_NS, 'w:type', 'dxa');
                    }
                }
                $tr->appendChild($tc);
            }
            $no->appendChild($tr);
        }
    }

    // ------------------------------------------------------------------ localizar/substituir

    /**
     * Substitui texto no modelo, inclusive quando o trecho está dividido em vários runs
     * (o Word/Google Docs costumam quebrar o texto em pedaços com a mesma formatação).
     */
    public static function substituir(array &$modelo, string $de, string $para): int
    {
        if ($de === '') {
            return 0;
        }
        $total = 0;
        foreach ($modelo['partes'] as &$parte) {
            $total += self::substituirBlocos($parte['blocos'], $de, $para);
        }
        return $total;
    }

    private static function substituirBlocos(array &$blocos, string $de, string $para): int
    {
        $n = 0;
        foreach ($blocos as &$b) {
            if ($b['t'] === 'p') {
                $n += self::substituirParagrafo($b, $de, $para);
            } elseif ($b['t'] === 'tbl') {
                foreach ($b['l'] as &$l) {
                    foreach ($l['c'] as &$c) {
                        $n += self::substituirBlocos($c['b'], $de, $para);
                    }
                }
            }
        }
        return $n;
    }

    private static function substituirParagrafo(array &$p, string $de, string $para): int
    {
        $idx = [];
        $texto = '';
        $ini = [];
        foreach ($p['r'] as $i => $r) {
            if (empty($r['ro'])) {
                $idx[] = $i;
                $ini[] = strlen($texto);
                $texto .= $r['x'];
            }
        }
        $pos = [];
        $o = 0;
        while (($k = strpos($texto, $de, $o)) !== false) {
            $pos[] = $k;
            $o = $k + strlen($de);
        }
        foreach (array_reverse($pos) as $k) {
            $fim = $k + strlen($de);
            $a = $bIdx = null;
            foreach ($idx as $j => $ri) {
                $s = $ini[$j];
                $e = $s + strlen($p['r'][$ri]['x']);
                if ($a === null && $k >= $s && $k < $e) {
                    $a = $j;
                }
                if ($fim > $s && $fim <= $e) {
                    $bIdx = $j;
                }
            }
            if ($a === null || $bIdx === null) {
                continue;
            }
            $ra = $idx[$a];
            $rb = $idx[$bIdx];
            $xa = $p['r'][$ra]['x'];
            $xb = $p['r'][$rb]['x'];
            if ($a === $bIdx) {
                $p['r'][$ra]['x'] = substr($xa, 0, $k - $ini[$a]) . $para . substr($xa, $fim - $ini[$a]);
            } else {
                $p['r'][$ra]['x'] = substr($xa, 0, $k - $ini[$a]) . $para;
                for ($j = $a + 1; $j < $bIdx; $j++) {
                    $p['r'][$idx[$j]]['x'] = '';
                }
                $p['r'][$rb]['x'] = substr($xb, $fim - $ini[$bIdx]);
            }
        }
        return count($pos);
    }

    /** Texto puro do documento (para busca/prévia). */
    public static function textoPuro(array $modelo): string
    {
        $out = [];
        $andar = function (array $blocos) use (&$andar, &$out) {
            foreach ($blocos as $b) {
                if ($b['t'] === 'p') {
                    $out[] = implode('', array_map(fn($r) => empty($r['ro']) ? $r['x'] : '', $b['r']));
                } elseif ($b['t'] === 'tbl') {
                    foreach ($b['l'] as $l) {
                        foreach ($l['c'] as $c) {
                            $andar($c['b']);
                        }
                    }
                }
            }
        };
        foreach ($modelo['partes'] as $p) {
            $andar($p['blocos']);
        }
        return implode("\n", $out);
    }
}
