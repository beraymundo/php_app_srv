<?php
/**
 * Divisão de despesas. Estado em dados/estado.json:
 *   eventos: [{id, nome, pessoas: [nomes], gastos: [{desc, valor, pagou, para: [nomes] (vazio = todos)}], pagos: ["A>B"]}], atual
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="cartao-topo" style="margin-bottom:14px">
  <div class="grupo">
    <select id="evento" style="width:auto;min-width:220px"></select>
    <button class="btn" id="novo-ev">+ Novo evento</button>
    <button class="btn btn-leve" id="ren-ev">Renomear</button>
    <button class="btn btn-leve btn-perigo" id="rm-ev">Excluir</button>
  </div>
  <span class="status pequeno" id="st"></span>
</div>
<div class="colunas">
  <section class="cartao">
    <h2>Pessoas</h2>
    <form id="add-pessoa" class="grupo"><input name="nome" placeholder="Nome" required style="flex:1"><button class="btn">+ Adicionar</button></form>
    <div class="chips" id="pessoas" style="margin-top:10px"></div>
    <h2 style="margin-top:20px">Novo gasto</h2>
    <form id="add-gasto" class="form-grade">
      <label class="largo">Descrição <input name="desc" placeholder="Ex.: carne, gasolina, aluguel" required></label>
      <label>Valor (R$) <input name="valor" inputmode="decimal" required></label>
      <label>Quem pagou <select name="pagou" required></select></label>
      <div class="largo"><span class="pequeno mudo">Dividir entre:</span><div class="chips" id="para" style="margin-top:6px"></div></div>
      <div class="largo"><button class="btn btn-primario">+ Lançar gasto</button></div>
    </form>
  </section>
  <section class="cartao">
    <h2>Acerto de contas</h2>
    <div id="acerto"></div>
    <div class="grupo" style="margin-top:12px"><button class="btn" id="copiar">Copiar resumo (WhatsApp)</button></div>
  </section>
</div>
<section class="cartao">
  <h2>Gastos <small class="mudo" id="total"></small></h2>
  <div class="tabela-rolagem"><table class="dados" id="gastos"></table></div>
</section>
<script>
(function () {
  const est = Object.assign({ eventos: [], atual: '' }, <?= json_js($estado ?: new stdClass()) ?>);
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const id = () => Math.random().toString(36).slice(2, 9);
  const R = App.reais;
  if (!est.eventos.length) est.eventos.push({ id: id(), nome: 'Meu evento', pessoas: [], gastos: [], pagos: [] });
  const ev = () => est.eventos.find((e) => e.id === est.atual) || (est.atual = est.eventos[0].id, est.eventos[0]);
  let paraSel = null; // null = todos

  function calcular(e) {
    const saldo = Object.fromEntries(e.pessoas.map((p) => [p, 0]));
    const gastou = Object.fromEntries(e.pessoas.map((p) => [p, 0]));
    for (const g of e.gastos) {
      const para = (g.para && g.para.length ? g.para : e.pessoas).filter((p) => p in saldo);
      if (!para.length || !(g.pagou in saldo)) continue;
      saldo[g.pagou] += g.valor;
      const parte = g.valor / para.length;
      para.forEach((p) => { saldo[p] -= parte; gastou[p] += parte; });
    }
    // transferências mínimas (guloso: maior devedor paga maior credor)
    const dev = Object.entries(saldo).filter(([, v]) => v < -0.005).map(([p, v]) => [p, -v]).sort((a, b) => b[1] - a[1]);
    const cre = Object.entries(saldo).filter(([, v]) => v > 0.005).sort((a, b) => b[1] - a[1]);
    const tr = [];
    let i = 0, j = 0;
    while (i < dev.length && j < cre.length) {
      const v = Math.min(dev[i][1], cre[j][1]);
      tr.push([dev[i][0], cre[j][0], v]);
      dev[i][1] -= v; cre[j][1] -= v;
      if (dev[i][1] < 0.005) i++;
      if (cre[j][1] < 0.005) j++;
    }
    return { saldo, gastou, tr };
  }
  function desenhar() {
    const e = ev();
    $('#evento').innerHTML = est.eventos.map((x) => `<option value="${x.id}" ${x.id === e.id ? 'selected' : ''}>${esc(x.nome)}</option>`).join('');
    $('#pessoas').innerHTML = e.pessoas.length ? e.pessoas.map((p) => `<span class="tag" style="font-size:.8rem;text-transform:none;padding:2px 8px">${esc(p)} <a href="#" data-rmp="${esc(p)}" title="Remover" style="text-decoration:none;margin-left:4px">×</a></span>`).join('') : '<span class="mudo pequeno">Adicione quem participa.</span>';
    $('#add-gasto').pagou.innerHTML = e.pessoas.map((p) => `<option>${esc(p)}</option>`).join('');
    $('#para').innerHTML = e.pessoas.map((p) => `<label class="check pequeno"><input type="checkbox" value="${esc(p)}" ${!paraSel || paraSel.includes(p) ? 'checked' : ''}> ${esc(p)}</label>`).join('');

    const total = e.gastos.reduce((s, g) => s + g.valor, 0);
    $('#total').textContent = e.gastos.length ? `(${R(total)} no total, ${e.pessoas.length ? R(total / e.pessoas.length) : '—'} por pessoa se dividido igual)` : '';
    $('#gastos').innerHTML = e.gastos.length ? '<thead><tr><th>descrição</th><th>pagou</th><th>dividido entre</th><th class="num">valor</th><th></th></tr></thead><tbody>' + e.gastos.map((g, k) => `<tr>
      <td>${esc(g.desc)}</td><td>${esc(g.pagou)}</td><td class="mudo pequeno">${g.para && g.para.length && g.para.length < e.pessoas.length ? esc(g.para.join(', ')) : 'todos'}</td>
      <td class="num">${R(g.valor)}</td><td class="num"><button class="btn btn-p btn-leve" data-rmg="${k}" title="Remover">×</button></td></tr>`).join('') + '</tbody>' : '<tr><td class="mudo">Nenhum gasto lançado.</td></tr>';

    const c = calcular(e);
    let html = '<table class="dados"><thead><tr><th>pessoa</th><th class="num">pagou</th><th class="num">consumiu</th><th class="num">saldo</th></tr></thead><tbody>';
    for (const p of e.pessoas) {
      const pagou = e.gastos.filter((g) => g.pagou === p).reduce((s, g) => s + g.valor, 0), s = c.saldo[p];
      html += `<tr><td>${esc(p)}</td><td class="num">${R(pagou)}</td><td class="num">${R(c.gastou[p])}</td><td class="num ${s > 0.005 ? 'estado-ok' : s < -0.005 ? 'estado-ruim' : 'mudo'}">${s > 0.005 ? '+' : ''}${R(s)}</td></tr>`;
    }
    html += '</tbody></table>';
    html += c.tr.length ? '<h3 class="pequeno" style="margin:16px 0 6px">Quem paga quem</h3>' + c.tr.map(([de, para, v]) => {
      const k = `${de}>${para}`, ok = (e.pagos || []).includes(k);
      return `<label class="check" style="padding:6px 0;border-bottom:1px solid var(--borda)"><input type="checkbox" data-pago="${esc(k)}" ${ok ? 'checked' : ''}>
        <span class="${ok ? 'riscado' : ''}"><strong>${esc(de)}</strong> paga <strong>${R(v)}</strong> para <strong>${esc(para)}</strong></span></label>`;
    }).join('') : (e.gastos.length ? '<p class="estado-ok">Tudo acertado.</p>' : '');
    $('#acerto').innerHTML = html;
  }
  $('#add-pessoa').addEventListener('submit', (x) => {
    x.preventDefault();
    const n = x.target.nome.value.trim(), e = ev();
    if (n && !e.pessoas.includes(n)) e.pessoas.push(n);
    x.target.reset(); desenhar(); salvar();
  });
  $('#pessoas').addEventListener('click', (x) => {
    const p = x.target.dataset.rmp;
    if (!p) return;
    x.preventDefault();
    const e = ev();
    if (e.gastos.some((g) => g.pagou === p) && !confirm(`${p} tem gastos lançados. Remover mesmo assim? (os gastos dele ficam fora da conta)`)) return;
    e.pessoas = e.pessoas.filter((y) => y !== p); desenhar(); salvar();
  });
  $('#para').addEventListener('change', () => {
    const marc = [...document.querySelectorAll('#para input:checked')].map((i) => i.value);
    paraSel = marc.length === ev().pessoas.length ? null : marc;
  });
  $('#add-gasto').addEventListener('submit', (x) => {
    x.preventDefault();
    const fm = x.target, e = ev(), v = App.numero(fm.valor.value);
    if (!v || v <= 0) return alert('Valor inválido.');
    if (!e.pessoas.length) return alert('Adicione as pessoas primeiro.');
    const para = [...document.querySelectorAll('#para input:checked')].map((i) => i.value);
    if (!para.length) return alert('Marque entre quem dividir.');
    e.gastos.push({ desc: fm.desc.value.trim(), valor: v, pagou: fm.pagou.value, para: para.length === e.pessoas.length ? [] : para });
    e.pagos = [];
    fm.desc.value = ''; fm.valor.value = ''; fm.desc.focus(); paraSel = null;
    desenhar(); salvar();
  });
  $('#gastos').addEventListener('click', (x) => { const k = x.target.dataset.rmg; if (k !== undefined) { ev().gastos.splice(+k, 1); ev().pagos = []; desenhar(); salvar(); } });
  $('#acerto').addEventListener('change', (x) => {
    const k = x.target.dataset.pago; if (!k) return;
    const e = ev(); e.pagos = e.pagos || [];
    e.pagos = x.target.checked ? [...e.pagos, k] : e.pagos.filter((y) => y !== k);
    desenhar(); salvar();
  });
  $('#evento').onchange = (x) => { est.atual = x.target.value; paraSel = null; desenhar(); salvar(); };
  $('#novo-ev').onclick = () => { const n = prompt('Nome do evento:', 'Churrasco'); if (!n) return; const e = { id: id(), nome: n.trim(), pessoas: [...ev().pessoas], gastos: [], pagos: [] }; est.eventos.push(e); est.atual = e.id; desenhar(); salvar(); };
  $('#ren-ev').onclick = () => { const n = prompt('Novo nome:', ev().nome); if (n) { ev().nome = n.trim(); desenhar(); salvar(); } };
  $('#rm-ev').onclick = () => {
    if (!confirm(`Excluir o evento "${ev().nome}"?`)) return;
    est.eventos = est.eventos.filter((e) => e !== ev());
    if (!est.eventos.length) est.eventos.push({ id: id(), nome: 'Meu evento', pessoas: [], gastos: [], pagos: [] });
    est.atual = est.eventos[0].id; desenhar(); salvar();
  };
  $('#copiar').onclick = (x) => {
    const e = ev(), c = calcular(e), total = e.gastos.reduce((s, g) => s + g.valor, 0);
    let t = `*${e.nome}*\nTotal: ${R(total)}\n\n` + e.gastos.map((g) => `- ${g.desc}: ${R(g.valor)} (${g.pagou})`).join('\n');
    t += '\n\n*Acerto:*\n' + (c.tr.length ? c.tr.map(([de, para, v]) => `${de} → ${para}: ${R(v)}`).join('\n') : 'tudo acertado');
    App.copiar(t, x.target);
  };
  desenhar();
})();
</script>
<?php rodape(); ?>
