<?php
/**
 * Conversa do bot: recebe as mensagens e cliques do Telegram, entende, pede confirmação e grava.
 *
 * Fluxo de um registro: frase -> bot_interpretar() -> pergunta o que faltar (valor, descrição, categoria...)
 * -> mostra o resumo com [Confirmar] [Corrigir] [Cancelar] -> grava -> oferece [Desfazer].
 */

final class BotCentral
{
    private string $login = '';
    private array $conv = [];
    private string|int $chat = 0;

    public function __construct(private ApiTelegram $api)
    {
    }

    // ================================================================ entrada

    public function processar(array $u): void
    {
        if (isset($u['callback_query'])) {
            $q = $u['callback_query'];
            $this->chat = $q['message']['chat']['id'] ?? 0;
            try {
                $this->api->chamar('answerCallbackQuery', ['callback_query_id' => $q['id']]);
            } catch (Throwable $e) {
            }
            if ($this->entrar()) {
                $this->clique((string)($q['data'] ?? ''), (int)($q['message']['message_id'] ?? 0));
                $this->sair();
            }
            return;
        }
        $m = $u['message'] ?? null;
        if (!$m || !isset($m['chat']['id'])) {
            return;
        }
        $this->chat = $m['chat']['id'];
        $texto = trim((string)($m['text'] ?? ''));
        if ($texto === '') {
            $this->enviar('Por enquanto eu só entendo texto.');
            return;
        }
        // vínculo: "/start 123456" (link do site) ou "/vincular 123456"
        if (preg_match('#^/(?:start|vincular)\s+(\d{6})\b#', $texto, $mm)) {
            $nome = trim(($m['from']['first_name'] ?? '') . ' ' . ($m['from']['last_name'] ?? ''));
            $login = tg_usar_codigo($mm[1], $this->chat, $nome);
            $this->enviar($login
                ? 'Pronto! Este chat agora está ligado ao usuário <b>' . $this->h($login) . "</b>.\n\n" . $this->textoAjuda()
                : 'Código inválido ou vencido. Gere outro na Central: <i>minha conta → Telegram</i>.');
            return;
        }
        if (!$this->entrar()) {
            $this->enviar("Este bot é privado.\nPara usar, abra a Central de Ferramentas, vá em <i>minha conta → Telegram</i> e toque em \"Vincular\".");
            return;
        }
        try {
            if ($texto[0] === '/') {
                $this->comando($texto);
            } else {
                $this->texto($texto);
            }
        } finally {
            $this->sair();
        }
    }

    private function entrar(): bool
    {
        $login = tg_login_do_chat($this->chat);
        if (!$login) {
            return false;
        }
        $this->login = $login;
        usuario_forcar($login);
        $this->conv = tg_conversa($login);
        return true;
    }

    private function sair(): void
    {
        tg_salvar_conversa($this->login, $this->conv);
        usuario_forcar('');
    }

    // ================================================================ comandos

    private function comando(string $texto): void
    {
        $cmd = strtolower(strtok(substr($texto, 1), " @"));
        match ($cmd) {
            'start', 'ajuda', 'help' => $this->enviar($this->textoAjuda()),
            'menu' => $this->menu(),
            'resumo' => $this->resumo(),
            'fixos' => $this->listarFixos(),
            'aniversarios' => $this->proximosAniversarios(),
            'desfazer' => $this->desfazer(),
            'cancelar' => $this->cancelar(),
            'gasto', 'entrada', 'aniversario' => $this->pedirFrase($cmd),
            default => $this->enviar('Comando desconhecido. Mande /ajuda para ver o que eu sei fazer.'),
        };
    }

