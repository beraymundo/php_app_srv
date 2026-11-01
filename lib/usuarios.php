<?php
/**
 * Usuários e permissões.
 *
 * usuarios/usuarios.json            contas: {"login": {nome, hash, admin, criado}}
 * usuarios/<login>/ferramentas/...  dados de cada pessoa (documentos, saídas, dados, versões, lixeira)
 * ferramentas/<cat>/<id>/modelos    modelos, compartilhados por todos
 *
 * Administrador: cria/edita usuários, categorias e ferramentas, mexe nos modelos e no backup geral.
 * Usuário comum: usa as ferramentas com os próprios dados.
 */

const DIR_USUARIOS = RAIZ . '/usuarios';

function usuarios(?array $trocar = null): array
{
    static $cache = null;
    if ($trocar !== null) {
        $cache = $trocar;
    }
    return $cache ??= ler_json(DIR_USUARIOS . '/usuarios.json');
}

function salvar_usuarios(array $u): void
{
    if (!is_dir(DIR_USUARIOS)) {
        mkdir(DIR_USUARIOS, 0777, true);
    }
    if (!is_file(DIR_USUARIOS . '/.htaccess')) {
        file_put_contents(DIR_USUARIOS . '/.htaccess', "Require all denied\n");
    }
    ksort($u);
    gravar_json(DIR_USUARIOS . '/usuarios.json', $u);
    usuarios($u);
}

function login_valido(string $login): bool
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9._-]{1,30}$/', $login);
}

/** Login de quem está usando agora (sessão), ou '' se ninguém entrou. */
function usuario_login(): string
{
    $l = (string)($_SESSION['usuario'] ?? '');
    return $l !== '' && isset(usuarios()[$l]) ? $l : '';
}

function usuario_atual(): ?array
{
    $l = usuario_login();
    return $l ? usuarios()[$l] + ['login' => $l] : null;
}

function eh_admin(): bool
{
    return !empty(usuario_atual()['admin']);
}

function exigir_admin(string $oque = 'Isso'): void
{
    if (!eh_admin()) {
        throw new RuntimeException("$oque só pode ser feito pelo administrador.");
    }
}

/** Pasta de dados do usuário atual. */
function dir_dados_usuario(?string $login = null): string
{
    $login ??= usuario_login();
    if ($login === '') {
        throw new RuntimeException('Entre com seu usuário para continuar.');
    }
    return DIR_USUARIOS . "/$login/ferramentas";
}

function criar_usuario(string $login, string $nome, string $senha, bool $admin): void
{
    $login = strtolower(trim($login));
    if (!login_valido($login)) {
        throw new RuntimeException('Usuário inválido: use letras minúsculas, números, ponto ou traço (2 a 31 caracteres).');
    }
    $u = usuarios();
    if (isset($u[$login])) {
        throw new RuntimeException("O usuário \"$login\" já existe.");
    }
    validar_senha($senha);
    $u[$login] = ['nome' => trim($nome) ?: ucfirst($login), 'hash' => password_hash($senha, PASSWORD_DEFAULT), 'admin' => $admin, 'criado' => date('c')];
    salvar_usuarios($u);
    if (!is_dir(dir_dados_usuario($login))) {
        mkdir(dir_dados_usuario($login), 0777, true);
    }
}

function validar_senha(string $senha): void
{
    if (mb_strlen($senha) < 4) {
        throw new RuntimeException('A senha precisa ter pelo menos 4 caracteres.');
    }
}

function entrar(string $login, string $senha): bool
{
    $login = strtolower(trim($login));
    $u = usuarios()[$login] ?? null;
    if (!$u || !password_verify($senha, $u['hash'])) {
        usleep(600000); // atrasa tentativas seguidas
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['usuario'] = $login;
    return true;
}

/**
 * Primeiro acesso: os dados que já existiam (de antes de haver usuários) passam a ser do administrador.
 * Modelos e o histórico de versões dos modelos continuam compartilhados na pasta da ferramenta.
 */
function migrar_dados_antigos(string $login): int
{
    $n = 0;
    $destinoBase = dir_dados_usuario($login);
    foreach (glob(DIR_FERRAMENTAS . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $rel = substr(str_replace('\\', '/', $dir), strlen(str_replace('\\', '/', DIR_FERRAMENTAS)) + 1);
        $destino = "$destinoBase/$rel";
        foreach (['documentos', 'saidas', 'dados', 'lixeira'] as $pasta) {
            if (is_dir("$dir/$pasta")) {
                if (!is_dir($destino)) {
                    mkdir($destino, 0777, true);
                }
                if (!is_dir("$destino/$pasta") && @rename("$dir/$pasta", "$destino/$pasta")) {
                    $n++;
                }
            }
        }
        // versões: as dos modelos ficam; as do resto vão junto com os arquivos
        foreach (glob("$dir/backups/*", GLOB_ONLYDIR) ?: [] as $v) {
            if (str_starts_with(basename($v), rawurlencode('modelos/'))) {
                continue;
            }
            if (!is_dir("$destino/backups")) {
                mkdir("$destino/backups", 0777, true);
            }
            if (@rename($v, "$destino/backups/" . basename($v))) {
                $n++;
            }
        }
    }
    return $n;
}
