<?php
/**
 * Testes do interpretador de frases do bot. Rode:  php testes/interpretar_teste.php
 */
require __DIR__ . '/../lib/nucleo.php';
require __DIR__ . '/../lib/acoes.php';
require __DIR__ . '/../lib/bot/interpretar.php';

$hoje = new DateTimeImmutable('2026-11-02'); // segunda-feira
$ctx = [
    'contas' => [
        ['id' => 'nu', 'nome' => 'Nubank', 'tipo' => 'corrente'],
        ['id' => 'cx', 'nome' => 'Caixa', 'tipo' => 'poupanca'],
        ['id' => 'cc', 'nome' => 'Cartão Nubank', 'tipo' => 'cartao'],
        ['id' => 'din', 'nome' => 'Carteira', 'tipo' => 'carteira'],
    ],
    'categorias' => FIN_CATEGORIAS,
    'fixos_mes' => [
        ['id' => 'net', 'desc' => 'Internet', 'valor' => 99.9, 'valor_mes' => 99.9, 'tipo' => 'despesa', 'pago' => false, 'dia' => 10],
        ['id' => 'luz', 'desc' => 'Conta de luz', 'valor' => 180, 'valor_mes' => 180.0, 'tipo' => 'despesa', 'pago' => false, 'dia' => 15],
        ['id' => 'sal', 'desc' => 'Salário', 'valor' => 3000, 'valor_mes' => 3000.0, 'tipo' => 'receita', 'pago' => false, 'dia' => 5],
    ],
    'historico' => fn($d) => sem_acentos($d) === 'padaria do ze' ? ['cat' => 'Alimentação', 'conta' => 'din'] : null,
];

