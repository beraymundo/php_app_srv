<?php
/** Gera listas/tabelas para imprimir (presença, assinaturas, entregas...) em Word. */
/** @var Ferramenta $f */
/** @var string $a */
$modelos = [
    'Lista de presença' => ['colunas' => "Nº:1\nNome:6\nAssinatura:5", 'linhas' => 30],
    'Lista de assinaturas' => ['colunas' => "Nome completo:6\nCPF:3\nAssinatura:5", 'linhas' => 25],
    'Controle de entregas' => ['colunas' => "Data:2\nNome:5\nItem:4\nQtd:1.5\nAssinatura:4", 'linhas' => 25],
    'Contatos' => ['colunas' => "Nome:5\nTelefone:3\nEndereço:6", 'linhas' => 25],
    'Inscrições' => ['colunas' => "Nº:1\nNome:5\nIdade:1.5\nTelefone:3\nResponsável:4", 'linhas' => 25],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'gerar') {
    $cols = [];
    foreach (preg_split('/\r?\n/', trim((string)$_POST['colunas'])) as $l) {
        if (trim($l) === '') {
            continue;
        }
        [$nome, $peso] = array_map('trim', explode(':', $l, 2)) + [1 => '1'];
        $cols[] = [$nome, max(0.3, (float)str_replace(',', '.', $peso) ?: 1)];
    }
    if (!$cols) {
        throw new RuntimeException('Informe pelo menos uma coluna.');
    }
    $paisagem = ($_POST['orientacao'] ?? '') === 'paisagem';
    $util = ($paisagem ? 16838 : 11906) - 2 * 851;
    $soma = array_sum(array_column($cols, 1));
    $larg = array_map(fn($c) => (int)floor($util * $c[1] / $soma), $cols);
    $n = max(1, min(1000, (int)$_POST['linhas']));
    $numerar = !empty($_POST['numerar']);
    $linhas = [array_column($cols, 0)];
    for ($i = 1; $i <= $n; $i++) {
        $l = array_fill(0, count($cols), '');
        if ($numerar) {
            $l[0] = (string)$i;
        }
        $linhas[] = $l;
    }
    $w = new DocxWriter('Calibri', (float)($_POST['fonte'] ?: 11), [851, 851, 851, 851], $paisagem);
    if (trim((string)$_POST['titulo']) !== '') {
        $w->p(trim((string)$_POST['titulo']), ['b' => true, 'sz' => 16, 'jc' => 'center', 'depois' => 60]);
    }
    if (trim((string)$_POST['subtitulo']) !== '') {
        $w->p(trim((string)$_POST['subtitulo']), ['jc' => 'center', 'depois' => 200, 'cor' => '444444']);
    }
    $w->tabela($linhas, $larg, ['cabecalho' => true, 'fundoCab' => 'E7E7E7', 'altura' => (int)round((float)$_POST['altura'] * 567), 'jcCelula' => $numerar ? ['center'] : []]);
    $f->pasta('saidas');
    $rel = $f->nomeLivre('saidas', (trim((string)$_POST['titulo']) ?: 'Lista') . '.docx');
    $w->salvar($f->caminho($rel));
    flash('Gerado: ' . basename($rel) . ' — dá para abrir no editor para ajustar.');
    redirecionar($f->url());
}

inicio_ferramenta($f);
?>
<section class="cartao">
  <div class="grupo" style="margin-bottom:12px">
    <span class="mudo pequeno">Começar de:</span>
    <?php foreach ($modelos as $nome => $m): ?>
    <button type="button" class="btn btn-p" data-modelo='<?= h(json_encode($m + ['titulo' => $nome], JSON_UNESCAPED_UNICODE)) ?>'><?= h($nome) ?></button>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?= h($f->url(['a' => 'gerar'])) ?>" class="form-grade" id="f">
    <label class="largo">Título <input name="titulo" value="Lista de presença"></label>
    <label class="largo">Subtítulo (opcional) <input name="subtitulo" placeholder="Ex.: Reunião do dia 15/11/2026 — Comunidade Sagrada Família"></label>
    <label>Colunas (uma por linha, <code>Nome:largura</code>)
      <textarea name="colunas" rows="6"><?= h($modelos['Lista de presença']['colunas']) ?></textarea>
      <small class="mudo">A largura é proporcional: <code>Nome:6</code> fica 6× maior que <code>Nº:1</code>.</small>
    </label>
    <div class="form-grade" style="grid-template-columns:1fr 1fr">
      <label>Quantidade de linhas <input type="number" name="linhas" min="1" max="1000" value="30"></label>
      <label>Altura da linha (cm) <input type="number" name="altura" step="0.1" min="0.4" value="0.8"></label>
      <label>Tamanho da letra <input type="number" name="fonte" step="0.5" min="6" value="11"></label>
      <label>Página <select name="orientacao"><option value="retrato">Em pé</option><option value="paisagem">Deitada</option></select></label>
      <label class="check"><input type="checkbox" name="numerar" checked> Numerar a 1ª coluna</label>
    </div>
    <div class="largo"><button class="btn btn-primario">📋 Gerar Word</button></div>
  </form>
</section>
<?php lista_saidas($f); ?>
<script>
document.querySelectorAll('[data-modelo]').forEach((b) => (b.onclick = () => {
  const m = JSON.parse(b.dataset.modelo);
  const f = document.getElementById('f');
  f.elements.titulo.value = m.titulo;
  f.elements.colunas.value = m.colunas;
  f.elements.linhas.value = m.linhas;
  f.elements.numerar.checked = m.colunas.startsWith('Nº');
}));
</script>
<?php rodape(); ?>
