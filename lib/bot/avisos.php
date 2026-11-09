<?php
/**
 * Avisos automáticos pelo Telegram: aniversários e contas que estão chegando.
 *
 * Cada aniversário (Aniversários), gasto fixo (Minhas Finanças) e conta (Contas da casa) pode ter o campo
 * "avisar": quantos dias antes avisar (0 = no próprio dia, -1 = não avisar). Sem o campo, avisa 1 dia antes.
 * O programa do bot confere de tempos em tempos, a partir da hora config('avisos_hora'), e manda cada aviso
 * uma vez só por ocorrência. Se o bot ficou desligado no dia certo, avisa assim que voltar (até a data).
 * Contas já marcadas como pagas não são avisadas.
 *
 * usuarios/<login>/telegram_avisos.json   {"<tipo>:<id>:<AAAA-MM-DD>": "quando avisou"}
 */

const AVISO_PADRAO = 1;

/** Dias de antecedência de um item, ou null para não avisar. */
function aviso_dias(array $item): ?int
{
    $v = $item['avisar'] ?? null;
    if ($v === null || $v === '') {
        return AVISO_PADRAO;
    }
    return (int)$v < 0 ? null : (int)$v;
}

/** Datas de vencimento no dia $dia deste mês e do próximo (o dia 31 vira o último dia de meses curtos). */
function aviso_vencimentos_mensais(int $dia, DateTimeImmutable $hoje): array
{
    $out = [];
    foreach ([$hoje->modify('first day of this month'), $hoje->modify('first day of next month')] as $m) {
        $out[] = $m->setDate((int)$m->format('Y'), (int)$m->format('n'), min(max($dia, 1), (int)$m->format('t')));
    }
    return $out;
}

/** Estado de uma ferramenta do usuário atual, ou null se a ferramenta não existir. */
function aviso_estado(string $cat, string $id): ?array
{
    try {
        return estado_ler(new Ferramenta($cat, $id));
    } catch (RuntimeException $e) {
        return null;
    }
}

/**
 * O que está dentro do prazo de aviso para o usuário atual (usuario_login()), mais próximo primeiro.
 * @return array<int,array{chave:string,falta:int,data:DateTimeImmutable,tipo:string,texto:string,fixo?:string}>
 */
function avisos_pendentes(?DateTimeImmutable $hoje = null): array
{
    $hoje ??= new DateTimeImmutable('today');
    $out = [];
    $dentro = function (?int $avisar, DateTimeImmutable $d) use ($hoje): ?int {
        $falta = (int)$hoje->diff($d)->format('%r%a');
        return $avisar !== null && $falta >= 0 && $falta <= $avisar ? $falta : null;
    };

    // ---- aniversários
    foreach (aviso_estado('genericos', 'aniversarios')['pessoas'] ?? [] as $p) {
        $d = DateTimeImmutable::createFromFormat('!Y-n-j', $hoje->format('Y') . "-{$p['mes']}-{$p['dia']}");
        if (!$d || empty($p['id'])) {
            continue;
        }
        if ($d < $hoje) {
            $d = $d->modify('+1 year');
        }
        if (($falta = $dentro(aviso_dias($p), $d)) === null) {
            continue;
        }
        $idade = !empty($p['ano']) ? ' · faz ' . ((int)$d->format('Y') - (int)$p['ano']) . ' anos' : '';
        $out[] = ['chave' => "aniv:{$p['id']}:" . $d->format('Y-m-d'), 'falta' => $falta, 'data' => $d, 'tipo' => 'aniv',
            'texto' => '🎂 Aniversário de <b>' . aviso_h($p['nome']) . '</b> ' . aviso_quando($falta, $d) . $idade];
    }

    // ---- Minhas Finanças: gastos fixos
    $e = aviso_estado('pessoal', 'financas') ?? [];
    foreach ($e['fixos'] ?? [] as $fx) {
        if (($fx['tipo'] ?? 'despesa') !== 'despesa' || empty($fx['id'])) {
            continue;
        }
        foreach (aviso_vencimentos_mensais((int)($fx['dia'] ?? 1), $hoje) as $d) {
            $k = $d->format('Y-m');
            if ((!empty($fx['desde']) && $fx['desde'] > $k) || (!empty($fx['ate']) && $fx['ate'] < $k)) {
                continue;
            }
            $r = $e['meses'][$k]['fixos'][$fx['id']] ?? [];
            if (!empty($r['pago']) || ($falta = $dentro(aviso_dias($fx), $d)) === null) {
                continue;
            }
            $out[] = ['chave' => "fin:{$fx['id']}:" . $d->format('Y-m-d'), 'falta' => $falta, 'data' => $d, 'tipo' => 'fin',
                'texto' => '💸 <b>' . aviso_h($fx['desc'] ?? '?') . '</b> vence ' . aviso_quando($falta, $d) . ' · ' . moeda((float)($r['v'] ?? $fx['valor'] ?? 0)),
                'fixo' => $k === date('Y-m') ? $fx['id'] : null];
        }
    }

    // ---- Contas da casa
    $e = aviso_estado('casa', 'contas') ?? [];
    foreach ($e['contas'] ?? [] as $c) {
        if (($c['ativa'] ?? true) === false || empty($c['id'])) {
            continue;
        }
        foreach (aviso_vencimentos_mensais((int)($c['dia'] ?? 1), $hoje) as $d) {
            $r = $e['meses'][$d->format('Y-m')][$c['id']] ?? [];
            if (!empty($r['pago']) || ($falta = $dentro(aviso_dias($c), $d)) === null) {
                continue;
            }
            $out[] = ['chave' => "casa:{$c['id']}:" . $d->format('Y-m-d'), 'falta' => $falta, 'data' => $d, 'tipo' => 'casa',
                'texto' => '🏠 <b>' . aviso_h($c['nome'] ?? '?') . '</b> vence ' . aviso_quando($falta, $d) . ' · ' . moeda((float)($r['v'] ?? $c['valor'] ?? 0))];
        }
    }

    usort($out, fn($a, $b) => [$a['falta'], $a['texto']] <=> [$b['falta'], $b['texto']]);
    return $out;
}