    private function textoAjuda(): string
    {
        return "<b>O que você pode me mandar</b>\n\n"
            . "<b>Gastos</b>\n<code>gastei 45 mercado</code>\n<code>brinquei num futebol por 12 reais</code>\n<code>paguei 35 na farmácia no nubank ontem</code>\n\n"
            . "<b>Entradas</b>\n<code>recebi 200 de um freela</code>\n\n"
            . "<b>Contas fixas</b>\n<code>paguei a internet</code>\n<code>paguei a luz 187,40</code>\n<code>recebi o salário</code>\n\n"
            . "<b>Aniversários</b>\n<code>aniversário da Ana 15/03</code>\n<code>niver do Carlos 02/07/1990</code>\n\n"
            . "Antes de gravar eu sempre peço confirmação.\n"
            . "/menu opções em botões · /resumo o mês · /fixos contas a pagar · /aniversarios próximos · /desfazer último registro";
    }

    private function menu(): void
    {
        $this->enviar('O que você quer fazer?', [
            [['Gasto', 'menu:gasto'], ['Entrada', 'menu:entrada']],
            [['Pagar conta fixa', 'menu:fixo'], ['Aniversário', 'menu:aniversario']],
            [['Resumo do mês', 'menu:resumo'], ['Próximos aniversários', 'menu:aniversarios']],
            [['Desfazer último', 'menu:desfazer']],
        ]);
    }

    private function pedirFrase(string $tipo): void
    {
        if ($tipo === 'fixo') {
            $this->listarFixos();
            return;
        }
        $this->conv['pendente'] = null;
        $this->conv['aguardando'] = "frase:$tipo";
        $exemplo = ['gasto' => 'Ex.: <code>25 lanche</code>', 'entrada' => 'Ex.: <code>150 venda do bolo</code>', 'aniversario' => 'Ex.: <code>Ana Paula 15/03</code>'][$tipo];
        $this->enviar(['gasto' => 'Qual foi o gasto?', 'entrada' => 'Qual foi a entrada?', 'aniversario' => 'De quem é o aniversário e quando?'][$tipo] . "\n$exemplo");
    }

    private function cancelar(): void
    {
        $this->conv['pendente'] = null;
        $this->conv['aguardando'] = null;
        $this->enviar('Ok, cancelado.');
    }

    // ================================================================ texto livre

    private function texto(string $texto): void
    {
        $ag = (string)($this->conv['aguardando'] ?? '');
        if (str_starts_with($ag, 'frase:')) {
            $this->conv['aguardando'] = null;
            $this->novaFrase($texto, substr($ag, 6));
            return;
        }
        if ($ag !== '' && $this->conv['pendente'] && $this->responderCampo($ag, $texto)) {
            return;
        }
        $this->conv['aguardando'] = null;
        $this->novaFrase($texto, null);
    }

    private function contexto(): array
    {
        $e = fin_estado();
        return [
            'contas' => $e['contas'],
            'categorias' => $e['categorias'] ?: FIN_CATEGORIAS,
            'fixos_mes' => fin_fixos_do_mes(date('Y-m'), $e),
            'historico' => fn($d) => fin_historico_desc($d, $e),
        ];
    }

    private function novaFrase(string $texto, ?string $forcar): void
    {
        $r = bot_interpretar($texto, $this->contexto(), $forcar);
        if ($r['tipo'] === 'desconhecido') {
            $this->conv['pendente'] = ['tipo' => 'desconhecido', 'texto' => $texto];
            $this->enviar("Não entendi. Isso é:", [
                [['Gasto', 'tipo:gasto'], ['Entrada', 'tipo:entrada']],
                [['Conta fixa paga', 'tipo:fixo'], ['Aniversário', 'tipo:aniversario']],
                [['Nada, cancelar', 'cancelar']],
            ]);
            return;
        }
        $this->conv['pendente'] = $r;
        $this->avancar();
    }

