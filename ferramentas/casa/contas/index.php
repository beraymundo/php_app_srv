<?php
/**
 * Contas do mês. Estado em dados/estado.json:
 *   contas: [{id, nome, valor (padrão), dia (vencimento), ativa}]
 *   meses:  {"2026-11": {"<id>": {v: valor do mês, pago: bool, data: "2026-11-05"}, "_extras": [{id, nome, v, pago}]}}
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="cartao-topo" style="margin-bottom:14px">
  <div class="grupo">
    <button class="btn" id="ant">← mês anterior</button>
    <strong id="titulo-mes" style="min-width:12ch;text-align:center"></strong>
    <button class="btn" id="prox">próximo mês →</button>
    <button class="btn btn-leve" id="hoje">mês atual</button>
  </div>
  <span class="status pequeno" id="st"></span>
</div>
<div class="resumo">
  <div><span class="rotulo">total do mês</span><span class="valor" id="r-total"></span></div>
  <div><span class="rotulo">já pago</span><span class="valor estado-ok" id="r-pago"></span></div>
  <div><span class="rotulo">falta pagar</span><span class="valor" id="r-falta"></span></div>
  <div><span class="rotulo">atrasadas</span><span class="valor" id="r-atraso"></span></div>
</div>
<section class="cartao">
  <h2>Contas</h2>
  <div class="tabela-rolagem">
    <table class="dados">
      <thead><tr><th style="width:30px">pago</th><th>conta</th><th>vence</th><th>situação</th><th class="num">valor (R$)</th><th></th></tr></thead>
      <tbody id="linhas"></tbody>
    </table>
  </div>
  <form id="extra" class="linha-form" style="margin-top:12px">
    <label>Conta avulsa só deste mês <input name="nome" placeholder="Ex.: IPVA, conserto..." required></label>
    <label>Valor <input name="v" inputmode="decimal" placeholder="0,00" required></label>
    <button class="btn">+ Adicionar ao mês</button>
  </form>
</section>
<section class="cartao">
  <div class="cartao-topo"><h2>Contas fixas</h2><button class="btn" data-abrir="#nova">+ Nova conta fixa</button></div>
  <form id="nova" class="painel-oculto linha-form">
    <label>Nome <input name="nome" required placeholder="Ex.: Energia (EDP)"></label>
    <label>Valor de costume <input name="valor" inputmode="decimal" placeholder="0,00"></label>
    <label>Dia do vencimento <input name="dia" type="number" min="1" max="31" value="10"></label>
    <button class="btn btn-primario">Salvar</button>
  </form>
  <table class="dados" style="margin-top:8px"><tbody id="fixas"></tbody></table>
  <p class="dica">O valor de costume já vem preenchido em cada mês; contas que mudam (luz, água) você corrige no mês. Mudanças aqui valem para os meses ainda não pagos.</p>
</section>
<section class="cartao">
  <h2>Últimos 12 meses</h2>
  <div class="tabela-rolagem"><table class="dados" id="historico"></table></div>
</section>
<script>
(function () {
  const est = Object.assign({ contas: [], meses: {} }, <?= json_js($estado ?: new stdClass()) ?>);
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = (n) => (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const id = () => Math.random().toString(36).slice(2, 9);
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  let ano = hoje.getFullYear(), mes = hoje.getMonth();
  const chave = (a, m) => `${a}-${String(m + 1).padStart(2, '0')}`;
  const doMes = (k) => (est.meses[k] = est.meses[k] || {});

  function linhasDoMes(a, m) {
    const k = chave(a, m), dm = est.meses[k] || {};
    const ultimoDia = new Date(a, m + 1, 0).getDate();
    const out = est.contas.filter((c) => c.ativa !== false || dm[c.id]).map((c) => {
      const r = dm[c.id] || {};
      const venc = new Date(a, m, Math.min(c.dia || 1, ultimoDia));
      return { id: c.id, nome: c.nome, v: r.v ?? c.valor ?? 0, pago: !!r.pago, venc, extra: false };
    });
    (dm._extras || []).forEach((x) => out.push({ id: x.id, nome: x.nome, v: x.v, pago: !!x.pago, venc: null, extra: true }));
    return out.sort((x, y) => (x.venc || 0) - (y.venc || 0));
  }
  function situacao(l) {
    if (l.pago) return ['pago', 'estado-ok'];
    if (!l.venc) return ['a pagar', ''];
    const d = Math.round((l.venc - hoje) / 864e5);
    if (d < 0) return [`atrasada ${-d} dia${d === -1 ? '' : 's'}`, 'estado-ruim'];
    if (d === 0) return ['vence hoje', 'estado-alerta'];
    if (d <= 5) return [`vence em ${d} dia${d === 1 ? '' : 's'}`, 'estado-alerta'];
    return [`em ${d} dias`, 'mudo'];
  }
  function desenhar() {
    $('#titulo-mes').textContent = `${MESES[mes]} ${ano}`;
    const ls = linhasDoMes(ano, mes);
    let total = 0, pago = 0, atraso = 0;
    $('#linhas').innerHTML = ls.length ? ls.map((l) => {
      const [txt, cls] = situacao(l);
      total += +l.v || 0; if (l.pago) pago += +l.v || 0; if (cls === 'estado-ruim') atraso++;
      return `<tr data-id="${l.id}" data-extra="${l.extra ? 1 : ''}">
        <td><input type="checkbox" data-k="pago" ${l.pago ? 'checked' : ''}></td>
        <td class="${l.pago ? 'riscado' : ''}">${esc(l.nome)}${l.extra ? ' <span class="tag">avulsa</span>' : ''}</td>
        <td class="mudo">${l.venc ? String(l.venc.getDate()).padStart(2, '0') + '/' + String(mes + 1).padStart(2, '0') : '—'}</td>
        <td class="${cls}">${txt}</td>
        <td class="num"><input data-k="v" value="${fmt(l.v)}" inputmode="decimal" style="width:110px;text-align:right"></td>
        <td class="num">${l.extra ? '<button class="btn btn-p btn-leve" data-k="rm" title="Remover">×</button>' : ''}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="mudo">Cadastre suas contas fixas abaixo.</td></tr>';
    $('#r-total').textContent = App.reais(total);
    $('#r-pago').textContent = App.reais(pago);
    $('#r-falta').textContent = App.reais(total - pago);
    $('#r-atraso').textContent = atraso;
    $('#r-atraso').className = 'valor' + (atraso ? ' estado-ruim' : '');

    $('#fixas').innerHTML = est.contas.map((c) => `<tr data-id="${c.id}">
      <td><input data-f="nome" value="${esc(c.nome)}"></td>
      <td style="width:130px"><input data-f="valor" value="${fmt(c.valor)}" inputmode="decimal" style="text-align:right"></td>
      <td style="width:110px"><input data-f="dia" type="number" min="1" max="31" value="${c.dia}" title="Dia do vencimento"></td>
      <td class="num"><label class="check"><input type="checkbox" data-f="ativa" ${c.ativa !== false ? 'checked' : ''}> ativa</label></td></tr>`).join('');

    // histórico
    let cab = '<thead><tr><th>mês</th><th class="num">total</th><th class="num">pago</th><th style="width:40%"></th></tr></thead><tbody>';
    const dados = [];
    for (let i = 11; i >= 0; i--) {
      const d = new Date(ano, mes - i, 1);
      // meses passados sem nenhum registro contam como vazios (antes de começar a usar)
      const usado = est.meses[chave(d.getFullYear(), d.getMonth())] || d >= new Date(hoje.getFullYear(), hoje.getMonth(), 1);
      const ls2 = usado ? linhasDoMes(d.getFullYear(), d.getMonth()) : [];
      const t = ls2.reduce((s, l) => s + (+l.v || 0), 0), p = ls2.reduce((s, l) => s + (l.pago ? +l.v || 0 : 0), 0);
      dados.push([d, t, p]);
    }
    const max = Math.max(1, ...dados.map((x) => x[1]));
    $('#historico').innerHTML = cab + dados.map(([d, t, p]) => `<tr><td>${MESES[d.getMonth()].slice(0, 3)}/${String(d.getFullYear()).slice(2)}</td><td class="num">${fmt(t)}</td><td class="num mudo">${fmt(p)}</td><td><div class="barra-progresso"><span style="width:${(t / max) * 100}%"></span></div></td></tr>`).join('') + '</tbody>';
  }
  $('#linhas').addEventListener('change', (e) => {
    const tr = e.target.closest('tr'), k = e.target.dataset.k;
    if (!tr || !k) return;
    const dm = doMes(chave(ano, mes));
    let alvo;
    if (tr.dataset.extra) alvo = (dm._extras || []).find((x) => x.id === tr.dataset.id);
    else {
      const c = est.contas.find((x) => x.id === tr.dataset.id);
      alvo = dm[tr.dataset.id] = dm[tr.dataset.id] || { v: c.valor || 0 };
    }
    if (k === 'pago') { alvo.pago = e.target.checked; if (alvo.pago) alvo.data = new Date().toISOString().slice(0, 10); }
    if (k === 'v') alvo.v = App.numero(e.target.value) ?? 0;
    desenhar(); salvar();
  });
  $('#linhas').addEventListener('click', (e) => {
    if (e.target.dataset.k !== 'rm') return;
    const dm = doMes(chave(ano, mes));
    dm._extras = (dm._extras || []).filter((x) => x.id !== e.target.closest('tr').dataset.id);
    desenhar(); salvar();
  });
  $('#fixas').addEventListener('change', (e) => {
    const c = est.contas.find((x) => x.id === e.target.closest('tr').dataset.id), k = e.target.dataset.f;
    if (k === 'nome') c.nome = e.target.value.trim() || c.nome;
    if (k === 'valor') c.valor = App.numero(e.target.value) ?? 0;
    if (k === 'dia') c.dia = Math.max(1, Math.min(31, +e.target.value || 1));
    if (k === 'ativa') c.ativa = e.target.checked;
    desenhar(); salvar();
  });
  $('#nova').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target;
    est.contas.push({ id: id(), nome: fm.nome.value.trim(), valor: App.numero(fm.valor.value) ?? 0, dia: +fm.dia.value || 1, ativa: true });
    fm.reset(); fm.dia.value = 10;
    desenhar(); salvar();
  });
  $('#extra').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target, dm = doMes(chave(ano, mes));
    (dm._extras = dm._extras || []).push({ id: id(), nome: fm.nome.value.trim(), v: App.numero(fm.v.value) ?? 0, pago: false });
    fm.reset(); desenhar(); salvar();
  });
  const mudar = (d) => { const x = new Date(ano, mes + d, 1); ano = x.getFullYear(); mes = x.getMonth(); desenhar(); };
  $('#ant').onclick = () => mudar(-1);
  $('#prox').onclick = () => mudar(1);
  $('#hoje').onclick = () => { ano = hoje.getFullYear(); mes = hoje.getMonth(); desenhar(); };
  desenhar();
})();
</script>
<?php rodape(); ?>
