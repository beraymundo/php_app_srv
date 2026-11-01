<?php
/**
 * Painel "hoje" da página inicial: um resumo do que pede atenção, lido dos dados das ferramentas.
 * Cada bloco só aparece se a ferramenta existir e tiver dados.
 */

/** Estado JSON de uma ferramenta, ou null se ela não existir. */
function painel_estado(string $cat, string $id): ?array
{
    try {
        $f = new Ferramenta($cat, $id);
    } catch (Throwable $e) {
        return null;
    }
    return ler_json($f->caminho('dados/estado.json')) + ['_url' => $f->url()];
}

/** Itens com vencimento no mês atual que ainda não foram pagos: [descrição, dia, valor, dias até vencer]. */
function painel_vencimentos(array $fixos, array $doMes, string $campoValor, callable $ativo): array
{
    $hoje = (int)date('j');
    $ultimo = (int)date('t');
    $out = [];
    foreach ($fixos as $fx) {
        if (!$ativo($fx)) {
            continue;
        }
        $r = $doMes[$fx['id']] ?? [];
        if (!empty($r['pago'])) {
            continue;
        }
        $dia = min((int)($fx['dia'] ?? 1), $ultimo);
        $out[] = [$fx['desc'] ?? $fx['nome'] ?? '?', $dia, (float)($r['v'] ?? $fx[$campoValor] ?? 0), $dia - $hoje];
    }
    usort($out, fn($a, $b) => $a[1] <=> $b[1]);
    return $out;
}

