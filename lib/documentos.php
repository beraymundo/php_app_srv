<?php
/**
 * Gerenciador genérico de documentos (.docx / .xlsx) de uma ferramenta:
 * modelos, documentos, editor visual, versões (backups) e criação a partir de modelo com campos.
 *
 * Configuração opcional em ferramenta.json:
 *   "nome_padrao": "Escala {Proximo_mes} {ano}",        nome sugerido ao criar documento novo
 *   "campos": [ { "rotulo": "Nome", "buscar": "Fulano", "antes": "", "depois": "", "padrao": "", "dica": "" } ]
 *       ao criar a partir de um modelo, troca  antes+buscar+depois  por  antes+valor+depois
 *   "importar_planilha": true                             mostra o botão de importar planilha no editor
 */
final class Documentos
{
    public const ACOES = ['editar', 'salvar', 'versoes', 'restaurar_versao', 'baixar_versao', 'midia', 'planilha', 'novo', 'upload', 'renomear', 'duplicar', 'pdf'];
    private const EXTS = ['docx', 'xlsx'];

    public static function rotear(Ferramenta $f, string $a): void
    {
        self::verificarPermissao($a);
        match ($a) {
            'editar' => self::editar($f),
            'salvar' => self::salvar($f),
            'versoes' => self::versoes($f),
            'restaurar_versao' => self::restaurarVersao($f),
            'baixar_versao' => enviar_arquivo($f->caminhoVersao(self::rel($f), (string)($_GET['v'] ?? '')), basename(self::rel($f), '.' . pathinfo(self::rel($f), PATHINFO_EXTENSION)) . ' (' . pathinfo((string)$_GET['v'], PATHINFO_FILENAME) . ').' . pathinfo(self::rel($f), PATHINFO_EXTENSION)),
            'midia' => self::midia($f),
            'planilha' => self::planilha($f),
            'novo' => self::novo($f),
            'upload' => self::upload($f),
            'renomear' => self::renomear($f),
            'duplicar' => self::duplicar($f),
            'pdf' => self::pdf($f),
            default => self::lista($f),
        };
    }

    /** Mexer nos modelos (compartilhados) é só para o administrador; usar é para todos. */
    private static function verificarPermissao(string $a): void
    {
        if (eh_admin()) {
            return;
        }
        $alvo = (string)($_POST['f'] ?? $_GET['f'] ?? '');
        if ($a === 'salvar') {
            $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $alvo = (string)($in['arquivo'] ?? '');
            if (!empty($in['como']) && empty($in['comoModelo'])) {
                return; // "salvar como" um documento a partir do modelo: permitido
            }
        }
        $escreve = in_array($a, ['salvar', 'renomear', 'duplicar', 'restaurar_versao'], true);
        if (($escreve && Ferramenta::ehModelo($alvo)) || ($a === 'upload' && ($_POST['sub'] ?? '') === 'modelos')) {
            exigir_admin('Alterar os modelos');
        }
    }

