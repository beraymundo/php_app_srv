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
require __DIR__ . '/lib/backup.php';
require __DIR__ . '/lib/painel.php';

// Sessão longa (30 dias) para não pedir senha toda hora no mesmo aparelho.
ini_set('session.gc_maxlifetime', (string)(30 * 86400));
session_set_cookie_params(['lifetime' => 30 * 86400, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$a = (string)($_GET['a'] ?? '');
$pedeJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

// ---------------------------------------------------------------- usuários
try {
    if (!usuarios()) {
        pagina_primeiro_acesso();
        exit;
    }
    if ($a === 'sair') {
        $_SESSION = [];
        session_regenerate_id(true);
        redirecionar('index.php');
    }
    if (!usuario_atual()) {
        if ($pedeJson) {
            json_saida(['erro' => 'Sua sessão expirou. Recarregue a página e entre de novo.'], 401);
        }
        pagina_login();
        exit;
    }
} catch (Throwable $e) {
    cabecalho('Erro');
    echo '<div class="aviso aviso-erro">' . h($e->getMessage()) . '</div><p><a class="btn" href="index.php">Tentar de novo</a></p>';
    rodape();
    exit;
}

$c = (string)($_GET['c'] ?? '');
$t = (string)($_GET['t'] ?? '');

try {
    Backup::automatico();
    if ($c === '' && str_starts_with($a, 'backup')) {
        exigir_admin('O backup geral');
        pagina_backup($a);
    } elseif ($c === '' && str_starts_with($a, 'usuario')) {
        exigir_admin('Gerenciar usuários');
        pagina_usuarios($a);
    } elseif ($c === '' && $a === 'conta') {
        pagina_conta();
    } elseif ($c === '') {
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
    if ($pedeJson) {
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


// ==================================================================== usuários

function pagina_primeiro_acesso(): void
{
    $erro = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ((string)$_POST['senha'] !== (string)$_POST['senha2']) {
                throw new RuntimeException('As senhas não conferem.');
            }
            $login = strtolower(trim((string)$_POST['login']));
            criar_usuario($login, (string)$_POST['nome'], (string)$_POST['senha'], true);
            $n = migrar_dados_antigos($login);
            entrar($login, (string)$_POST['senha']);
            flash('Administrador criado.' . ($n ? ' Os dados que já existiam agora são seus.' : '') . ' Crie os outros usuários em "usuários", no topo.');
            redirecionar('index.php');
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
    }
    cabecalho('Primeiro acesso');
    ?>
<form method="post" class="cartao login">
  <h1>Primeiro acesso</h1>
  <p class="mudo pequeno" style="margin:0">Crie a conta do administrador. Ele cria os outros usuários e cuida dos modelos e do backup. Os dados que já existem passam a ser dele.</p>
  <?php if ($erro): ?><div class="aviso aviso-erro"><?= h($erro) ?></div><?php endif; ?>
  <label>Usuário <input name="login" value="<?= h($_POST['login'] ?? 'bernardo') ?>" required autocapitalize="none"></label>
  <label>Nome <input name="nome" value="<?= h($_POST['nome'] ?? 'Bernardo') ?>"></label>
  <label>Senha <input type="password" name="senha" required autofocus></label>
  <label>Repita a senha <input type="password" name="senha2" required></label>
  <button class="btn btn-primario">Criar e entrar</button>
</form>
<?php
    rodape();
}

function pagina_login(): void
{
    $erro = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if (entrar((string)$_POST['login'], (string)$_POST['senha'])) {
            $volta = (string)($_POST['volta'] ?? '');
            redirecionar(preg_match('#^index\.php(\?|$)#', $volta) && !str_contains($volta, 'a=sair') ? $volta : 'index.php');
        }
        $erro = true;
    }
    $volta = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)) === 'index.php' ? 'index.php' . (($q = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY)) ? "?$q" : '') : 'index.php';
    cabecalho('Entrar');
    ?>
<form method="post" class="cartao login">
  <h1>Entrar</h1>
  <?php if ($erro): ?><div class="aviso aviso-erro">Usuário ou senha incorretos.</div><?php endif; ?>
  <input type="hidden" name="volta" value="<?= h($_POST['volta'] ?? $volta) ?>">
  <label>Usuário <input name="login" value="<?= h($_POST['login'] ?? '') ?>" required autocapitalize="none" autocomplete="username" <?= empty($_POST['login']) ? 'autofocus' : '' ?>></label>
  <label>Senha <input type="password" name="senha" required autocomplete="current-password" <?= !empty($_POST['login']) ? 'autofocus' : '' ?>></label>
  <button class="btn btn-primario">Entrar</button>
</form>
<?php
    rodape();
}

