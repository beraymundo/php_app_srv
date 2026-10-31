<?php
/** Gerador de recibo em Word. Os dados do último recibo ficam salvos para agilizar o próximo. */
/** @var Ferramenta $f */
/** @var string $a */
$arqUltimo = $f->caminho('dados/ultimo.json');
$u = ler_json($arqUltimo) + ['recebedor' => '', 'doc_recebedor' => '', 'pagador' => '', 'doc_pagador' => '', 'referente' => '', 'cidade' => 'Colatina (ES)', 'vias' => 2];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'gerar') {
    $d = array_map(fn($v) => trim((string)$v), $_POST);
    if ($d['valor'] === '' || $d['pagador'] === '' || $d['recebedor'] === '') {
        throw new RuntimeException('Preencha valor, quem paga e quem recebe.');
    }
    gravar_json($arqUltimo, array_intersect_key($d, $u));
    $valor = 'R$ ' . preg_replace('/^R\$\s*/', '', $d['valor']);
    $vias = max(1, min(2, (int)$d['vias']));
    $w = new DocxWriter('Calibri', 12, [851, 1134, 851, 1134]);
    for ($i = 0; $i < $vias; $i++) {
        if ($i > 0) {
            $w->p('- - - - - - - - - - - - - - - - - - - - - - - -  corte aqui  - - - - - - - - - - - - - - - - - - - - - - - -', ['jc' => 'center', 'sz' => 8, 'cor' => '888888', 'antes' => 360, 'depois' => 360]);
        }
        $w->tabela([[['texto' => 'RECIBO' . ($d['numero'] !== '' ? ' Nº ' . $d['numero'] : ''), 'b' => true, 'sz' => 18], ['texto' => $valor, 'b' => true, 'sz' => 16, 'jc' => 'right']]], [6000, 3600], ['bordas' => false, 'jc' => 'center']);
        $pag = $d['pagador'] . ($d['doc_pagador'] !== '' ? ", CPF/CNPJ {$d['doc_pagador']}," : '');
        $w->p("Recebi de $pag a importância de $valor ({$d['extenso']}), referente a {$d['referente']}.", ['jc' => 'justify', 'antes' => 360, 'depois' => 200, 'sz' => 12.5]);
        $w->p('Para maior clareza, firmo o presente recibo, dando plena quitação do valor acima.', ['jc' => 'justify', 'depois' => 360]);
        $w->p("{$d['cidade']}, {$d['data']}.", ['jc' => 'right', 'depois' => 720]);
        $w->p('_________________________________________', ['jc' => 'center', 'depois' => 0]);
        $w->p($d['recebedor'], ['jc' => 'center', 'depois' => 0, 'b' => true]);
        if ($d['doc_recebedor'] !== '') {
            $w->p('CPF/CNPJ ' . $d['doc_recebedor'], ['jc' => 'center', 'sz' => 10]);
        }
    }
    $f->pasta('saidas');
    $rel = $f->nomeLivre('saidas', 'Recibo - ' . $d['pagador'] . ' - ' . date('Y-m-d') . '.docx');
    $w->salvar($f->caminho($rel));
    flash('Recibo gerado: ' . basename($rel));
    redirecionar($f->url());
}

inicio_ferramenta($f);
?>
<section class="cartao">
  <form method="post" action="<?= h($f->url(['a' => 'gerar'])) ?>" class="form-grade" id="f">
    <label>Valor (R$) <input name="valor" required placeholder="Ex.: 150,00" autofocus></label>
    <label>Nº do recibo (opcional) <input name="numero"></label>
    <label class="largo">Valor por extenso (automático, pode ajustar) <input name="extenso" required></label>
    <label>Quem paga <input name="pagador" value="<?= h($u['pagador']) ?>" required></label>
    <label>CPF/CNPJ de quem paga (opcional) <input name="doc_pagador" value="<?= h($u['doc_pagador']) ?>"></label>
    <label>Quem recebe (assina) <input name="recebedor" value="<?= h($u['recebedor']) ?>" required></label>
    <label>CPF/CNPJ de quem recebe (opcional) <input name="doc_recebedor" value="<?= h($u['doc_recebedor']) ?>"></label>
    <label class="largo">Referente a <input name="referente" value="<?= h($u['referente']) ?>" placeholder="Ex.: aluguel do mês de novembro de 2026" required></label>
    <label>Cidade <input name="cidade" value="<?= h($u['cidade']) ?>"></label>
    <label>Data <input name="data" value="<?= h(data_extenso()) ?>"></label>
    <label>Vias por folha <select name="vias"><option value="1">1</option><option value="2" <?= (int)$u['vias'] === 2 ? 'selected' : '' ?>>2 (com linha de corte)</option></select></label>
    <div class="largo"><button class="btn btn-primario">🧾 Gerar recibo</button></div>
  </form>
</section>
<?php lista_saidas($f, 'saidas', 'Recibos gerados'); ?>
<script>
(function () {
  const f = document.getElementById('f');
  let manual = false;
  f.elements.extenso.addEventListener('input', () => (manual = true));
  f.elements.valor.addEventListener('input', () => {
    if (manual) return;
    const n = App.numero(f.elements.valor.value);
    f.elements.extenso.value = n === null ? '' : App.extenso(n, true);
  });
})();
</script>
<?php rodape(); ?>
