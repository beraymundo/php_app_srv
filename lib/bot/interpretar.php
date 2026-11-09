<?php
/**
 * Interpreta frases em português para o bot, por regras (sem IA, sem internet).
 *
 *   "gastei 45 mercado"                         -> gasto 45,00 "Mercado"
 *   "brinquei num futebol de sabão por 12 reais" -> gasto 12,00 "Futebol de sabão" (Lazer)
 *   "recebi 3000 salário"                        -> entrada (ou o fixo "Salário", se existir)
 *   "paguei a internet 99,90"                    -> marca o fixo "Internet" como pago
 *   "novo fixo internet 99,90 dia 10"            -> cadastra o gasto fixo "Internet" (todo dia 10)
 *   "aniversário do Carlos 02/07/1990"           -> aniversário
 *
 * Devolve ['tipo' => gasto|entrada|fixo|novofixo|aniversario|desconhecido, ...campos, 'faltando' => [...]].
 */

const BOT_VERBOS_GASTO = ['gastei', 'comprei', 'paguei', 'peguei', 'usei', 'brinquei', 'gasto', 'compra', 'comprado', 'paguei', 'pago', 'custou', 'torrei', 'investi'];
const BOT_VERBOS_ENTRADA = ['recebi', 'ganhei', 'entrou', 'entraram', 'caiu', 'cairam', 'depositaram', 'recebido', 'entrada', 'vendi'];
const BOT_PALAVRAS_DINHEIRO = ['r$', 'rs', 'reais', 'real', 'conto', 'contos', 'pila', 'pilas', 'mangos', 'paus'];
const BOT_PALAVRAS_PAGAMENTO = ['pix', 'debito', 'credito', 'cartao', 'dinheiro', 'especie', 'boleto', 'transferencia', 'ted'];
const BOT_VAZIAS = ['no', 'na', 'nos', 'nas', 'num', 'numa', 'nuns', 'numas', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'com', 'por', 'pra', 'pro', 'para', 'o', 'a', 'os', 'as', 'e', 'ai', 'so', 'la', 'foi', 'hoje', 'ontem', 'anteontem', 'agora', 'mais', 'que', 'eu', 'me', 'meu', 'minha', 'conta', 'valor', 'total', 'de'];
const BOT_MESES = ['janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    'jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12];
const BOT_DIAS_SEMANA = ['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6];

/** Palavras que indicam a categoria (na forma sem acentos). A primeira que bater vence. */
const BOT_CATEGORIAS = [
    'Mercado' => '/\b(mercado|supermercado|feira|atacad\w*|hortifruti|sacolao|acougue|compras do mes)\b/',
    'Alimentação' => '/\b(lanche|lanches|pizza|ifood|restaurante|almoco|janta|jantar|cafe|padaria|pao|sorvete|acai|hamburguer|burguer|marmita|pastel|salgado|comida|delivery|doce|bolo|churrasco|refrigerante|refri)\b/',
    'Transporte' => '/\b(uber|99|onibus|passagem|gasolina|combustivel|alcool|etanol|posto|estacionamento|pedagio|moto|carro|oficina|mecanico|pneu|taxi|metro|bicicleta)\b/',
    'Saúde' => '/\b(farmacia|remedio|remedios|medico|consulta|exame|exames|dentista|academia|hospital|plano de saude|vacina|oculos)\b/',
    'Lazer' => '/\b(futebol|futsal|cinema|bar|show|festa|jogo|jogos|passeio|viagem|clube|ingresso|role|balada|praia|sinuca|boliche|parque|steam|game)\b/',
    'Assinaturas' => '/\b(netflix|spotify|prime|disney|youtube|assinatura|hbo|max|globoplay|icloud|google one|deezer|chatgpt|claude|gemini)\b/',
    'Contas da casa' => '/\b(luz|energia|agua|internet|gas|telefone|celular|recarga|condominio)\b/',
    'Moradia' => '/\b(aluguel|reforma|movel|moveis|eletrodomestico)\b/',
    'Compras' => '/\b(roupa|roupas|tenis|sapato|camisa|calca|loja|shopee|mercado livre|amazon|shein|presente|aliexpress|eletronico|fone|carregador)\b/',
    'Educação' => '/\b(livro|livros|curso|faculdade|escola|xerox|copia|apostila|material|mensalidade|ifes)\b/',
    'Igreja / doações' => '/\b(dizimo|oferta|doacao|igreja|missa|paroquia|rifa)\b/',
];

/** Frase que cadastra um fixo novo (texto sem acentos): "novo fixo ...", "gasto fixo ...", "... todo dia 10", "... por mês". */
const BOT_RE_NOVO_FIXO = '/\b((nov[oa]|cadastr\w*|cri[ae]r?|adicion\w*)( (um|uma|o|a))?( (gasto|despesa|conta|entrada|receita))? fix[oa]|(gasto|despesa|entrada|receita) fix[oa]|todo mes|todos os meses|mensal(mente)?|por mes|ao mes|todo dia \d{1,2})\b/';
/** Palavras tiradas da descrição de um fixo novo. */
const BOT_PALAVRAS_NOVO_FIXO = ['novo', 'nova', 'fixo', 'fixa', 'gasto', 'despesa', 'entrada', 'receita', 'cadastra', 'cadastrar', 'cadastre', 'cadastro',
    'cria', 'criar', 'crie', 'adiciona', 'adicionar', 'adicione', 'todo', 'todos', 'mes', 'meses', 'mensal', 'mensalmente', 'cada', 'vence', 'vencimento',
    'pago', 'pagar', 'pagamos', 'recebo', 'receber', 'ganho'];

/** "1.234,56" / "12,5" / "12.50" / "1200" -> float */
function bot_numero(string $s): ?float
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s)) {         // 1.234 ou 1.234,56
        return (float)str_replace(['.', ','], ['', '.'], $s);
    }
    if (preg_match('/^\d+,\d{1,2}$/', $s)) {                          // 12,5
        return (float)str_replace(',', '.', $s);
    }
    if (preg_match('/^\d+\.\d{1,2}$/', $s)) {                         // 12.50
        return (float)$s;
    }
    if (preg_match('/^\d+$/', $s)) {
        return (float)$s;
    }
    return null;
}