function pagina_conta(): void
{
    $u = usuario_atual();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $todos = usuarios();
        if (!password_verify((string)$_POST['atual'], $todos[$u['login']]['hash'])) {
            throw new RuntimeException('A senha atual está errada.');
        }
        if ((string)$_POST['nova'] !== (string)$_POST['nova2']) {
            throw new RuntimeException('As senhas novas não conferem.');
        }
        validar_senha((string)$_POST['nova']);
        $todos[$u['login']]['hash'] = password_hash((string)$_POST['nova'], PASSWORD_DEFAULT);
        if (trim((string)($_POST['nome'] ?? '')) !== '') {
            $todos[$u['login']]['nome'] = trim((string)$_POST['nome']);
        }
        salvar_usuarios($todos);
        flash('Senha alterada.');
        redirecionar('index.php?a=conta');
    }
    cabecalho('Minha conta', [['Minha conta', null]]);
    ?>
<div class="titulo-pagina"><div><h1><?= h($u['nome']) ?></h1><p class="mudo">usuário <code><?= h($u['login']) ?></code> · <?= $u['admin'] ? 'administrador' : 'usuário' ?></p></div></div>
<div class="barra-voltar"><a class="btn" href="index.php">← Início</a><a class="btn btn-leve" href="index.php?a=sair">Sair</a></div>
<form method="post" class="cartao form-grade" style="max-width:640px">
  <h2 class="largo">Trocar senha</h2>
  <label class="largo">Nome <input name="nome" value="<?= h($u['nome']) ?>"></label>
  <label class="largo">Senha atual <input type="password" name="atual" required autocomplete="current-password"></label>
  <label>Nova senha <input type="password" name="nova" required autocomplete="new-password"></label>
  <label>Repita a nova senha <input type="password" name="nova2" required autocomplete="new-password"></label>
  <div class="largo"><button class="btn btn-primario">Salvar</button></div>
</form>
<?php
    rodape();
}

function pagina_usuarios(string $a): void
{
    $todos = usuarios();
    $eu = usuario_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = strtolower(trim((string)($_POST['login'] ?? '')));
        switch ($a) {
            case 'usuario_novo':
                criar_usuario($login, (string)$_POST['nome'], (string)$_POST['senha'], !empty($_POST['admin']));
                flash("Usuário \"$login\" criado. Passe a senha para a pessoa; ela pode trocar em \"minha conta\".");
                break;
            case 'usuario_senha':
                isset($todos[$login]) || throw new RuntimeException('Usuário não encontrado.');
                validar_senha((string)$_POST['senha']);
                $todos[$login]['hash'] = password_hash((string)$_POST['senha'], PASSWORD_DEFAULT);
                salvar_usuarios($todos);
                flash("Senha de \"$login\" redefinida.");
                break;
            case 'usuario_admin':
                isset($todos[$login]) || throw new RuntimeException('Usuário não encontrado.');
                if ($login === $eu) {
                    throw new RuntimeException('Você não pode tirar o seu próprio acesso de administrador.');
                }
                $todos[$login]['admin'] = empty($todos[$login]['admin']);
                salvar_usuarios($todos);
                flash("\"$login\" agora é " . ($todos[$login]['admin'] ? 'administrador.' : 'usuário comum.'));
                break;
            case 'usuario_excluir':
                isset($todos[$login]) || throw new RuntimeException('Usuário não encontrado.');
                if ($login === $eu) {
                    throw new RuntimeException('Você não pode excluir a sua própria conta.');
                }
                unset($todos[$login]);
                salvar_usuarios($todos);
                $msg = "Conta \"$login\" excluída.";
                if (!empty($_POST['apagar_dados'])) {
                    apagar_recursivo(DIR_USUARIOS . "/$login");
                    $msg .= ' Os dados também foram apagados.';
                } else {
                    $msg .= ' Os dados dela continuam em usuarios/' . $login . ' (e voltam se recriar a conta com o mesmo nome).';
                }
                flash($msg);
                break;
        }
        redirecionar('index.php?a=usuarios');
    }
    cabecalho('Usuários', [['Usuários', null]]);
    ?>
