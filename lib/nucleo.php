<?php
/**
 * Núcleo: configuração, utilidades, registro de categorias/ferramentas e armazenamento.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil');

define('RAIZ', dirname(__DIR__));
define('DIR_FERRAMENTAS', RAIZ . '/ferramentas');

require_once __DIR__ . '/zip.php';
require_once __DIR__ . '/docx.php';
require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/docxwriter.php';
require_once __DIR__ . '/usuarios.php';

function config(?string $chave = null): mixed
{
    static $c = null;
    $c ??= require RAIZ . '/config.php';
    return $chave === null ? $c : ($c[$chave] ?? null);
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $msg = null, string $tipo = 'ok'): array
{
    if ($msg !== null) {
        $_SESSION['flash'][] = [$tipo, $msg];
        return [];
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function json_saida(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function slug(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s));
    return trim($s, '-') ?: 'item';
}

/** Nome de arquivo seguro (mantém acentos e espaços). */
function nome_seguro(string $s): string
{
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
    $s = preg_replace('/[\x00-\x1f\\\\\/:*?"<>|]+/u', ' ', $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s), " .");
    return mb_substr($s, 0, 120) ?: 'arquivo';
}

function tamanho_legivel(int $b): string
{
    return $b < 1024 ? "$b B" : ($b < 1048576 ? round($b / 1024) . ' KB' : round($b / 1048576, 1) . ' MB');
}

function data_legivel(int $ts): string
{
    $hoje = date('Y-m-d');
    $d = date('Y-m-d', $ts);
    if ($d === $hoje) {
        return 'hoje ' . date('H:i', $ts);
    }
    if ($d === date('Y-m-d', strtotime('-1 day'))) {
        return 'ontem ' . date('H:i', $ts);
    }
    return date('d/m/Y H:i', $ts);
}

const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

