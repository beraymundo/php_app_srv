<?php
/**
 * Teste dos lembretes do Telegram (aniversários e contas chegando), com Telegram de mentira e dados numa pasta temporária.
 * Rode:  php testes/avisos_teste.php
 */
$tmp = sys_get_temp_dir() . '/central_avisos_teste_' . getmypid();
define('DIR_USUARIOS', $tmp);
require __DIR__ . '/../lib/nucleo.php';
require __DIR__ . '/../lib/acoes.php';
require __DIR__ . '/../lib/bot/telegram.php';
require __DIR__ . '/../lib/bot/avisos.php';

final class TelegramFalso implements ApiTelegram
{
    public array $saida = [];

    public function chamar(string $metodo, array $params = []): mixed
    {
        $this->saida[] = [$metodo, $params];
        return ['message_id' => count($this->saida)];
    }
}

$ok = 0;
$falhas = 0;
function confere(string $nome, bool $cond, string $detalhe = ''): void
{
    global $ok, $falhas;
    if ($cond) {
        $ok++;
    } else {
        $falhas++;
        echo "FALHOU  $nome" . ($detalhe ? "\n        $detalhe" : '') . "\n";
    }
}

// "hoje" fixo: sexta, 13/11/2026
$hoje = new DateTimeImmutable('2026-11-13');

criar_usuario('ana', 'Ana', 'teste123', true);
criar_usuario('rui', 'Rui', 'teste123', false); // sem Telegram: não recebe nada
tg_alterar(function ($c) {
    $c['vinculos']['111'] = ['login' => 'ana', 'nome' => 'Ana', 'desde' => date('c')];
    return $c;
});

usuario_forcar('ana');
estado_alterar(aniv(), fn($e) => ['pessoas' => [
    ['id' => 'p1', 'nome' => 'Bia', 'dia' => 14, 'mes' => 11, 'ano' => 1990],             // amanhã, padrão 1 dia -> avisa
    ['id' => 'p2', 'nome' => 'Caio', 'dia' => 16, 'mes' => 11, 'ano' => null],            // em 3 dias, padrão -> não
    ['id' => 'p3', 'nome' => 'Duda', 'dia' => 20, 'mes' => 11, 'avisar' => 7],            // em 7 dias, avisar 7 -> avisa
    ['id' => 'p4', 'nome' => 'Edu', 'dia' => 13, 'mes' => 11, 'avisar' => -1],            // hoje, mas "não avisar"
    ['id' => 'p5', 'nome' => 'Fê <b>', 'dia' => 13, 'mes' => 11, 'avisar' => 0],          // hoje, "no dia" -> avisa (e escapa o HTML)
    ['id' => 'p6', 'nome' => 'Gil', 'dia' => 12, 'mes' => 11],                            // foi ontem -> não
]]);
estado_alterar(fin(), fn($e) => [
    'fixos' => [
        ['id' => 'f1', 'desc' => 'Internet', 'valor' => 99.9, 'dia' => 14, 'tipo' => 'despesa', 'desde' => '2026-01', 'ate' => null],       // amanhã -> avisa, com botão
        ['id' => 'f2', 'desc' => 'Aluguel', 'valor' => 1500, 'dia' => 14, 'tipo' => 'despesa', 'desde' => '2026-01', 'ate' => null],        // amanhã, mas já pago
        ['id' => 'f3', 'desc' => 'Salário', 'valor' => 3000, 'dia' => 14, 'tipo' => 'receita', 'desde' => '2026-01', 'ate' => null],        // entrada: nunca avisa
        ['id' => 'f4', 'desc' => 'Escola', 'valor' => 800, 'dia' => 2, 'tipo' => 'despesa', 'desde' => '2026-01', 'ate' => null, 'avisar' => 20], // dia 02/12 -> em 19 dias, sem botão (mês que vem)
        ['id' => 'f5', 'desc' => 'Academia', 'valor' => 90, 'dia' => 14, 'tipo' => 'despesa', 'desde' => '2026-01', 'ate' => '2026-10'],    // encerrada
    ],
    'meses' => ['2026-11' => ['fixos' => ['f2' => ['v' => 1500, 'pago' => true]], 'lanc' => [], 'saldos' => []]],
], FIN_MAPAS);
estado_alterar(new Ferramenta('casa', 'contas'), fn($e) => [
    'contas' => [
        ['id' => 'c1', 'nome' => 'Energia', 'valor' => 180, 'dia' => 15, 'ativa' => true, 'avisar' => 2],  // em 2 dias -> avisa
        ['id' => 'c2', 'nome' => 'Água', 'valor' => 60, 'dia' => 15, 'ativa' => false, 'avisar' => 5],     // inativa
    ],
    'meses' => ['2026-11' => ['c1' => ['v' => 210.5, 'pago' => false]]],
]);
usuario_forcar('');

