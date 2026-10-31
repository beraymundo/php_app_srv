<?php
/**
 * Layout das páginas e componentes reutilizáveis.
 * $trilha: lista de [rótulo, url|null] para a navegação (Início › Categoria › Ferramenta).
 */

function cabecalho(string $titulo, array $trilha = [], array $op = []): void
{
    $app = config('titulo');
    $largo = !empty($op['largo']) ? ' largo' : '';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo === $app ? $app : "$titulo · $app") ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🧰</text></svg>">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(RAIZ . '/assets/style.css') ?>">
</head>
<body>
<header class="topo">
  <div class="topo-in<?= $largo ?>">
    <a class="marca" href="index.php">🧰 <?= h($app) ?></a>
    <?php if ($trilha): ?>
    <nav class="trilha">
      <a href="index.php">Início</a>
      <?php foreach ($trilha as $i => [$rot, $url]): ?>
        <span>›</span>
        <?php if ($url && $i < count($trilha) - 1): ?><a href="<?= h($url) ?>"><?= h($rot) ?></a><?php else: ?><strong><?= h($rot) ?></strong><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="conteudo<?= $largo ?>">
<?php foreach (flash() as [$tipo, $msg]): ?>
  <div class="aviso aviso-<?= h($tipo) ?>"><?= h($msg) ?></div>
<?php endforeach;
}

function rodape(array $scripts = []): void
{
    ?>
</main>
<script src="assets/app.js?v=<?= filemtime(RAIZ . '/assets/app.js') ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="assets/<?= h($s) ?>?v=<?= filemtime(RAIZ . '/assets/' . $s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Barra com botões de voltar (para a categoria e para o início). */
function barra_voltar(Ferramenta $f, ?string $voltarPara = null, string $rotulo = 'Voltar'): void
{
    ?>
<div class="barra-voltar">
  <?php if ($voltarPara): ?><a class="btn" href="<?= h($voltarPara) ?>">← <?= h($rotulo) ?></a><?php endif; ?>
  <a class="btn btn-leve" href="index.php?c=<?= h($f->cat) ?>">↑ Ferramentas de <?= h(categorias()[$f->cat]['nome'] ?? $f->cat) ?></a>
  <a class="btn btn-leve" href="index.php">⌂ Início</a>
</div>
<?php
}

/** Cabeçalho padrão de uma página de ferramenta. */
function cabecalho_ferramenta(Ferramenta $f, ?string $sub = null, array $op = []): void
{
    $cat = categorias()[$f->cat] ?? ['nome' => $f->cat];
    $trilha = [[$cat['nome'], "index.php?c=$f->cat"], [$f->nome, $f->url()]];
    if ($sub) {
        $trilha[] = [$sub, null];
    }
    cabecalho($sub ?? $f->nome, $trilha, $op);
}

/** Cabeçalho + título + barra de voltar: o começo padrão de uma ferramenta "app". */
function inicio_ferramenta(Ferramenta $f): void
{
    cabecalho_ferramenta($f);
    ?>
<div class="titulo-pagina">
  <span class="icone-grande"><?= h($f->icone) ?></span>
  <div><h1><?= h($f->nome) ?></h1><p class="mudo"><?= h($f->descricao) ?></p></div>
</div>
<?php
    barra_voltar($f);
}

/**
 * Lista de arquivos gerados (pasta "saidas" da ferramenta), com baixar / baixar pasta em .zip / excluir.
 */
function lista_saidas(Ferramenta $f, string $sub = 'saidas', string $titulo = 'Arquivos gerados'): void
{
    $itens = $f->listar($sub);
    ?>
<section class="cartao">
  <h2><?= h($titulo) ?> <small class="mudo">(<?= h($sub) ?>)</small></h2>
  <?php if (!$itens): ?>
    <p class="mudo">Nada gerado ainda.</p>
  <?php else: ?>
  <table class="lista">
    <?php foreach ($itens as $i): ?>
    <tr>
      <td><?= $i['dir'] ? '📂' : icone_arquivo($i['ext']) ?>
        <?php if ($i['dir']): ?><a href="<?= h($f->url(['a' => 'pasta', 'f' => $i['rel']])) ?>"><?= h($i['nome']) ?></a>
        <?php else: ?><?= h($i['nome']) ?><?php endif; ?></td>
      <td class="mudo nao-celular"><?= $i['dir'] ? '' : tamanho_legivel($i['tamanho']) ?></td>
      <td class="mudo nao-celular"><?= data_legivel($i['mtime']) ?></td>
      <td class="acoes">
        <?php if ($i['dir']): ?>
          <a class="btn btn-p" href="<?= h($f->url(['a' => 'zip', 'f' => $i['rel']])) ?>">⬇ .zip</a>
        <?php else: ?>
          <?php if ($i['ext'] === 'docx' || $i['ext'] === 'xlsx'): ?><a class="btn btn-p" href="<?= h($f->url(['a' => 'editar', 'f' => $i['rel']])) ?>">✎ Abrir</a><?php endif; ?>
          <?php if (in_array($i['ext'], ['txt', 'pdf', 'png', 'jpg', 'csv'], true)): ?><a class="btn btn-p" target="_blank" href="<?= h($f->url(['a' => 'ver', 'f' => $i['rel']])) ?>">👁 Ver</a><?php endif; ?>
          <a class="btn btn-p" href="<?= h($f->url(['a' => 'baixar', 'f' => $i['rel']])) ?>">⬇</a>
        <?php endif; ?>
        <form method="post" action="<?= h($f->url(['a' => 'excluir'])) ?>" class="inline" data-confirmar="Mover &quot;<?= h($i['nome']) ?>&quot; para a lixeira?">
          <input type="hidden" name="f" value="<?= h($i['rel']) ?>">
          <input type="hidden" name="voltar" value="<?= h($_SERVER['REQUEST_URI'] ?? '') ?>">
          <button class="btn btn-p btn-perigo" title="Excluir">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php
}

function icone_arquivo(string $ext): string
{
    return ['docx' => '📝', 'xlsx' => '📊', 'pdf' => '📕', 'txt' => '📄', 'zip' => '🗜', 'png' => '🖼', 'jpg' => '🖼', 'csv' => '📊'][$ext] ?? '📄';
}

function link_lixeira(Ferramenta $f): void
{
    $n = count($f->listar('lixeira'));
    echo '<a class="btn btn-leve" href="' . h($f->url(['a' => 'lixeira'])) . '">🗑 Lixeira' . ($n ? " ($n)" : '') . '</a>';
}