function painel_inicio(): void
{
    $k = date('Y-m');
    $blocos = [];
    $moeda = fn(float $v) => 'R$ ' . number_format($v, 2, ',', '.');
    $linhaVenc = function (array $v) use ($moeda): string {
        [$desc, $dia, $valor, $falta] = $v;
        $quando = $falta < 0 ? '<span class="estado-ruim">atrasada</span>' : ($falta === 0 ? '<span class="estado-alerta">hoje</span>' : "<span class=\"mudo\">dia $dia</span>");
        return '<li><span>' . h($desc) . '</span><span class="num">' . $moeda($valor) . " · $quando</span></li>";
    };

    // ---- Minhas Finanças
    if (($e = painel_estado('pessoal', 'financas')) && (!empty($e['fixos']) || !empty($e['meses']))) {
        $dm = $e['meses'][$k] ?? [];
        $rec = $desp = 0.0;
        foreach ($e['fixos'] ?? [] as $fx) {
            if ((!empty($fx['desde']) && $fx['desde'] > $k) || (!empty($fx['ate']) && $fx['ate'] < $k)) {
                continue;
            }
            $v = (float)($dm['fixos'][$fx['id']]['v'] ?? $fx['valor'] ?? 0);
            $fx['tipo'] === 'receita' ? $rec += $v : $desp += $v;
        }
        foreach ($dm['lanc'] ?? [] as $l) {
            $l['tipo'] === 'receita' ? $rec += (float)$l['valor'] : $desp += (float)$l['valor'];
        }
        $venc = array_filter(
            painel_vencimentos(array_filter($e['fixos'] ?? [], fn($fx) => ($fx['tipo'] ?? '') === 'despesa'), $dm['fixos'] ?? [], 'valor',
                fn($fx) => (empty($fx['desde']) || $fx['desde'] <= $k) && (empty($fx['ate']) || $fx['ate'] >= $k)),
            fn($v) => $v[3] <= 7
        );
        $html = '<div class="painel-num"><span><small>entradas</small>' . $moeda($rec) . '</span><span><small>gastos</small>' . $moeda($desp) . '</span><span><small>sobra</small><b class="' . ($rec - $desp < 0 ? 'estado-ruim' : '') . '">' . $moeda($rec - $desp) . '</b></span></div>';
        if ($venc) {
            $html .= '<ul class="painel-lista">' . implode('', array_map($linhaVenc, array_slice($venc, 0, 4))) . '</ul>';
        }
        $blocos[] = ['Minhas finanças · ' . MESES[(int)date('n') - 1], $e['_url'], $html];
    }

    // ---- Contas da casa
    if (($e = painel_estado('casa', 'contas')) && !empty($e['contas'])) {
        $venc = array_filter(painel_vencimentos($e['contas'], $e['meses'][$k] ?? [], 'valor', fn($c) => ($c['ativa'] ?? true) !== false), fn($v) => $v[3] <= 7);
        $html = $venc ? '<ul class="painel-lista">' . implode('', array_map($linhaVenc, array_slice($venc, 0, 5))) . '</ul>' : '<p class="mudo">Nada vencendo nos próximos 7 dias.</p>';
        $blocos[] = ['Contas da casa', $e['_url'], $html];
    }

    // ---- Aniversariantes
    if (($e = painel_estado('genericos', 'aniversarios')) && !empty($e['pessoas'])) {
        $hoje = new DateTimeImmutable('today');
        $prox = [];
        foreach ($e['pessoas'] as $p) {
            $d = DateTimeImmutable::createFromFormat('!Y-n-j', $hoje->format('Y') . "-{$p['mes']}-{$p['dia']}");
            if (!$d) {
                continue;
            }
            if ($d < $hoje) {
                $d = $d->modify('+1 year');
            }
            $falta = (int)$hoje->diff($d)->days;
            if ($falta <= 7) {
                $prox[] = [$falta, $p['nome'], $d];
            }
        }
        sort($prox);
        if ($prox) {
            $html = '<ul class="painel-lista">' . implode('', array_map(fn($x) => '<li><span>' . h($x[1]) . '</span><span class="num ' . ($x[0] === 0 ? 'estado-ok' : 'mudo') . '">' . ($x[0] === 0 ? 'hoje' : $x[2]->format('d/m')) . '</span></li>', array_slice($prox, 0, 5))) . '</ul>';
            $blocos[] = ['Aniversários da semana', $e['_url'], $html];
        }
    }

    // ---- Faltas no IFES
    if (($e = painel_estado('ifes', 'semestre')) && !empty($e['disc'])) {
        $lim = (float)($e['cfg']['limite'] ?? 25);
        $itens = [];
        foreach ($e['disc'] as $d) {
            if (empty($d['aulas'])) {
                continue;
            }
            $resta = (int)floor($d['aulas'] * $lim / 100) - (int)$d['faltas'];
            $itens[] = [$resta, $d['nome']];
        }
        sort($itens);
        if ($itens) {
            $html = '<ul class="painel-lista">' . implode('', array_map(fn($x) => '<li><span>' . h($x[1]) . '</span><span class="num ' . ($x[0] < 0 ? 'estado-ruim' : ($x[0] <= 2 ? 'estado-alerta' : 'mudo')) . '">' . ($x[0] < 0 ? 'passou do limite' : "pode faltar {$x[0]}") . '</span></li>', array_slice($itens, 0, 4))) . '</ul>';
            $blocos[] = ['Faltas do semestre', $e['_url'], $html];
        }
    }

    // ---- Lista de compras
    if (($e = painel_estado('casa', 'lista-compras')) && !empty($e['itens'])) {
        $pend = array_values(array_filter($e['itens'], fn($i) => empty($i['ok'])));
        if ($pend) {
            $nomes = implode(', ', array_map(fn($i) => $i['t'], array_slice($pend, 0, 6))) . (count($pend) > 6 ? '...' : '');
            $blocos[] = ['Lista de compras', $e['_url'], '<p><b>' . count($pend) . '</b> ite' . (count($pend) > 1 ? 'ns' : 'm') . ' para comprar</p><p class="mudo pequeno">' . h($nomes) . '</p>'];
        }
    }

    // ---- Liturgia de hoje (calculada no navegador)
    $lit = null;
    try {
        $lit = (new Ferramenta('igreja', 'calendario-liturgico'))->url();
    } catch (Throwable $e) {
    }
    ?>
<section class="painel">
  <div class="painel-topo">
    <h2><?= h(DIAS_SEMANA[(int)date('w')]) ?>, <?= h(data_extenso()) ?></h2>
    <?php if ($lit): ?><a class="painel-lit" id="painel-lit" href="<?= h($lit) ?>"></a><?php endif; ?>
  </div>
  <?php if ($blocos): ?>
  <div class="painel-grade">
    <?php foreach ($blocos as [$titulo, $url, $html]): ?>
    <a class="painel-bloco" href="<?= h($url) ?>"><span class="painel-titulo"><?= h($titulo) ?></span><?= $html ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php if ($lit): ?>
<script src="assets/liturgia.js?v=<?= filemtime(RAIZ . '/assets/liturgia.js') ?>"></script>
<script>
(function () {
  const L = window.Liturgia, t = new Date(), i = L.info(L.D(t.getFullYear(), t.getMonth() + 1, t.getDate()));
  const el = document.getElementById('painel-lit');
  el.innerHTML = `<span class="cor-lit" style="background:${L.CORES[i.cor]}"></span>${i.nome} <span class="mudo">· ${i.tempo === 'Comum' ? 'Tempo Comum' : i.tempo} · ano ${i.ciclo}</span>`;
})();
</script>
<?php endif;
}
