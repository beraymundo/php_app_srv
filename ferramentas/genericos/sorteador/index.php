<?php
/** Sorteador de números ou nomes. Os sorteios ficam registrados em saidas/sorteios.txt. */
/** @var Ferramenta $f */
/** @var string $a */
$log = 'saidas/sorteios.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'registrar') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $f->pasta('saidas');
    $linha = '[' . date('d/m/Y H:i:s') . '] ' . mb_substr(trim((string)($in['titulo'] ?? '')) ?: 'Sorteio', 0, 100)
        . ' — ' . mb_substr((string)($in['regra'] ?? ''), 0, 200) . "\n    Resultado: " . mb_substr(implode(', ', (array)($in['resultado'] ?? [])), 0, 5000) . "\n";
    file_put_contents($f->caminho($log), $linha, FILE_APPEND);
    json_saida(['ok' => true]);
}

$historico = is_file($f->caminho($log)) ? array_slice(preg_split('/\n(?=\[)/', trim(file_get_contents($f->caminho($log)))), -15) : [];
inicio_ferramenta($f);
?>
<div class="colunas">
  <section class="cartao">
    <h2>Configurar</h2>
    <div class="form-grade" id="f">
      <label class="largo">Título (opcional) <input name="titulo" placeholder="Ex.: Rifa da leitoa"></label>
      <label class="check"><input type="radio" name="modo" value="num" checked> Números</label>
      <label class="check"><input type="radio" name="modo" value="nomes"> Lista de nomes</label>
      <label class="m-num">De <input type="number" name="de" value="1"></label>
      <label class="m-num">Até <input type="number" name="ate" value="100"></label>
      <label class="largo m-nomes" hidden>Nomes (um por linha) <textarea name="nomes" rows="8"></textarea></label>
      <label>Quantos sortear <input type="number" name="qtd" value="1" min="1"></label>
      <label class="check"><input type="checkbox" name="repetir"> Permitir repetir</label>
      <label class="largo m-num">Excluir números (já sorteados etc.) <input name="excluir" placeholder="Ex.: 5, 12, 40"></label>
      <div class="largo"><button class="btn btn-primario" id="sortear">Sortear</button></div>
    </div>
  </section>
  <section class="cartao">
    <h2>Resultado</h2>
    <div class="destaque" id="res">—</div>
    <div class="grupo" style="justify-content:center"><button class="btn" id="copiar" hidden>Copiar</button></div>
    <h2 style="margin-top:18px">Últimos sorteios</h2>
    <div class="saida-texto pequeno" id="hist"><?= $historico ? h(implode("\n", array_reverse($historico))) : '<span class="mudo">Nenhum ainda.</span>' ?></div>
  </section>
</div>
<script>
(function () {
  const f = document.getElementById('f');
  const el = (n) => f.querySelector(`[name=${n}]`);
  const aleatorio = (n) => { const a = new Uint32Array(1); crypto.getRandomValues(a); return a[0] % n; };
  f.addEventListener('change', () => {
    const nomes = f.querySelector('[name=modo]:checked').value === 'nomes';
    f.querySelectorAll('.m-num').forEach((x) => (x.hidden = nomes));
    f.querySelectorAll('.m-nomes').forEach((x) => (x.hidden = !nomes));
  });
  document.getElementById('sortear').onclick = async () => {
    const nomes = f.querySelector('[name=modo]:checked').value === 'nomes';
    let pool, regra;
    if (nomes) {
      pool = el('nomes').value.split('\n').map((s) => s.trim()).filter(Boolean);
      regra = `${pool.length} nomes`;
    } else {
      const de = parseInt(el('de').value), ate = parseInt(el('ate').value);
      if (isNaN(de) || isNaN(ate) || ate < de) return alert('Intervalo inválido.');
      if (ate - de > 1e6) return alert('Intervalo grande demais.');
      const fora = new Set(el('excluir').value.split(/[^\d-]+/).filter(Boolean).map(Number));
      pool = [];
      for (let n = de; n <= ate; n++) if (!fora.has(n)) pool.push(n);
      regra = `números de ${de} a ${ate}` + (fora.size ? ` (excluídos: ${[...fora].join(', ')})` : '');
    }
    const qtd = Math.max(1, parseInt(el('qtd').value) || 1);
    const rep = el('repetir').checked;
    if (!pool.length) return alert('Nada para sortear.');
    if (!rep && qtd > pool.length) return alert('Quantidade maior que as opções disponíveis.');
    const res = [];
    const p = [...pool];
    for (let i = 0; i < qtd; i++) {
      const k = aleatorio(p.length);
      res.push(p[k]);
      if (!rep) p.splice(k, 1);
    }
    const out = document.getElementById('res');
    // pequena animação
    for (let i = 0; i < 12; i++) { out.textContent = pool[aleatorio(pool.length)]; await new Promise((r) => setTimeout(r, 40 + i * 8)); }
    out.textContent = res.join(', ');
    document.getElementById('copiar').hidden = false;
    document.getElementById('copiar').onclick = (e) => App.copiar(res.join(', '), e.target);
    const titulo = el('titulo').value;
    fetch(<?= json_encode($f->url(['a' => 'registrar'])) ?>, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ titulo, regra: regra + `, ${qtd} sorteado(s)`, resultado: res }) });
    const h = document.getElementById('hist');
    h.textContent = `[agora] ${titulo || 'Sorteio'} — ${regra}\n    Resultado: ${res.join(', ')}\n` + h.textContent.replace('Nenhum ainda.', '');
  };
})();
</script>
<?php lista_saidas($f, 'saidas', 'Registro completo'); rodape(); ?>
