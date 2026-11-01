<?php
/**
 * Backup geral: um .zip com os dados de todas as ferramentas (modelos, documentos, saídas e dados).
 * Ficam de fora o histórico de versões (backups/) e a lixeira, que só ocupariam espaço.
 *
 * Automático: a cada N dias (config 'backup_geral' > 'dias') um zip é gravado em backups_gerais/,
 * mantendo os últimos 'manter'. A verificação roda no carregamento das páginas e é barata.
 */
final class Backup
{
    /** Pastas da raiz do projeto que entram no backup. */
    public const RAIZES = ['ferramentas', 'usuarios'];

    public static function dir(): string
    {
        $d = RAIZ . '/backups_gerais';
        if (!is_dir($d)) {
            mkdir($d, 0777, true);
            file_put_contents("$d/.htaccess", "Require all denied\n");
        }
        return $d;
    }

    /** @return array<string,string> caminho relativo => conteúdo */
    private static function arquivos(): array
    {
        $out = [];
        foreach (self::RAIZES as $raiz) {
            $base = RAIZ . "/$raiz";
            if (!is_dir($base)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) {
                    continue;
                }
                $rel = $raiz . '/' . substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $base)) + 1);
                if (preg_match('#/(backups|lixeira)/#', $rel) || str_contains($rel, '.tmp')) {
                    continue;
                }
                $out[$rel] = (string)file_get_contents($f->getPathname());
            }
        }
        return $out;
    }

    public static function gerar(): string
    {
        $arq = self::arquivos();
        $arq['LEIA-ME.txt'] = "Backup da " . config('titulo') . " gerado em " . date('d/m/Y H:i') . ".\r\n"
            . "Para restaurar: entre como administrador, abra Backup e envie este arquivo.\r\n";
        return Zip::gerar($arq);
    }

    /** Grava um backup na pasta backups_gerais e apaga os mais antigos. */
    public static function gravar(string $motivo = 'auto'): string
    {
        $nome = 'backup_' . date('Y-m-d_H-i') . "_$motivo.zip";
        $destino = self::dir() . "/$nome";
        file_put_contents("$destino.parcial", self::gerar());
        rename("$destino.parcial", $destino);
        self::podar();
        return $nome;
    }

    private static function podar(): void
    {
        $manter = (int)(config('backup_geral')['manter'] ?? 12);
        $lista = self::lista();
        foreach (array_slice($lista, $manter) as $b) {
            @unlink(self::dir() . '/' . $b['nome']);
        }
    }

    /** @return array<int,array{nome:string,tamanho:int,mtime:int}> mais novos primeiro */
    public static function lista(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/backup_*.zip') ?: [] as $p) {
            $out[] = ['nome' => basename($p), 'tamanho' => filesize($p), 'mtime' => filemtime($p)];
        }
        usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /** Faz o backup automático se o último tiver mais de N dias. */
    public static function automatico(): void
    {
        $dias = (int)(config('backup_geral')['dias'] ?? 30);
        if ($dias <= 0) {
            return;
        }
        $marca = self::dir() . '/ultimo.txt';
        $ultimo = is_file($marca) ? (int)file_get_contents($marca) : 0;
        if ($ultimo > time() - $dias * 86400) {
            return;
        }
        file_put_contents($marca, (string)time()); // marca antes, para dois acessos simultâneos não gerarem dois
        try {
            self::gravar('auto');
        } catch (Throwable $e) {
            @unlink($marca);
        }
    }

    /**
     * Restaura um zip de backup por cima dos dados atuais (antes grava um backup do estado atual).
     * Só aceita caminhos dentro das RAIZES, sem "..".
     */
    public static function restaurar(string $zipConteudo): int
    {
        $arqs = Zip::lerString($zipConteudo);
        $validos = [];
        foreach ($arqs as $rel => $dados) {
            $rel = str_replace('\\', '/', $rel);
            $partes = explode('/', $rel);
            if (str_ends_with($rel, '/') || !in_array($partes[0], self::RAIZES, true) || in_array('..', $partes, true) || str_contains($rel, ':') || str_contains($rel, "\0")) {
                continue;
            }
            $validos[$rel] = $dados;
        }
        if (!$validos) {
            throw new RuntimeException('Este arquivo não parece um backup da Central de Ferramentas.');
        }
        self::gravar('antes-de-restaurar');
        foreach ($validos as $rel => $dados) {
            $p = RAIZ . '/' . $rel;
            if (!is_dir(dirname($p))) {
                mkdir(dirname($p), 0777, true);
            }
            file_put_contents($p, $dados);
        }
        return count($validos);
    }
}
