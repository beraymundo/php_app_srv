<?php
/**
 * Leitor/gravador de ZIP em PHP puro (usa apenas zlib).
 * O XAMPP vem com a extensão zip desligada, então não dependemos de ZipArchive.
 * Suficiente para .docx / .xlsx e para empacotar pastas de saída.
 */
final class Zip
{
    /** @return array<string,string> nome => conteúdo (na ordem do arquivo) */
    public static function ler(string $caminho): array
    {
        $dados = @file_get_contents($caminho);
        if ($dados === false) {
            throw new RuntimeException("Não foi possível ler: $caminho");
        }
        return self::lerString($dados);
    }

    /** @return array<string,string> */
    public static function lerString(string $dados): array
    {
        $pos = strrpos($dados, "PK\x05\x06");
        if ($pos === false) {
            throw new RuntimeException('Arquivo não é um ZIP/Office válido.');
        }
        $eocd = unpack('vdisco/vdiscoCd/vqtdDisco/vqtd/Vtam/Voffset', substr($dados, $pos + 4, 16));
        $off = $eocd['offset'];
        $saida = [];
        for ($i = 0; $i < $eocd['qtd']; $i++) {
            if (substr($dados, $off, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Diretório central do ZIP corrompido.');
            }
            $c = unpack('vfeito/vprecisa/vflag/vmetodo/vhora/vdata/Vcrc/Vcomp/Vorig/vnlen/velen/vclen/vdisco/viattr/Veattr/Vlocal', substr($dados, $off + 4, 42));
            $nome = substr($dados, $off + 46, $c['nlen']);
            $off += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
            if (substr($nome, -1) === '/') {
                continue;
            }
            $l = unpack('vprecisa/vflag/vmetodo/vhora/vdata/Vcrc/Vcomp/Vorig/vnlen/velen', substr($dados, $c['local'] + 4, 26));
            $ini = $c['local'] + 30 + $l['nlen'] + $l['elen'];
            $bruto = substr($dados, $ini, $c['comp']);
            if ($c['metodo'] === 0) {
                $conteudo = $bruto;
            } elseif ($c['metodo'] === 8) {
                $conteudo = @gzinflate($bruto);
                if ($conteudo === false) {
                    throw new RuntimeException("Falha ao descompactar $nome");
                }
            } else {
                throw new RuntimeException("Compressão não suportada em $nome");
            }
            $saida[$nome] = $conteudo;
        }
        return $saida;
    }

    /** @param array<string,string> $arquivos nome => conteúdo */
    public static function gravar(string $caminho, array $arquivos): void
    {
        $tmp = $caminho . '.tmp' . bin2hex(random_bytes(3));
        file_put_contents($tmp, self::gerar($arquivos));
        if (!@rename($tmp, $caminho)) {
            @unlink($tmp);
            throw new RuntimeException("Não foi possível gravar $caminho (arquivo aberto em outro programa?)");
        }
    }

    /** @param array<string,string> $arquivos */
    public static function gerar(array $arquivos): string
    {
        $t = getdate();
        $hora = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $data = (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $corpo = '';
        $central = '';
        $qtd = 0;
        foreach ($arquivos as $nome => $conteudo) {
            $nome = str_replace('\\', '/', (string)$nome);
            $crc = crc32($conteudo);
            $comp = gzdeflate($conteudo, 6);
            $metodo = 8;
            if (strlen($comp) >= strlen($conteudo)) {
                $comp = $conteudo;
                $metodo = 0;
            }
            $offset = strlen($corpo);
            $corpo .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $metodo, $hora, $data, $crc, strlen($comp), strlen($conteudo), strlen($nome), 0) . $nome . $comp;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $metodo, $hora, $data, $crc, strlen($comp), strlen($conteudo), strlen($nome), 0, 0, 0, 0, 0, $offset) . $nome;
            $qtd++;
        }
        return $corpo . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $qtd, $qtd, strlen($central), strlen($corpo), 0);
    }

    /** Compacta uma pasta inteira (recursivo) em uma string ZIP. */
    public static function pasta(string $dir): string
    {
        $arquivos = [];
        $base = rtrim(str_replace('\\', '/', $dir), '/') . '/';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($base));
                $arquivos[$rel] = file_get_contents($f->getPathname());
            }
        }
        return self::gerar($arquivos);
    }
}
