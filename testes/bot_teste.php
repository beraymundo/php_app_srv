<?php
/**
 * Teste de ponta a ponta do bot com um Telegram de mentira e dados numa pasta temporária.
 * Rode:  php testes/bot_teste.php
 */
$tmp = sys_get_temp_dir() . '/central_bot_teste_' . getmypid();
define('DIR_USUARIOS', $tmp);
require __DIR__ . '/../lib/nucleo.php';
require __DIR__ . '/../lib/acoes.php';
require __DIR__ . '/../lib/bot/interpretar.php';
require __DIR__ . '/../lib/bot/telegram.php';
require __DIR__ . '/../lib/bot/bot.php';

final class TelegramFalso implements ApiTelegram
{
    public array $saida = [];
    private int $id = 100;

    public function chamar(string $metodo, array $params = []): mixed
    {
        if ($metodo === 'answerCallbackQuery') {
            return true;
        }
        $this->saida[] = [$metodo, $params];
        return ['message_id' => ++$this->id];
    }

    public function ultima(): array
    {
        return end($this->saida) ?: ['', []];
    }

    public function texto(): string
    {
        return strip_tags($this->ultima()[1]['text'] ?? '');
    }

    /** Dado do botão cujo rótulo contém $rotulo, na última mensagem. */
    public function botao(string $rotulo): string
    {
        foreach ($this->ultima()[1]['reply_markup']['inline_keyboard'] ?? [] as $linha) {
            foreach ($linha as $b) {
                if (str_contains($b['text'], $rotulo)) {
                    return $b['callback_data'];
                }
            }
        }
        throw new RuntimeException("Botão \"$rotulo\" não encontrado em: " . json_encode($this->ultima()[1]['reply_markup'] ?? null, JSON_UNESCAPED_UNICODE));
    }

    public function idMensagem(): int
    {
        return $this->ultima()[1]['message_id'] ?? $this->id;
    }
}

$api = new TelegramFalso();
$bot = new BotCentral($api);
$chat = 555;
$msg = fn(string $t) => $bot->processar(['message' => ['chat' => ['id' => $chat], 'from' => ['first_name' => 'Bernardo'], 'text' => $t]]);
$clicar = fn(string $dado) => $bot->processar(['callback_query' => ['id' => 'q', 'data' => $dado, 'message' => ['chat' => ['id' => $chat], 'message_id' => $api->idMensagem()]]]);

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

// ---- preparação: usuário, contas e fixos
criar_usuario('bernardo', 'Bernardo', 'teste123', true);
usuario_forcar('bernardo');
estado_alterar(fin(), fn($e) => [
    'contas' => [['id' => 'nu', 'nome' => 'Nubank', 'tipo' => 'corrente', 'ativa' => true]],
    'fixos' => [
        ['id' => 'net', 'desc' => 'Internet', 'valor' => 99.9, 'dia' => 10, 'tipo' => 'despesa', 'cat' => 'Contas da casa', 'conta' => 'nu', 'desde' => '2020-01', 'ate' => null],
        ['id' => 'sal', 'desc' => 'Salário', 'valor' => 3000, 'dia' => 5, 'tipo' => 'receita', 'cat' => 'Salário', 'conta' => 'nu', 'desde' => '2020-01', 'ate' => null],
    ],
    'categorias' => FIN_CATEGORIAS,
    'meses' => [],
], FIN_MAPAS);
usuario_forcar('');
$k = date('Y-m');

// ---- chat não vinculado
$msg('gastei 10 lanche');
confere('não vinculado é recusado', str_contains($api->texto(), 'privado'));

// ---- vínculo
$codigo = tg_gerar_codigo('bernardo');
$msg("/start $codigo");
confere('vínculo pelo /start', str_contains($api->texto(), 'ligado ao usuário bernardo'), $api->texto());
confere('chat vinculado', tg_login_do_chat($chat) === 'bernardo');

