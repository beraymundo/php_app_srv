<?php
/**
 * Programa do bot do Telegram. Fica rodando e busca as mensagens a cada poucos segundos (long polling),
 * sem precisar de endereço público. Use o php-win.exe para não abrir janela:
 *
 *   H:\XAMPP\php\php-win.exe H:\XAMPP\htdocs\php_app_srv\bin\telegram_bot.php
 *
 * Só roda uma cópia por vez: se já houver outra rodando, esta sai na hora. Por isso a tarefa agendada
 * do Windows pode chamá-lo a cada 5 minutos com segurança (serve para religar depois de um reinício).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../lib/nucleo.php';
require __DIR__ . '/../lib/acoes.php';
require __DIR__ . '/../lib/bot/interpretar.php';
require __DIR__ . '/../lib/bot/telegram.php';
require __DIR__ . '/../lib/bot/bot.php';
require __DIR__ . '/../lib/bot/avisos.php';

set_time_limit(0);
if (!is_dir(DIR_USUARIOS)) {
    mkdir(DIR_USUARIOS, 0777, true);
}
$trava = fopen(DIR_USUARIOS . '/telegram.run.lock', 'c');
if (!flock($trava, LOCK_EX | LOCK_NB)) {
    exit(0); // já existe um rodando
}
$vivo = DIR_USUARIOS . '/telegram_vivo.txt';
$parar = DIR_USUARIOS . '/telegram_parar.txt';
@unlink($parar);
tg_log('bot iniciado (pid ' . getmypid() . ')');

$tokenAtual = '';
$api = null;
$bot = null;
$falhas = 0;
$ultimoAviso = 0;
while (true) {
    file_put_contents($vivo, (string)time());
    if (is_file($parar)) {
        @unlink($parar);
        tg_log('bot parado pela página');
        @unlink($vivo);
        break;
    }
    usuarios(ler_json(DIR_USUARIOS . '/usuarios.json')); // contas criadas depois que o bot ligou
    $cfg = tg_config();
    if ($cfg['token'] === '') {
        sleep(15);
        continue;
    }
    if ($cfg['token'] !== $tokenAtual) {
        $tokenAtual = $cfg['token'];
        $api = new TelegramHttp($tokenAtual, 40);
        $bot = new BotCentral($api);
        try {
            $api->chamar('setMyCommands', ['commands' => tg_comandos()]);
            $api->chamar('deleteWebhook'); // garante o modo de busca (polling)
        } catch (Throwable $e) {
            tg_log('configuração inicial: ' . $e->getMessage());
        }
    }
    try {
        $updates = $api->chamar('getUpdates', ['offset' => (int)$cfg['offset'], 'timeout' => 25, 'allowed_updates' => ['message', 'callback_query']]);
        $falhas = 0;
    } catch (Throwable $e) {
        $falhas++;
        if ($falhas === 1 || $falhas % 20 === 0) {
            tg_log('getUpdates: ' . $e->getMessage());
        }
        sleep(min(60, 5 * $falhas));
        continue;
    }
    // lembretes de aniversários e contas (confere a cada 10 minutos, a partir da hora configurada)
    if (time() - $ultimoAviso >= 600 && (int)date('G') >= (int)(config('avisos_hora') ?? 8)) {
        $ultimoAviso = time();
        try {
            avisos_enviar($api);
        } catch (Throwable $e) {
            tg_log('avisos: ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
    foreach ($updates as $u) {
        // grava o offset antes de processar: uma mensagem que derruba o bot não fica repetindo para sempre
        tg_alterar(function (array $c) use ($u) {
            $c['offset'] = max((int)$c['offset'], (int)$u['update_id'] + 1);
            return $c;
        });
        try {
            $bot->processar($u);
        } catch (Throwable $e) {
            tg_log('erro processando mensagem: ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
            try {
                $chat = $u['message']['chat']['id'] ?? $u['callback_query']['message']['chat']['id'] ?? null;
                if ($chat) {
                    $api->chamar('sendMessage', ['chat_id' => $chat, 'text' => 'Deu um erro aqui e não consegui fazer isso. Tente de novo; se continuar, veja o registro em usuarios/telegram.log.']);
                }
            } catch (Throwable $e2) {
            }
        }
    }
}
