<?php
/**
 * Lista de compras. Estado em dados/estado.json:
 *   itens: [{t: texto, q: quantidade, s: seção, ok: pego?}]
 *   frequentes: {"texto": {s: seção, n: vezes usado}}  (sugestões rápidas)
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="colunas colunas-lateral">
  <section class="cartao">
    <h2>Adicionar</h2>
    <form id="add" class="form-grade" style="grid-template-columns:1fr">
      <label>Item <input name="t" required placeholder="Ex.: arroz 5kg" autocomplete="off"></label>
      <label>Quantidade <input name="q" placeholder="Ex.: 2"></label>
      <label>Seção <select name="s"></select></label>
      <button class="btn btn-primario">+ Adicionar</button>
    </form>
    <h2 style="margin-top:20px">Frequentes</h2>
    <p class="mudo pequeno">Clique para pôr de volta na lista.</p>
    <div class="chips" id="freq"></div>
  </section>
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Lista <small class="mudo" id="contagem"></small></h2>
      <div class="grupo">
        <button class="btn" id="copiar">Copiar (WhatsApp)</button>
        <button class="btn" id="limpar-ok">Tirar os marcados</button>
        <button class="btn btn-perigo" id="limpar">Esvaziar</button>
        <span class="status pequeno" id="st"></span>
      </div>
    </div>
    <div id="lista"></div>
  </section>
</div>
<script>
(function () {
  const SECOES = ['Hortifrúti', 'Mercearia', 'Açougue / frios', 'Padaria', 'Laticínios', 'Bebidas', 'Limpeza', 'Higiene', 'Outros'];
  const PALPITES = { 'Mercearia': /arroz|feijão|feijao|açúcar|acucar|óleo|oleo|macarrão|macarrao|farinha|fubá|sal|azeite|molho|extrato|milho|ervilha|enlatado|biscoito|bolacha|tempero|vinagre|aveia|achocolatado|leite condensado|fermento/i,
    'Hortifrúti': /banana|maç|tomate|cebola|alho|batata|cenoura|alface|fruta|legume|verdura|limão|laranja|mamão|abacate|ovo/i,
    'Açougue / frios': /carne|frango|peixe|linguiça|presunto|mussarela|queijo|salsicha|bacon|picanha|patinho|moída/i,
    'Padaria': /pão|pao|bolo|rosca|biscoito de padaria/i, 'Laticínios': /leite|iogurte|manteiga|requeijão|creme de leite|margarina/i,
    'Bebidas': /suco|refri|cerveja|água|agua|café|cafe|chá/i, 'Limpeza': /sabão|detergente|amaciante|desinfetante|água sanitária|esponja|vassoura|saco de lixo|lysoform|veja/i,
    'Higiene': /papel higiênico|sabonete|shampoo|xampu|condicionador|pasta de dente|creme dental|escova|desodorante|absorvente|fralda/i };
  const est = Object.assign({ itens: [], frequentes: {} }, <?= json_js($estado ?: new stdClass()) ?>);
  const $ = (s) => document.querySelector(s);
  const form = $('#add');
  form.s.innerHTML = SECOES.map((s) => `<option>${s}</option>`).join('');
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  form.t.addEventListener('input', () => {
    const t = form.t.value.trim().toLowerCase();
    const f = est.frequentes[t];
    if (f) { form.s.value = f.s; return; }
    for (const [s, re] of Object.entries(PALPITES)) if (re.test(t)) { form.s.value = s; return; }
    form.s.value = 'Outros';
  });
  function adicionar(t, q, s) {
    t = t.trim();
    if (!t) return;
    const ja = est.itens.find((i) => i.t.toLowerCase() === t.toLowerCase() && !i.ok);
    if (ja) { if (q) ja.q = q; } else est.itens.push({ t, q: q || '', s: s || 'Outros', ok: false });
    const k = t.toLowerCase();
    est.frequentes[k] = { t, s: s || 'Outros', n: ((est.frequentes[k] || {}).n || 0) + 1 };
    desenhar(); salvar();
  }
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    adicionar(form.t.value, form.q.value.trim(), form.s.value);
    form.t.value = ''; form.q.value = ''; form.t.focus();
  });
  function desenhar() {
    const lista = $('#lista');
    const pend = est.itens.filter((i) => !i.ok).length;
    $('#contagem').textContent = est.itens.length ? `(${pend} faltando de ${est.itens.length})` : '';
    if (!est.itens.length) { lista.innerHTML = '<p class="mudo">Lista vazia. Adicione itens ao lado.</p>'; }
    else {
      let html = '';
      for (const s of SECOES) {
        const it = est.itens.map((x, i) => [x, i]).filter(([x]) => x.s === s);
        if (!it.length) continue;
        it.sort((a, b) => a[0].ok - b[0].ok || a[0].t.localeCompare(b[0].t));
        html += `<h3 class="pequeno mudo" style="margin:14px 0 4px">${esc(s)}</h3><table class="dados">`;
        for (const [x, i] of it) {
          html += `<tr><td style="width:28px"><input type="checkbox" data-i="${i}" ${x.ok ? 'checked' : ''}></td>
            <td class="${x.ok ? 'riscado' : ''}">${esc(x.t)}${x.q ? ` <span class="mudo">× ${esc(x.q)}</span>` : ''}</td>
            <td class="num"><button class="btn btn-p btn-leve" data-rm="${i}" title="Remover">×</button></td></tr>`;
        }
        html += '</table>';
      }
      lista.innerHTML = html;
    }
    const naLista = new Set(est.itens.filter((i) => !i.ok).map((i) => i.t.toLowerCase()));
    const freq = Object.values(est.frequentes).filter((x) => !naLista.has(x.t.toLowerCase())).sort((a, b) => b.n - a.n).slice(0, 30);
    $('#freq').innerHTML = freq.length ? freq.map((x) => `<button class="chip-btn" data-f="${esc(x.t.toLowerCase())}">+ ${esc(x.t)}</button>`).join('') : '<span class="mudo pequeno">Os itens que você usa aparecem aqui.</span>';
  }
  $('#lista').addEventListener('change', (e) => { const i = e.target.dataset.i; if (i !== undefined) { est.itens[i].ok = e.target.checked; desenhar(); salvar(); } });
  $('#lista').addEventListener('click', (e) => { const i = e.target.dataset.rm; if (i !== undefined) { est.itens.splice(+i, 1); desenhar(); salvar(); } });
  $('#freq').addEventListener('click', (e) => { const k = e.target.dataset.f; if (k) { const x = est.frequentes[k]; adicionar(x.t, '', x.s); } });
  $('#limpar-ok').onclick = () => { est.itens = est.itens.filter((i) => !i.ok); desenhar(); salvar(); };
  $('#limpar').onclick = () => { if (confirm('Esvaziar a lista inteira?')) { est.itens = []; desenhar(); salvar(); } };
  $('#copiar').onclick = (e) => {
    let txt = '*Lista de compras*\n';
    for (const s of SECOES) {
      const it = est.itens.filter((x) => x.s === s && !x.ok);
      if (it.length) txt += `\n_${s}_\n` + it.map((x) => `- ${x.t}${x.q ? ' (' + x.q + ')' : ''}`).join('\n') + '\n';
    }
    App.copiar(txt.trim(), e.target);
  };
  desenhar();
})();
</script>
<?php rodape(); ?>
