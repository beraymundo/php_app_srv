<?php
/**
 * Central de Ferramentas
 *   index.php                      -> menu de categorias
 *   index.php?c=igreja             -> ferramentas da categoria
 *   index.php?c=igreja&t=liturgia  -> a ferramenta (a=ação opcional)
 *
 * Para criar uma ferramenta nova: crie ferramentas/<categoria>/<id>/ferramenta.json
 *   - "tipo": "documentos"  -> gerenciador de modelos/documentos com editor (não precisa de código)
 *   - "tipo": "app"         -> página própria em ferramentas/<categoria>/<id>/index.php (recebe $f)
 */
require __DIR__ . '/lib/nucleo.php';
require __DIR__ . '/lib/layout.php';
require __DIR__ . '/lib/documentos.php';

session_start();

// ---------------------------------------------------------------- senha (opcional)
if (config('senha') !== '' && empty($_SESSION['logado'])) {
    if (isset($_POST['senha']) && hash_equals((string)config('senha'), (string)$_POST['senha'])) {
        $_SESSION['logado'] = true;
        redirecionar($_SERVER['REQUEST_URI']);
    }
    cabecalho('Entrar');
    ?>
    <form method="post" class="cartao login">
      <h1>Entrar</h1>
      <?php if (isset($_POST['senha'])): ?><div class="aviso aviso-erro">Senha incorreta.</div><?php endif; ?>
      <input type="password" name="senha" placeholder="Senha" autofocus>
      <button class="btn btn-primario">Entrar</button>
    </form>
    <?php
    rodape();
    exit;
}

$c = (string)($_GET['c'] ?? '');
$t = (string)($_GET['t'] ?? '');
$a = (string)($_GET['a'] ?? '');

