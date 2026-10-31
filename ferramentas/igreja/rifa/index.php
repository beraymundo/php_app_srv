<?php
/**
 * Gerador de Rifas — versão web do antigo rifa.py
 * Cada geração vai para saidas/<nome da rifa>/ com:
 *   rifas_completo.docx          todas as rifas, com quebra de página a cada N tabelas
 *   rifa_1_a_40.docx ...         (opcional) um arquivo por folha
 */
/** @var Ferramenta $f */
/** @var string $a */

$arqUltimo = $f->caminho('dados/ultimo.json');
$padrao = ler_json($arqUltimo) + [
    'local' => 'Comunidade Sagrada Família',
    'descricao' => '',
    'data' => '',
    'valor' => 'R$ 2,00',
    'inicio' => 1,
    'total' => 500,
    'por_rifa' => 20,
    'por_folha' => 2,
    'por_folha_arquivo' => 0,
    'fonte' => 11,
];

function rifa_bloco(DocxWriter $w, array $o, int $de, int $ate): void
{
    $w->p($o['local'], ['b' => true, 'depois' => 0]);
    if ($o['descricao'] !== '') {
        $w->p($o['descricao'], ['depois' => 0]);
    }
    $w->p("Sorteio: {$o['data']}. Valor: {$o['valor']}", ['depois' => 80]);
    $linhas = [['Nº', 'Nome', 'Contato']];
    for ($n = $de; $n <= $ate; $n++) {
        $linhas[] = [(string)$n, '', ''];
    }
    // larguras do original: 0,6" / 3,0" / 2,5"
    $w->tabela($linhas, [864, 4320, 3600], ['cabecalho' => true, 'jcCelula' => ['center', null, null]]);
    $w->p('', ['depois' => 0]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'gerar') {
    $o = [
        'local' => trim((string)$_POST['local']),
        'descricao' => trim((string)$_POST['descricao']),
        'data' => trim((string)$_POST['data']),
        'valor' => trim((string)$_POST['valor']),
        'inicio' => max(0, (int)$_POST['inicio']),
        'total' => max(1, (int)$_POST['total']),
        'por_rifa' => max(1, (int)$_POST['por_rifa']),
        'por_folha' => max(1, (int)$_POST['por_folha']),
        'por_folha_arquivo' => !empty($_POST['por_folha_arquivo']) ? 1 : 0,
        'fonte' => max(6, min(20, (float)$_POST['fonte'])),
    ];
    if ($o['total'] > 20000) {
        throw new RuntimeException('Quantidade muito grande (máximo 20.000 números).');
    }
    gravar_json($arqUltimo, $o);

    $nomePasta = nome_seguro((string)($_POST['pasta'] ?: ($o['descricao'] ?: 'rifa') . ' ' . date('d-m-Y')));
    $sub = 'saidas/' . $nomePasta;
    if (is_dir($f->caminho($sub))) {
        $f->paraLixeira($sub); // gerar de novo com o mesmo nome: a versão anterior vai para a lixeira
    }
    $f->pasta($sub);

    $fim = $o['inicio'] + $o['total'] - 1;
    $porFolha = $o['por_rifa'] * $o['por_folha'];
    $margens = [1134, 1134, 1134, 1134];
    $total = new DocxWriter('Calibri', $o['fonte'], $margens);
    $blocos = 0;
    $qtdBlocos = (int)ceil($o['total'] / $o['por_rifa']);
    $arquivos = 0;
    for ($ini = $o['inicio']; $ini <= $fim; $ini += $porFolha) {
        $folha = new DocxWriter('Calibri', $o['fonte'], $margens);
        for ($b = 0; $b < $o['por_folha']; $b++) {
            $de = $ini + $b * $o['por_rifa'];
            if ($de > $fim) {
                break;
            }
            $ate = min($de + $o['por_rifa'] - 1, $fim);
            rifa_bloco($folha, $o, $de, $ate);
            rifa_bloco($total, $o, $de, $ate);
            $blocos++;
            if ($blocos % $o['por_folha'] === 0 && $blocos < $qtdBlocos) {
                $total->quebra();
            }
        }
        if ($o['por_folha_arquivo']) {
            $folha->salvar($f->caminho("$sub/rifa_{$ini}_a_" . min($ini + $porFolha - 1, $fim) . '.docx'));
            $arquivos++;
        }
    }
    $total->salvar($f->caminho("$sub/rifas_completo.docx"));
    flash("Rifas geradas em \"$nomePasta\": $blocos tabela(s), números {$o['inicio']} a $fim" . ($arquivos ? ", $arquivos arquivo(s) por folha" : '') . '.');
    redirecionar($f->url(['a' => 'pasta', 'f' => $sub]));
}

cabecalho_ferramenta($f);
?>
<div class="titulo-pagina">
  <span class="icone-grande"><?= h($f->icone) ?></span>
  <div><h1><?= h($f->nome) ?></h1><p class="mudo"><?= h($f->descricao) ?></p></div>
</div>
<?php barra_voltar($f); ?>

<section class="cartao">
  <h2>Nova rifa</h2>
  <form method="post" action="<?= h($f->url(['a' => 'gerar'])) ?>" class="form-grade" id="form-rifa">
    <label class="largo">Local / Comunidade <input name="local" value="<?= h($padrao['local']) ?>" required></label>
    <label class="largo">Descrição do prêmio <input name="descricao" value="<?= h($padrao['descricao']) ?>" placeholder="Ex.: Cesta de chocolates - Dia dos Pais"></label>
    <label>Data do sorteio <input name="data" value="<?= h($padrao['data']) ?>" placeholder="Ex.: 10/08/2026"></label>
    <label>Valor <input name="valor" value="<?= h($padrao['valor']) ?>"></label>
    <label>Primeiro número <input type="number" name="inicio" min="0" value="<?= (int)$padrao['inicio'] ?>"></label>
    <label>Quantidade TOTAL de números <input type="number" name="total" min="1" value="<?= (int)$padrao['total'] ?>" required></label>
    <label>Números por rifa (linhas da tabela) <input type="number" name="por_rifa" min="1" value="<?= (int)$padrao['por_rifa'] ?>" required></label>
    <label>Rifas por folha (tabelas por página) <input type="number" name="por_folha" min="1" value="<?= (int)$padrao['por_folha'] ?>" required></label>
    <label>Tamanho da letra <input type="number" name="fonte" min="6" max="20" step="0.5" value="<?= h((string)$padrao['fonte']) ?>"></label>
    <label>Nome da pasta de saída <input name="pasta" placeholder="automático (prêmio + data)"></label>
    <label class="check largo"><input type="checkbox" name="por_folha_arquivo" value="1" <?= $padrao['por_folha_arquivo'] ? 'checked' : '' ?>> Também gerar um arquivo separado para cada folha (como o programa antigo fazia)</label>
    <p class="largo mudo pequeno" id="resumo"></p>
    <div class="largo"><button class="btn btn-primario">🎟️ Gerar rifas</button></div>
  </form>
</section>

<?php lista_saidas($f, 'saidas', 'Rifas geradas'); ?>

<script>
(function () {
  const f = document.getElementById('form-rifa');
  const atualizar = () => {
    const v = (n) => +f.elements[n].value || 0;
    const tabelas = Math.ceil(v('total') / Math.max(1, v('por_rifa')));
    const folhas = Math.ceil(tabelas / Math.max(1, v('por_folha')));
    document.getElementById('resumo').textContent = `Números ${v('inicio')} a ${v('inicio') + v('total') - 1} → ${tabelas} tabela(s) em ${folhas} página(s).`;
  };
  f.addEventListener('input', atualizar);
  atualizar();
})();
</script>
<?php rodape(); ?>