// ---- gasto completo
$msg('brinquei num futebol de sabão por 12 reais');
confere('confirmação do gasto', str_contains($api->texto(), 'R$ 12,00') && str_contains($api->texto(), 'Futebol de sabão') && str_contains($api->texto(), 'Lazer'), $api->texto());
$clicar('ok');
usuario_forcar('bernardo');
$e = fin_estado();
usuario_forcar('');
$l = $e['meses'][$k]['lanc'][0] ?? null;
confere('gasto gravado', $l && $l['valor'] == 12 && $l['desc'] === 'Futebol de sabão' && $l['cat'] === 'Lazer' && $l['tipo'] === 'despesa', json_encode($l, JSON_UNESCAPED_UNICODE));
confere('versão aumentou', ($e['_v'] ?? 0) >= 2);
confere('mensagem de registrado com desfazer', str_contains($api->texto(), 'Registrado') && $api->botao('Desfazer') === 'desfazer');

// ---- desfazer
$clicar('desfazer');
usuario_forcar('bernardo');
confere('desfazer remove o gasto', empty(fin_estado()['meses'][$k]['lanc']));
usuario_forcar('');

// ---- gasto sem categoria conhecida: pergunta com botões
$msg('comprei uma coisa estranha 15');
confere('pergunta categoria', str_contains($api->texto(), 'Qual a categoria'), $api->texto());
$clicar($api->botao('Compras'));
confere('depois da categoria, confirma', str_contains($api->texto(), 'Compras') && str_contains($api->texto(), 'R$ 15,00'), $api->texto());
// corrigir o valor
$clicar('corrigir');
$clicar('campo:valor');
$msg('17,50');
confere('valor corrigido', str_contains($api->texto(), 'R$ 17,50'), $api->texto());
$clicar('ok');
usuario_forcar('bernardo');
$lanc = fin_estado()['meses'][$k]['lanc'];
usuario_forcar('');
confere('gasto corrigido gravado', count($lanc) === 1 && $lanc[0]['valor'] == 17.5 && $lanc[0]['cat'] === 'Compras');

// ---- a mesma descrição de novo usa a categoria do histórico
$msg('comprei uma coisa estranha 9');
confere('categoria vem do histórico', str_contains($api->texto(), 'Compras') && !str_contains($api->texto(), 'Qual a categoria'), $api->texto());
$clicar('cancelar');

// ---- valor faltando
$msg('gastei no mercado');
confere('pergunta valor', str_contains($api->texto(), 'Qual o valor'));
$msg('230,15');
confere('confirma após valor', str_contains($api->texto(), 'R$ 230,15') && str_contains($api->texto(), 'Mercado'));
$clicar('ok');

// ---- pagar fixo
$msg('paguei a internet');
confere('confirma fixo', str_contains($api->texto(), 'Pagar conta fixa') && str_contains($api->texto(), 'Internet') && str_contains($api->texto(), 'R$ 99,90'), $api->texto());
$clicar('ok');
usuario_forcar('bernardo');
$dm = fin_estado()['meses'][$k];
usuario_forcar('');
confere('fixo marcado pago', !empty($dm['fixos']['net']['pago']));
$json = file_get_contents($tmp . '/bernardo/ferramentas/pessoal/financas/dados/estado.json');
confere('JSON mantém fixos como objeto', str_contains($json, '"fixos": {'), substr($json, strpos($json, '"meses"'), 200));

// ---- salário (entrada fixa) com valor diferente
$msg('caiu o salário 3150');
confere('confirma salário', str_contains($api->texto(), 'Receber conta fixa') && str_contains($api->texto(), 'R$ 3.150,00'), $api->texto());
$clicar('ok');

// ---- /fixos sem pendências
$msg('/fixos');
confere('sem fixos pendentes', str_contains($api->texto(), 'já estão pagas'), $api->texto());

// ---- frase desconhecida
$msg('bom dia');
confere('desconhecido oferece tipos', str_contains($api->texto(), 'Não entendi'));
$clicar('cancelar');