    /** Resposta a uma pergunta ("qual o valor?"). Devolve false se o texto parece ser uma frase nova. */
    private function responderCampo(string $campo, string $texto): bool
    {
        $p = &$this->conv['pendente'];
        $n = ' ' . sem_acentos($texto) . ' ';
        switch ($campo) {
            case 'valor':
                $v = bot_extrair_valor($n);
                if ($v === null || trim(preg_replace('/\b(reais|real|r\$)\b/', '', $n)) !== '' && preg_match('/[a-z]{3,}/', trim($n))) {
                    if ($v === null) {
                        $this->enviar('Não achei um valor. Mande só o número, ex.: <code>45,90</code>');
                        return true;
                    }
                    return false; // veio uma frase inteira: trata como novo registro
                }
                $p['valor'] = $v;
                break;
            case 'desc':
                $p['desc'] = mb_strtoupper(mb_substr(trim($texto), 0, 1)) . mb_substr(trim($texto), 1);
                if (empty($p['cat'])) {
                    $ctx = $this->contexto();
                    $p['cat'] = ($ctx['historico'])($p['desc'])['cat'] ?? bot_categoria($p['desc'], $p['tipo'] === 'entrada' ? 'receita' : 'despesa', $ctx['categorias']);
                }
                break;
            case 'data':
                if ($p['tipo'] === 'aniversario') {
                    $r = bot_interpretar('aniversario x ' . $texto, [], 'aniversario');
                    if (!$r['dia']) {
                        $this->enviar('Não entendi a data. Mande assim: <code>15/03</code> ou <code>15/03/1990</code>');
                        return true;
                    }
                    [$p['dia'], $p['mes'], $p['ano']] = [$r['dia'], $r['mes'], $r['ano']];
                } else {
                    $d = bot_extrair_data($n, new DateTimeImmutable('today'));
                    if (!$d) {
                        $this->enviar('Não entendi a data. Mande <code>hoje</code>, <code>ontem</code>, <code>dia 5</code> ou <code>05/11</code>.');
                        return true;
                    }
                    $p['data'] = $d;
                }
                break;
            case 'nome':
                $p['nome'] = trim($texto);
                break;
            default:
                return false;
        }
        $this->conv['aguardando'] = null;
        $p['faltando'] = array_values(array_diff($p['faltando'] ?? [], [$campo]));
        if ($campo === 'desc' && !empty($p['cat'])) {
            $p['faltando'] = array_values(array_diff($p['faltando'], ['cat']));
        }
        $this->avancar();
        return true;
    }

    // ================================================================ perguntar o que falta / confirmar

    private function avancar(?int $editar = null): void
    {
        $p = $this->conv['pendente'];
        $falta = $p['faltando'][0] ?? null;
        $perguntas = [
            'valor' => 'Qual o valor?', 'desc' => 'Qual a descrição? (ex.: <code>mercado</code>)',
            'nome' => 'De quem é o aniversário?', 'data' => 'Qual a data? (<code>15/03</code> ou <code>15/03/1990</code>)',
        ];
        if (isset($perguntas[$falta])) {
            $this->conv['aguardando'] = $falta;
            $this->enviar($perguntas[$falta], [[['Cancelar', 'cancelar']]], $editar);
            return;
        }
        if ($falta === 'cat') {
            $this->perguntarCategoria($editar);
            return;
        }
        if ($falta === 'fixo') {
            $this->listarFixos($editar);
            return;
        }
        $this->conv['aguardando'] = null;
        $this->enviar($this->resumoPendente(), [
            [['Confirmar', 'ok'], ['Corrigir', 'corrigir'], ['Cancelar', 'cancelar']],
        ], $editar);
    }

    private function perguntarCategoria(?int $editar = null): void
    {
        $p = $this->conv['pendente'];
        $cats = fin_estado()['categorias'] ?: FIN_CATEGORIAS;
        $botoes = [];
        foreach (array_chunk(array_keys($cats), 3) as $grupo) {
            $botoes[] = array_map(fn($i) => [$cats[$i], "cat:$i"], $grupo);
        }
        $botoes[] = [['Cancelar', 'cancelar']];
        $this->enviar('Qual a categoria de <b>' . $this->h($p['desc'] ?: 'este registro') . '</b>?', $botoes, $editar);
    }