/** Data a partir de "hoje", "ontem", "dia 5", "05/11", "segunda"... (AAAA-MM-DD). Remove o trecho do texto. */
function bot_extrair_data(string &$n, DateTimeImmutable $hoje): ?string
{
    if (preg_match('/\b(\d{1,2})[\/\.-](\d{1,2})(?:[\/\.-](\d{2,4}))?\b/', $n, $m)) {
        $ano = isset($m[3]) ? (int)$m[3] : (int)$hoje->format('Y');
        if ($ano < 100) {
            $ano += 2000;
        }
        if (checkdate((int)$m[2], (int)$m[1], $ano)) {
            $n = str_replace($m[0], ' ', $n);
            $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $m[2], $m[1]));
            if (!isset($m[3]) && $d > $hoje->modify('+7 days')) {
                $d = $d->modify('-1 year'); // "25/12" falado em janeiro é o do ano passado
            }
            return $d->format('Y-m-d');
        }
    }
    if (preg_match('/\banteontem\b/', $n)) {
        $n = preg_replace('/\banteontem\b/', ' ', $n);
        return $hoje->modify('-2 days')->format('Y-m-d');
    }
    if (preg_match('/\bontem\b/', $n)) {
        $n = preg_replace('/\bontem\b/', ' ', $n);
        return $hoje->modify('-1 day')->format('Y-m-d');
    }
    if (preg_match('/\bhoje\b/', $n)) {
        $n = preg_replace('/\bhoje\b/', ' ', $n);
        return $hoje->format('Y-m-d');
    }
    if (preg_match('/\bdia (\d{1,2})\b/', $n, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 31) {
        $n = str_replace($m[0], ' ', $n);
        $d = $hoje->setDate((int)$hoje->format('Y'), (int)$hoje->format('n'), min((int)$m[1], (int)$hoje->format('t')));
        if ($d > $hoje) {
            $d = $d->modify('first day of last month');
            $d = $d->setDate((int)$d->format('Y'), (int)$d->format('n'), min((int)$m[1], (int)$d->format('t')));
        }
        return $d->format('Y-m-d');
    }
    foreach (BOT_DIAS_SEMANA as $nome => $num) {
        if (preg_match("/\\b(n[ao] |na )?$nome(-feira)?( passad[ao])?\\b/", $n, $m)) {
            $n = str_replace($m[0], ' ', $n);
            $volta = ((int)$hoje->format('w') - $num + 7) % 7;
            return $hoje->modify("-$volta days")->format('Y-m-d');
        }
    }
    return null;
}