// ---- menu -> gasto
$msg('/menu');
$clicar('menu:gasto');
$msg('25 lanche');
confere('menu gasto', str_contains($api->texto(), 'R$ 25,00') && str_contains($api->texto(), 'Lanche'), $api->texto());
$clicar('ok');

// ---- aniversário
$msg('aniversário da Ana Paula 15/03/1995');
confere('confirma aniversário', str_contains($api->texto(), 'Ana Paula') && str_contains($api->texto(), '15/03/1995'), $api->texto());
$clicar('ok');
usuario_forcar('bernardo');
$p = estado_ler(aniv())['pessoas'][0] ?? null;
usuario_forcar('');
confere('aniversário gravado', $p && $p['nome'] === 'Ana Paula' && $p['dia'] === 15 && $p['mes'] === 3 && $p['ano'] === 1995, json_encode($p));

$msg('niver do João');
confere('pede data do aniversário', str_contains($api->texto(), 'Qual a data'));
$msg('8 de setembro');
confere('data por extenso', str_contains($api->texto(), 'João') && str_contains($api->texto(), '08/09'), $api->texto());
$clicar('ok');

// ---- cadastrar conta fixa
$msg('novo fixo academia 90');
confere('novo fixo pede o dia', str_contains($api->texto(), 'Em que dia do mês vence'), $api->texto());
$msg('12');
confere('confirma novo fixo', str_contains($api->texto(), 'Nova conta fixa') && str_contains($api->texto(), 'Academia · todo dia 12') && str_contains($api->texto(), 'R$ 90,00') && str_contains($api->texto(), 'Saúde'), $api->texto());
$clicar('corrigir');
$clicar('campo:valor');
$msg('99,50');
confere('valor do novo fixo corrigido', str_contains($api->texto(), 'R$ 99,50'), $api->texto());
$clicar('ok');
usuario_forcar('bernardo');
$novo = current(array_filter(fin_estado()['fixos'], fn($fx) => $fx['desc'] === 'Academia'));
usuario_forcar('');
confere('novo fixo gravado como na página', $novo && $novo['valor'] == 99.5 && $novo['dia'] === 12 && $novo['tipo'] === 'despesa' && $novo['cat'] === 'Saúde' && $novo['desde'] === $k && $novo['ate'] === null, json_encode($novo, JSON_UNESCAPED_UNICODE));
$msg('/fixos');
confere('novo fixo aparece para pagar', str_contains(json_encode($api->ultima()[1]['reply_markup'] ?? [], JSON_UNESCAPED_UNICODE), 'Academia'), $api->texto());
$clicar('cancelar');
$msg('/desfazer');
usuario_forcar('bernardo');
$restou = array_filter(fin_estado()['fixos'], fn($fx) => $fx['desc'] === 'Academia');
usuario_forcar('');
confere('desfazer remove o novo fixo', !$restou && str_contains($api->texto(), 'Desfeito'), $api->texto());

$msg('/menu');
$clicar('menu:novofixo');
$msg('internet 120 dia 20');
confere('avisa fixo com mesmo nome', str_contains($api->texto(), 'já existe a conta fixa "Internet"'), $api->texto());
$clicar('corrigir');
$clicar('ftipo:receita');
confere('troca para entrada fixa', str_contains($api->texto(), 'Nova entrada fixa'), $api->texto());
$clicar('cancelar');

// ---- resumo
$msg('/resumo');
confere('resumo do mês', str_contains($api->texto(), 'Entradas: R$ 3.150,00') && str_contains($api->texto(), 'Gastos:'), $api->texto());

// ---- página aberta com versão antiga é recusada (conflito)
usuario_forcar('bernardo');
$v = (int)(estado_ler(fin())['_v'] ?? 0);
usuario_forcar('');
confere('versão final consistente', $v > 5, "v=$v");

apagar_recursivo($tmp);
echo "\n$ok ok, $falhas falha(s)\n";
exit($falhas ? 1 : 0);
