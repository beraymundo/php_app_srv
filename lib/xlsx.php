<?php
/**
 * Leitura e edição simples de .xlsx preservando estilos.
 * Edita valores de células existentes e a quantidade de linhas usadas (clonando o estilo da última linha).
 */
const S_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

final class Xlsx
{
    /** @var array<string,string> */
    public array $arquivos;
    private array $planilhas = [];
    private array $textos = [];
    /** índice de estilo => 'd' (data), 't' (hora), 'dt' (data e hora), '' */
    private array $tipoEstilo = [];
    private array $fmtEstilo = [];

    public function __construct(string $caminho)
    {
        $this->arquivos = Zip::ler($caminho);
        if (!isset($this->arquivos['xl/workbook.xml'])) {
            throw new RuntimeException('Este arquivo não parece ser uma planilha do Excel (.xlsx).');
        }
        $this->carregar();
    }

    public function salvar(string $caminho): void
    {
        Zip::gravar($caminho, $this->arquivos);
    }

    private static function xp(string $xml): array
    {
        $d = new DOMDocument();
        $d->preserveWhiteSpace = true;
        $d->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        $x = new DOMXPath($d);
        $x->registerNamespace('s', S_NS);
        $x->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        return [$d, $x];
    }

    private function rels(string $parte): array
    {
        $dir = dirname($parte);
        $p = "$dir/_rels/" . basename($parte) . '.rels';
        if (!isset($this->arquivos[$p])) {
            return [];
        }
        [$d] = self::xp($this->arquivos[$p]);
        $out = [];
        foreach ($d->getElementsByTagName('Relationship') as $r) {
            $alvo = $r->getAttribute('Target');
            $caminho = str_starts_with($alvo, '/') ? ltrim($alvo, '/') : "$dir/$alvo";
            $partes = [];
            foreach (explode('/', $caminho) as $s) {
                $s === '..' ? array_pop($partes) : ($s !== '' && $s !== '.' ? $partes[] = $s : null);
            }
            $out[$r->getAttribute('Id')] = implode('/', $partes);
        }
        return $out;
    }