$casos = [
    // [frase, tipo esperado, campos esperados]
    ['gastei 45 mercado', 'gasto', ['valor' => 45.0, 'desc' => 'Mercado', 'cat' => 'Mercado']],
    ['brinquei num futebol de sabão por 12 reais', 'gasto', ['valor' => 12.0, 'desc' => 'Futebol de sabão', 'cat' => 'Lazer']],
    ['brinquei no boliche 30', 'gasto', ['valor' => 30.0, 'desc' => 'Boliche', 'cat' => 'Lazer']],
    ['comprei um tênis por R$ 249,90 no cartão', 'gasto', ['valor' => 249.9, 'desc' => 'Tênis', 'cat' => 'Compras', 'conta' => 'cc']],
    ['peguei um uber de 18,50', 'gasto', ['valor' => 18.5, 'desc' => 'Uber', 'cat' => 'Transporte']],
    ['usei 60 de gasolina ontem', 'gasto', ['valor' => 60.0, 'desc' => 'Gasolina', 'cat' => 'Transporte', 'data' => '2026-11-01']],
    ['paguei 35 na farmácia no nubank', 'gasto', ['valor' => 35.0, 'desc' => 'Farmácia', 'cat' => 'Saúde', 'conta' => 'nu']],
    ['45,90 pizza', 'gasto', ['valor' => 45.9, 'desc' => 'Pizza', 'cat' => 'Alimentação']],
    ['gastei 1.250,00 no conserto do carro dia 28', 'gasto', ['valor' => 1250.0, 'desc' => 'Conserto do carro', 'cat' => 'Transporte', 'data' => '2026-10-28']],
    ['padaria do zé 12', 'gasto', ['valor' => 12.0, 'desc' => 'Padaria do zé', 'cat' => 'Alimentação', 'conta' => 'din']],
    ['comprei pão 8 reais em dinheiro', 'gasto', ['valor' => 8.0, 'desc' => 'Pão', 'conta' => 'din']],
    ['gastei 20 no sábado com lanche', 'gasto', ['valor' => 20.0, 'desc' => 'Lanche', 'data' => '2026-10-31']],
    ['gastei 50 em 30/10', 'gasto', ['valor' => 50.0, 'data' => '2026-10-30', 'faltando' => ['desc', 'cat']]],
    ['comprei uma coisa estranha 15', 'gasto', ['valor' => 15.0, 'desc' => 'Coisa estranha', 'faltando' => ['cat']]],
    ['gastei no mercado', 'gasto', ['desc' => 'Mercado', 'faltando' => ['valor']]],
    ['recebi 200 de um freela', 'entrada', ['valor' => 200.0, 'desc' => 'Freela', 'cat' => 'Renda extra']],
    ['ganhei 2 mil de bônus', 'entrada', ['valor' => 2000.0, 'desc' => 'Bônus']],
    ['recebi o salário', 'fixo', ['fixo_id' => 'sal', 'valor' => 3000.0]],
    ['caiu o salário 3150', 'fixo', ['fixo_id' => 'sal', 'valor' => 3150.0]],
    ['paguei a internet', 'fixo', ['fixo_id' => 'net', 'valor' => 99.9]],
    ['paguei a luz 187,40', 'fixo', ['fixo_id' => 'luz', 'valor' => 187.4]],
    ['aniversário do Carlos é dia 02/07/1990', 'aniversario', ['nome' => 'Carlos', 'dia' => 2, 'mes' => 7, 'ano' => 1990]],
    ['niver da ana paula 15/03', 'aniversario', ['nome' => 'Ana Paula', 'dia' => 15, 'mes' => 3, 'ano' => null]],
    ['aniversário da tia Maria 8 de setembro', 'aniversario', ['nome' => 'Tia Maria', 'dia' => 8, 'mes' => 9]],
    ['aniversário do João', 'aniversario', ['nome' => 'João', 'faltando' => ['data']]],
    ['novo fixo internet 99,90 dia 10', 'novofixo', ['fixotipo' => 'despesa', 'desc' => 'Internet', 'valor' => 99.9, 'dia' => 10, 'cat' => 'Contas da casa', 'faltando' => []]],
    ['netflix 55,90 todo dia 15', 'novofixo', ['desc' => 'Netflix', 'valor' => 55.9, 'dia' => 15, 'cat' => 'Assinaturas']],
    ['entrada fixa salário 3000 dia 5', 'novofixo', ['fixotipo' => 'receita', 'desc' => 'Salário', 'valor' => 3000.0, 'dia' => 5, 'cat' => 'Salário']],
    ['cadastra um gasto fixo de aluguel 1.500 vence dia 5 no nubank', 'novofixo', ['desc' => 'Aluguel', 'valor' => 1500.0, 'dia' => 5, 'cat' => 'Moradia', 'conta' => 'nu']],
    ['aluguel 1500 todo mês dia 8', 'novofixo', ['desc' => 'Aluguel', 'valor' => 1500.0, 'dia' => 8]],
    ['nova conta fixa academia 90 por mês', 'novofixo', ['desc' => 'Academia', 'valor' => 90.0, 'dia' => null, 'cat' => 'Saúde', 'faltando' => ['dia']]],
    ['novo fixo', 'novofixo', ['faltando' => ['desc', 'valor', 'dia', 'cat']]],
    ['bom dia', 'desconhecido', []],
];

$ok = $falhas = 0;
foreach ($casos as [$frase, $tipo, $esperado]) {
    $r = bot_interpretar($frase, $ctx, null, $hoje);
    $erros = [];
    if ($r['tipo'] !== $tipo) {
        $erros[] = "tipo={$r['tipo']}";
    }
    foreach ($esperado as $k => $v) {
        $obtido = $k === 'fixo_id' ? ($r['fixo']['id'] ?? null) : ($r[$k] ?? null);
        if ($obtido !== $v) {
            $erros[] = "$k=" . json_encode($obtido, JSON_UNESCAPED_UNICODE) . ' (esperado ' . json_encode($v, JSON_UNESCAPED_UNICODE) . ')';
        }
    }
    if ($erros) {
        $falhas++;
        echo "FALHOU  \"$frase\"\n        " . implode("\n        ", $erros) . "\n";
    } else {
        $ok++;
    }
}
echo "\n$ok ok, $falhas falha(s)\n";
exit($falhas ? 1 : 0);
