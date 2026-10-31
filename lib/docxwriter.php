<?php
/**
 * Gerador simples de .docx do zero (para saídas como rifas, escalas, listas).
 * Uso:
 *   $w = new DocxWriter();
 *   $w->p('Título', ['b' => true, 'sz' => 16, 'jc' => 'center']);
 *   $w->tabela([['Nº','Nome'],['1','']], [800, 4000], ['cabecalho' => true]);
 *   $w->quebra();
 *   $w->salvar('saida.docx');
 */
final class DocxWriter
{
    private array $corpo = [];

    public function __construct(
        private string $fonte = 'Calibri',
        private float $tamanho = 11,
        private array $margens = [1134, 1134, 1134, 1134], // topo, direita, baixo, esquerda (twips) ≈ 2 cm
        private bool $paisagem = false,
    ) {
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function runs(string $texto, array $o): string
    {
        $rpr = '';
        if (!empty($o['b'])) {
            $rpr .= '<w:b/>';
        }
        if (!empty($o['i'])) {
            $rpr .= '<w:i/>';
        }
        if (!empty($o['u'])) {
            $rpr .= '<w:u w:val="single"/>';
        }
        if (!empty($o['cor'])) {
            $rpr .= '<w:color w:val="' . self::esc(ltrim($o['cor'], '#')) . '"/>';
        }
        if (!empty($o['sz'])) {
            $hp = (int)round($o['sz'] * 2);
            $rpr .= "<w:sz w:val=\"$hp\"/><w:szCs w:val=\"$hp\"/>";
        }
        $rpr = $rpr ? "<w:rPr>$rpr</w:rPr>" : '';
        $xml = '';
        foreach (explode("\n", $texto) as $i => $linha) {
            $xml .= '<w:r>' . $rpr . ($i ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . self::esc($linha) . '</w:t></w:r>';
        }
        return $xml;
    }

    private static function ppr(array $o): string
    {
        $p = '';
        if (isset($o['antes']) || isset($o['depois'])) {
            $p .= '<w:spacing w:before="' . (int)($o['antes'] ?? 0) . '" w:after="' . (int)($o['depois'] ?? 0) . '"/>';
        }
        if (!empty($o['jc'])) {
            $p .= '<w:jc w:val="' . ($o['jc'] === 'justify' ? 'both' : self::esc($o['jc'])) . '"/>';
        }
        return $p ? "<w:pPr>$p</w:pPr>" : '';
    }

    /** Parágrafo. Opções: b, i, u, sz (pt), cor, jc (left|center|right|justify), antes/depois (twips). */
    public function p(string $texto = '', array $o = []): self
    {
        $this->corpo[] = '<w:p>' . self::ppr($o) . ($texto !== '' ? self::runs($texto, $o) : '') . '</w:p>';
        return $this;
    }

    public function quebra(): self
    {
        $this->corpo[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    /**
     * @param array<array<string|array>> $linhas cada célula: texto ou ['texto'=>..., 'b'=>..., 'jc'=>...]
     * @param int[] $larguras em twips (1 cm ≈ 567)
     * Opções: cabecalho (negrito + repetir), jc (alinhamento da tabela), altura (twips), sz, bordas (bool, padrão true), fundoCab
     */
    public function tabela(array $linhas, array $larguras, array $o = []): self
    {
        $bordas = ($o['bordas'] ?? true)
            ? '<w:tblBorders>' . implode('', array_map(fn($b) => "<w:$b w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>", ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])) . '</w:tblBorders>'
            : '';
        $total = array_sum($larguras);
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . $total . '" w:type="dxa"/><w:jc w:val="' . self::esc($o['jc'] ?? 'center') . '"/>' . $bordas
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($larguras as $w) {
            $xml .= '<w:gridCol w:w="' . (int)$w . '"/>';
        }
        $xml .= '</w:tblGrid>';
        foreach ($linhas as $i => $linha) {
            $cab = !empty($o['cabecalho']) && $i === 0;
            $trpr = ($cab ? '<w:tblHeader/>' : '') . (!empty($o['altura']) ? '<w:trHeight w:val="' . (int)$o['altura'] . '"/>' : '');
            $xml .= '<w:tr>' . ($trpr ? "<w:trPr>$trpr</w:trPr>" : '');
            foreach (array_values($larguras) as $c => $w) {
                $cel = $linha[$c] ?? '';
                $co = is_array($cel) ? $cel : ['texto' => (string)$cel];
                $co += ['b' => $cab, 'sz' => $o['sz'] ?? null, 'jc' => $o['jcCelula'][$c] ?? null];
                $fundo = $cab && !empty($o['fundoCab']) ? '<w:shd w:val="clear" w:color="auto" w:fill="' . self::esc($o['fundoCab']) . '"/>' : '';
                if (isset($co['paragrafos'])) {
                    // célula com vários parágrafos: [[texto, opções], ...]
                    $conteudo = '';
                    foreach ($co['paragrafos'] as [$txt, $po]) {
                        $conteudo .= '<w:p>' . self::ppr($po) . ($txt !== '' ? self::runs($txt, $po) : '') . '</w:p>';
                    }
                    $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . (int)$w . '" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>' . ($conteudo ?: '<w:p/>') . '</w:tc>';
                    continue;
                }
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . (int)$w . '" w:type="dxa"/>' . $fundo . '<w:vAlign w:val="center"/></w:tcPr><w:p>'
                    . self::ppr(['jc' => $co['jc'], 'antes' => 0, 'depois' => 0]) . (($co['texto'] ?? '') !== '' ? self::runs((string)$co['texto'], $co) : '') . '</w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }
        $this->corpo[] = $xml . '</w:tbl>';
        return $this;
    }

    public function gerar(): string
    {
        [$w, $h] = $this->paisagem ? [16838, 11906] : [11906, 16838];
        [$t, $r, $b, $l] = $this->margens;
        $sect = "<w:sectPr><w:pgSz w:w=\"$w\" w:h=\"$h\"" . ($this->paisagem ? ' w:orient="landscape"' : '') . "/><w:pgMar w:top=\"$t\" w:right=\"$r\" w:bottom=\"$b\" w:left=\"$l\" w:header=\"709\" w:footer=\"709\" w:gutter=\"0\"/></w:sectPr>";
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . W_NS . '" xmlns:r="' . R_NS . '"><w:body>'
            . implode('', $this->corpo) . $sect . '</w:body></w:document>';
        $hp = (int)round($this->tamanho * 2);
        $f = self::esc($this->fonte);
        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="' . W_NS . '">'
            . "<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii=\"$f\" w:hAnsi=\"$f\" w:cs=\"$f\" w:eastAsia=\"$f\"/><w:sz w:val=\"$hp\"/><w:szCs w:val=\"$hp\"/><w:lang w:val=\"pt-BR\"/></w:rPr></w:rPrDefault>"
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';
        return Zip::gerar([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'word/document.xml' => $doc,
            'word/styles.xml' => $estilos,
        ]);
    }

    public function salvar(string $caminho): void
    {
        $dir = dirname($caminho);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($caminho, $this->gerar());
    }
}