<div class="titulo-pagina"><div><h1>Usuários</h1><p class="mudo">Cada pessoa tem os próprios documentos, listas e finanças. Os modelos são de todos.</p></div></div>
<div class="barra-voltar"><a class="btn" href="index.php">← Início</a><a class="btn btn-leve" href="index.php?a=backup">Backup geral</a></div>
<section class="cartao">
  <table class="dados">
    <thead><tr><th>usuário</th><th>nome</th><th>acesso</th><th class="num nao-celular">dados</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($todos as $login => $u):
        $dir = DIR_USUARIOS . "/$login";
        $tam = 0;
        if (is_dir($dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $arq) {
                $tam += $arq->getSize();
            }
        } ?>
      <tr>
        <td><code><?= h($login) ?></code><?= $login === $eu ? ' <span class="mudo pequeno">(você)</span>' : '' ?></td>
        <td><?= h($u['nome']) ?></td>
        <td><?= !empty($u['admin']) ? '<span class="estado-ok">administrador</span>' : 'usuário' ?></td>
        <td class="num mudo nao-celular"><?= tamanho_legivel($tam) ?></td>
        <td class="num">
          <details class="menu"><summary class="btn btn-p" title="Opções">...</summary>
            <div class="menu-itens">
              <form method="post" action="index.php?a=usuario_senha" data-senha><input type="hidden" name="login" value="<?= h($login) ?>"><input type="hidden" name="senha"><button>Redefinir senha</button></form>
              <?php if ($login !== $eu): ?>
              <form method="post" action="index.php?a=usuario_admin"><input type="hidden" name="login" value="<?= h($login) ?>"><button><?= !empty($u['admin']) ? 'Tornar usuário comum' : 'Tornar administrador' ?></button></form>
              <form method="post" action="index.php?a=usuario_excluir" data-confirmar="Excluir a conta &quot;<?= h($login) ?>&quot;? Os dados ficam guardados."><input type="hidden" name="login" value="<?= h($login) ?>"><button class="perigo">Excluir conta</button></form>
              <form method="post" action="index.php?a=usuario_excluir" data-confirmar="Excluir a conta &quot;<?= h($login) ?>&quot; E APAGAR todos os dados dela? Não dá para desfazer."><input type="hidden" name="login" value="<?= h($login) ?>"><input type="hidden" name="apagar_dados" value="1"><button class="perigo">Excluir conta e dados</button></form>
              <?php endif; ?>
            </div>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<form method="post" action="index.php?a=usuario_novo" class="cartao form-grade">
  <h2 class="largo">Novo usuário</h2>
  <label>Usuário (para entrar) <input name="login" required placeholder="ex.: michelle" autocapitalize="none"></label>
  <label>Nome <input name="nome" placeholder="ex.: Michelle"></label>
  <label>Senha inicial <input name="senha" required autocomplete="new-password"></label>
  <label class="check"><input type="checkbox" name="admin"> administrador</label>
  <div class="largo"><button class="btn btn-primario">Criar usuário</button></div>
</form>
<script>
document.addEventListener('submit', (e) => {
  const f = e.target;
  if (f.dataset.senha === undefined) return;
  const s = prompt('Nova senha para ' + f.login.value + ':');
  if (!s) { e.preventDefault(); return; }
  f.senha.value = s;
});
</script>
<?php
    rodape();
}

// ==================================================================== páginas