function aviso_quando(int $falta, DateTimeImmutable $d): string
{
    return match ($falta) {
        0 => '<b>hoje</b>',
        1 => 'amanhã (' . $d->format('d/m') . ')',
        default => "em $falta dias (" . DIAS_SEMANA[(int)$d->format('w')] . ', ' . $d->format('d/m') . ')',
    };
}

function aviso_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Manda os avisos ainda não enviados a todos os usuários com Telegram vinculado.
 * Devolve quantos avisos foram enviados.
 */
function avisos_enviar(ApiTelegram $api, ?DateTimeImmutable $hoje = null): int
{
    $hoje ??= new DateTimeImmutable('today');
    $porLogin = [];
    foreach (tg_config()['vinculos'] as $chat => $v) {
        $login = is_array($v) ? ($v['login'] ?? '') : (string)$v;
        if ($login !== '' && isset(usuarios()[$login])) {
            $porLogin[$login][] = $chat;
        }
    }
    $total = 0;
    foreach ($porLogin as $login => $chats) {
        usuario_forcar($login);
        try {
            $arq = DIR_USUARIOS . "/$login/telegram_avisos.json";
            $feitos = ler_json($arq);
            $novos = array_values(array_filter(avisos_pendentes($hoje), fn($a) => !isset($feitos[$a['chave']])));
            if (!$novos) {
                continue;
            }
            $texto = '<b>🔔 Lembretes</b>' . "\n\n" . implode("\n", array_map(fn($a) => $a['texto'], $novos));
            $botoes = [];
            foreach ($novos as $a) {
                if (!empty($a['fixo'])) {
                    $botoes[] = [['✅ Paguei: ' . strip_tags(explode(' vence ', $a['texto'])[0]), 'fixo:' . $a['fixo']]];
                }
            }
            $p = ['text' => $texto, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
            if ($botoes) {
                $p['reply_markup'] = ['inline_keyboard' => array_map(fn($l) => array_map(fn($b) => ['text' => $b[0], 'callback_data' => $b[1]], $l), $botoes)];
            }
            $enviou = false;
            foreach ($chats as $chat) {
                try {
                    $api->chamar('sendMessage', $p + ['chat_id' => $chat]);
                    $enviou = true;
                } catch (Throwable $e) {
                    tg_log("aviso para $login (chat $chat): " . $e->getMessage());
                }
            }
            if (!$enviou) {
                continue; // tenta de novo na próxima volta
            }
            foreach ($novos as $a) {
                $feitos[$a['chave']] = date('c');
            }
            // esquece avisos de datas que já passaram há mais de 40 dias
            $limite = $hoje->modify('-40 days')->format('Y-m-d');
            $feitos = array_filter($feitos, fn($k) => substr($k, -10) >= $limite, ARRAY_FILTER_USE_KEY);
            gravar_json($arq, $feitos);
            $total += count($novos);
            tg_log("avisos para $login: " . count($novos));
        } finally {
            usuario_forcar('');
        }
    }
    return $total;
}