    private function carregar(): void
    {
        $rels = $this->rels('xl/workbook.xml');
        [, $x] = self::xp($this->arquivos['xl/workbook.xml']);
        foreach ($x->query('//s:sheets/s:sheet') as $s) {
            $rid = $s->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            if (isset($rels[$rid], $this->arquivos[$rels[$rid]])) {
                $this->planilhas[] = ['nome' => $s->getAttribute('name'), 'parte' => $rels[$rid]];
            }
        }
        if (isset($this->arquivos['xl/sharedStrings.xml'])) {
            [, $x] = self::xp($this->arquivos['xl/sharedStrings.xml']);
            foreach ($x->query('/s:sst/s:si') as $si) {
                $t = '';
                foreach ($x->query('.//s:t[not(ancestor::s:rPh)]', $si) as $n) {
                    $t .= $n->textContent;
                }
                $this->textos[] = $t;
            }
        }
        if (isset($this->arquivos['xl/styles.xml'])) {
            [, $x] = self::xp($this->arquivos['xl/styles.xml']);
            $custom = [];
            foreach ($x->query('//s:numFmts/s:numFmt') as $nf) {
                $custom[(int)$nf->getAttribute('numFmtId')] = $nf->getAttribute('formatCode');
            }
            foreach ($x->query('/s:styleSheet/s:cellXfs/s:xf') as $i => $xf) {
                $id = (int)$xf->getAttribute('numFmtId');
                $codigo = $custom[$id] ?? '';
                $tipo = '';
                if (in_array($id, [14, 15, 16, 17, 22], true)) {
                    $tipo = $id === 22 ? 'dt' : 'd';
                } elseif (in_array($id, [18, 19, 20, 21, 45, 46, 47], true)) {
                    $tipo = 't';
                } elseif ($codigo !== '') {
                    $limpo = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $codigo);
                    $d = (bool)preg_match('/[dy]/i', $limpo);
                    $h = (bool)preg_match('/[hs]/i', $limpo);
                    $tipo = $d && $h ? 'dt' : ($d ? 'd' : ($h ? 't' : ''));
                }
                $this->tipoEstilo[$i] = $tipo;
                $this->fmtEstilo[$i] = $codigo;
            }
        }
    }

    public function nomes(): array
    {
        return array_column($this->planilhas, 'nome');
    }

    public static function colNum(string $col): int
    {
        $n = 0;
        foreach (str_split(strtoupper($col)) as $c) {
            $n = $n * 26 + (ord($c) - 64);
        }
        return $n;
    }

    public static function colLetra(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    private static function separar(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/i', $ref, $m);
        return [self::colNum($m[1]), (int)$m[2]];
    }

    private function formatar(string $v, int $estilo): array
    {
        $tipo = $this->tipoEstilo[$estilo] ?? '';
        if ($tipo === '' || !is_numeric($v)) {
            $n = (float)$v;
            if (is_numeric($v) && floor($n) == $n && abs($n) < 1e15) {
                return [(string)(int)$n, 'n'];
            }
            return [is_numeric($v) ? str_replace('.', ',', (string)$n) : $v, 'n'];
        }
        $serial = (float)$v;
        $base = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        $dt = $base->modify('+' . (int)round($serial * 86400) . ' seconds');
        $ano = stripos($this->fmtEstilo[$estilo] ?? 'y', 'y') !== false || ($this->fmtEstilo[$estilo] ?? '') === '';
        $data = $dt->format($ano ? 'd/m/Y' : 'd/m');
        $hora = $dt->format('H:i');
        return match ($tipo) {
            'd' => [$data, 'd'],
            't' => [$hora, 't'],
            default => ["$data $hora", 'dt'],
        };
    }

    /**
     * Dados de uma planilha: ['nome','linhas','colunas','cel'=>[ref=>['v','k','ro'?]]]
     */
    public function dados(int $idx): array
    {
        $pl = $this->planilhas[$idx] ?? throw new RuntimeException('Planilha inexistente.');
        [, $x] = self::xp($this->arquivos[$pl['parte']]);
        $cel = [];
        $maxL = 0;
        $maxC = 0;
        foreach ($x->query('//s:sheetData/s:row/s:c') as $c) {
            /** @var DOMElement $c */
            $ref = $c->getAttribute('r');
            $t = $c->getAttribute('t');
            $s = (int)$c->getAttribute('s');
            $vEl = $x->query('s:v', $c)->item(0);
            $f = $x->query('s:f', $c)->item(0);
            $v = $vEl ? $vEl->textContent : null;
            if ($t === 'inlineStr') {
                $v = '';
                foreach ($x->query('s:is//s:t', $c) as $n) {
                    $v .= $n->textContent;
                }
                $k = 's';
            } elseif ($t === 's') {
                $v = $this->textos[(int)$v] ?? '';
                $k = 's';
            } elseif ($t === 'b') {
                $v = $v === '1' ? 'VERDADEIRO' : 'FALSO';
                $k = 'b';
            } elseif ($t === 'str' || $t === 'e') {
                $k = 's';
            } elseif ($v !== null) {
                [$v, $k] = $this->formatar($v, $s);
            } else {
                $k = $this->tipoEstilo[$s] ?? '';
            }
            if (($v === null || $v === '') && !$f) {
                continue;
            }
            [$col, $lin] = self::separar($ref);
            $cel[$ref] = ['v' => (string)$v, 'k' => $k] + ($f ? ['ro' => 1] : []);
            $maxL = max($maxL, $lin);
            $maxC = max($maxC, $col);
        }
        return ['nome' => $pl['nome'], 'linhas' => $maxL, 'colunas' => $maxC, 'cel' => $cel];
    }

    /** Matriz de textos (para importar em tabelas do Word). */
    public function matriz(int $idx): array
    {
        $d = $this->dados($idx);
        $m = [];
        for ($l = 1; $l <= $d['linhas']; $l++) {
            $linha = [];
            for ($c = 1; $c <= $d['colunas']; $c++) {
                $linha[] = $d['cel'][self::colLetra($c) . $l]['v'] ?? '';
            }
            $m[] = $linha;
        }
        return $m;
    }

    /**
     * @param array<string,string> $edicoes ref => novo valor (texto como digitado)
     * @param int|null $linhas nova quantidade de linhas usadas (null = não mexe)
     */
    public function aplicar(int $idx, array $edicoes, ?int $linhas = null): void
    {
        $info = $this->dados($idx);
        $pl = $this->planilhas[$idx];
        [$d, $x] = self::xp($this->arquivos[$pl['parte']]);
        $sheetData = $x->query('//s:sheetData')->item(0);
        $rows = [];
        foreach ($x->query('s:row', $sheetData) as $r) {
            $rows[(int)$r->getAttribute('r')] = $r;
        }
        $obterLinha = function (int $n) use (&$rows, $d, $sheetData): DOMElement {
            if (isset($rows[$n])) {
                return $rows[$n];
            }
            $novo = $d->createElementNS(S_NS, 'row');
            $novo->setAttribute('r', (string)$n);
            $depois = null;
            foreach ($rows as $k => $r) {
                if ($k > $n && ($depois === null || $k < (int)$depois->getAttribute('r'))) {
                    $depois = $r;
                }
            }
            $depois ? $sheetData->insertBefore($novo, $depois) : $sheetData->appendChild($novo);
            return $rows[$n] = $novo;
        };
        $obterCel = function (string $ref) use ($obterLinha, $d, $x): DOMElement {
            [$col, $lin] = self::separar($ref);
            $row = $obterLinha($lin);
            $depois = null;
            foreach ($x->query('s:c', $row) as $c) {
                if ($c->getAttribute('r') === $ref) {
                    return $c;
                }
                if ($depois === null && self::separar($c->getAttribute('r'))[0] > $col) {
                    $depois = $c;
                }
            }
            $c = $d->createElementNS(S_NS, 'c');
            $c->setAttribute('r', $ref);
            $depois ? $row->insertBefore($c, $depois) : $row->appendChild($c);
            return $c;
        };
        $limpar = function (DOMElement $c) use ($x) {
            foreach (iterator_to_array($x->query('s:v|s:f|s:is', $c)) as $n) {
                $c->removeChild($n);
            }
            $c->removeAttribute('t');
        };

        // quantidade de linhas
        $antigo = $info['linhas'];
        if ($linhas !== null && $linhas >= 1 && $linhas !== $antigo) {
            $cols = max(1, $info['colunas']);
            if ($linhas > $antigo) {
                for ($l = $antigo + 1; $l <= $linhas; $l++) {
                    for ($c = 1; $c <= $cols; $c++) {
                        $mod = $x->query('s:c[@r="' . self::colLetra($c) . $antigo . '"]', $rows[$antigo] ?? $sheetData)->item(0);
                        $cel = $obterCel(self::colLetra($c) . $l);
                        $limpar($cel);
                        if ($mod && $mod->hasAttribute('s')) {
                            $cel->setAttribute('s', $mod->getAttribute('s'));
                        }
                    }
                }
            } else {
                for ($l = $linhas + 1; $l <= $antigo; $l++) {
                    if (!isset($rows[$l])) {
                        continue;
                    }
                    foreach ($x->query('s:c', $rows[$l]) as $cel) {
                        $limpar($cel);
                        [$col] = self::separar($cel->getAttribute('r'));
                        $mod = isset($rows[$antigo + 1]) ? $x->query('s:c[@r="' . self::colLetra($col) . ($antigo + 1) . '"]', $rows[$antigo + 1])->item(0) : null;
                        $mod && $mod->hasAttribute('s') ? $cel->setAttribute('s', $mod->getAttribute('s')) : $cel->removeAttribute('s');
                    }
                }
            }
            $this->ajustarTabelas($pl['parte'], $antigo, $linhas);
        }

        // valores
        foreach ($edicoes as $ref => $valor) {
            if (!preg_match('/^[A-Z]{1,3}\d{1,7}$/', (string)$ref) || !empty($info['cel'][$ref]['ro'])) {
                continue;
            }
            $valor = trim(str_replace(["\r\n", "\r"], "\n", (string)$valor));
            $cel = $obterCel($ref);
            $estilo = (int)$cel->getAttribute('s');
            $limpar($cel);
            if ($valor === '') {
                continue;
            }
            $num = $this->paraNumero($valor, $this->tipoEstilo[$estilo] ?? '', $info['cel'][$ref]['v'] ?? '');
            if ($num !== null) {
                $cel->appendChild($d->createElementNS(S_NS, 'v', $num));
            } else {
                $cel->setAttribute('t', 'inlineStr');
                $is = $d->createElementNS(S_NS, 'is');
                $t = $d->createElementNS(S_NS, 't');
                $t->setAttributeNS(XML_NS, 'xml:space', 'preserve');
                $t->appendChild($d->createTextNode($valor));
                $is->appendChild($t);
                $cel->appendChild($is);
            }
        }
        $this->arquivos[$pl['parte']] = $d->saveXML();
    }

    private function paraNumero(string $v, string $tipo, string $anterior): ?string
    {
        $base = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));
        if ($tipo === 'd' || $tipo === 'dt') {
            if (preg_match('#^(\d{1,2})/(\d{1,2})(?:/(\d{2,4}))?(?:\s+(\d{1,2}):(\d{2}))?$#', $v, $m)) {
                $ano = $m[3] ?? '';
                if ($ano === '' && preg_match('#/(\d{4})#', $anterior, $a)) {
                    $ano = $a[1];
                }
                $ano = $ano === '' ? date('Y') : (strlen($ano) === 2 ? '20' . $ano : $ano);
                $dt = DateTimeImmutable::createFromFormat('!Y-n-j', "$ano-{$m[2]}-{$m[1]}", new DateTimeZone('UTC'));
                if ($dt) {
                    $dias = (int)$base->diff($dt)->format('%r%a');
                    $frac = isset($m[4]) && $m[4] !== '' ? ((int)$m[4] * 60 + (int)$m[5]) / 1440 : 0;
                    return (string)($dias + $frac);
                }
            }
            return null;
        }
        if ($tipo === 't' && preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) {
            return (string)(((int)$m[1] * 3600 + (int)$m[2] * 60 + (int)($m[3] ?? 0)) / 86400);
        }
        if (preg_match('/^-?\d{1,15}([.,]\d+)?$/', $v) && !preg_match('/^0\d/', $v)) {
            return str_replace(',', '.', $v);
        }
        return null;
    }

    private function ajustarTabelas(string $parteSheet, int $antigo, int $novo): void
    {
        foreach ($this->rels($parteSheet) as $alvo) {
            if (!str_contains($alvo, '/tables/') || !isset($this->arquivos[$alvo])) {
                continue;
            }
            [$d, $x] = self::xp($this->arquivos[$alvo]);
            $mudou = false;
            foreach ([$d->documentElement, $x->query('//s:autoFilter')->item(0)] as $el) {
                if ($el && preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $el->getAttribute('ref'), $m) && (int)$m[4] === $antigo) {
                    $el->setAttribute('ref', "{$m[1]}{$m[2]}:{$m[3]}" . max($novo, (int)$m[2] + 1));
                    $mudou = true;
                }
            }
            if ($mudou) {
                $this->arquivos[$alvo] = $d->saveXML();
            }
        }
    }
}