    private function resumoPendente(): string
    {
        $p = $this->conv['pendente'];
        switch ($p['tipo']) {
            case 'gasto':
            case 'entrada':
                $conta = $this->nomeConta($p['conta'] ?? '');
                return '<b>' . ($p['tipo'] === 'gasto' ? 'Gasto' : 'Entrada') . '</b>  ' . moeda((float)$p['valor']) . "\n"
                    . $this->h($p['desc']) . "\n"
                    . '<i>' . $this->h($p['cat']) . ($conta ? ' · ' . $this->h($conta) : '') . ' · ' . $this->dataFalada($p['data']) . '</i>';
            case 'fixo':
                $fx = $p['fixo'];
                $mes = MESES[(int)substr($p['mes'], 5, 2) - 1];
                return '<b>' . ($fx['tipo'] === 'receita' ? 'Receber' : 'Pagar') . ' conta fixa</b>  ' . moeda((float)$p['valor']) . "\n"
                    . $this->h($fx['desc']) . " · $mes" . ($fx['pago'] ? "\n<i>(já estava marcada como " . ($fx['tipo'] === 'receita' ? 'recebida' : 'paga') . '; vou só atualizar o valor)</i>' : '');
            case 'aniversario':
                return "<b>Aniversário</b>\n" . $this->h($p['nome']) . ' · ' . sprintf('%02d/%02d', $p['dia'], $p['mes']) . ($p['ano'] ? '/' . $p['ano'] : '');
        }
        return '';
    }

    // ================================================================ cliques

    private function clique(string $d, int $msg): void
    {
        [$acao, $arg] = explode(':', $d, 2) + [1 => ''];
        if (str_starts_with($d, 'menu:')) {
            $this->tirarBotoes($msg);
            match ($arg) {
                'gasto', 'entrada', 'aniversario', 'fixo' => $this->pedirFrase($arg),
                'resumo' => $this->resumo(),
                'aniversarios' => $this->proximosAniversarios(),
                'desfazer' => $this->desfazer(),
                default => null,
            };
            return;
        }
        if ($acao === 'desfazer') {
            $this->tirarBotoes($msg);
            $this->desfazer();
            return;
        }
        if ($acao === 'fixo') {
            $this->escolherFixo($arg, $msg);
            return;
        }
        $p = &$this->conv['pendente'];
        if (!$p) {
            $this->editar($msg, '<i>Isso já foi resolvido.</i>');
            return;
        }
        switch ($acao) {
            case 'cancelar':
                $p = null;
                $this->conv['aguardando'] = null;
                $this->editar($msg, '<i>Cancelado.</i>');
                return;
            case 'tipo':
                $texto = $p['texto'] ?? '';
                $this->tirarBotoes($msg);
                if ($arg === 'fixo') {
                    $r = bot_interpretar($texto, $this->contexto(), 'fixo');
                    $this->conv['pendente'] = $r;
                    $this->avancar();
                } elseif ($p['tipo'] === 'gasto' || $p['tipo'] === 'entrada') {
                    $p['tipo'] = $arg; // corrigindo gasto <-> entrada
                    $p['cat'] = bot_categoria($p['desc'], $arg === 'entrada' ? 'receita' : 'despesa', fin_estado()['categorias'] ?: FIN_CATEGORIAS);
                    $p['faltando'] = $p['cat'] ? [] : ['cat'];
                    $this->avancar();
                } else {
                    $this->novaFrase($texto, $arg);
                }
                return;
            case 'cat':
                $cats = fin_estado()['categorias'] ?: FIN_CATEGORIAS;
                $p['cat'] = $cats[(int)$arg] ?? 'Outros';
                $p['faltando'] = array_values(array_diff($p['faltando'] ?? [], ['cat']));
                $this->avancar($msg);
                return;
            case 'conta':
                $p['conta'] = $arg === '-' ? '' : $arg;
                $this->avancar($msg);
                return;
            case 'corrigir':
                $this->menuCorrigir($msg);
                return;
            case 'campo':
                if ($arg === 'cat') {
                    $this->perguntarCategoria($msg);
                } elseif ($arg === 'conta') {
                    $contas = array_values(array_filter(fin_estado()['contas'], fn($c) => ($c['ativa'] ?? true) !== false));
                    $botoes = array_map(fn($g) => array_map(fn($c) => [$c['nome'], 'conta:' . $c['id']], $g), array_chunk($contas, 2));
                    $botoes[] = [['Sem conta', 'conta:-'], ['Voltar', 'voltar']];
                    $this->enviar('Qual conta?', $botoes, $msg);
                } elseif ($arg === 'outrofixo') {
                    $this->conv['pendente'] = ['tipo' => 'fixo', 'fixo' => null, 'valor' => null, 'mes' => date('Y-m'), 'faltando' => ['fixo']];
                    $this->listarFixos($msg);
                } else {
                    $this->conv['aguardando'] = $arg;
                    $perg = ['valor' => 'Qual o valor certo?', 'desc' => 'Qual a descrição certa?', 'data' => 'Qual a data certa?', 'nome' => 'Qual o nome certo?'][$arg] ?? 'Mande o valor certo:';
                    $this->enviar($perg, [[['Voltar', 'voltar']]], $msg);
                }
                return;
            case 'voltar':
                $this->conv['aguardando'] = null;
                $this->avancar($msg);
                return;
            case 'ok':
                $this->confirmar($msg);
                return;
        }
    }

