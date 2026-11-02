<?php
/**
 * Ações sobre os dados das ferramentas feitas pelo servidor (usadas pelo bot do Telegram).
 * Gravam no mesmo formato que as páginas usam e passam por estado_alterar(), que tranca o arquivo e
 * aumenta a versão; se a página estiver aberta, ela percebe e recarrega em vez de sobrescrever.
 * Sempre agem em nome de usuario_login() (no bot, definido com usuario_forcar()).
 */

const FIN_CATEGORIAS = ['Moradia', 'Contas da casa', 'Alimentação', 'Mercado', 'Transporte', 'Saúde', 'Educação', 'Lazer', 'Assinaturas', 'Compras', 'Igreja / doações', 'Outros', 'Salário', 'Renda extra'];
const FIN_MAPAS = ['meses', 'meses.*.fixos'];

function sem_acentos(string $s): string
{
    $s = mb_strtolower($s);
    return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e', 'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o', 'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c']);
}

function novo_id(): string
{
    return substr(base_convert(bin2hex(random_bytes(6)), 16, 36), 0, 7);
}

function moeda(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

// ==================================================================== Minhas Finanças

function fin(): Ferramenta
{
    return new Ferramenta('pessoal', 'financas');
}

function fin_estado(): array
{
    $e = estado_ler(fin());
    $e += ['contas' => [], 'fixos' => [], 'categorias' => FIN_CATEGORIAS, 'meses' => []];
    return $e;
}

function fin_mes_vazio(): array
{
    return ['fixos' => [], 'lanc' => [], 'saldos' => []];
}

/** Lança um gasto ou entrada avulso. $l: tipo (despesa|receita), valor, desc, data (AAAA-MM-DD), cat, conta. */
function fin_lancar(array $l): array
{
    $l = ['id' => novo_id(), 'data' => $l['data'], 'desc' => $l['desc'], 'valor' => round((float)$l['valor'], 2), 'tipo' => $l['tipo'], 'cat' => $l['cat'] ?? 'Outros', 'conta' => $l['conta'] ?? ''];
    $k = substr($l['data'], 0, 7);
    estado_alterar(fin(), function (array $e) use ($l, $k) {
        $e['meses'][$k] = ($e['meses'][$k] ?? []) + fin_mes_vazio();
        $e['meses'][$k]['lanc'][] = $l;
        return $e;
    }, FIN_MAPAS);
    return ['mes' => $k, 'id' => $l['id']];
}

function fin_remover_lancamento(string $k, string $id): void
{
    estado_alterar(fin(), function (array $e) use ($k, $id) {
        if (isset($e['meses'][$k]['lanc'])) {
            $e['meses'][$k]['lanc'] = array_values(array_filter($e['meses'][$k]['lanc'], fn($l) => $l['id'] !== $id));
        }
        return $e;
    }, FIN_MAPAS);
}

/** Fixos que valem no mês $k, com valor do mês e se já foram pagos. */
function fin_fixos_do_mes(string $k, ?array $e = null): array
{
    $e ??= fin_estado();
    $dm = $e['meses'][$k] ?? [];
    $out = [];
    foreach ($e['fixos'] as $fx) {
        if ((!empty($fx['desde']) && $fx['desde'] > $k) || (!empty($fx['ate']) && $fx['ate'] < $k)) {
            continue;
        }
        $r = $dm['fixos'][$fx['id']] ?? [];
        $out[] = $fx + ['valor_mes' => (float)($r['v'] ?? $fx['valor'] ?? 0), 'pago' => !empty($r['pago'])];
    }
    usort($out, fn($a, $b) => ($a['dia'] ?? 1) <=> ($b['dia'] ?? 1));
    return $out;
}

/** Marca um fixo como pago/recebido no mês (opcionalmente com o valor real). Devolve o registro anterior, para desfazer. */
function fin_pagar_fixo(string $fixoId, string $k, ?float $valor = null): ?array
{
    $anterior = null;
    estado_alterar(fin(), function (array $e) use ($fixoId, $k, $valor, &$anterior) {
        $fx = current(array_filter($e['fixos'] ?? [], fn($x) => $x['id'] === $fixoId)) ?: throw new RuntimeException('Fixo não encontrado.');
        $e['meses'][$k] = ($e['meses'][$k] ?? []) + fin_mes_vazio();
        $anterior = $e['meses'][$k]['fixos'][$fixoId] ?? null;
        $e['meses'][$k]['fixos'][$fixoId] = [
            'v' => $valor ?? (float)($anterior['v'] ?? $fx['valor'] ?? 0),
            'pago' => true,
            'data' => date('Y-m-d'),
        ];
        return $e;
    }, FIN_MAPAS);
    return $anterior;
}

function fin_restaurar_fixo(string $fixoId, string $k, ?array $anterior): void
{
    estado_alterar(fin(), function (array $e) use ($fixoId, $k, $anterior) {
        if ($anterior === null) {
            unset($e['meses'][$k]['fixos'][$fixoId]);
        } else {
            $e['meses'][$k]['fixos'][$fixoId] = $anterior;
        }
        return $e;
    }, FIN_MAPAS);
}

/** Resumo do mês: entradas, gastos, sobra, o que falta pagar. */
function fin_resumo(string $k): array
{
    $e = fin_estado();
    $r = ['rec' => 0.0, 'desp' => 0.0, 'aPagar' => 0.0, 'pendentes' => [], 'porCat' => []];
    foreach (fin_fixos_do_mes($k, $e) as $fx) {
        if ($fx['tipo'] === 'receita') {
            $r['rec'] += $fx['valor_mes'];
        } else {
            $r['desp'] += $fx['valor_mes'];
            $r['porCat'][$fx['cat'] ?: 'Outros'] = ($r['porCat'][$fx['cat'] ?: 'Outros'] ?? 0) + $fx['valor_mes'];
            if (!$fx['pago']) {
                $r['aPagar'] += $fx['valor_mes'];
                $r['pendentes'][] = $fx;
            }
        }
    }
    foreach ($e['meses'][$k]['lanc'] ?? [] as $l) {
        if ($l['tipo'] === 'receita') {
            $r['rec'] += (float)$l['valor'];
        } else {
            $r['desp'] += (float)$l['valor'];
            $r['porCat'][$l['cat'] ?: 'Outros'] = ($r['porCat'][$l['cat'] ?: 'Outros'] ?? 0) + (float)$l['valor'];
        }
    }
    arsort($r['porCat']);
    return $r;
}

/** Último lançamento com a mesma descrição (para repetir categoria e conta). */
function fin_historico_desc(string $desc, ?array $e = null): ?array
{
    $e ??= fin_estado();
    $alvo = sem_acentos(trim($desc));
    $achado = null;
    foreach ($e['meses'] as $dm) {
        foreach ($dm['lanc'] ?? [] as $l) {
            if (sem_acentos(trim($l['desc'])) === $alvo && (!$achado || $l['data'] >= $achado['data'])) {
                $achado = $l;
            }
        }
    }
    return $achado;
}

// ==================================================================== Aniversários

function aniv(): Ferramenta
{
    return new Ferramenta('genericos', 'aniversarios');
}

function aniv_adicionar(string $nome, int $dia, int $mes, ?int $ano, string $grupo = '', string $tel = ''): string
{
    $id = novo_id();
    estado_alterar(aniv(), function (array $e) use ($id, $nome, $dia, $mes, $ano, $grupo, $tel) {
        $e['pessoas'][] = ['id' => $id, 'nome' => $nome, 'dia' => $dia, 'mes' => $mes, 'ano' => $ano, 'grupo' => $grupo, 'tel' => $tel];
        return $e;
    });
    return $id;
}

function aniv_remover(string $id): void
{
    estado_alterar(aniv(), function (array $e) use ($id) {
        $e['pessoas'] = array_values(array_filter($e['pessoas'] ?? [], fn($p) => $p['id'] !== $id));
        return $e;
    });
}

/** Aniversários nos próximos $dias dias: [[dias até, pessoa, data], ...]. */
function aniv_proximos(int $dias = 30): array
{
    $hoje = new DateTimeImmutable('today');
    $out = [];
    foreach (estado_ler(aniv())['pessoas'] ?? [] as $p) {
        $d = DateTimeImmutable::createFromFormat('!Y-n-j', $hoje->format('Y') . "-{$p['mes']}-{$p['dia']}");
        if (!$d) {
            continue;
        }
        if ($d < $hoje) {
            $d = $d->modify('+1 year');
        }
        $falta = (int)$hoje->diff($d)->days;
        if ($falta <= $dias) {
            $out[] = [$falta, $p, $d];
        }
    }
    usort($out, fn($a, $b) => $a[0] <=> $b[0]);
    return $out;
}
