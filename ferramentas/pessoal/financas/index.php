<?php
/**
 * Minhas Finanças. Estado em dados/estado.json, separado em:
 *
 * Informações gerais (valem para todos os meses):
 *   contas:     [{id, nome, tipo: corrente|poupanca|carteira|investimento|cartao, ativa}]
 *   fixos:      [{id, desc, valor, dia, tipo: despesa|receita, cat, conta, desde: "AAAA-MM", ate: "AAAA-MM"|null}]
 *   categorias: [nomes]
 *
 * Informações de cada mês (meses["AAAA-MM"]):
 *   fixos:  {"<id do fixo>": {v: valor deste mês, pago: bool, data}}   (valor real e se já foi pago/recebido)
 *   lanc:   [{id, data, desc, valor, tipo, cat, conta}]                  (gastos e entradas avulsos)
 *   saldos: [{id, conta, data, valor}]                                    ("em tal data a conta X tinha Y")
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="cartao-topo" style="margin-bottom:14px">
  <div class="grupo" id="abas">
    <button class="btn btn-primario" data-aba="mes">Mês</button>
    <button class="btn" data-aba="stats">Estatísticas</button>
    <button class="btn" data-aba="geral">Contas e fixos</button>
  </div>
  <span class="status pequeno" id="st"></span>
</div>

<!-- ============================================================ MÊS -->
<div data-painel="mes">
  <div class="cartao-topo" style="margin-bottom:12px">
    <div class="grupo">
      <button class="btn btn-p" id="ant">←</button>
      <strong id="titulo-mes" style="min-width:15ch;text-align:center;font-size:1.05rem"></strong>
      <button class="btn btn-p" id="prox">→</button>
      <button class="btn btn-p btn-leve" id="hoje">mês atual</button>
    </div>
  </div>
  <div class="resumo" id="resumo-mes"></div>

  <section class="cartao">
    <div class="cartao-topo"><h2>Lançar</h2></div>
    <form id="lancar" class="linha-form">
      <label style="flex:0 0 130px">Tipo <select name="tipo"><option value="despesa">gasto</option><option value="receita">entrada</option></select></label>
      <label style="flex:2 1 200px">Descrição <input name="desc" required placeholder="Ex.: mercado, uber, freela" autocomplete="off" list="sugestoes"></label>
      <label style="flex:0 1 120px">Valor <input name="valor" required inputmode="decimal" placeholder="0,00"></label>
      <label style="flex:0 1 150px">Data <input name="data" type="date" required></label>
      <label style="flex:1 1 150px">Categoria <select name="cat"></select></label>
      <label style="flex:1 1 140px">Conta <select name="conta"></select></label>
      <button class="btn btn-primario">+ Lançar</button>
    </form>
    <datalist id="sugestoes"></datalist>
  </section>

  <section class="cartao">
    <div class="cartao-topo">
      <h2>Movimentações do mês</h2>
      <div class="grupo">
        <select id="filtro" style="width:auto">
          <option value="">tudo</option><option value="despesa">só gastos</option><option value="receita">só entradas</option>
          <option value="fixo">só fixos</option><option value="var">só avulsos</option><option value="pendente">a pagar / receber</option>
        </select>
      </div>
    </div>
    <div class="tabela-rolagem"><table class="dados" id="tabela-mes"></table></div>
  </section>

  <section class="cartao">
    <div class="cartao-topo"><h2>Saldo das contas</h2></div>
    <div class="tabela-rolagem"><table class="dados" id="saldos"></table></div>
    <form id="form-saldo" class="linha-form" style="margin-top:12px">
      <label>Conta <select name="conta" required></select></label>
      <label>Em <input name="data" type="date" required></label>
      <label>Tinha (R$) <input name="valor" required inputmode="decimal" placeholder="0,00"></label>
      <button class="btn">Registrar saldo</button>
    </form>
    <p class="dica">Registre o saldo quando quiser (no começo e no fim do mês já ajuda). O sistema compara com o que foi lançado e mostra a diferença, que costuma ser algo que ficou sem lançar.</p>
  </section>
</div>

<!-- ============================================================ ESTATÍSTICAS -->
<div data-painel="stats" hidden>
  <div class="resumo" id="resumo-ano"></div>
  <div class="colunas">
    <section class="cartao"><h2>Gastos por categoria <small class="mudo" id="cat-mes"></small></h2><table class="dados" id="por-cat"></table></section>
    <section class="cartao"><h2>Maiores gastos do mês</h2><table class="dados" id="maiores"></table><h2 style="margin-top:18px">Fixos × avulsos</h2><div id="fixo-var"></div></section>
  </div>
  <section class="cartao"><h2>Últimos 12 meses</h2><div class="tabela-rolagem"><table class="dados" id="doze"></table></div></section>
  <section class="cartao"><h2>Patrimônio (soma das contas no fim de cada mês)</h2><div class="tabela-rolagem"><table class="dados" id="patrimonio"></table></div></section>
</div>

<!-- ============================================================ GERAL -->
<div data-painel="geral" hidden>
  <section class="cartao">
    <div class="cartao-topo"><h2>Minhas contas</h2></div>
    <table class="dados" id="lista-contas"></table>
    <form id="nova-conta" class="linha-form" style="margin-top:10px">
      <label>Nome <input name="nome" required placeholder="Ex.: Nubank, Caixa, dinheiro"></label>
      <label>Tipo <select name="tipo"><option value="corrente">conta corrente</option><option value="poupanca">poupança</option><option value="carteira">dinheiro / carteira</option><option value="investimento">investimento</option><option value="cartao">cartão de crédito</option></select></label>
      <button class="btn">+ Conta</button>
    </form>
  </section>
  <section class="cartao">
    <div class="cartao-topo"><h2>Gastos e entradas fixos</h2></div>
    <p class="mudo pequeno" style="margin-top:0">Tudo que se repete todo mês: aluguel, internet, assinaturas, salário, mesada... Eles aparecem sozinhos em cada mês, a partir do mês em que foram criados; no mês você só confirma o valor e marca como pago.</p>
    <div class="tabela-rolagem"><table class="dados" id="lista-fixos"></table></div>
    <form id="novo-fixo" class="linha-form" style="margin-top:10px">
      <label style="flex:0 0 120px">Tipo <select name="tipo"><option value="despesa">gasto</option><option value="receita">entrada</option></select></label>
      <label style="flex:2 1 180px">Descrição <input name="desc" required placeholder="Ex.: Internet, Salário"></label>
      <label style="flex:0 1 110px">Valor <input name="valor" inputmode="decimal" placeholder="0,00"></label>
      <label style="flex:0 1 80px">Dia <input name="dia" type="number" min="1" max="31" value="10"></label>
      <label>Categoria <select name="cat"></select></label>
      <label>Conta <select name="conta"></select></label>
      <button class="btn">+ Fixo</button>
    </form>
  </section>
  <section class="cartao">
    <h2>Categorias</h2>
    <div class="chips" id="lista-cats"></div>
    <form id="nova-cat" class="grupo" style="margin-top:10px"><input name="nome" placeholder="Nova categoria" required style="max-width:240px"><button class="btn btn-p">+ Categoria</button></form>
  </section>
</div>

<script>
(function () {
  const CATS = ['Moradia', 'Contas da casa', 'Alimentação', 'Mercado', 'Transporte', 'Saúde', 'Educação', 'Lazer', 'Assinaturas', 'Compras', 'Igreja / doações', 'Outros', 'Salário', 'Renda extra'];
  const est = Object.assign({ contas: [], fixos: [], categorias: CATS.slice(), meses: {} }, <?= json_js($estado ?: new stdClass()) ?>);
  if (Array.isArray(est.meses)) est.meses = {};
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const TIPOS_CONTA = { corrente: 'conta corrente', poupanca: 'poupança', carteira: 'dinheiro', investimento: 'investimento', cartao: 'cartão de crédito' };
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const R = App.reais;
  const f2 = (n) => (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const p2 = (n) => String(n).padStart(2, '0');
  const id = () => Math.random().toString(36).slice(2, 9);
  const hoje = new Date();
  const hojeISO = `${hoje.getFullYear()}-${p2(hoje.getMonth() + 1)}-${p2(hoje.getDate())}`;
  const chave = (a, m) => `${a}-${p2(m + 1)}`;
  const kHoje = chave(hoje.getFullYear(), hoje.getMonth());
  let ano = hoje.getFullYear(), mes = hoje.getMonth();
  const kAtual = () => chave(ano, mes);
  const mesDe = (k) => (est.meses[k] = est.meses[k] || { fixos: {}, lanc: [], saldos: [] });
  const ler = (k) => est.meses[k] || { fixos: {}, lanc: [], saldos: [] };
  const nomeConta = (cid) => (est.contas.find((c) => c.id === cid) || {}).nome || '';
  const somaMes = (k, offset) => { const [y, m] = k.split('-').map(Number); const d = new Date(y, m - 1 + offset, 1); return chave(d.getFullYear(), d.getMonth()); };

  // ------------------------------------------------------------------ cálculo do mês
  function itens(k) {
    const dm = ler(k), [y, m] = k.split('-').map(Number), ultimo = new Date(y, m, 0).getDate();
    const out = [];
    for (const fx of est.fixos) {
      if (fx.desde && fx.desde > k) continue;
      if (fx.ate && fx.ate < k) continue;
      const r = (dm.fixos || {})[fx.id] || {};
      out.push({ origem: 'fixo', id: fx.id, desc: fx.desc, valor: r.v ?? fx.valor ?? 0, data: `${k}-${p2(Math.min(fx.dia || 1, ultimo))}`, tipo: fx.tipo, cat: fx.cat, conta: fx.conta, pago: !!r.pago, quando: r.data || null });
    }
    for (const l of dm.lanc || []) out.push({ origem: 'lanc', ...l, pago: true, quando: l.data });
    return out.sort((a, b) => a.data.localeCompare(b.data) || (a.tipo === 'receita' ? -1 : 1));
  }
  function totais(k) {
    const t = { rec: 0, desp: 0, fixo: 0, vari: 0, aPagar: 0, aReceber: 0, recFixa: 0 };
    for (const i of itens(k)) {
      const v = +i.valor || 0;
      if (i.tipo === 'receita') { t.rec += v; if (!i.pago) t.aReceber += v; if (i.origem === 'fixo') t.recFixa += v; }
      else { t.desp += v; if (i.origem === 'fixo') t.fixo += v; else t.vari += v; if (!i.pago) t.aPagar += v; }
    }
    t.saldo = t.rec - t.desp;
    return t;
  }
  // último saldo conhecido de uma conta até a data (inclusive)
  function saldoAte(contaId, dataISO) {
    let melhor = null;
    for (const [k, dm] of Object.entries(est.meses)) for (const s of dm.saldos || []) if (s.conta === contaId && s.data <= dataISO && (!melhor || s.data > melhor.data)) melhor = s;
    return melhor;
  }
  const sinalConta = (cid) => ((est.contas.find((c) => c.id === cid) || {}).tipo === 'cartao' ? -1 : 1); // fatura do cartão é dívida
  function patrimonio(dataISO) {
    let tot = 0, algum = false;
    for (const c of est.contas) { const s = saldoAte(c.id, dataISO); if (s) { tot += sinalConta(c.id) * s.valor; algum = true; } }
    return algum ? tot : null;
  }

  // ------------------------------------------------------------------ selects
  function preencherSelects() {
    const cats = est.categorias.map((c) => `<option>${esc(c)}</option>`).join('');
    const contas = '<option value="">—</option>' + est.contas.filter((c) => c.ativa !== false).map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
    for (const s of document.querySelectorAll('select[name=cat]')) { const v = s.value; s.innerHTML = cats; if (v) s.value = v; }
    for (const s of document.querySelectorAll('select[name=conta]')) { const v = s.value; s.innerHTML = contas; if (v) s.value = v; }
    $('#form-saldo').conta.innerHTML = est.contas.filter((c) => c.ativa !== false).map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
    // sugestões de descrição a partir do histórico (com categoria e conta usadas por último)
    const vistos = new Map();
    for (const dm of Object.values(est.meses)) for (const l of dm.lanc || []) vistos.set(l.desc.toLowerCase(), l);
    $('#sugestoes').innerHTML = [...vistos.values()].slice(-200).map((l) => `<option value="${esc(l.desc)}">`).join('');
    $('#lancar')._vistos = vistos;
  }

  // ------------------------------------------------------------------ aba Mês
  function desenharMes() {
    const k = kAtual(), t = totais(k);
    $('#titulo-mes').textContent = `${MESES[mes]} ${ano}`;
    const ehAtual = k === kHoje;
    const ini = `${k}-01`, fimMes = `${k}-${p2(new Date(ano, mes + 1, 0).getDate())}`;
    const pat = patrimonio(ehAtual ? hojeISO : fimMes);
    $('#resumo-mes').innerHTML = `
      <div><span class="rotulo">entradas</span><span class="valor estado-ok">${R(t.rec)}</span>${t.aReceber ? `<span class="mudo pequeno">${R(t.aReceber)} a receber</span>` : ''}</div>
      <div><span class="rotulo">gastos</span><span class="valor">${R(t.desp)}</span><span class="mudo pequeno">fixos ${R(t.fixo)} · avulsos ${R(t.vari)}</span></div>
      <div><span class="rotulo">sobra do mês</span><span class="valor ${t.saldo < 0 ? 'estado-ruim' : ''}">${R(t.saldo)}</span>${t.rec ? `<span class="mudo pequeno">${Math.round((t.saldo / t.rec) * 100)}% das entradas</span>` : ''}</div>
      <div><span class="rotulo">falta pagar</span><span class="valor ${t.aPagar ? 'estado-alerta' : ''}">${R(t.aPagar)}</span></div>
      <div><span class="rotulo">${ehAtual ? 'patrimônio hoje' : 'patrimônio no fim do mês'}</span><span class="valor">${pat === null ? '—' : R(pat)}</span></div>`;

    const filtro = $('#filtro').value;
    const lista = itens(k).filter((i) => !filtro || (filtro === 'fixo' ? i.origem === 'fixo' : filtro === 'var' ? i.origem === 'lanc' : filtro === 'pendente' ? !i.pago : i.tipo === filtro));
    const atrasado = (i) => !i.pago && i.tipo === 'despesa' && i.data < hojeISO;
    $('#tabela-mes').innerHTML = '<thead><tr><th style="width:34px" title="pago / recebido">ok</th><th>dia</th><th>descrição</th><th>categoria</th><th>conta</th><th class="num">valor (R$)</th><th></th></tr></thead><tbody>' +
      (lista.length ? lista.map((i) => `<tr data-o="${i.origem}" data-id="${i.id}">
        <td>${i.origem === 'fixo' ? `<input type="checkbox" data-k="pago" ${i.pago ? 'checked' : ''}>` : ''}</td>
        <td class="mudo">${i.data.slice(8)}/${i.data.slice(5, 7)}</td>
        <td class="${i.origem === 'fixo' && i.pago ? 'mudo' : ''}">${esc(i.desc)} ${i.origem === 'fixo' ? '<span class="tag">fixo</span>' : ''}${atrasado(i) ? ' <span class="estado-ruim pequeno">atrasado</span>' : ''}</td>
        <td class="mudo pequeno">${esc(i.cat)}</td>
        <td class="mudo pequeno">${esc(nomeConta(i.conta))}</td>
        <td class="num">${i.origem === 'fixo' ? `<input data-k="v" value="${f2(i.valor)}" inputmode="decimal" style="width:110px;text-align:right" class="${i.tipo === 'receita' ? 'estado-ok' : ''}">` : `<span class="${i.tipo === 'receita' ? 'estado-ok' : ''}">${i.tipo === 'receita' ? '+' : '−'} ${f2(i.valor)}</span>`}</td>
        <td class="num">${i.origem === 'lanc' ? '<button class="btn btn-p btn-leve" data-k="rm" title="Remover">×</button>' : ''}</td></tr>`).join('')
        : `<tr><td colspan="7" class="mudo">${est.fixos.length || Object.keys(est.meses).length ? 'Nada aqui.' : 'Comece cadastrando suas contas e seus gastos fixos na aba "Contas e fixos", e lance aqui o que for gastando.'}</td></tr>`) + '</tbody>';

    // saldos
    const dm = ler(k);
    $('#saldos').innerHTML = est.contas.length ? '<thead><tr><th>conta</th><th class="num">início do mês</th><th class="num">último registro</th><th class="num">variação</th><th class="num">lançado no mês</th><th class="num" title="variação real − o que foi lançado">diferença</th></tr></thead><tbody>' +
      est.contas.filter((c) => c.ativa !== false || (dm.saldos || []).some((s) => s.conta === c.id)).map((c) => {
        const antes = saldoAte(c.id, somaMesData(ini, -1));
        const doMes = (dm.saldos || []).filter((s) => s.conta === c.id).sort((a, b) => a.data.localeCompare(b.data));
        const inicio = doMes.length && doMes[0].data === ini ? doMes[0] : antes;
        const fim = doMes.length ? doMes[doMes.length - 1] : null;
        const sgn = sinalConta(c.id);
        // compara só o que foi lançado entre os dois registros de saldo
        const temPar = inicio && fim && fim.data > inicio.data;
        const lancado = temPar ? itens(k).filter((i) => i.conta === c.id && i.pago && i.quando && i.quando > inicio.data && i.quando <= fim.data).reduce((s, i) => s + (i.tipo === 'receita' ? 1 : -1) * i.valor, 0) * sgn : 0;
        const variacao = temPar ? fim.valor - inicio.valor : null;
        const difer = variacao !== null ? variacao - lancado : null;
        return `<tr><td>${esc(c.nome)} <span class="mudo pequeno">${TIPOS_CONTA[c.tipo] || ''}</span></td>
          <td class="num">${inicio ? f2(inicio.valor) + `<div class="mudo pequeno">${inicio.data.slice(8)}/${inicio.data.slice(5, 7)}</div>` : '<span class="mudo">—</span>'}</td>
          <td class="num">${fim ? `<strong>${f2(fim.valor)}</strong><div class="mudo pequeno">${fim.data.slice(8)}/${fim.data.slice(5, 7)} <a href="#" data-rms="${fim.id}" title="apagar este registro">×</a></div>` : '<span class="mudo">—</span>'}</td>
          <td class="num ${variacao > 0 ? 'estado-ok' : variacao < 0 ? 'estado-ruim' : ''}">${variacao === null ? '' : (variacao > 0 ? '+' : '') + f2(variacao)}</td>
          <td class="num mudo">${lancado ? (lancado > 0 ? '+' : '') + f2(lancado) : ''}</td>
          <td class="num ${difer !== null && Math.abs(difer) > 0.5 ? 'estado-alerta' : 'mudo'}">${difer === null ? '' : Math.abs(difer) <= 0.5 ? 'bate' : (difer > 0 ? '+' : '') + f2(difer)}</td></tr>`;
      }).join('') + '</tbody>' : '<tr><td class="mudo">Cadastre suas contas na aba "Contas e fixos".</td></tr>';
    const fs = $('#form-saldo');
    if (!fs.data.value || fs.data.value.slice(0, 7) !== k) fs.data.value = ehAtual ? hojeISO : fimMes;
    const fl = $('#lancar');
    if (!fl.data.value || fl.data.value.slice(0, 7) !== k) fl.data.value = ehAtual ? hojeISO : `${k}-01`;
  }
  const somaMesData = (iso, dias) => { const d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + dias); return `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`; };

  $('#tabela-mes').addEventListener('change', (e) => {
    const tr = e.target.closest('tr'), k = e.target.dataset.k;
    if (!tr || tr.dataset.o !== 'fixo') return;
    const fx = est.fixos.find((x) => x.id === tr.dataset.id), dm = mesDe(kAtual());
    const r = (dm.fixos[fx.id] = dm.fixos[fx.id] || { v: fx.valor || 0 });
    if (k === 'pago') { r.pago = e.target.checked; r.data = r.pago ? hojeISO : null; }
    if (k === 'v') r.v = App.numero(e.target.value) ?? 0;
    tudo(); salvar();
  });
  $('#tabela-mes').addEventListener('click', (e) => {
    if (e.target.dataset.k !== 'rm') return;
    const dm = mesDe(kAtual());
    dm.lanc = dm.lanc.filter((l) => l.id !== e.target.closest('tr').dataset.id);
    tudo(); salvar();
  });
  $('#saldos').addEventListener('click', (e) => {
    const sid = e.target.dataset.rms; if (!sid) return;
    e.preventDefault();
    if (!confirm('Apagar este registro de saldo?')) return;
    for (const dm of Object.values(est.meses)) dm.saldos = (dm.saldos || []).filter((s) => s.id !== sid);
    tudo(); salvar();
  });
  $('#lancar').desc.addEventListener('change', (e) => {
    const l = ($('#lancar')._vistos || new Map()).get(e.target.value.trim().toLowerCase());
    if (l) { const fm = $('#lancar'); fm.cat.value = l.cat; fm.conta.value = l.conta || ''; fm.tipo.value = l.tipo; }
  });
  $('#lancar').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target, v = App.numero(fm.valor.value);
    if (!v || v <= 0) return alert('Valor inválido.');
    const k = fm.data.value.slice(0, 7);
    mesDe(k).lanc.push({ id: id(), data: fm.data.value, desc: fm.desc.value.trim(), valor: v, tipo: fm.tipo.value, cat: fm.cat.value, conta: fm.conta.value });
    if (k !== kAtual()) { const [y, m] = k.split('-').map(Number); ano = y; mes = m - 1; }
    fm.desc.value = ''; fm.valor.value = ''; fm.desc.focus();
    tudo(); salvar();
  });
  $('#form-saldo').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target, v = App.numero(fm.valor.value);
    if (v === null) return alert('Valor inválido.');
    const dm = mesDe(fm.data.value.slice(0, 7));
    dm.saldos = (dm.saldos || []).filter((s) => !(s.conta === fm.conta.value && s.data === fm.data.value));
    dm.saldos.push({ id: id(), conta: fm.conta.value, data: fm.data.value, valor: v });
    fm.valor.value = '';
    tudo(); salvar();
  });
  $('#filtro').onchange = desenharMes;
  const mudar = (d) => { const x = new Date(ano, mes + d, 1); ano = x.getFullYear(); mes = x.getMonth(); tudo(); };
  $('#ant').onclick = () => mudar(-1);
  $('#prox').onclick = () => mudar(1);
  $('#hoje').onclick = () => { ano = hoje.getFullYear(); mes = hoje.getMonth(); tudo(); };

  // ------------------------------------------------------------------ aba Estatísticas
  function desenharStats() {
    const k = kAtual(), t = totais(k);
    const ultimos = []; for (let i = 11; i >= 0; i--) ultimos.push(somaMes(k, -i));
    const usados = ultimos.filter((x) => est.meses[x] || (x <= kHoje && est.fixos.some((f) => !f.desde || f.desde <= x)));
    const seis = ultimos.slice(-6).filter((x) => usados.includes(x) && x !== k);
    const media = (fn, ks) => (ks.length ? ks.reduce((s, x) => s + fn(x), 0) / ks.length : 0);
    const mediaDesp = media((x) => totais(x).desp, seis), mediaRec = media((x) => totais(x).rec, seis);

    // projeção do mês atual: avulsos no ritmo atual + todos os fixos
    let proj = '';
    if (k === kHoje && hoje.getDate() >= 5) {
      const dia = hoje.getDate(), dias = new Date(ano, mes + 1, 0).getDate();
      const varAteHoje = itens(k).filter((i) => i.origem === 'lanc' && i.tipo === 'despesa' && i.data <= hojeISO).reduce((s, i) => s + i.valor, 0);
      const projetado = t.fixo + (varAteHoje / dia) * dias;
      proj = `<div><span class="rotulo">projeção de gastos do mês</span><span class="valor">${R(projetado)}</span><span class="mudo pequeno">avulsos no ritmo de ${R(varAteHoje / dia)}/dia</span></div>`;
    }
    $('#resumo-ano').innerHTML = `
      <div><span class="rotulo">gasto médio (6 meses)</span><span class="valor">${R(mediaDesp)}</span>${mediaDesp ? `<span class="mudo pequeno">este mês: ${t.desp > mediaDesp ? '+' : ''}${Math.round(((t.desp - mediaDesp) / mediaDesp) * 100)}%</span>` : ''}</div>
      <div><span class="rotulo">entrada média (6 meses)</span><span class="valor">${R(mediaRec)}</span></div>
      <div><span class="rotulo">fixos comprometem</span><span class="valor ${t.recFixa && t.fixo / t.recFixa > 0.7 ? 'estado-ruim' : ''}">${t.recFixa ? Math.round((t.fixo / t.recFixa) * 100) + '%' : '—'}</span><span class="mudo pequeno">das entradas fixas</span></div>
      ${proj}`;

    // por categoria
    $('#cat-mes').textContent = `(${MESES[mes]})`;
    const porCat = {}, porCatMedia = {};
    for (const i of itens(k)) if (i.tipo === 'despesa') porCat[i.cat || 'Outros'] = (porCat[i.cat || 'Outros'] || 0) + +i.valor;
    for (const x of seis) for (const i of itens(x)) if (i.tipo === 'despesa') porCatMedia[i.cat || 'Outros'] = (porCatMedia[i.cat || 'Outros'] || 0) + +i.valor / seis.length;
    const cats = Object.entries(porCat).sort((a, b) => b[1] - a[1]);
    const maxC = Math.max(1, ...cats.map((c) => c[1]));
    $('#por-cat').innerHTML = cats.length ? '<thead><tr><th>categoria</th><th class="num">no mês</th><th style="width:30%"></th><th class="num">média</th></tr></thead><tbody>' + cats.map(([c, v]) => {
      const m = porCatMedia[c] || 0, acima = m && v > m * 1.2;
      return `<tr><td>${esc(c)}</td><td class="num">${f2(v)}<div class="mudo pequeno">${t.desp ? Math.round((v / t.desp) * 100) : 0}%</div></td><td><div class="barra-progresso ${acima ? 'ruim' : ''}"><span style="width:${(v / maxC) * 100}%"></span></div></td><td class="num ${acima ? 'estado-ruim' : 'mudo'}">${m ? f2(m) : '—'}</td></tr>`;
    }).join('') + '</tbody>' : '<tr><td class="mudo">Sem gastos neste mês.</td></tr>';

    const top = itens(k).filter((i) => i.tipo === 'despesa').sort((a, b) => b.valor - a.valor).slice(0, 6);
    $('#maiores').innerHTML = top.map((i) => `<tr><td>${esc(i.desc)} <span class="mudo pequeno">${esc(i.cat)}</span></td><td class="num">${f2(i.valor)}</td></tr>`).join('') || '<tr><td class="mudo">—</td></tr>';
    const pf = t.desp ? (t.fixo / t.desp) * 100 : 0;
    $('#fixo-var').innerHTML = t.desp ? `<div class="barra-progresso" style="height:10px"><span style="width:${pf}%"></span></div>
      <p class="pequeno" style="margin:6px 0 0"><span class="estado-ok">fixos ${Math.round(pf)}% (${R(t.fixo)})</span> · <span class="mudo">avulsos ${Math.round(100 - pf)}% (${R(t.vari)})</span></p>` : '<p class="mudo">—</p>';

    // 12 meses
    const linhas = ultimos.map((x) => [x, usados.includes(x) ? totais(x) : null]);
    const maxV = Math.max(1, ...linhas.filter((l) => l[1]).map((l) => Math.max(l[1].rec, l[1].desp)));
    $('#doze').innerHTML = '<thead><tr><th>mês</th><th class="num">entradas</th><th class="num">gastos</th><th class="num">sobra</th><th class="num">guardou</th><th style="width:30%">entradas / gastos</th></tr></thead><tbody>' + linhas.map(([x, tt]) => {
      const [y, m] = x.split('-').map(Number), nome = `${MESES[m - 1].slice(0, 3)}/${String(y).slice(2)}`;
      if (!tt) return `<tr><td class="mudo">${nome}</td><td colspan="5" class="mudo pequeno">sem registros</td></tr>`;
      return `<tr${x === k ? ' style="font-weight:600"' : ''}><td>${nome}</td><td class="num estado-ok">${f2(tt.rec)}</td><td class="num">${f2(tt.desp)}</td><td class="num ${tt.saldo < 0 ? 'estado-ruim' : ''}">${f2(tt.saldo)}</td><td class="num mudo">${tt.rec ? Math.round((tt.saldo / tt.rec) * 100) + '%' : ''}</td>
        <td><div class="barra-progresso" style="margin-bottom:3px"><span style="width:${(tt.rec / maxV) * 100}%"></span></div><div class="barra-progresso ruim"><span style="width:${(tt.desp / maxV) * 100}%"></span></div></td></tr>`;
    }).join('') + '</tbody>';

    // patrimônio
    const pats = ultimos.map((x) => { const [y, m] = x.split('-').map(Number); return [x, patrimonio(`${x}-${p2(new Date(y, m, 0).getDate())}`)]; });
    const comValor = pats.filter((p) => p[1] !== null);
    $('#patrimonio').innerHTML = comValor.length ? '<thead><tr><th>mês</th><th class="num">patrimônio</th><th class="num">variação</th></tr></thead><tbody>' + comValor.map(([x, v], i) => {
      const [y, m] = x.split('-').map(Number), d = i ? v - comValor[i - 1][1] : null;
      return `<tr><td>${MESES[m - 1].slice(0, 3)}/${String(y).slice(2)}</td><td class="num">${f2(v)}</td><td class="num ${d > 0 ? 'estado-ok' : d < 0 ? 'estado-ruim' : 'mudo'}">${d === null ? '' : (d > 0 ? '+' : '') + f2(d)}</td></tr>`;
    }).join('') + '</tbody>' : '<tr><td class="mudo">Registre saldos das contas (aba Mês) para acompanhar o patrimônio.</td></tr>';
  }

  // ------------------------------------------------------------------ aba Geral
  function desenharGeral() {
    $('#lista-contas').innerHTML = est.contas.length ? '<thead><tr><th>conta</th><th>tipo</th><th class="num">último saldo</th><th></th></tr></thead><tbody>' + est.contas.map((c) => {
      const s = saldoAte(c.id, '9999-12-31');
      return `<tr data-id="${c.id}"><td><input data-k="nome" value="${esc(c.nome)}"></td>
      <td style="width:200px"><select data-k="tipo">${Object.entries(TIPOS_CONTA).map(([v, n]) => `<option value="${v}" ${c.tipo === v ? 'selected' : ''}>${n}</option>`).join('')}</select></td>
      <td class="num">${s ? f2(s.valor) + `<div class="mudo pequeno">${s.data.split('-').reverse().join('/')}</div>` : '<span class="mudo">—</span>'}</td>
      <td class="num"><label class="check pequeno"><input type="checkbox" data-k="ativa" ${c.ativa !== false ? 'checked' : ''}> em uso</label></td></tr>`;
    }).join('') + '</tbody>' : '<tr><td class="mudo">Nenhuma conta ainda.</td></tr>';

    const cats = est.categorias.map((c) => `<option ${''}>${esc(c)}</option>`).join('');
    const contas = '<option value="">—</option>' + est.contas.map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
    const ordem = est.fixos.slice().sort((a, b) => (a.tipo === b.tipo ? (a.dia || 0) - (b.dia || 0) : a.tipo === 'receita' ? -1 : 1));
    $('#lista-fixos').innerHTML = ordem.length ? '<thead><tr><th>descrição</th><th class="num">valor</th><th class="num">dia</th><th>categoria</th><th>conta</th><th>período</th><th></th></tr></thead><tbody>' + ordem.map((fx) => `<tr data-id="${fx.id}" class="${fx.ate && fx.ate < kHoje ? 'mudo' : ''}">
      <td><input data-k="desc" value="${esc(fx.desc)}"><span class="pequeno ${fx.tipo === 'receita' ? 'estado-ok' : 'mudo'}">${fx.tipo === 'receita' ? 'entrada' : 'gasto'}</span></td>
      <td class="num" style="width:110px"><input data-k="valor" value="${f2(fx.valor)}" inputmode="decimal" style="text-align:right"></td>
      <td class="num" style="width:70px"><input data-k="dia" type="number" min="1" max="31" value="${fx.dia}"></td>
      <td style="width:150px"><select data-k="cat">${cats.replace(`<option >${esc(fx.cat)}</option>`, `<option selected>${esc(fx.cat)}</option>`)}</select></td>
      <td style="width:130px"><select data-k="conta">${contas.replace(`value="${fx.conta}"`, `value="${fx.conta}" selected`)}</select></td>
      <td class="pequeno mudo" style="white-space:nowrap">desde ${fx.desde ? fx.desde.split('-').reverse().join('/') : 'sempre'}${fx.ate ? `<br>até ${fx.ate.split('-').reverse().join('/')}` : ''}</td>
      <td class="num" style="white-space:nowrap">${fx.ate ? '<button class="btn btn-p" data-k="reativar">reativar</button>' : '<button class="btn btn-p" data-k="encerrar" title="Para de aparecer a partir do próximo mês; o histórico fica">encerrar</button>'}
        <button class="btn btn-p btn-leve" data-k="rm" title="Excluir de todos os meses">×</button></td></tr>`).join('') + '</tbody>' : '<tr><td class="mudo">Nenhum fixo ainda.</td></tr>';

    $('#lista-cats').innerHTML = est.categorias.map((c) => `<span class="tag" style="font-size:.8rem;text-transform:none;padding:2px 8px">${esc(c)} <a href="#" data-rmc="${esc(c)}" style="text-decoration:none;margin-left:4px" title="Remover">×</a></span>`).join('');
  }
  $('#lista-contas').addEventListener('change', (e) => {
    const c = est.contas.find((x) => x.id === e.target.closest('tr').dataset.id), k = e.target.dataset.k;
    if (k === 'ativa') c.ativa = e.target.checked; else c[k] = e.target.value.trim();
    tudo(); salvar();
  });
  $('#nova-conta').addEventListener('submit', (e) => {
    e.preventDefault();
    est.contas.push({ id: id(), nome: e.target.nome.value.trim(), tipo: e.target.tipo.value, ativa: true });
    e.target.reset(); tudo(); salvar();
  });
  $('#lista-fixos').addEventListener('change', (e) => {
    const fx = est.fixos.find((x) => x.id === e.target.closest('tr').dataset.id), k = e.target.dataset.k;
    if (k === 'valor') fx.valor = App.numero(e.target.value) ?? 0;
    else if (k === 'dia') fx.dia = Math.max(1, Math.min(31, +e.target.value || 1));
    else fx[k] = e.target.value;
    tudo(); salvar();
  });
  $('#lista-fixos').addEventListener('click', (e) => {
    const k = e.target.dataset.k; if (!k) return;
    const fx = est.fixos.find((x) => x.id === e.target.closest('tr').dataset.id);
    if (k === 'encerrar') fx.ate = kHoje;
    else if (k === 'reativar') fx.ate = null;
    else if (k === 'rm') {
      if (!confirm(`Excluir "${fx.desc}" de TODOS os meses, inclusive os passados? (Para só parar daqui pra frente, use "encerrar".)`)) return;
      est.fixos = est.fixos.filter((x) => x !== fx);
      for (const dm of Object.values(est.meses)) if (dm.fixos) delete dm.fixos[fx.id];
    } else return;
    tudo(); salvar();
  });
  $('#novo-fixo').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target;
    est.fixos.push({ id: id(), desc: fm.desc.value.trim(), valor: App.numero(fm.valor.value) ?? 0, dia: +fm.dia.value || 1, tipo: fm.tipo.value, cat: fm.cat.value, conta: fm.conta.value, desde: kAtual() < kHoje ? kAtual() : kHoje, ate: null });
    fm.desc.value = ''; fm.valor.value = '';
    tudo(); salvar();
  });
  $('#lista-cats').addEventListener('click', (e) => {
    const c = e.target.dataset.rmc; if (!c) return;
    e.preventDefault();
    est.categorias = est.categorias.filter((x) => x !== c); tudo(); salvar();
  });
  $('#nova-cat').addEventListener('submit', (e) => {
    e.preventDefault();
    const n = e.target.nome.value.trim();
    if (n && !est.categorias.includes(n)) est.categorias.push(n);
    e.target.reset(); tudo(); salvar();
  });

  // ------------------------------------------------------------------ abas
  let aba = 'mes';
  document.querySelectorAll('#abas [data-aba]').forEach((b) => (b.onclick = () => {
    aba = b.dataset.aba;
    document.querySelectorAll('#abas [data-aba]').forEach((x) => x.classList.toggle('btn-primario', x === b));
    document.querySelectorAll('[data-painel]').forEach((p) => (p.hidden = p.dataset.painel !== aba));
    tudo();
  }));
  function tudo() {
    preencherSelects();
    if (aba === 'mes') desenharMes();
    if (aba === 'stats') desenharStats();
    if (aba === 'geral') desenharGeral();
  }
  tudo();
})();
</script>
<?php rodape(); ?>