// ---- o que está no prazo
usuario_forcar('ana');
$p = avisos_pendentes($hoje);
usuario_forcar('');
$chaves = array_column($p, 'chave');
$esperado = ['aniv:p5:2026-11-13', 'aniv:p1:2026-11-14', 'fin:f1:2026-11-14', 'casa:c1:2026-11-15', 'aniv:p3:2026-11-20', 'fin:f4:2026-12-02'];
confere('itens no prazo, mais próximos primeiro', $chaves === $esperado, json_encode($chaves));
confere('padrão é 1 dia', aviso_dias([]) === 1 && aviso_dias(['avisar' => '']) === 1 && aviso_dias(['avisar' => 0]) === 0 && aviso_dias(['avisar' => -1]) === null);

// ---- envio
$api = new TelegramFalso();
$n = avisos_enviar($api, $hoje);
confere('envia os 6 avisos numa mensagem', $n === 6 && count($api->saida) === 1, "n=$n, mensagens=" . count($api->saida));
[$metodo, $msg] = $api->saida[0] ?? ['', []];
$txt = $msg['text'] ?? '';
confere('vai para o chat vinculado', $metodo === 'sendMessage' && (string)$msg['chat_id'] === '111');
confere('texto do aniversário com idade', str_contains($txt, 'Aniversário de <b>Bia</b> amanhã (14/11) · faz 36 anos'), $txt);
confere('aniversário de hoje', str_contains($txt, 'Fê &lt;b&gt;</b> <b>hoje</b>'), $txt);
confere('conta com valor do mês', str_contains($txt, 'Energia</b> vence em 2 dias (domingo, 15/11) · R$ 210,50'), $txt);
confere('fixo do mês que vem', str_contains($txt, 'Escola</b> vence em 19 dias') && str_contains($txt, 'R$ 800,00'), $txt);
confere('não avisa pago, entrada, inativo, encerrado, passado', !preg_match('/Aluguel|Salário|Água|Academia|Gil|Edu|Caio/', $txt), $txt);
$botoes = array_merge(...($msg['reply_markup']['inline_keyboard'] ?? [[]]));
confere('botão "Paguei" só para o fixo deste mês', count($botoes) === 1 && $botoes[0]['callback_data'] === 'fixo:f1' && str_contains($botoes[0]['text'], 'Internet'), json_encode($botoes, JSON_UNESCAPED_UNICODE));

// ---- não repete
$api->saida = [];
confere('não repete o mesmo aviso', avisos_enviar($api, $hoje) === 0 && !$api->saida);

// ---- no dia seguinte só o que entrou no prazo
$api->saida = [];
usuario_forcar('ana');
estado_alterar(aniv(), function ($e) {
    $e['pessoas'][] = ['id' => 'p7', 'nome' => 'Hugo', 'dia' => 15, 'mes' => 11];
    return $e;
});
usuario_forcar('');
$n = avisos_enviar($api, $hoje->modify('+1 day'));
$txt = $api->saida[0][1]['text'] ?? '';
confere('dia seguinte: só o novo', $n === 1 && str_contains($txt, 'Hugo') && !str_contains($txt, 'Bia'), "n=$n $txt");

// ---- pagar antes evita o aviso; registro antigo é podado
usuario_forcar('ana');
$feitos = ler_json(DIR_USUARIOS . '/ana/telegram_avisos.json');
usuario_forcar('');
confere('registro dos avisos', isset($feitos['fin:f1:2026-11-14'], $feitos['aniv:p7:2026-11-15']));
$api->saida = [];
avisos_enviar($api, new DateTimeImmutable('2027-01-30'));
$feitos = ler_json(DIR_USUARIOS . '/ana/telegram_avisos.json');
confere('esquece avisos com mais de 40 dias', !isset($feitos['aniv:p1:2026-11-14']), json_encode(array_keys($feitos)));

// ---- falha no Telegram: não marca como avisado
$quebrado = new class implements ApiTelegram {
    public function chamar(string $metodo, array $params = []): mixed
    {
        throw new RuntimeException('sem rede');
    }
};
usuario_forcar('ana');
estado_alterar(aniv(), function ($e) {
    $e['pessoas'][] = ['id' => 'p8', 'nome' => 'Iara', 'dia' => 1, 'mes' => 3];
    return $e;
});
usuario_forcar('');
$dia = new DateTimeImmutable('2027-02-28');
confere('sem rede não conta como enviado', avisos_enviar($quebrado, $dia) === 0);
$api->saida = [];
confere('tenta de novo depois', avisos_enviar($api, $dia) >= 1 && str_contains($api->saida[0][1]['text'] ?? '', 'Iara'));
confere('usuário volta a ser ninguém', usuario_forcar() === '');

apagar_recursivo($tmp);
echo "\n$ok ok, $falhas falha(s)\n";
exit($falhas ? 1 : 0);