try {
    if ($c === '') {
        pagina_inicio();
    } elseif ($t === '') {
        pagina_categoria($c);
    } else {
        $f = new Ferramenta($c, $t);
        if (mt_rand(1, 10) === 1) {
            $f->limpar();
        }
        if (!acoes_comuns($f, $a)) {
            if ($f->tipo === 'documentos' || in_array($a, Documentos::ACOES, true)) {
                Documentos::rotear($f, $a);
            } else {
                (function (Ferramenta $f, string $a) {
                    require $f->dir . '/index.php';
                })($f, $a);
            }
        }
    }
} catch (Throwable $e) {
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    if ($json) {
        json_saida(['erro' => $e->getMessage()], 400);
    }
    if (!headers_sent()) {
        http_response_code(400);
        cabecalho('Erro');
    }
    echo '<div class="aviso aviso-erro"><strong>Ops:</strong> ' . h($e->getMessage()) . '</div>';
    echo '<p><a class="btn" href="javascript:history.back()">← Voltar</a> <a class="btn btn-leve" href="index.php">Início</a></p>';
    if (!($e instanceof RuntimeException)) {
        echo '<pre class="mudo pequeno">' . h($e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
    rodape();
}

// ==================================================================== páginas

function pagina_inicio(): void
{
    if (($_GET['a'] ?? '') === 'nova_categoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim((string)$_POST['nome']);
        $id = slug($nome);
        if ($nome === '' || is_dir(DIR_FERRAMENTAS . "/$id")) {
            throw new RuntimeException('Nome vazio ou categoria já existe.');
        }
        gravar_json(DIR_FERRAMENTAS . "/$id/_categoria.json", ['nome' => $nome, 'descricao' => trim((string)$_POST['descricao']), 'ordem' => 40]);
        flash("Categoria \"$nome\" criada.");
        redirecionar("index.php?c=$id");
    }
    cabecalho(config('titulo'));
    ?>
<div class="titulo-pagina">
  <div><h1>O que vamos fazer hoje?</h1><p class="mudo">Escolha um conjunto de ferramentas.</p></div>
</div>
<label class="busca">
  <span class="prompt">&gt;</span>
  <input id="busca-inicio" type="search" placeholder="buscar ferramenta... (atalho: /)" autocomplete="off" spellcheck="false">
</label>
<ul class="resultados" id="busca-resultados" hidden></ul>
<div class="grade" id="grade-inicio">
  <?php foreach (categorias() as $cat): ?>
  <a class="bloco" href="index.php?c=<?= h($cat['id']) ?>">
    <span class="bloco-caminho">~/<?= h($cat['id']) ?></span>
    <span class="bloco-nome"><?= h($cat['nome']) ?></span>
    <span class="bloco-desc"><?= h($cat['descricao']) ?></span>
    <span class="bloco-qtd"><?= $cat['qtd'] ? $cat['qtd'] . ' ferramenta' . ($cat['qtd'] > 1 ? 's' : '') : 'vazia' ?></span>
  </a>
  <?php endforeach; ?>
  <button class="bloco bloco-novo" data-abrir="#nova-cat">
    <span class="bloco-caminho">mkdir</span><span class="bloco-nome">+ Nova categoria</span>
  </button>
</div>
<form id="nova-cat" class="cartao painel-oculto form-grade" method="post" action="index.php?a=nova_categoria">
  <h2 class="largo">Nova categoria</h2>
  <label>Nome <input name="nome" required placeholder="Ex.: Trabalho"></label>
  <label class="largo">Descrição <input name="descricao"></label>
  <div class="largo"><button class="btn btn-primario">Criar</button></div>
</form>
<?php
    rodape();
}

function pagina_categoria(string $c): void
{
    $cats = categorias();
    $cat = $cats[$c] ?? throw new RuntimeException('Categoria não encontrada.');

    if (($_GET['a'] ?? '') === 'nova_ferramenta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim((string)$_POST['nome']);
        $id = slug($nome);
        $dir = "{$cat['dir']}/$id";
        if ($nome === '' || is_dir($dir)) {
            throw new RuntimeException('Nome vazio ou ferramenta já existe.');
        }
        $arq = $_FILES['modelo'] ?? null;
        $ext = strtolower(pathinfo((string)($arq['name'] ?? ''), PATHINFO_EXTENSION));
        if ($arq && $arq['error'] === UPLOAD_ERR_OK && !in_array($ext, ['docx', 'xlsx'], true)) {
            throw new RuntimeException('O modelo precisa ser .docx ou .xlsx.');
        }
        gravar_json("$dir/ferramenta.json", [
            'nome' => $nome,
            'descricao' => trim((string)$_POST['descricao']),
            'tipo' => 'documentos',
            'importar_planilha' => true,
        ]);
        foreach (['modelos', 'documentos'] as $p) {
            @mkdir("$dir/$p", 0777, true);
        }
        if ($arq && $arq['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($arq['tmp_name'], "$dir/modelos/" . nome_seguro($arq['name']));
        }
        flash("Ferramenta \"$nome\" criada. Para campos automáticos, edite ferramentas/$c/$id/ferramenta.json.");
        redirecionar("index.php?c=$c&t=$id");
    }

    $lista = ferramentas($c);
    cabecalho($cat['nome'], [[$cat['nome'], null]]);
    ?>
<div class="titulo-pagina">
  <div><h1><?= h($cat['nome']) ?></h1><p class="mudo"><?= h($cat['descricao']) ?></p></div>
</div>
<div class="barra-voltar">
  <a class="btn" href="index.php">← Início</a>
  <?php if (count($cats) > 1): ?>
  <span class="mudo pequeno">Ir para:</span>
  <?php foreach ($cats as $o): if ($o['id'] === $c) continue; ?>
    <a class="btn btn-leve btn-p" href="index.php?c=<?= h($o['id']) ?>"><?= h($o['nome']) ?></a>
  <?php endforeach; endif; ?>
</div>
<?php if (!$lista): ?>
  <div class="cartao vazio">
    <p>Ainda não há ferramentas aqui.</p>
    <p class="mudo pequeno">Crie uma ferramenta de documento abaixo (basta enviar um .docx/.xlsx de modelo), ou coloque um programa em <code>ferramentas/<?= h($c) ?>/&lt;nome&gt;/</code> com um <code>ferramenta.json</code>.</p>
  </div>
<?php endif; ?>
<div class="grade">
  <?php foreach ($lista as $f): ?>
  <a class="bloco" href="<?= h($f->url()) ?>">
    <span class="bloco-caminho">./<?= h($f->id) ?></span>
    <span class="bloco-nome"><?= h($f->nome) ?></span>
    <span class="bloco-desc"><?= h($f->descricao) ?></span>
  </a>
  <?php endforeach; ?>
  <button class="bloco bloco-novo" data-abrir="#nova-ferr">
    <span class="bloco-caminho">novo</span><span class="bloco-nome">+ Nova ferramenta de documento</span>
    <span class="bloco-desc">A partir de um .docx ou .xlsx modelo</span>
  </button>
</div>
<form id="nova-ferr" class="cartao painel-oculto form-grade" method="post" enctype="multipart/form-data" action="index.php?c=<?= h($c) ?>&a=nova_ferramenta">
  <h2 class="largo">Nova ferramenta de documento</h2>
  <label>Nome <input name="nome" required placeholder="Ex.: Recibo de aluguel"></label>
  <label class="largo">Descrição <input name="descricao"></label>
  <label class="largo">Arquivo modelo (.docx ou .xlsx) <input type="file" name="modelo" accept=".docx,.xlsx"></label>
  <div class="largo"><button class="btn btn-primario">Criar ferramenta</button></div>
</form>
<?php
    rodape();
}

// ==================================================================== ações comuns a todas as ferramentas

function acoes_comuns(Ferramenta $f, string $a): bool
{
    switch ($a) {
        case 'baixar':
        case 'ver':
            $rel = (string)($_GET['f'] ?? '');
            enviar_arquivo($f->caminho($rel), null, $a === 'ver');
        case 'zip':
            $rel = (string)($_GET['f'] ?? '');
            $p = $f->caminho($rel);
            if (!is_dir($p)) {
                throw new RuntimeException('Pasta não encontrada.');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'zip');
            file_put_contents($tmp, Zip::pasta($p));
            register_shutdown_function(fn() => @unlink($tmp));
            enviar_arquivo($tmp, basename($p) . '.zip');
        case 'pasta':
            $rel = (string)($_GET['f'] ?? '');
            if (!is_dir($f->caminho($rel))) {
                throw new RuntimeException('Pasta não encontrada.');
            }
            cabecalho_ferramenta($f, basename($rel));
            barra_voltar($f, $f->url(), $f->nome);
            echo '<p><a class="btn btn-primario" href="' . h($f->url(['a' => 'zip', 'f' => $rel])) . '">Baixar tudo em .zip</a></p>';
            lista_saidas($f, $rel, basename($rel));
            rodape();
            return true;
        case 'excluir':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return false;
            }
            $rel = (string)($_POST['f'] ?? '');
            $f->paraLixeira($rel);
            flash('"' . basename($rel) . '" foi para a lixeira (dá para restaurar por ' . config('lixeira_dias') . ' dias).');
            $volta = (string)($_POST['voltar'] ?? '');
            redirecionar(preg_match('#^/[^/]|^index\.php#', $volta) ? $volta : $f->url());
        case 'lixeira':
            pagina_lixeira($f);
            return true;
        case 'restaurar_lixeira':
            $destino = $f->restaurarLixeira((string)($_POST['n'] ?? ''));
            flash('Restaurado em ' . $destino);
            redirecionar($f->url(['a' => 'lixeira']));
        case 'esvaziar_lixeira':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                apagar_recursivo($f->caminho('lixeira'));
                flash('Lixeira esvaziada.');
            }
            redirecionar($f->url(['a' => 'lixeira']));
    }
    return false;
}

function pagina_lixeira(Ferramenta $f): void
{
    $itens = $f->itensLixeira();
    cabecalho_ferramenta($f, 'Lixeira');
    barra_voltar($f, $f->url(), $f->nome);
    ?>
<section class="cartao">
  <div class="cartao-topo">
    <h2>Lixeira</h2>
    <?php if ($itens): ?>
    <form method="post" action="<?= h($f->url(['a' => 'esvaziar_lixeira'])) ?>" data-confirmar="Apagar DEFINITIVAMENTE tudo da lixeira?"><button class="btn btn-perigo">Esvaziar</button></form>
    <?php endif; ?>
  </div>
  <p class="mudo pequeno">Itens são apagados automaticamente depois de <?= config('lixeira_dias') ?> dias.</p>
  <?php if (!$itens): ?><p class="mudo">Vazia.</p><?php else: ?>
  <table class="lista">
    <?php foreach ($itens as $i): ?>
    <tr>
      <td><?= $i['dir'] ? icone_arquivo('dir') : icone_arquivo(strtolower(pathinfo($i['original'], PATHINFO_EXTENSION))) ?> <?= h($i['original']) ?></td>
      <td class="mudo">excluído <?= data_legivel($i['mtime']) ?></td>
      <td class="acoes">
        <form method="post" class="inline" action="<?= h($f->url(['a' => 'restaurar_lixeira'])) ?>"><input type="hidden" name="n" value="<?= h($i['nome']) ?>"><button class="btn btn-p">Restaurar</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php
    rodape();
}
