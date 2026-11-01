<?php
/** Bloco de notas: cada nota é um .txt em dados/, com histórico de versões a cada salvamento. */
/** @var Ferramenta $f */
/** @var string $a */
$f->pasta('dados');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'salvar_nota') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $nome = nome_seguro((string)($in['nome'] ?? 'Nota'));
    $nome = str_ends_with(strtolower($nome), '.txt') ? $nome : "$nome.txt";
    $antigo = basename((string)($in['antigo'] ?? ''));
    if ($antigo !== '' && $antigo !== $nome && is_file($f->caminho("dados/$antigo"))) {
        if (file_exists($f->caminho("dados/$nome"))) {
            json_saida(['erro' => 'Já existe uma nota com esse nome.'], 400);
        }
        rename($f->caminho("dados/$antigo"), $f->caminho("dados/$nome"));
    }
    // como salva sozinho, só guarda versão no histórico a cada 10 minutos
    $versoes = $f->versoes("dados/$nome");
    if ($versoes && $versoes[0]['mtime'] > time() - 600) {
        file_put_contents($f->caminho("dados/$nome"), (string)($in['texto'] ?? ''));
    } else {
        $f->salvarComBackup("dados/$nome", (string)($in['texto'] ?? ''));
    }
    json_saida(['ok' => true, 'nome' => $nome, 'hora' => date('H:i:s')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'nova') {
    $rel = $f->nomeLivre('dados', 'Nota ' . date('d-m-Y') . '.txt');
    file_put_contents($f->caminho($rel), '');
    redirecionar($f->url(['n' => basename($rel)]));
}

$notas = $f->listar('dados', ['txt']);
$atual = isset($_GET['n']) ? basename((string)$_GET['n']) : ($notas[0]['nome'] ?? null);
$texto = $atual && is_file($f->caminho("dados/$atual")) ? file_get_contents($f->caminho("dados/$atual")) : null;

inicio_ferramenta($f);
?>
<div class="colunas colunas-lateral">
  <section class="cartao">
    <form method="post" action="<?= h($f->url(['a' => 'nova'])) ?>"><button class="btn btn-primario" style="width:100%;justify-content:center">+ Nova nota</button></form>
    <ul class="lista-ordenavel" style="margin-top:12px">
      <?php foreach ($notas as $n): ?>
      <li style="<?= $n['nome'] === $atual ? 'border-color:var(--primario)' : '' ?>"><a href="<?= h($f->url(['n' => $n['nome']])) ?>" style="flex:1;text-decoration:none"><?= h(substr($n['nome'], 0, -4)) ?><br><small class="mudo"><?= data_legivel($n['mtime']) ?></small></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="cartao">
    <?php if ($texto === null): ?>
      <p class="mudo">Crie uma nota para começar.</p>
    <?php else: ?>
    <div class="cartao-topo">
      <input id="nome" value="<?= h(substr($atual, 0, -4)) ?>" style="font-weight:600;font-size:1.05rem;max-width:420px">
      <div class="grupo">
        <span class="status" id="st"></span>
        <button class="btn btn-primario" id="salvar">Salvar</button>
        <button class="btn" onclick="App.copiar(document.getElementById('txt').value, this)">Copiar</button>
        <a class="btn btn-leve" href="<?= h($f->url(['a' => 'versoes', 'f' => "dados/$atual"])) ?>">Versões</a>
        <form method="post" class="inline" action="<?= h($f->url(['a' => 'excluir'])) ?>" data-confirmar="Mover a nota para a lixeira?"><input type="hidden" name="f" value="<?= h("dados/$atual") ?>"><button class="btn btn-perigo">Excluir</button></form>
      </div>
    </div>
    <textarea id="txt" style="min-height:60vh;font-family:ui-monospace,Consolas,monospace"><?= h($texto) ?></textarea>
    <p class="mudo pequeno">Salva sozinho alguns segundos depois de parar de digitar (e com Ctrl+S).</p>
    <?php endif; ?>
  </section>
</div>
<?php if ($texto !== null): ?>
<script>
(function () {
  let antigo = <?= json_encode($atual, JSON_UNESCAPED_UNICODE) ?>;
  const st = document.getElementById('st');
  let timer = null, sujo = false;
  const salvar = async () => {
    clearTimeout(timer);
    st.textContent = 'Salvando…';
    const r = await fetch(<?= json_encode($f->url(['a' => 'salvar_nota'])) ?>, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ nome: document.getElementById('nome').value, antigo, texto: document.getElementById('txt').value }) });
    const j = await r.json();
    if (j.erro) { st.textContent = 'erro: ' + j.erro; return; }
    sujo = false;
    st.textContent = 'salvo ' + j.hora;
    if (j.nome !== antigo) { antigo = j.nome; history.replaceState(null, '', '?' + new URLSearchParams({ ...Object.fromEntries(new URLSearchParams(location.search)), n: j.nome })); }
  };
  const mudou = () => { sujo = true; st.textContent = '* não salvo'; clearTimeout(timer); timer = setTimeout(salvar, 4000); };
  document.getElementById('txt').addEventListener('input', mudou);
  document.getElementById('nome').addEventListener('change', salvar);
  document.getElementById('salvar').onclick = salvar;
  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); salvar(); } });
  window.addEventListener('beforeunload', (e) => { if (sujo) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php endif; ?>
<?php rodape(); ?>