    private function menuCorrigir(int $msg): void
    {
        $p = $this->conv['pendente'];
        $b = match ($p['tipo']) {
            'gasto', 'entrada' => [
                [['Valor', 'campo:valor'], ['Descrição', 'campo:desc'], ['Data', 'campo:data']],
                [['Categoria', 'campo:cat'], ['Conta', 'campo:conta'], [$p['tipo'] === 'gasto' ? 'É entrada' : 'É gasto', 'tipo:' . ($p['tipo'] === 'gasto' ? 'entrada' : 'gasto')]],
            ],
            'fixo' => [[['Valor', 'campo:valor'], ['Outra conta fixa', 'campo:outrofixo']]],
            'aniversario' => [[['Nome', 'campo:nome'], ['Data', 'campo:data']]],
            default => [],
        };
        $b[] = [['Voltar', 'voltar'], ['Cancelar', 'cancelar']];
        $this->enviar($this->resumoPendente() . "\n\n<b>O que corrigir?</b>", $b, $msg);
    }

    // ================================================================ gravar / desfazer

    private function confirmar(int $msg): void
    {
        $p = $this->conv['pendente'];
        try {
            switch ($p['tipo']) {
                case 'gasto':
                case 'entrada':
                    $r = fin_lancar(['tipo' => $p['tipo'] === 'gasto' ? 'despesa' : 'receita', 'valor' => $p['valor'], 'desc' => $p['desc'], 'data' => $p['data'], 'cat' => $p['cat'], 'conta' => $p['conta'] ?? '']);
                    $this->conv['ultima'] = ['acao' => 'lanc', 'mes' => $r['mes'], 'id' => $r['id'], 'texto' => $p['desc'] . ' ' . moeda((float)$p['valor'])];
                    break;
                case 'fixo':
                    $ant = fin_pagar_fixo($p['fixo']['id'], $p['mes'], (float)$p['valor']);
                    $this->conv['ultima'] = ['acao' => 'fixo', 'id' => $p['fixo']['id'], 'mes' => $p['mes'], 'anterior' => $ant, 'texto' => $p['fixo']['desc']];
                    break;
                case 'aniversario':
                    $id = aniv_adicionar($p['nome'], (int)$p['dia'], (int)$p['mes'], $p['ano'] ? (int)$p['ano'] : null);
                    $this->conv['ultima'] = ['acao' => 'aniv', 'id' => $id, 'texto' => 'aniversário de ' . $p['nome']];
                    break;
            }
        } catch (Throwable $e) {
            tg_log("erro ao gravar ({$this->login}): " . $e->getMessage());
            $this->editar($msg, 'Não consegui gravar: ' . $this->h($e->getMessage()));
            return;
        }
        $texto = $this->resumoPendente();
        $this->conv['pendente'] = null;
        $this->conv['aguardando'] = null;
        $extra = '';
        if (in_array($p['tipo'], ['gasto', 'entrada', 'fixo'], true)) {
            $res = fin_resumo(date('Y-m'));
            $extra = "\n\n<i>Mês: entradas " . moeda($res['rec']) . ' · gastos ' . moeda($res['desp']) . '</i>';
        }
        $this->enviar("Registrado.\n\n$texto$extra", [[['Desfazer', 'desfazer']]], $msg);
    }