/** Valor em dinheiro do texto (já sem acentos). Remove o trecho do texto. */
function bot_extrair_valor(string &$n): ?float
{
    $num = '\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?';
    $padroes = [
        "/(?:r\\$|rs)\\s*($num)(\\s*mil)?/",
        "/($num)(\\s*mil)?\\s*(?:reais|real|conto|contos|pila|pilas|mangos|paus|r\\$)\\b/",
        "/(?<![\\d\\/])($num)(\\s*mil)?(?![\\d\\/])/",
    ];
    foreach ($padroes as $p) {
        if (preg_match($p, $n, $m)) {
            $v = bot_numero($m[1]);
            if ($v === null) {
                continue;
            }
            if (!empty($m[2])) {
                $v *= 1000;
            }
            $n = str_replace($m[0], ' ', $n);
            return $v;
        }
    }
    return null;
}

/** Junta as palavras do texto original que sobraram, tirando as vazias das pontas. */
function bot_descricao(array $palavrasOriginais, array $remover): string
{
    $sobra = [];
    foreach ($palavrasOriginais as $i => $p) {
        if (!in_array($i, $remover, true)) {
            $sobra[] = $p;
        }
    }
    $vazia = fn($p) => in_array(sem_acentos(trim($p, ".,;:!?")), BOT_VAZIAS, true);
    while ($sobra && $vazia($sobra[0])) {
        array_shift($sobra);
    }
    while ($sobra && $vazia(end($sobra))) {
        array_pop($sobra);
    }
    $d = trim(implode(' ', $sobra), " .,;:!?-");
    return $d === '' ? '' : mb_strtoupper(mb_substr($d, 0, 1)) . mb_substr($d, 1);
}

function bot_categoria(string $desc, string $tipo, array $categorias, ?string $verbo = null): ?string
{
    $n = sem_acentos($desc);
    if ($tipo === 'receita') {
        $c = preg_match('/\b(salario|pagamento|holerite|contracheque)\b/', $n) ? 'Salário' : 'Renda extra';
        return in_array($c, $categorias, true) ? $c : null;
    }
    foreach (BOT_CATEGORIAS as $cat => $re) {
        if (in_array($cat, $categorias, true) && preg_match($re, $n)) {
            return $cat;
        }
    }
    if ($verbo === 'brinquei' && in_array('Lazer', $categorias, true)) {
        return 'Lazer';
    }
    return null;
}

/** Fixo cujo nome aparece no texto (prefere os ainda não pagos no mês). */
function bot_achar_fixo(string $n, array $fixosDoMes, string $tipo): ?array
{
    $melhor = null;
    foreach ($fixosDoMes as $fx) {
        if (($fx['tipo'] === 'receita') !== ($tipo === 'receita')) {
            continue;
        }
        $nome = sem_acentos($fx['desc']);
        $bate = preg_match('/\b' . preg_quote($nome, '/') . '\b/', $n);
        if (!$bate) {
            // "paguei a luz" com fixo "Conta de luz": basta uma palavra significativa do nome
            foreach (preg_split('/\s+/', $nome) as $w) {
                if (mb_strlen($w) >= 3 && !in_array($w, BOT_VAZIAS, true) && preg_match('/\b' . preg_quote($w, '/') . '\b/', $n)) {
                    $bate = true;
                    break;
                }
            }
        }
        if ($bate && (!$melhor || ($melhor['pago'] && !$fx['pago']))) {
            $melhor = $fx;
        }
    }
    return $melhor;
}

