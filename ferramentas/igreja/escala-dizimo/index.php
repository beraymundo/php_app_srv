<?php
/**
 * Escala do Dízimo — versão web do antigo Escala_automated2.py
 * dados/duplas.txt   lista de casais (um por linha, na ordem do rodízio)
 * dados/estado.json  qual casal vem a seguir (continua o rodízio da última escala)
 * saidas/            escalas geradas (.txt e .docx)
 */
/** @var Ferramenta $f */
/** @var string $a */

$arqDuplas = $f->caminho('dados/duplas.txt');
$arqEstado = $f->caminho('dados/estado.json');
$duplas = is_file($arqDuplas) ? array_values(array_filter(array_map('trim', file($arqDuplas)), 'strlen')) : [];
$estado = ler_json($arqEstado);

function escala_dias(int $anoIni, int $mesIni, int $anoFim, int $mesFim, int $diaSemana): array
{
    $ini = new DateTimeImmutable(sprintf('%04d-%02d-01', $anoIni, $mesIni));
    $fim = (new DateTimeImmutable(sprintf('%04d-%02d-01', $anoFim, $mesFim)))->modify('last day of this month');
    $d = $ini->modify('+' . (($diaSemana - (int)$ini->format('w') + 7) % 7) . ' days');
    $out = [];
    for (; $d <= $fim; $d = $d->modify('+7 days')) {
        $out[] = $d;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'salvar_duplas') {
    $novas = array_values(array_filter(array_map('trim', (array)($_POST['duplas'] ?? [])), 'strlen'));
    $f->pasta('dados');
    $f->salvarComBackup('dados/duplas.txt', implode("\n", $novas) . "\n");
    flash(count($novas) . ' casais salvos. (A lista anterior ficou guardada no histórico.)');
    redirecionar($f->url());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'gerar') {
    if (!$duplas) {
        throw new RuntimeException('Cadastre os casais primeiro.');
    }
    [$anoIni, $mesIni] = array_map('intval', explode('-', (string)$_POST['inicio']));
    [$anoFim, $mesFim] = array_map('intval', explode('-', (string)$_POST['fim']));
    if (!$anoIni || !$anoFim || $anoIni * 12 + $mesIni > $anoFim * 12 + $mesFim) {
        throw new RuntimeException('Período inválido: o mês final deve ser igual ou depois do inicial.');
    }
    $diaSemana = max(0, min(6, (int)($_POST['dia_semana'] ?? 0)));
    $primeira = max(0, min(count($duplas) - 1, (int)$_POST['primeira']));
    $dias = escala_dias($anoIni, $mesIni, $anoFim, $mesFim, $diaSemana);

    $porMes = [];
    $i = $primeira;
    foreach ($dias as $d) {
        $mes = mb_convert_case(MESES[(int)$d->format('n') - 1], MB_CASE_TITLE) . ($anoIni !== $anoFim ? ' ' . $d->format('Y') : '');
        $porMes[$mes][] = [$d->format('d/m'), $duplas[$i % count($duplas)]];
        $i++;
    }
    $txt = '';
    foreach ($porMes as $mes => $linhas) {
        $txt .= "$mes\n";
        foreach ($linhas as [$data, $casal]) {
            $txt .= "$data $casal\n";
        }
        $txt .= "\n";
    }
    $base = sprintf('escala_%04d-%02d_a_%04d-%02d', $anoIni, $mesIni, $anoFim, $mesFim);
    $f->pasta('saidas');
    file_put_contents($f->caminho("saidas/$base.txt"), $txt);

    if (!empty($_POST['docx'])) {
        // Layout: duas colunas sem bordas; metade dos meses à esquerda, o resto à direita.
        // Cada mês: nome em negrito, uma linha "dd/mm Casal" por domingo e uma linha em branco depois.
        $w = new DocxWriter('Calibri', 12);
        if (trim((string)($_POST['titulo'] ?? '')) !== '') {
            $w->p(trim((string)$_POST['titulo']), ['b' => true, 'sz' => 14, 'jc' => 'center', 'depois' => 240]);
        }
        $meses = array_keys($porMes);
        $metade = (int)ceil(count($meses) / 2);
        $colunas = [[], []];
        foreach ($meses as $k => $mes) {
            $col = &$colunas[$k < $metade ? 0 : 1];
            if ($col) {
                $col[] = ['', ['depois' => 200]];
            }
            $col[] = [$mes, ['b' => true, 'depois' => 200]];
            foreach ($porMes[$mes] as [$data, $casal]) {
                $col[] = ["$data $casal", ['depois' => 200]];
            }
            unset($col);
        }
        $w->tabela([[['paragrafos' => $colunas[0]], ['paragrafos' => $colunas[1]]]], [4800, 4800], ['bordas' => false]);
        $w->salvar($f->caminho("saidas/$base.docx"));
    }

    $estado = ['proxima' => $i % count($duplas), 'ultima' => "$base.txt", 'gerada_em' => date('c')];
    gravar_json($arqEstado, $estado);
    flash('Escala gerada! O próximo casal do rodízio agora é: ' . $duplas[$estado['proxima']] . '.');
    redirecionar($f->url(['ver' => "$base.txt"]));
}

// ------------------------------------------------------------------ página
$proxima = (int)($estado['proxima'] ?? 0) % max(1, count($duplas));
$mesSeg = date('Y-m', strtotime('first day of next month'));
$verArq = isset($_GET['ver']) ? basename((string)$_GET['ver']) : ($estado['ultima'] ?? null);
$textoVer = $verArq && is_file($f->caminho("saidas/$verArq")) ? file_get_contents($f->caminho("saidas/$verArq")) : null;

cabecalho_ferramenta($f);
?>
<div class="titulo-pagina">
  <div><h1><?= h($f->nome) ?></h1><p class="mudo"><?= h($f->descricao) ?></p></div>
</div>
<?php barra_voltar($f); ?>

<div class="colunas">
  <section class="cartao">
    <h2>1. Casais (ordem do rodízio)</h2>
    <form method="post" action="<?= h($f->url(['a' => 'salvar_duplas'])) ?>" id="form-duplas">
      <ul class="lista-ordenavel" id="duplas">
        <?php foreach ($duplas as $i => $d): ?>
        <li draggable="true"><span class="alca" title="Arraste para mudar a ordem">::</span><span class="num"><?= $i + 1 ?>.</span><input name="duplas[]" value="<?= h($d) ?>"><button type="button" class="btn btn-p btn-perigo" data-remover title="Remover">✕</button></li>
        <?php endforeach; ?>
      </ul>
      <div class="grupo">
        <button type="button" class="btn" id="add-dupla">+ Adicionar casal</button>
        <button class="btn btn-primario">Salvar lista</button>
        <?php if (is_file($arqDuplas)): ?><a class="btn btn-leve" href="<?= h($f->url(['a' => 'versoes', 'f' => 'dados/duplas.txt'])) ?>">Listas anteriores</a><?php endif; ?>
      </div>
      <p class="mudo pequeno">Arraste pela alça :: para trocar a ordem. Ao salvar, a lista anterior fica guardada no histórico.</p>
    </form>
  </section>

  <section class="cartao">
    <h2>2. Gerar escala</h2>
    <form method="post" action="<?= h($f->url(['a' => 'gerar'])) ?>" class="form-grade">
      <label>Do mês <input type="month" name="inicio" value="<?= h($mesSeg) ?>" required></label>
      <label>Até o mês <input type="month" name="fim" value="<?= h($mesSeg) ?>" required></label>
      <label>Dia da semana
        <select name="dia_semana"><?php foreach (DIAS_SEMANA as $i => $n): ?><option value="<?= $i ?>" <?= $i === 0 ? 'selected' : '' ?>><?= h(ucfirst($n)) ?></option><?php endforeach; ?></select>
      </label>
      <label>Começar pelo casal
        <select name="primeira"><?php foreach ($duplas as $i => $d): ?><option value="<?= $i ?>" <?= $i === $proxima ? 'selected' : '' ?>><?= $i + 1 ?>. <?= h($d) ?></option><?php endforeach; ?></select>
        <small class="mudo">Já vem no próximo da última escala.</small>
      </label>
      <label class="largo">Título no Word (opcional) <input name="titulo" placeholder="deixe vazio para não ter título"></label>
      <label class="check largo"><input type="checkbox" name="docx" value="1" checked> Gerar também em Word (.docx)</label>
      <div class="largo"><button class="btn btn-primario">Gerar escala</button></div>
    </form>
  </section>
</div>

<?php if ($textoVer !== null): ?>
<section class="cartao">
  <div class="cartao-topo">
    <h2><?= h($verArq) ?></h2>
    <div class="grupo">
      <button class="btn" onclick="App.copiar(document.getElementById('txt-escala').textContent, this)">Copiar texto (WhatsApp)</button>
      <?php $docx = preg_replace('/\.txt$/', '.docx', $verArq); if (is_file($f->caminho("saidas/$docx"))): ?>
        <a class="btn" href="<?= h($f->url(['a' => 'editar', 'f' => "saidas/$docx"])) ?>">Abrir Word no editor</a>
        <a class="btn" href="<?= h($f->url(['a' => 'baixar', 'f' => "saidas/$docx"])) ?>">Baixar .docx</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="saida-texto" id="txt-escala"><?= h($textoVer) ?></div>
</section>
<?php endif; ?>

<?php lista_saidas($f); ?>

<template id="tpl-dupla"><li draggable="true"><span class="alca">::</span><span class="num"></span><input name="duplas[]" placeholder="Nome do casal"><button type="button" class="btn btn-p btn-perigo" data-remover>✕</button></li></template>
<script>
(function () {
  const ul = document.getElementById('duplas');
  const renumerar = () => ul.querySelectorAll('li').forEach((li, i) => (li.querySelector('.num').textContent = i + 1 + '.'));
  document.getElementById('add-dupla').onclick = () => {
    ul.appendChild(document.getElementById('tpl-dupla').content.cloneNode(true));
    renumerar();
    ul.lastElementChild.querySelector('input').focus();
  };
  ul.addEventListener('click', (e) => { if (e.target.matches('[data-remover]')) { e.target.closest('li').remove(); renumerar(); } });
  let arrastando = null;
  ul.addEventListener('dragstart', (e) => { arrastando = e.target.closest('li'); arrastando.classList.add('arrastando'); });
  ul.addEventListener('dragend', () => { arrastando && arrastando.classList.remove('arrastando'); arrastando = null; renumerar(); });
  ul.addEventListener('dragover', (e) => {
    e.preventDefault();
    const alvo = e.target.closest('li');
    if (!alvo || alvo === arrastando) return;
    const r = alvo.getBoundingClientRect();
    ul.insertBefore(arrastando, e.clientY < r.top + r.height / 2 ? alvo : alvo.nextSibling);
  });
})();
</script>
<?php rodape(); ?>
