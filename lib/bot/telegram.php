<?php
/**
 * Cliente da API do Telegram e configuração do bot.
 *
 * usuarios/telegram.json          token, nome do bot, offset das mensagens, vínculos chat -> usuário, códigos de vínculo
 * usuarios/<login>/telegram.json  conversa em andamento (o que está esperando confirmação) e último registro (para desfazer)
 * usuarios/telegram_vivo.txt      hora da última volta do programa do bot (para saber se está rodando)
 */

interface ApiTelegram
{
    public function chamar(string $metodo, array $params = []): mixed;
}

final class TelegramHttp implements ApiTelegram
{
    public function __construct(private string $token, private int $timeout = 40)
    {
    }

    public function chamar(string $metodo, array $params = []): mixed
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/$metodo");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $ca = dirname(PHP_BINARY, 2) . '/apache/bin/curl-ca-bundle.crt'; // certificados que vêm no XAMPP
        if (!ini_get('curl.cainfo') && is_file($ca)) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException("Sem conexão com o Telegram: $erro");
        }
        $j = json_decode((string)$resp, true);
        if (!is_array($j) || empty($j['ok'])) {
            throw new RuntimeException('Telegram: ' . ($j['description'] ?? 'resposta inválida'));
        }
        return $j['result'];
    }
}

// ==================================================================== configuração

function tg_arquivo(): string
{
    return DIR_USUARIOS . '/telegram.json';
}

function tg_config(): array
{
    return ler_json(tg_arquivo()) + ['token' => '', 'bot' => '', 'offset' => 0, 'vinculos' => [], 'codigos' => []];
}

/** Altera a configuração com o arquivo trancado (o programa do bot e a página podem gravar ao mesmo tempo). */
function tg_alterar(callable $fn): array
{
    if (!is_dir(DIR_USUARIOS)) {
        mkdir(DIR_USUARIOS, 0777, true);
    }
    $trava = fopen(tg_arquivo() . '.lock', 'c');
    flock($trava, LOCK_EX);
    try {
        $c = $fn(tg_config());
        $obj = $c;
        $obj['vinculos'] = (object)$c['vinculos'];
        $obj['codigos'] = (object)$c['codigos'];
        estado_gravar_arquivo(tg_arquivo(), json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $c;
    } finally {
        flock($trava, LOCK_UN);
        fclose($trava);
    }
}

/** Código de 6 dígitos, válido por 15 minutos, para ligar um chat do Telegram ao usuário. */
function tg_gerar_codigo(string $login): string
{
    $codigo = (string)random_int(100000, 999999);
    tg_alterar(function (array $c) use ($login, $codigo) {
        $c['codigos'] = array_filter($c['codigos'], fn($x) => $x['expira'] > time() && $x['login'] !== $login);
        $c['codigos'][$codigo] = ['login' => $login, 'expira' => time() + 900];
        return $c;
    });
    return $codigo;
}

/** Usa um código: devolve o login vinculado ou null. */
function tg_usar_codigo(string $codigo, string|int $chatId, string $nomeChat): ?string
{
    $login = null;
    tg_alterar(function (array $c) use ($codigo, $chatId, $nomeChat, &$login) {
        $x = $c['codigos'][$codigo] ?? null;
        if ($x && $x['expira'] > time() && isset(usuarios()[$x['login']])) {
            $login = $x['login'];
            $c['vinculos'][(string)$chatId] = ['login' => $login, 'nome' => $nomeChat, 'desde' => date('c')];
            unset($c['codigos'][$codigo]);
        }
        return $c;
    });
    return $login;
}

function tg_login_do_chat(string|int $chatId): ?string
{
    $v = tg_config()['vinculos'][(string)$chatId] ?? null;
    $login = is_array($v) ? ($v['login'] ?? null) : $v;
    return $login && isset(usuarios()[$login]) ? $login : null;
}

function tg_chats_do_usuario(string $login): array
{
    return array_filter(tg_config()['vinculos'], fn($v) => (is_array($v) ? $v['login'] : $v) === $login);
}

function tg_bot_rodando(): bool
{
    $f = DIR_USUARIOS . '/telegram_vivo.txt';
    return is_file($f) && (int)file_get_contents($f) > time() - 90;
}

// ==================================================================== conversa de cada usuário

function tg_conversa(string $login): array
{
    return ler_json(DIR_USUARIOS . "/$login/telegram.json") + ['pendente' => null, 'aguardando' => null, 'ultima' => null];
}

function tg_salvar_conversa(string $login, array $c): void
{
    gravar_json(DIR_USUARIOS . "/$login/telegram.json", $c);
}

function tg_log(string $msg): void
{
    $f = DIR_USUARIOS . '/telegram.log';
    if (is_file($f) && filesize($f) > 512 * 1024) {
        rename($f, "$f.1");
    }
    file_put_contents($f, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
}