function data_extenso(?int $ts = null): string
{
    $ts ??= time();
    return (int)date('j', $ts) . ' de ' . MESES[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/** Troca marcadores como {hoje_extenso}, {mes}, {ano} em valores padrão. */
function expandir_marcadores(string $s): string
{
    $prox = strtotime('first day of next month');
    return strtr($s, [
        '{hoje}' => date('d/m/Y'),
        '{hoje_extenso}' => data_extenso(),
        '{dia}' => date('j'),
        '{mes}' => MESES[(int)date('n') - 1],
        '{Mes}' => mb_convert_case(MESES[(int)date('n') - 1], MB_CASE_TITLE),
        '{proximo_mes}' => MESES[(int)date('n', $prox) - 1],
        '{Proximo_mes}' => mb_convert_case(MESES[(int)date('n', $prox) - 1], MB_CASE_TITLE),
        '{ano}' => date('Y'),
    ]);
}

function ler_json(string $arq): array
{
    if (!is_file($arq)) {
        return [];
    }
    $j = json_decode((string)file_get_contents($arq), true);
    return is_array($j) ? $j : [];
}

function gravar_json(string $arq, array $dados): void
{
    if (!is_dir(dirname($arq))) {
        mkdir(dirname($arq), 0777, true);
    }
    file_put_contents($arq, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Estado em JSON de uma ferramenta (listas, cadastros...). Atende o salvamento automático
 * (POST JSON em ?a=salvar_estado) e devolve o estado atual. Guarda uma cópia de segurança
 * a cada 6 horas no histórico de versões.
 */
function estado_ferramenta(Ferramenta $f, string $a, string $rel = 'dados/estado.json'): array
{
    if ($a === 'salvar_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) {
            json_saida(['erro' => 'Dados inválidos.'], 400);
        }
        $p = $f->caminho($rel);
        if (is_file($p)) {
            $vs = $f->versoes($rel);
            if (!$vs || $vs[0]['mtime'] < time() - 6 * 3600) {
                $f->backup($rel);
            }
        }
        gravar_json($p, $in);
        json_saida(['ok' => true, 'hora' => date('H:i:s')]);
    }
    return ler_json($f->caminho($rel));
}

function soffice(): ?string
{
    $c = config('soffice');
    $lista = $c ? [$c] : ['C:/Program Files/LibreOffice/program/soffice.exe', 'C:/Program Files (x86)/LibreOffice/program/soffice.exe', '/usr/bin/soffice', '/usr/bin/libreoffice'];
    foreach ($lista as $p) {
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

// ======================================================================== registro

function categorias(): array
{
    $out = [];
    foreach (glob(DIR_FERRAMENTAS . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $id = basename($dir);
        if (!preg_match('/^[a-z0-9_-]+$/', $id)) {
            continue;
        }
        $cfg = ler_json("$dir/_categoria.json");
        $out[$id] = [
            'id' => $id,
            'dir' => $dir,
            'nome' => $cfg['nome'] ?? ucfirst($id),
            'icone' => $cfg['icone'] ?? '',
            'descricao' => $cfg['descricao'] ?? '',
            'ordem' => (int)($cfg['ordem'] ?? 50),
            'qtd' => count(glob("$dir/*/ferramenta.json") ?: []),
        ];
    }
    uasort($out, fn($a, $b) => [$a['ordem'], $a['nome']] <=> [$b['ordem'], $b['nome']]);
    return $out;
}

/** @return Ferramenta[] */
function ferramentas(string $cat): array
{
    $out = [];
    foreach (glob(DIR_FERRAMENTAS . "/$cat/*/ferramenta.json") ?: [] as $j) {
        $f = new Ferramenta($cat, basename(dirname($j)));
        $out[$f->id] = $f;
    }
    uasort($out, fn($a, $b) => [$a->ordem, $a->nome] <=> [$b->ordem, $b->nome]);
    return $out;
}

final class Ferramenta
{
    public const PASTAS = ['modelos', 'documentos', 'saidas', 'dados', 'backups', 'lixeira'];
    public string $dir;
    public string $nome;
    public string $descricao;
    public string $icone;
    public string $tipo;
    public int $ordem;
    public array $cfg;

    public function __construct(public string $cat, public string $id)
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $cat) || !preg_match('/^[a-z0-9_-]+$/', $id)) {
            throw new RuntimeException('Ferramenta inválida.');
        }
        $this->dir = DIR_FERRAMENTAS . "/$cat/$id";
        if (!is_file("$this->dir/ferramenta.json")) {
            throw new RuntimeException('Ferramenta não encontrada.');
        }
        $this->cfg = ler_json("$this->dir/ferramenta.json");
        $this->nome = $this->cfg['nome'] ?? $id;
        $this->descricao = $this->cfg['descricao'] ?? '';
        $this->icone = $this->cfg['icone'] ?? '';
        $this->tipo = $this->cfg['tipo'] ?? 'documentos';
        $this->ordem = (int)($this->cfg['ordem'] ?? 50);
    }

    public function url(array $q = []): string
    {
        return 'index.php?' . http_build_query(['c' => $this->cat, 't' => $this->id] + $q);
    }

    /**
     * Caminho absoluto seguro para um caminho relativo dentro da ferramenta.
     * "modelos/..." fica na pasta da ferramenta (compartilhado); o resto fica na pasta do usuário.
     */
    public function caminho(string $rel): string
    {
        $rel = trim(str_replace('\\', '/', $rel), '/');
        $partes = explode('/', $rel);
        if ($rel === '' || !in_array($partes[0], self::PASTAS, true) || in_array('..', $partes, true) || in_array('.', $partes, true) || str_contains($rel, ':') || str_contains($rel, "\0")) {
            throw new RuntimeException('Caminho inválido.');
        }
        return ($partes[0] === 'modelos' ? $this->dir : $this->dirUsuario()) . "/$rel";
    }

    /** Pasta desta ferramenta dentro dos dados do usuário atual. */
    public function dirUsuario(): string
    {
        return dir_dados_usuario() . "/$this->cat/$this->id";
    }

    /** Arquivos em "modelos/" são compartilhados entre todos os usuários. */
    public static function ehModelo(string $rel): bool
    {
        return str_starts_with(trim(str_replace('\\', '/', $rel), '/'), 'modelos/');
    }

    public function pasta(string $sub): string
    {
        $p = $this->caminho($sub);
        if (!is_dir($p)) {
            mkdir($p, 0777, true);
        }
        return $p;
    }

    /** Lista arquivos (e subpastas) de uma pasta da ferramenta, mais recentes primeiro. */
    public function listar(string $sub, ?array $exts = null): array
    {
        $dir = $this->caminho($sub);
        $out = [];
        foreach (is_dir($dir) ? scandir($dir) : [] as $n) {
            if ($n[0] === '.' || $n === 'index.json') {
                continue;
            }
            $p = "$dir/$n";
            $ehDir = is_dir($p);
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!$ehDir && $exts !== null && !in_array($ext, $exts, true)) {
                continue;
            }
            $out[] = ['nome' => $n, 'rel' => "$sub/$n", 'dir' => $ehDir, 'ext' => $ext, 'tamanho' => $ehDir ? 0 : filesize($p), 'mtime' => filemtime($p)];
        }
        usort($out, fn($a, $b) => [$b['dir'], $b['mtime']] <=> [$a['dir'], $a['mtime']]);
        return $out;
    }

    /** Nome livre dentro de uma pasta (acrescenta " (2)", " (3)"...). */
    public function nomeLivre(string $sub, string $nome): string
    {
        $nome = nome_seguro($nome);
        $base = pathinfo($nome, PATHINFO_FILENAME);
        $ext = pathinfo($nome, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ".$ext" : '';
        $cand = $nome;
        for ($i = 2; file_exists($this->caminho("$sub/$cand")); $i++) {
            $cand = "$base ($i)$ext";
        }
        return "$sub/$cand";
    }

    // ------------------------------------------------------------ backups

    /** Pasta do histórico de versões de um arquivo (a dos modelos é compartilhada). */
    public function pastaVersoes(string $rel): string
    {
        $base = self::ehModelo($rel) ? "$this->dir/backups" : $this->pasta('backups');
        return $base . '/' . rawurlencode($rel);
    }

    /** Guarda a versão atual do arquivo antes de sobrescrever. */
    public function backup(string $rel): ?string
    {
        $p = $this->caminho($rel);
        if (!is_file($p)) {
            return null;
        }
        $dir = $this->pastaVersoes($rel);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $ext = pathinfo($p, PATHINFO_EXTENSION);
        $destino = $dir . '/' . date('Y-m-d_H-i-s') . ($ext ? ".$ext" : '');
        for ($i = 2; file_exists($destino); $i++) {
            $destino = $dir . '/' . date('Y-m-d_H-i-s') . "_$i" . ($ext ? ".$ext" : '');
        }
        copy($p, $destino);
        $this->podarVersoes($dir);
        return $destino;
    }

    /** Grava conteúdo fazendo backup da versão anterior. */
    public function salvarComBackup(string $rel, string $conteudo): void
    {
        $p = $this->caminho($rel);
        if (!is_dir(dirname($p))) {
            mkdir(dirname($p), 0777, true);
        }
        $this->backup($rel);
        $tmp = "$p.tmp" . bin2hex(random_bytes(3));
        file_put_contents($tmp, $conteudo);
        if (!@rename($tmp, $p)) {
            @unlink($tmp);
            throw new RuntimeException('Não foi possível salvar. O arquivo está aberto no Word/Excel? Feche e tente de novo.');
        }
    }

    public function versoes(string $rel): array
    {
        $dir = $this->pastaVersoes($rel);
        $out = [];
        foreach (is_dir($dir) ? scandir($dir, SCANDIR_SORT_DESCENDING) : [] as $n) {
            if ($n[0] !== '.') {
                $out[] = ['nome' => $n, 'tamanho' => filesize("$dir/$n"), 'mtime' => filemtime("$dir/$n")];
            }
        }
        return $out;
    }

    public function caminhoVersao(string $rel, string $versao): string
    {
        if (!preg_match('/^[\w.-]+$/', $versao)) {
            throw new RuntimeException('Versão inválida.');
        }
        $p = $this->pastaVersoes($rel) . '/' . $versao;
        if (!is_file($p)) {
            throw new RuntimeException('Versão não encontrada.');
        }
        return $p;
    }

    public function restaurarVersao(string $rel, string $versao): void
    {
        $this->salvarComBackup($rel, (string)file_get_contents($this->caminhoVersao($rel, $versao)));
    }

    private function podarVersoes(string $dir): void
    {
        $cfg = config('backup');
        $arqs = array_values(array_filter(scandir($dir, SCANDIR_SORT_DESCENDING), fn($n) => $n[0] !== '.'));
        $limite = time() - $cfg['dias'] * 86400;
        foreach ($arqs as $i => $n) {
            if ($i >= $cfg['max_versoes'] || ($i >= $cfg['min_versoes'] && filemtime("$dir/$n") < $limite)) {
                @unlink("$dir/$n");
            }
        }
    }

    // ------------------------------------------------------------ lixeira

    public function paraLixeira(string $rel): void
    {
        $p = $this->caminho($rel);
        if (!file_exists($p)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }
        if (str_starts_with($rel, 'lixeira/') || str_starts_with($rel, 'backups/')) {
            throw new RuntimeException('Não é possível mover isso para a lixeira.');
        }
        $destino = $this->pasta('lixeira') . '/' . date('Y-m-d_H-i-s') . '~' . rawurlencode($rel);
        if (!@rename($p, $destino)) {
            throw new RuntimeException('Não foi possível excluir (arquivo aberto em outro programa?).');
        }
    }

    public function itensLixeira(): array
    {
        $out = [];
        foreach ($this->listar('lixeira') as $i) {
            [$quando, $orig] = explode('~', $i['nome'], 2) + [1 => ''];
            $out[] = $i + ['original' => rawurldecode($orig), 'quando' => $quando];
        }
        return $out;
    }

    public function restaurarLixeira(string $nome): string
    {
        $p = $this->caminho('lixeira/' . basename($nome));
        [, $orig] = explode('~', basename($nome), 2) + [1 => ''];
        $orig = rawurldecode($orig);
        $this->caminho($orig); // valida
        $sub = dirname($orig);
        $destino = file_exists($this->caminho($orig)) ? $this->nomeLivre($sub, basename($orig)) : $orig;
        if (!is_dir(dirname($this->caminho($destino)))) {
            mkdir(dirname($this->caminho($destino)), 0777, true);
        }
        rename($p, $this->caminho($destino));
        return $destino;
    }

    /** Limpeza periódica (lixeira antiga). */
    public function limpar(): void
    {
        $limite = time() - config('lixeira_dias') * 86400;
        foreach ($this->listar('lixeira') as $i) {
            if ($i['mtime'] < $limite) {
                apagar_recursivo($this->caminho($i['rel']));
            }
        }
    }
}

function apagar_recursivo(string $p): void
{
    if (is_dir($p) && !is_link($p)) {
        foreach (scandir($p) as $n) {
            if ($n !== '.' && $n !== '..') {
                apagar_recursivo("$p/$n");
            }
        }
        @rmdir($p);
    } elseif (file_exists($p)) {
        @unlink($p);
    }
}

function enviar_arquivo(string $p, ?string $nome = null, bool $inline = false): never
{
    if (!is_file($p)) {
        http_response_code(404);
        exit('Arquivo não encontrado.');
    }
    $nome ??= basename($p);
    $tipos = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        'zip' => 'application/zip', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
    ];
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($p));
    $ascii = preg_replace('/[^\x20-\x7e]/', '_', $nome);
    header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s', $inline ? 'inline' : 'attachment', str_replace('"', '', $ascii), rawurlencode($nome)));
    readfile($p);
    exit;
}
