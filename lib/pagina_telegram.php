<?php
/**
 * Página "Telegram" (index.php?a=telegram): vincular o Telegram do usuário e, para o administrador,
 * configurar o token do bot e ligar/desligar o programa.
 */
require_once __DIR__ . '/bot/telegram.php';
require_once __DIR__ . '/bot/bot.php';

const TG_TAREFA = 'CentralFerramentas\\TelegramBot';

function tg_comando_bot(): string
{
    return '"' . dirname(PHP_BINARY) . '\\php-win.exe" "' . str_replace('/', '\\', RAIZ) . '\\bin\\telegram_bot.php"';
}

function pagina_telegram(string $a): void
{
    $eu = usuario_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($a) {
            case 'telegram_codigo':
                $_SESSION['tg_codigo'] = tg_gerar_codigo($eu);
                break;
            case 'telegram_desvincular':
                $chat = (string)($_POST['chat'] ?? '');
                tg_alterar(function (array $c) use ($chat, $eu) {
                    $v = $c['vinculos'][$chat] ?? null;
                    if ($v && ((is_array($v) ? $v['login'] : $v) === $eu || eh_admin())) {
                        unset($c['vinculos'][$chat]);
                    }
                    return $c;
                });
                flash('Telegram desvinculado.');
                break;
            case 'telegram_token':
                exigir_admin('Configurar o bot');
                $token = trim((string)($_POST['token'] ?? ''));
                if ($token === '') {
                    tg_alterar(fn($c) => ['token' => '', 'bot' => '', 'offset' => 0] + $c);
                    flash('Token removido. O bot fica desligado.');
                    break;
                }
                $eu_bot = (new TelegramHttp($token, 15))->chamar('getMe'); // valida o token
                tg_alterar(fn($c) => ['token' => $token, 'bot' => (string)($eu_bot['username'] ?? ''), 'offset' => 0] + $c);
                flash('Bot @' . ($eu_bot['username'] ?? '?') . ' configurado.');
                if (!tg_bot_rodando()) {
                    try {
                        tg_iniciar();
                    } catch (RuntimeException $e) {
                        flash($e->getMessage(), 'erro');
                    }
                }
                break;
            case 'telegram_iniciar':
                exigir_admin('Ligar o bot');
                tg_iniciar();
                flash('Pedido para ligar o bot enviado. Pode levar alguns segundos.');
                break;
            case 'telegram_parar':
                exigir_admin('Desligar o bot');
                file_put_contents(DIR_USUARIOS . '/telegram_parar.txt', '1');
                flash('O bot vai parar em até 30 segundos. A tarefa agendada liga de novo em até 5 minutos; para desligar de vez, remova o token.');
                break;
        }
        redirecionar('index.php?a=telegram');
    }

    $cfg = tg_config();
    $codigo = $_SESSION['tg_codigo'] ?? null;
    unset($_SESSION['tg_codigo']);
    $chats = tg_chats_do_usuario($eu);
    $rodando = tg_bot_rodando();
    cabecalho('Telegram', [['Minha conta', 'index.php?a=conta'], ['Telegram', null]]);
    ?>
<div class="titulo-pagina"><div><h1>Telegram</h1><p class="mudo">Registre gastos, entradas, contas pagas e aniversários mandando mensagem para o bot.</p></div></div>
<div class="barra-voltar"><a class="btn" href="index.php?a=conta">← Minha conta</a><a class="btn btn-leve" href="index.php">Início</a></div>