/**
 * Cadastro de um fixo: descrição, valor, dia do vencimento, categoria e conta.
 * Devolve ['tipo' => 'novofixo', 'fixotipo' => despesa|receita, desc, valor, dia, cat, conta, faltando].
 */
function bot_interpretar_novo_fixo(string $original, string $n, array $ctx): array
{
    $fixotipo = preg_match('/\b(entrada|receita|recebo|receber|ganho|salario)\b/', $n) ? 'receita' : 'despesa';
    $dia = null;
    if (preg_match('/\b(?:todo |no |vence (?:no |em |dia )?)?dia (\d{1,2})\b|\bvence (?:no |em )?(\d{1,2})\b/', $n, $m)) {
        $d = (int)($m[1] !== '' ? $m[1] : $m[2]);
        if ($d >= 1 && $d <= 31) {
            $dia = $d;
            $n = str_replace($m[0], ' ', $n);
        }
    }
    $valor = bot_extrair_valor($n);
    $conta = bot_achar_conta($n, $ctx['contas'] ?? []);

    $palavrasO = preg_split('/\s+/u', $original);
    $restante = preg_split('/\s+/', trim(preg_replace('/\s+/', ' ', $n)));
    $contaN = $conta ? preg_split('/\s+/', sem_acentos($conta['nome'])) : [];
    $remover = [];
    foreach ($palavrasO as $i => $p) {
        $w = sem_acentos(trim($p, '.,;:!?()'));
        if (in_array($w, BOT_PALAVRAS_NOVO_FIXO, true) || in_array($w, BOT_PALAVRAS_DINHEIRO, true) || in_array($w, BOT_PALAVRAS_PAGAMENTO, true)
            || in_array($w, $contaN, true) || (preg_match('/^(r\$)?\d/', $w) && !in_array($w, $restante, true)) || preg_match('/^(r\$|rs)$/', $w) || $w === 'mil') {
            $remover[] = $i;
        }
        if ($w === 'dia' && isset($palavrasO[$i + 1]) && preg_match('/^\d{1,2}$/', trim($palavrasO[$i + 1], '.,;:!?'))) {
            $remover[] = $i;
            $remover[] = $i + 1;
        }
    }
    $desc = bot_descricao($palavrasO, $remover);
    $cat = $desc !== '' ? bot_categoria($desc, $fixotipo, $ctx['categorias'] ?? FIN_CATEGORIAS) : null;

    $r = ['tipo' => 'novofixo', 'fixotipo' => $fixotipo, 'desc' => $desc, 'valor' => $valor, 'dia' => $dia, 'cat' => $cat, 'conta' => $conta['id'] ?? '', 'faltando' => []];
    if ($desc === '') {
        $r['faltando'][] = 'desc';
    }
    if ($valor === null || $valor <= 0) {
        $r['faltando'][] = 'valor';
    }
    if (!$dia) {
        $r['faltando'][] = 'dia';
    }
    if (!$cat) {
        $r['faltando'][] = 'cat';
    }
    return $r;
}

function bot_achar_conta(string $n, array $contas): ?array
{
    foreach ($contas as $c) {
        if (($c['ativa'] ?? true) === false) {
            continue;
        }
        if (preg_match('/\b' . preg_quote(sem_acentos($c['nome']), '/') . '\b/', $n)) {
            return $c;
        }
    }
    $porTipo = fn($t) => current(array_filter($contas, fn($c) => ($c['tipo'] ?? '') === $t && ($c['ativa'] ?? true) !== false)) ?: null;
    if (preg_match('/\b(credito|cartao)\b/', $n) && ($c = $porTipo('cartao'))) {
        return $c;
    }
    if (preg_match('/\b(dinheiro|especie)\b/', $n) && ($c = $porTipo('carteira'))) {
        return $c;
    }
    return null;
}