    private function desfazer(): void
    {
        $u = $this->conv['ultima'];
        if (!$u) {
            $this->enviar('Não tenho nada para desfazer.');
            return;
        }
        match ($u['acao']) {
            'lanc' => fin_remover_lancamento($u['mes'], $u['id']),
            'fixo' => fin_restaurar_fixo($u['id'], $u['mes'], $u['anterior']),
            'aniv' => aniv_remover($u['id']),
        };
        $this->conv['ultima'] = null;
        $this->enviar('Desfeito: ' . $this->h($u['texto']) . '.');
    }

    // ================================================================ consultas

    private function listarFixos(?int $editar = null): void
    {
        $k = date('Y-m');
        $pend = array_values(array_filter(fin_fixos_do_mes($k), fn($fx) => !$fx['pago']));
        if (!$pend) {
            $this->conv['pendente'] = null;
            $this->enviar('Todas as contas fixas de ' . MESES[(int)date('n') - 1] . ' já estão pagas/recebidas.', null, $editar);
            return;
        }
        $botoes = array_map(fn($fx) => [[sprintf('%s · %s · dia %d', $fx['desc'], moeda($fx['valor_mes']), $fx['dia'] ?? 1), 'fixo:' . $fx['id']]], $pend);
        $botoes[] = [['Cancelar', 'cancelar']];
        $this->conv['pendente'] = $this->conv['pendente'] ?? ['tipo' => 'fixo', 'fixo' => null, 'valor' => null, 'mes' => $k, 'faltando' => ['fixo']];
        $this->enviar('Qual conta fixa?', $botoes, $editar);
    }

    private function escolherFixo(string $id, int $msg): void
    {
        $k = date('Y-m');
        $fx = current(array_filter(fin_fixos_do_mes($k), fn($x) => $x['id'] === $id));
        if (!$fx) {
            $this->editar($msg, 'Essa conta fixa não existe mais.');
            return;
        }
        $antes = $this->conv['pendente'];
        $valor = ($antes['tipo'] ?? '') === 'fixo' && !empty($antes['informou_valor']) ? $antes['valor'] : $fx['valor_mes'];
        $this->conv['pendente'] = ['tipo' => 'fixo', 'fixo' => $fx, 'valor' => $valor, 'mes' => $k, 'faltando' => []];
        $this->avancar($msg);
    }

    private function resumo(): void
    {
        $k = date('Y-m');
        $r = fin_resumo($k);
        $t = '<b>' . ucfirst(MESES[(int)date('n') - 1]) . "</b>\n"
            . 'Entradas: ' . moeda($r['rec']) . "\n"
            . 'Gastos: ' . moeda($r['desp']) . "\n"
            . 'Sobra: <b>' . moeda($r['rec'] - $r['desp']) . "</b>\n"
            . 'Falta pagar: ' . moeda($r['aPagar']);
        if ($r['porCat']) {
            $t .= "\n\n<b>Por categoria</b>\n";
            foreach (array_slice($r['porCat'], 0, 6, true) as $c => $v) {
                $t .= $this->h($c) . ': ' . moeda($v) . "\n";
            }
        }
        if ($r['pendentes']) {
            $t .= "\n<b>A pagar</b>\n" . implode("\n", array_map(fn($fx) => sprintf('dia %02d · %s · %s', $fx['dia'] ?? 1, $this->h($fx['desc']), moeda($fx['valor_mes'])), $r['pendentes']));
        }
        $this->enviar($t);
    }