function pagina_inicio(): void
{
    if (($_GET['a'] ?? '') === 'nova_categoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        exigir_admin('Criar categorias');
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
<?php painel_inicio(); ?>
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
  <?php if (eh_admin()): ?>
  <button class="bloco bloco-novo" data-abrir="#nova-cat">
    <span class="bloco-caminho">mkdir</span><span class="bloco-nome">+ Nova categoria</span>
  </button>
  <?php endif; ?>
</div>
<?php if (eh_admin()): ?>
<form id="nova-cat" class="cartao painel-oculto form-grade" method="post" action="index.php?a=nova_categoria">
  <h2 class="largo">Nova categoria</h2>
  <label>Nome <input name="nome" required placeholder="Ex.: Trabalho"></label>
  <label class="largo">Descrição <input name="descricao"></label>
  <div class="largo"><button class="btn btn-primario">Criar</button></div>
</form>
<?php endif; ?>
<?php
    rodape();
}

function pagina_backup(string $a): void
{
    if ($a === 'backup_baixar') {
        $tmp = tempnam(sys_get_temp_dir(), 'bkp');
        file_put_contents($tmp, Backup::gerar());
        register_shutdown_function(fn() => @unlink($tmp));
        enviar_arquivo($tmp, 'backup_central_' . date('Y-m-d') . '.zip');
    }
    if ($a === 'backup_arquivo') {
        $nome = basename((string)($_GET['n'] ?? ''));
        if (!preg_match('/^backup_[\w-]+\.zip$/', $nome) || !is_file(Backup::dir() . "/$nome")) {
            throw new RuntimeException('Backup não encontrado.');
        }
        enviar_arquivo(Backup::dir() . "/$nome");
    }
    if ($a === 'backup_agora' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        flash('Backup gravado: ' . Backup::gravar('manual'));
        redirecionar('index.php?a=backup');
    }
    if ($a === 'backup_restaurar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $arq = $_FILES['zip'] ?? null;
        if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Envie o arquivo .zip do backup.');
        }
        $n = Backup::restaurar((string)file_get_contents($arq['tmp_name']));
        flash("Backup restaurado: $n arquivos. O estado anterior foi guardado em um backup \"antes-de-restaurar\".");
        redirecionar('index.php?a=backup');
    }
    $lista = Backup::lista();
    $cfg = config('backup_geral');
    cabecalho('Backup', [['Backup', null]]);
    ?>
<div class="titulo-pagina"><div><h1>Backup</h1><p class="mudo">Uma cópia de todos os dados: modelos, documentos, arquivos gerados e o que cada ferramenta guarda.</p></div></div>
<div class="barra-voltar"><a class="btn" href="index.php">← Início</a></div>
<div class="colunas">
  <section class="cartao">
    <h2>Fazer backup</h2>
    <p class="mudo pequeno">Baixe e guarde em outro lugar (pen drive, Google Drive). O histórico de versões e a lixeira ficam de fora.</p>
    <div class="grupo">
      <a class="btn btn-primario" href="index.php?a=backup_baixar">Baixar backup agora</a>
      <form method="post" action="index.php?a=backup_agora" class="inline"><button class="btn">Gravar uma cópia aqui no servidor</button></form>
    </div>
    <h2 style="margin-top:20px">Restaurar</h2>
    <p class="mudo pequeno">Envie um .zip de backup. Os arquivos dele substituem os atuais com o mesmo nome. Antes, o estado atual é guardado automaticamente.</p>
    <form method="post" enctype="multipart/form-data" action="index.php?a=backup_restaurar" class="grupo" data-confirmar="Restaurar este backup por cima dos dados atuais?">
      <input type="file" name="zip" accept=".zip" required style="max-width:320px">
      <button class="btn btn-perigo">Restaurar</button>
    </form>
  </section>
  <section class="cartao">
    <h2>Cópias no servidor</h2>
    <p class="mudo pequeno"><?= $cfg['dias'] > 0 ? 'Uma cópia automática é feita a cada ' . (int)$cfg['dias'] . ' dias.' : 'O backup automático está desligado.' ?> Ficam as <?= (int)$cfg['manter'] ?> mais recentes (ajuste em config.php).</p>
    <?php if (!$lista): ?><p class="mudo">Nenhuma ainda.</p><?php else: ?>
    <table class="lista">
      <?php foreach ($lista as $b): ?>
      <tr>
        <td><span class="tag">zip</span><?= h(data_legivel($b['mtime'])) ?> <span class="mudo pequeno"><?= h(str_contains($b['nome'], '_auto') ? 'automático' : (str_contains($b['nome'], 'antes-de-restaurar') ? 'antes de restaurar' : 'manual')) ?></span></td>
        <td class="mudo nao-celular"><?= tamanho_legivel($b['tamanho']) ?></td>
        <td class="acoes"><a class="btn btn-p" href="index.php?a=backup_arquivo&amp;n=<?= h(rawurlencode($b['nome'])) ?>">Baixar</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php
    rodape();
}

function pagina_categoria(string $c): void
{
    $cats = categorias();
    $cat = $cats[$c] ?? throw new RuntimeException('Categoria não encontrada.');

    if (($_GET['a'] ?? '') === 'nova_ferramenta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        exigir_admin('Criar ferramentas');
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
  <?php if (eh_admin()): ?>
  <button class="bloco bloco-novo" data-abrir="#nova-ferr">
    <span class="bloco-caminho">novo</span><span class="bloco-nome">+ Nova ferramenta de documento</span>
    <span class="bloco-desc">A partir de um .docx ou .xlsx modelo</span>
  </button>
  <?php endif; ?>
</div>
<?php if (eh_admin()): ?>
<form id="nova-ferr" class="cartao painel-oculto form-grade" method="post" enctype="multipart/form-data" action="index.php?c=<?= h($c) ?>&a=nova_ferramenta">
  <h2 class="largo">Nova ferramenta de documento</h2>
  <label>Nome <input name="nome" required placeholder="Ex.: Recibo de aluguel"></label>
  <label class="largo">Descrição <input name="descricao"></label>
  <label class="largo">Arquivo modelo (.docx ou .xlsx) <input type="file" name="modelo" accept=".docx,.xlsx"></label>
  <div class="largo"><button class="btn btn-primario">Criar ferramenta</button></div>
</form>
<?php endif; ?>
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
            if (Ferramenta::ehModelo($rel)) {
                exigir_admin('Excluir modelos');
            }
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