    private static function rel(Ferramenta $f, ?string $v = null): string
    {
        $rel = $v ?? (string)($_POST['f'] ?? $_GET['f'] ?? '');
        $p = $f->caminho($rel);
        if (!is_file($p)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $rel);
        }
        return $rel;
    }

    private static function exigirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Ação inválida.');
        }
    }

    // ================================================================== lista

    public static function lista(Ferramenta $f): void
    {
        $docs = $f->listar('documentos', self::EXTS);
        $modelos = $f->listar('modelos', self::EXTS);
        $campos = $f->cfg['campos'] ?? [];
        cabecalho_ferramenta($f);
        ?>
<div class="titulo-pagina">
  <div><h1><?= h($f->nome) ?></h1><p class="mudo"><?= h($f->descricao) ?></p></div>
</div>
<?php barra_voltar($f); ?>

<section class="cartao">
  <div class="cartao-topo">
    <h2>Documentos</h2>
    <div class="grupo">
      <button class="btn" data-abrir="#up-doc">Enviar arquivo</button>
      <?php link_lixeira($f); ?>
    </div>
  </div>
  <form id="up-doc" class="painel-oculto" method="post" enctype="multipart/form-data" action="<?= h($f->url(['a' => 'upload'])) ?>">
    <input type="hidden" name="sub" value="documentos">
    <input type="file" name="arquivos[]" accept=".docx,.xlsx" multiple required>
    <button class="btn btn-primario">Enviar</button>
  </form>
  <?php if (!$docs): ?>
    <p class="mudo">Nenhum documento ainda. <?= $modelos ? 'Crie um a partir de um modelo abaixo.' : 'Envie um arquivo .docx ou .xlsx.' ?></p>
  <?php else: ?>
  <table class="lista">
    <?php foreach ($docs as $d): $nv = count($f->versoes($d['rel'])); ?>
    <tr>
      <td><?= icone_arquivo($d['ext']) ?> <a href="<?= h($f->url(['a' => 'editar', 'f' => $d['rel']])) ?>"><?= h($d['nome']) ?></a></td>
      <td class="mudo nao-celular"><?= data_legivel($d['mtime']) ?></td>
      <td class="acoes">
        <a class="btn btn-p btn-primario" href="<?= h($f->url(['a' => 'editar', 'f' => $d['rel']])) ?>">Editar</a>
        <a class="btn btn-p" href="<?= h($f->url(['a' => 'baixar', 'f' => $d['rel']])) ?>" title="Baixar">Baixar</a>
        <?php if (soffice()): ?><a class="btn btn-p" href="<?= h($f->url(['a' => 'pdf', 'f' => $d['rel']])) ?>" title="Gerar PDF">PDF</a><?php endif; ?>
        <details class="menu">
          <summary class="btn btn-p" title="Mais opções">...</summary>
          <div class="menu-itens">
            <a href="<?= h($f->url(['a' => 'versoes', 'f' => $d['rel']])) ?>">Versões anteriores (<?= $nv ?>)</a>
            <form method="post" action="<?= h($f->url(['a' => 'duplicar'])) ?>"><input type="hidden" name="f" value="<?= h($d['rel']) ?>"><button>Duplicar</button></form>
            <form method="post" action="<?= h($f->url(['a' => 'renomear'])) ?>" data-renomear="<?= h(pathinfo($d['nome'], PATHINFO_FILENAME)) ?>"><input type="hidden" name="f" value="<?= h($d['rel']) ?>"><input type="hidden" name="novo"><button>Renomear</button></form>
            <form method="post" action="<?= h($f->url(['a' => 'excluir'])) ?>" data-confirmar="Mover &quot;<?= h($d['nome']) ?>&quot; para a lixeira?"><input type="hidden" name="f" value="<?= h($d['rel']) ?>"><button class="perigo">Excluir</button></form>
          </div>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>

<section class="cartao">
  <div class="cartao-topo">
    <h2>Modelos</h2>
    <?php if (eh_admin()): ?><button class="btn" data-abrir="#up-mod">Enviar modelo</button><?php endif; ?>
  </div>
  <p class="mudo pequeno">O modelo é a base, compartilhado por todos os usuários. "Novo documento" faz uma cópia sua para editar sem estragar o original.</p>
  <form id="up-mod" class="painel-oculto" method="post" enctype="multipart/form-data" action="<?= h($f->url(['a' => 'upload'])) ?>">
    <input type="hidden" name="sub" value="modelos">
    <input type="file" name="arquivos[]" accept=".docx,.xlsx" multiple required>
    <button class="btn btn-primario">Enviar</button>
  </form>
  <?php if (!$modelos): ?><p class="mudo">Nenhum modelo.</p><?php endif; ?>
  <?php foreach ($modelos as $i => $m):
      $camposM = array_values(array_filter($campos, fn($c) => empty($c['modelo']) || $c['modelo'] === $m['nome']));
      $padrao = expandir_marcadores($f->cfg['nome_padrao'] ?? pathinfo($m['nome'], PATHINFO_FILENAME) . ' {hoje}');
      $padrao = str_replace('/', '-', $padrao);
  ?>
  <div class="modelo">
    <div class="modelo-topo">
      <span><?= icone_arquivo($m['ext']) ?> <strong><?= h($m['nome']) ?></strong> <small class="mudo"><?= data_legivel($m['mtime']) ?></small></span>
      <span class="acoes">
        <button class="btn btn-p btn-primario" data-abrir="#novo-<?= $i ?>">+ Novo documento</button>
        <?php if (eh_admin()): ?><a class="btn btn-p" href="<?= h($f->url(['a' => 'editar', 'f' => $m['rel']])) ?>">Editar modelo</a><?php endif; ?>
        <a class="btn btn-p" href="<?= h($f->url(['a' => 'baixar', 'f' => $m['rel']])) ?>">Baixar</a>
        <?php if (eh_admin()): ?><details class="menu">
          <summary class="btn btn-p" title="Mais opções">...</summary>
          <div class="menu-itens">
            <a href="<?= h($f->url(['a' => 'versoes', 'f' => $m['rel']])) ?>">Versões anteriores</a>
            <form method="post" action="<?= h($f->url(['a' => 'excluir'])) ?>" data-confirmar="Mover o modelo para a lixeira?"><input type="hidden" name="f" value="<?= h($m['rel']) ?>"><button class="perigo">Excluir</button></form>
          </div>
        </details><?php endif; ?>
      </span>
    </div>
    <form id="novo-<?= $i ?>" class="painel-oculto form-grade" method="post" action="<?= h($f->url(['a' => 'novo'])) ?>">
      <input type="hidden" name="modelo" value="<?= h($m['rel']) ?>">
      <label class="largo">Nome do novo arquivo
        <input name="nome" value="<?= h($padrao) ?>" required>
      </label>
      <?php foreach ($camposM as $k => $c): ?>
      <label><?= h($c['rotulo'] ?? $c['buscar']) ?>
        <input name="campos[<?= $k ?>]" value="<?= h(expandir_marcadores($c['padrao'] ?? $c['buscar'])) ?>">
        <?php if (!empty($c['dica'])): ?><small class="mudo"><?= h($c['dica']) ?></small><?php endif; ?>
      </label>
      <?php endforeach; ?>
      <div class="largo"><button class="btn btn-primario">Criar e abrir no editor</button></div>
    </form>
  </div>
  <?php endforeach; ?>
</section>
<?php
        if ($f->listar('saidas')) {
            lista_saidas($f);
        }
        rodape();
    }

    // ================================================================== criar / enviar / renomear

    private static function novo(Ferramenta $f): never
    {
        self::exigirPost();
        $modelo = self::rel($f, (string)($_POST['modelo'] ?? ''));
        $ext = pathinfo($modelo, PATHINFO_EXTENSION);
        $nome = nome_seguro((string)($_POST['nome'] ?? 'Documento'));
        if (strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== $ext) {
            $nome .= ".$ext";
        }
        $f->pasta('documentos');
        $destino = $f->nomeLivre('documentos', $nome);
        copy($f->caminho($modelo), $f->caminho($destino));

        $valores = (array)($_POST['campos'] ?? []);
        $campos = array_values(array_filter($f->cfg['campos'] ?? [], fn($c) => empty($c['modelo']) || $c['modelo'] === basename($modelo)));
        if ($ext === 'docx' && $campos && $valores) {
            $d = new Docx($f->caminho($destino));
            $m = $d->modelo();
            $n = 0;
            foreach ($campos as $k => $c) {
                $v = (string)($valores[$k] ?? '');
                if ($v === '' || $v === $c['buscar']) {
                    continue;
                }
                $antes = $c['antes'] ?? '';
                $depois = $c['depois'] ?? '';
                $n += Docx::substituir($m, $antes . $c['buscar'] . $depois, $antes . $v . $depois);
            }
            if ($n) {
                $d->aplicar($m);
                $d->salvar($f->caminho($destino));
            }
        }
        flash('Documento "' . basename($destino) . '" criado a partir do modelo.');
        redirecionar($f->url(['a' => 'editar', 'f' => $destino]));
    }

    private static function upload(Ferramenta $f): never
    {
        self::exigirPost();
        $sub = in_array($_POST['sub'] ?? '', ['documentos', 'modelos'], true) ? $_POST['sub'] : 'documentos';
        $f->pasta($sub);
        $n = 0;
        foreach ((array)($_FILES['arquivos']['name'] ?? []) as $i => $nome) {
            if (($_FILES['arquivos']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTS, true)) {
                flash("\"$nome\" ignorado: só .docx e .xlsx.", 'erro');
                continue;
            }
            $rel = "$sub/" . nome_seguro($nome);
            if (file_exists($f->caminho($rel))) {
                $f->backup($rel);
            }
            move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $f->caminho($rel));
            $n++;
        }
        if ($n) {
            flash("$n arquivo(s) enviado(s). Se já existia um com o mesmo nome, a versão anterior foi para o histórico.");
        }
        redirecionar($f->url());
    }

    private static function renomear(Ferramenta $f): never
    {
        self::exigirPost();
        $rel = self::rel($f);
        $ext = pathinfo($rel, PATHINFO_EXTENSION);
        $novo = nome_seguro((string)($_POST['novo'] ?? ''));
        if (!str_ends_with(strtolower($novo), ".$ext")) {
            $novo .= ".$ext";
        }
        $destino = dirname($rel) . '/' . $novo;
        if ($destino !== $rel) {
            if (file_exists($f->caminho($destino))) {
                throw new RuntimeException('Já existe um arquivo com esse nome.');
            }
            rename($f->caminho($rel), $f->caminho($destino));
            $vAnt = $f->pastaVersoes($rel);
            if (is_dir($vAnt)) {
                @rename($vAnt, $f->pastaVersoes($destino));
            }
            flash('Renomeado para "' . $novo . '".');
        }
        redirecionar($f->url());
    }

    private static function duplicar(Ferramenta $f): never
    {
        self::exigirPost();
        $rel = self::rel($f);
        $ext = pathinfo($rel, PATHINFO_EXTENSION);
        $destino = $f->nomeLivre(dirname($rel), pathinfo($rel, PATHINFO_FILENAME) . " - cópia.$ext");
        copy($f->caminho($rel), $f->caminho($destino));
        flash('Cópia criada: ' . basename($destino));
        redirecionar($f->url());
    }

    private static function pdf(Ferramenta $f): never
    {
        $rel = self::rel($f);
        $so = soffice() ?? throw new RuntimeException('LibreOffice não encontrado para gerar PDF.');
        $dir = $f->pasta('saidas/pdf');
        $cmd = escapeshellarg($so) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($dir) . ' ' . escapeshellarg($f->caminho($rel));
        exec($cmd . ' 2>&1', $saida, $cod);
        $pdf = $dir . '/' . pathinfo($rel, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($pdf)) {
            throw new RuntimeException('Falha ao gerar PDF: ' . implode(' ', $saida));
        }
        enviar_arquivo($pdf, null, true);
    }

    // ================================================================== versões

    private static function versoes(Ferramenta $f): void
    {
        $rel = self::rel($f);
        $vs = $f->versoes($rel);
        cabecalho_ferramenta($f, 'Versões de ' . basename($rel));
        barra_voltar($f, $f->url(), $f->nome);
        $cfg = config('backup');
        ?>
<section class="cartao">
  <h2>Versões anteriores de <?= h(basename($rel)) ?></h2>
  <p class="mudo pequeno">Cada vez que você salva, a versão anterior fica guardada aqui. São mantidas até <?= $cfg['max_versoes'] ?> versões; as com mais de <?= $cfg['dias'] ?> dias são apagadas (as <?= $cfg['min_versoes'] ?> mais recentes ficam sempre).</p>
  <?php if (in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), self::EXTS, true)): ?>
  <p><a class="btn btn-primario" href="<?= h($f->url(['a' => 'editar', 'f' => $rel])) ?>">Abrir versão atual</a></p>
  <?php endif; ?>
  <?php if (!$vs): ?><p class="mudo">Nenhuma versão anterior ainda.</p><?php else: ?>
  <table class="lista">
    <?php foreach ($vs as $v): ?>
    <tr>
      <td><?= data_legivel($v['mtime']) ?></td>
      <td class="mudo"><?= tamanho_legivel($v['tamanho']) ?></td>
      <td class="acoes">
        <a class="btn btn-p" href="<?= h($f->url(['a' => 'baixar_versao', 'f' => $rel, 'v' => $v['nome']])) ?>">Baixar</a>
        <form method="post" class="inline" action="<?= h($f->url(['a' => 'restaurar_versao'])) ?>" data-confirmar="Voltar o arquivo para esta versão? (a versão atual também fica guardada no histórico)">
          <input type="hidden" name="f" value="<?= h($rel) ?>"><input type="hidden" name="v" value="<?= h($v['nome']) ?>">
          <button class="btn btn-p">Restaurar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php
        rodape();
    }

    private static function restaurarVersao(Ferramenta $f): never
    {
        self::exigirPost();
        $rel = self::rel($f);
        $f->restaurarVersao($rel, (string)($_POST['v'] ?? ''));
        flash('Versão restaurada. A versão que estava antes também foi guardada no histórico.');
        redirecionar($f->url(['a' => 'versoes', 'f' => $rel]));
    }

    // ================================================================== editor

    /** Arquivos .xlsx da ferramenta (para importar em tabelas). */
    private static function planilhasDisponiveis(Ferramenta $f): array
    {
        $out = [];
        foreach (['documentos', 'modelos', 'saidas'] as $sub) {
            foreach ($f->listar($sub, ['xlsx']) as $x) {
                $out[] = ['rel' => $x['rel'], 'nome' => "$sub/" . $x['nome']];
            }
        }
        return $out;
    }

    private static function editar(Ferramenta $f): void
    {
        $rel = self::rel($f);
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $caminho = $f->caminho($rel);
        $voltar = str_starts_with($rel, 'saidas/') ? $f->url() : $f->url();
        $dados = [
            'arquivo' => $rel,
            'nome' => basename($rel),
            'hash' => md5_file($caminho),
            'urls' => [
                'salvar' => $f->url(['a' => 'salvar']),
                'midia' => $f->url(['a' => 'midia', 'f' => $rel]),
                'planilha' => $f->url(['a' => 'planilha']),
                'baixar' => $f->url(['a' => 'baixar', 'f' => $rel]),
                'editar' => $f->url(['a' => 'editar']),
            ],
        ];
        if ($ext === 'docx') {
            $dados['modelo'] = (new Docx($caminho))->modelo();
            $dados['planilhas'] = self::planilhasDisponiveis($f);
        } elseif ($ext === 'xlsx') {
            $x = new Xlsx($caminho);
            $dados['planilhas'] = [];
            foreach ($x->nomes() as $i => $n) {
                $dados['planilhas'][] = $x->dados($i);
            }
        } else {
            throw new RuntimeException('Só é possível editar .docx e .xlsx.');
        }
        cabecalho_ferramenta($f, basename($rel), ['largo' => true]);
        ?>
<div class="editor-barra" id="barra">
  <a class="btn" href="<?= h($voltar) ?>" data-sair>← Voltar</a>
  <button class="btn btn-primario" data-acao="salvar" title="Ctrl+S">Salvar</button>
  <button class="btn" data-acao="salvar-como">Salvar como…</button>
  <button class="btn" data-acao="desfazer" title="Desfazer alteração de estrutura">Desfazer</button>
  <?php if ($ext === 'docx'): ?>
  <button class="btn" data-acao="buscar">Localizar / substituir</button>
  <?php endif; ?>
  <a class="btn btn-leve" href="<?= h($f->url(['a' => 'baixar', 'f' => $rel])) ?>" data-sair>Baixar</a>
  <a class="btn btn-leve" href="<?= h($f->url(['a' => 'versoes', 'f' => $rel])) ?>" data-sair>Versões</a>
  <span id="status" class="status"></span>
</div>
<div id="painel-busca" class="painel-flutuante" hidden>
  <label>Localizar <input id="busca-de"></label>
  <label>Substituir por <input id="busca-para"></label>
  <label class="check"><input type="checkbox" id="busca-maius"> diferenciar maiúsculas</label>
  <button class="btn btn-primario" data-acao="substituir-tudo">Substituir tudo</button>
  <span id="busca-info" class="mudo"></span>
</div>
<?php if ($ext === 'docx'): ?>
<p class="dica nao-imprimir">Clique no texto para editar. <kbd>Enter</kbd> cria nova linha (parágrafo), <kbd>Shift</kbd>+<kbd>Enter</kbd> quebra a linha dentro do parágrafo. Passe o mouse num parágrafo ou tabela para ver as opções. A formatação original é mantida.</p>
<div id="editor" class="area-folha"></div>
<?php else: ?>
<p class="dica">Clique numa célula para editar. Datas no formato dd/mm/aaaa e horas hh:mm. Fórmulas aparecem em cinza e não são editáveis.</p>
<div id="editor" class="area-planilha"></div>
<?php endif; ?>
<script id="dados-editor" type="application/json"><?= json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php
        rodape([$ext === 'docx' ? 'editor-docx.js' : 'editor-xlsx.js']);
    }

    private static function salvar(Ferramenta $f): never
    {
        self::exigirPost();
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) {
            json_saida(['erro' => 'Dados inválidos.'], 400);
        }
        $rel = self::rel($f, (string)($in['arquivo'] ?? ''));
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $caminho = $f->caminho($rel);
        if (empty($in['forcar']) && md5_file($caminho) !== ($in['hash'] ?? '')) {
            json_saida(['erro' => 'O arquivo foi alterado em outro lugar (outra aba ou programa) depois que você abriu. Salvar mesmo assim vai sobrescrever essa alteração (ela fica no histórico de versões).', 'conflito' => true], 409);
        }
        if ($ext === 'docx') {
            $d = new Docx($caminho);
            $d->aplicar((array)($in['modelo'] ?? []));
            $conteudo = Zip::gerar($d->arquivos);
        } else {
            $x = new Xlsx($caminho);
            foreach ((array)($in['planilhas'] ?? []) as $p) {
                $x->aplicar((int)$p['i'], (array)($p['cel'] ?? []), isset($p['linhas']) ? (int)$p['linhas'] : null);
            }
            $conteudo = Zip::gerar($x->arquivos);
        }
        $destino = $rel;
        if (!empty($in['como'])) {
            $nome = nome_seguro((string)$in['como']);
            if (!str_ends_with(strtolower($nome), ".$ext")) {
                $nome .= ".$ext";
            }
            $sub = str_starts_with($rel, 'modelos/') && !empty($in['comoModelo']) ? 'modelos' : 'documentos';
            $f->pasta($sub);
            $destino = $f->nomeLivre($sub, $nome);
        }
        $f->salvarComBackup($destino, $conteudo);
        json_saida([
            'ok' => true,
            'hash' => md5_file($f->caminho($destino)),
            'arquivo' => $destino,
            'url' => $destino !== $rel ? $f->url(['a' => 'editar', 'f' => $destino]) : null,
            'hora' => date('H:i:s'),
        ]);
    }

    private static function midia(Ferramenta $f): never
    {
        $rel = self::rel($f);
        $parte = (string)($_GET['p'] ?? '');
        $arqs = Zip::ler($f->caminho($rel));
        if (!str_contains($parte, 'media/') || !isset($arqs[$parte])) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($parte, PATHINFO_EXTENSION));
        $tipos = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
        header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: max-age=3600');
        echo $arqs[$parte];
        exit;
    }

    private static function planilha(Ferramenta $f): never
    {
        $rel = self::rel($f);
        $x = new Xlsx($f->caminho($rel));
        $out = [];
        foreach ($x->nomes() as $i => $n) {
            $out[] = ['nome' => $n, 'm' => $x->matriz($i)];
        }
        json_saida(['planilhas' => $out]);
    }
}