    private function proximosAniversarios(): void
    {
        $l = aniv_proximos(30);
        if (!$l) {
            $this->enviar('Nenhum aniversário nos próximos 30 dias.');
            return;
        }
        $t = "<b>Próximos aniversários</b>\n";
        foreach ($l as [$falta, $p, $d]) {
            $quando = $falta === 0 ? '<b>hoje</b>' : ($falta === 1 ? 'amanhã' : "em $falta dias");
            $t .= $d->format('d/m') . ' · ' . $this->h($p['nome']) . ($p['ano'] ? ' (' . ((int)$d->format('Y') - $p['ano']) . ' anos)' : '') . " · $quando\n";
        }
        $this->enviar($t);
    }

    // ================================================================ utilidades

    private function nomeConta(string $id): string
    {
        foreach (fin_estado()['contas'] as $c) {
            if ($c['id'] === $id) {
                return $c['nome'];
            }
        }
        return '';
    }

    private function dataFalada(string $iso): string
    {
        $d = new DateTimeImmutable($iso);
        $dif = (int)(new DateTimeImmutable('today'))->diff($d)->format('%r%a');
        return match ($dif) { 0 => 'hoje', -1 => 'ontem', -2 => 'anteontem', default => $d->format('d/m') };
    }

    private function h(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @param array|null $botoes linhas de [rótulo, dado] */
    private function teclado(?array $botoes): ?array
    {
        if ($botoes === null) {
            return null;
        }
        return ['inline_keyboard' => array_map(fn($linha) => array_map(fn($b) => ['text' => $b[0], 'callback_data' => $b[1]], $linha), $botoes)];
    }

    /** Envia uma mensagem nova ou, com $editar, substitui a mensagem dos botões clicados. */
    private function enviar(string $texto, ?array $botoes = null, ?int $editar = null): void
    {
        $p = ['chat_id' => $this->chat, 'text' => $texto, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($t = $this->teclado($botoes)) {
            $p['reply_markup'] = $t;
        }
        if ($editar) {
            try {
                $this->api->chamar('editMessageText', $p + ['message_id' => $editar]);
                return;
            } catch (Throwable $e) {
                // mensagem antiga demais para editar: manda uma nova
            }
        }
        $this->api->chamar('sendMessage', $p);
    }

    private function editar(int $msg, string $texto): void
    {
        $this->enviar($texto, null, $msg);
    }

    private function tirarBotoes(int $msg): void
    {
        if ($msg) {
            try {
                $this->api->chamar('editMessageReplyMarkup', ['chat_id' => $this->chat, 'message_id' => $msg, 'reply_markup' => ['inline_keyboard' => []]]);
            } catch (Throwable $e) {
            }
        }
    }
}

/** Comandos que aparecem no menu "/" do Telegram. */
function tg_comandos(): array
{
    return [
        ['command' => 'menu', 'description' => 'Opções em botões'],
        ['command' => 'resumo', 'description' => 'Resumo do mês'],
        ['command' => 'fixos', 'description' => 'Pagar uma conta fixa'],
        ['command' => 'aniversarios', 'description' => 'Próximos aniversários'],
        ['command' => 'desfazer', 'description' => 'Desfazer o último registro'],
        ['command' => 'cancelar', 'description' => 'Cancelar o que está em andamento'],
        ['command' => 'ajuda', 'description' => 'Exemplos de frases'],
    ];
}