/**
 * @param array $ctx ['contas', 'categorias', 'fixos_mes' => fin_fixos_do_mes(), 'historico' => callable(desc): ?array]
 * @param string|null $forcar 'gasto'|'entrada'|'fixo'|'novofixo'|'aniversario' quando o tipo já foi escolhido no menu
 */
function bot_interpretar(string $texto, array $ctx, ?string $forcar = null, ?DateTimeImmutable $hoje = null): array
{
    $hoje ??= new DateTimeImmutable('today');
    $original = trim(preg_replace('/\s+/u', ' ', $texto));
    $n = ' ' . sem_acentos($original) . ' ';

    // ---------------------------------------------------------------- aniversário
    if ($forcar === 'aniversario' || (!$forcar && preg_match('/\b(aniversario|aniver|niver|nasceu|nascimento|faz anos)\b/', $n))) {
        $r = ['tipo' => 'aniversario', 'nome' => '', 'dia' => null, 'mes' => null, 'ano' => null, 'faltando' => []];
        $resto = $original;
        if (preg_match('/\b(\d{1,2})[\/\.-](\d{1,2})(?:[\/\.-](\d{2,4}))?\b/u', $resto, $m)) {
            [$r['dia'], $r['mes']] = [(int)$m[1], (int)$m[2]];
            if (!empty($m[3])) {
                $r['ano'] = (int)$m[3] < 100 ? (int)$m[3] + ((int)$m[3] > 30 ? 1900 : 2000) : (int)$m[3];
            }
            $resto = str_replace($m[0], ' ', $resto);
        } elseif (preg_match('/\b(\d{1,2}) de (' . implode('|', array_keys(BOT_MESES)) . ')\w*(?: de (\d{4}))?/u', sem_acentos($resto), $m)) {
            [$r['dia'], $r['mes']] = [(int)$m[1], BOT_MESES[$m[2]]];
            $r['ano'] = !empty($m[3]) ? (int)$m[3] : null;
            // remove o mesmo trecho do texto original (mesmo tamanho, só sem acentos)
            $pos = mb_strpos(sem_acentos($resto), $m[0]);
            $resto = mb_substr($resto, 0, $pos) . ' ' . mb_substr($resto, $pos + mb_strlen($m[0]));
        }
        if ($r['dia'] && ($r['mes'] < 1 || $r['mes'] > 12 || !checkdate($r['mes'], $r['dia'], 2024))) {
            $r['dia'] = $r['mes'] = null;
        }
        $palavras = preg_split('/\s+/u', trim($resto));
        $tirar = ['aniversario', 'aniver', 'niver', 'nasceu', 'nascimento', 'faz', 'anos', 'eh', 'e', 'é', 'dia', 'em', 'no', 'na', 'do', 'da', 'de', 'o', 'a', 'meu', 'minha', 'sera', 'vai', 'ser', 'que'];
        $nome = array_filter($palavras, fn($p) => $p !== '' && !in_array(sem_acentos(trim($p, '.,;:!?')), $tirar, true));
        $r['nome'] = trim(implode(' ', $nome), ' .,;:!?');
        $r['nome'] = implode(' ', array_map(fn($w) => in_array(mb_strtolower($w), ['de', 'da', 'do', 'dos', 'das', 'e'], true) ? mb_strtolower($w) : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1), preg_split('/\s+/u', $r['nome'])));
        if ($r['nome'] === '') {
            $r['faltando'][] = 'nome';
        }
        if (!$r['dia']) {
            $r['faltando'][] = 'data';
        }
        return $r;
    }

    // ---------------------------------------------------------------- novo fixo: "novo fixo internet 99,90 dia 10"
    if ($forcar === 'novofixo' || (!$forcar && preg_match(BOT_RE_NOVO_FIXO, $n) && !preg_match('/\b(paguei|recebi|caiu|entrou)\b/', $n))) {
        return bot_interpretar_novo_fixo($original, $n, $ctx);
    }

    // ---------------------------------------------------------------- dinheiro
    $data = bot_extrair_data($n, $hoje) ?? $hoje->format('Y-m-d');
    $valor = bot_extrair_valor($n);

    $palavrasN = preg_split('/\s+/', trim($n));
    $verbo = null;
    foreach ($palavrasN as $w) {
        if (in_array($w, BOT_VERBOS_GASTO, true) || in_array($w, BOT_VERBOS_ENTRADA, true)) {
            $verbo = $w;
            break;
        }
    }
    $tipo = match (true) {
        $forcar === 'entrada' => 'receita',
        $forcar === 'gasto' || $forcar === 'fixo' => 'despesa',
        $verbo !== null && in_array($verbo, BOT_VERBOS_ENTRADA, true) => 'receita',
        default => 'despesa',
    };
    $conta = bot_achar_conta($n, $ctx['contas'] ?? []);

    // fixo: "paguei a internet", "recebi o salário"
    $podeFixo = $forcar === 'fixo' || (!$forcar && in_array($verbo, ['paguei', 'pago', 'recebi', 'caiu', 'entrou'], true)) || ($forcar === 'entrada');
    if ($podeFixo && ($fx = bot_achar_fixo($n, $ctx['fixos_mes'] ?? [], $tipo))) {
        return ['tipo' => 'fixo', 'fixo' => $fx, 'valor' => $valor ?? $fx['valor_mes'], 'mes' => substr($data, 0, 7), 'faltando' => [], 'informou_valor' => $valor !== null];
    }
    if ($forcar === 'fixo') {
        return ['tipo' => 'fixo', 'fixo' => null, 'valor' => $valor, 'mes' => substr($data, 0, 7), 'faltando' => ['fixo']];
    }

    // descrição: o que sobra do texto original
    $palavrasO = preg_split('/\s+/u', $original);
    $remover = [];
    $restante = preg_split('/\s+/', trim(preg_replace('/\s+/', ' ', $n)));
    $contaN = $conta ? preg_split('/\s+/', sem_acentos($conta['nome'])) : [];
    foreach ($palavrasO as $i => $p) {
        $w = sem_acentos(trim($p, '.,;:!?()'));
        $ehNumero = preg_match('/^(r\$)?\d/', $w);
        if ($w === $verbo || in_array($w, BOT_PALAVRAS_DINHEIRO, true) || in_array($w, BOT_PALAVRAS_PAGAMENTO, true)
            || in_array($w, $contaN, true) || ($ehNumero && !in_array($w, $restante, true)) || preg_match('/^(r\$|rs)$/', $w)
            || in_array($w, ['hoje', 'ontem', 'anteontem', 'mil'], true) || array_key_exists($w, BOT_DIAS_SEMANA) || $w === 'feira' && $i > 0) {
            $remover[] = $i;
        }
        // "dia 5": tira "dia" e o número
        if ($w === 'dia' && isset($palavrasO[$i + 1]) && preg_match('/^\d{1,2}$/', $palavrasO[$i + 1])) {
            $remover[] = $i;
            $remover[] = $i + 1;
        }
    }
    $desc = bot_descricao($palavrasO, $remover);

    if (!$verbo && $valor === null && !$forcar) {
        return ['tipo' => 'desconhecido', 'texto' => $original];
    }

    $hist = isset($ctx['historico']) && $desc !== '' ? ($ctx['historico'])($desc) : null;
    $cat = $hist['cat'] ?? bot_categoria($desc, $tipo, $ctx['categorias'] ?? FIN_CATEGORIAS, $verbo);
    if (!$conta && !empty($hist['conta'])) {
        $conta = current(array_filter($ctx['contas'] ?? [], fn($c) => $c['id'] === $hist['conta'])) ?: null;
    }
    $r = [
        'tipo' => $tipo === 'receita' ? 'entrada' : 'gasto',
        'valor' => $valor, 'desc' => $desc, 'data' => $data, 'cat' => $cat, 'conta' => $conta['id'] ?? '',
        'faltando' => [],
    ];
    if ($valor === null || $valor <= 0) {
        $r['faltando'][] = 'valor';
    }
    if ($desc === '') {
        $r['faltando'][] = 'desc';
    }
    if (!$cat) {
        $r['faltando'][] = 'cat';
    }
    return $r;
}