<div class="colunas">
  <section class="cartao">
    <h2>Seu Telegram</h2>
    <?php if ($cfg['token'] === ''): ?>
      <p class="mudo">O bot ainda não foi configurado<?= eh_admin() ? ' (veja ao lado).' : '. Peça ao administrador.' ?></p>
    <?php else: ?>
      <p>Bot: <a href="https://t.me/<?= h($cfg['bot']) ?>" target="_blank" rel="noopener">@<?= h($cfg['bot']) ?></a>
        · <?= $rodando ? '<span class="estado-ok">funcionando</span>' : '<span class="estado-ruim">desligado</span>' ?></p>
      <?php if ($chats): ?>
        <table class="dados">
          <?php foreach ($chats as $id => $v): ?>
          <tr><td><?= h(is_array($v) ? ($v['nome'] ?: 'Telegram') : 'Telegram') ?> <span class="mudo pequeno">vinculado <?= is_array($v) && !empty($v['desde']) ? h(data_legivel(strtotime($v['desde']))) : '' ?></span></td>
            <td class="num"><form method="post" action="index.php?a=telegram_desvincular" data-confirmar="Desvincular este Telegram?"><input type="hidden" name="chat" value="<?= h((string)$id) ?>"><button class="btn btn-p btn-perigo">Desvincular</button></form></td></tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
      <?php if ($codigo): ?>
        <div class="aviso aviso-ok" style="margin-top:12px">
          <p style="margin:0 0 8px"><a class="btn btn-primario" href="https://t.me/<?= h($cfg['bot']) ?>?start=<?= h($codigo) ?>" target="_blank" rel="noopener">Abrir o bot e vincular</a></p>
          <p class="pequeno" style="margin:0">Ou mande para o bot: <code>/vincular <?= h($codigo) ?></code> · vale por 15 minutos.</p>
        </div>
      <?php else: ?>
        <form method="post" action="index.php?a=telegram_codigo" style="margin-top:12px"><button class="btn <?= $chats ? '' : 'btn-primario' ?>"><?= $chats ? 'Vincular outro aparelho' : 'Vincular meu Telegram' ?></button></form>
      <?php endif; ?>
    <?php endif; ?>
    <h2 style="margin-top:20px">O que mandar</h2>
    <table class="tabela-simples pequeno">
      <tr><td><code>gastei 45 mercado</code></td><td>gasto (categoria pelo histórico ou por palavras)</td></tr>
      <tr><td><code>brinquei num futebol por 12 reais</code></td><td>gasto em Lazer</td></tr>
      <tr><td><code>paguei 35 farmácia no nubank ontem</code></td><td>gasto com conta e data</td></tr>
      <tr><td><code>recebi 200 de um freela</code></td><td>entrada</td></tr>
      <tr><td><code>paguei a internet</code> · <code>paguei a luz 187,40</code></td><td>marca a conta fixa como paga</td></tr>
      <tr><td><code>recebi o salário</code></td><td>marca a entrada fixa como recebida</td></tr>
      <tr><td><code>aniversário da Ana 15/03</code></td><td>novo aniversário</td></tr>
      <tr><td><code>/menu</code> <code>/resumo</code> <code>/fixos</code> <code>/desfazer</code></td><td>botões, resumo do mês, contas a pagar, desfazer</td></tr>
    </table>
    <p class="dica">Verbos de gasto: gastei, comprei, paguei, peguei, usei, brinquei (e "45 lanche" sem verbo também vale). Antes de gravar o bot sempre pede confirmação.</p>
  </section>

  <?php if (eh_admin()): ?>
  <section class="cartao">
    <h2>Configuração do bot</h2>
    <form method="post" action="index.php?a=telegram_token" class="form-grade" style="grid-template-columns:1fr">
      <label>Token do bot (do @BotFather)
        <input name="token" type="password" autocomplete="off" placeholder="<?= $cfg['token'] ? 'configurado: ' . h(substr($cfg['token'], 0, 6)) . '... (deixe vazio e salve para remover)' : '123456789:AA...' ?>">
      </label>
      <div class="grupo"><button class="btn btn-primario">Salvar token</button></div>
    </form>
    <?php if ($cfg['token'] === ''): ?>
    <ol class="pequeno" style="padding-left:18px">
      <li>No Telegram, abra o <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> e mande <code>/newbot</code>.</li>
      <li>Escolha um nome (ex.: <i>Central Bernardo</i>) e um usuário terminado em <code>bot</code> (ex.: <i>central_bernardo_bot</i>).</li>
      <li>Ele responde com um token parecido com <code>123456789:AAH...</code>. Cole aqui e salve.</li>
      <li>Depois, em "Seu Telegram", toque em <b>Vincular meu Telegram</b>.</li>
    </ol>
    <?php else: ?>
    <p>Programa do bot: <?= $rodando ? '<span class="estado-ok">rodando</span>' : '<span class="estado-ruim">parado</span>' ?></p>
    <div class="grupo">
      <?php if (!$rodando): ?><form method="post" action="index.php?a=telegram_iniciar" class="inline"><button class="btn">Ligar agora</button></form><?php endif; ?>
      <?php if ($rodando): ?><form method="post" action="index.php?a=telegram_parar" class="inline"><button class="btn btn-leve">Reiniciar</button></form><?php endif; ?>
    </div>
    <?php if (!$rodando): ?><p class="pequeno">Primeira vez? No PC, dê dois cliques em <code>bin\instalar_bot_telegram.bat</code> (na pasta do projeto). Ele cria a tarefa que mantém o bot ligado.</p><?php endif; ?>
    <p class="dica">Uma tarefa agendada do Windows (<code><?= h(TG_TAREFA) ?></code>) confere a cada 5 minutos se o bot está rodando e liga de novo se precisar, inclusive depois de reiniciar o PC (após o login no Windows).</p>
    <?php
    $log = DIR_USUARIOS . '/telegram.log';
    $linhas = is_file($log) ? array_slice(file($log, FILE_IGNORE_NEW_LINES), -12) : [];
    if ($linhas): ?>
      <h2 style="margin-top:16px">Registro</h2>
      <div class="saida-texto pequeno"><?= h(implode("\n", array_reverse($linhas))) ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php
    rodape();
}

/** Liga o programa do bot pela tarefa agendada (fica independente do Apache). */
function tg_iniciar(): void
{
    exec('schtasks /run /tn "' . TG_TAREFA . '" 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        throw new RuntimeException('Não consegui ligar o bot: a tarefa agendada "' . TG_TAREFA . '" ainda não existe. No PC, dê dois cliques em bin\instalar_bot_telegram.bat (dentro da pasta do projeto) e tente de novo.');
    }
}
