<?php
/** Simuladores de juros. Tudo calculado no navegador. */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<div class="grupo" style="margin-bottom:16px" id="abas">
  <button class="btn btn-primario" data-aba="fin">Financiamento / empréstimo</button>
  <button class="btn" data-aba="inv">Investimento</button>
  <button class="btn" data-aba="taxa">Converter taxa</button>
  <button class="btn" data-aba="vista">À vista ou parcelado?</button>
</div>

<div data-painel="fin">
  <section class="cartao">
    <h2>Financiamento</h2>
    <div class="form-grade" id="fin">
      <label>Valor financiado (R$) <input name="valor" inputmode="decimal" value="10.000,00"></label>
      <label>Taxa de juros (%) <input name="taxa" inputmode="decimal" value="1,99"></label>
      <label>Taxa por <select name="per"><option value="m">mês</option><option value="a">ano</option></select></label>
      <label>Número de parcelas <input name="n" type="number" min="1" max="600" value="24"></label>
      <label>Sistema <select name="sis"><option value="price">Price (parcelas iguais)</option><option value="sac">SAC (parcelas decrescentes)</option></select></label>
    </div>
  </section>
  <div class="resumo" id="fin-res"></div>
  <section class="cartao">
    <div class="cartao-topo"><h2>Parcelas</h2><button class="btn btn-p" id="fin-copiar">Copiar tabela</button></div>
    <div class="tabela-rolagem" style="max-height:420px;overflow:auto"><table class="dados" id="fin-tab"></table></div>
  </section>
</div>

<div data-painel="inv" hidden>
  <section class="cartao">
    <h2>Investimento com aportes</h2>
    <div class="form-grade" id="inv">
      <label>Valor inicial (R$) <input name="ini" inputmode="decimal" value="1.000,00"></label>
      <label>Aporte mensal (R$) <input name="aporte" inputmode="decimal" value="200,00"></label>
      <label>Rendimento (%) <input name="taxa" inputmode="decimal" value="10,5"></label>
      <label>Rendimento por <select name="per"><option value="a">ano</option><option value="m">mês</option></select></label>
      <label>Tempo (meses) <input name="n" type="number" min="1" max="720" value="60"></label>
      <label>Imposto de renda <select name="ir"><option value="tab">tabela regressiva (CDB, Tesouro)</option><option value="0">isento (poupança, LCI/LCA)</option></select></label>
    </div>
  </section>
  <div class="resumo" id="inv-res"></div>
  <section class="cartao"><h2>Evolução por ano</h2><div class="tabela-rolagem"><table class="dados" id="inv-tab"></table></div></section>
</div>

<div data-painel="taxa" hidden>
  <section class="cartao">
    <h2>Taxa equivalente</h2>
    <div class="form-grade" id="taxa">
      <label>Taxa (%) <input name="t" inputmode="decimal" value="1"></label>
      <label>É uma taxa <select name="de"><option value="m">ao mês</option><option value="a">ao ano</option><option value="d">ao dia (útil, 252/ano)</option></select></label>
    </div>
    <div class="resumo" id="taxa-res" style="margin-top:14px"></div>
    <p class="dica">Juros compostos: 1% ao mês não é 12% ao ano, é 12,68%. Para comparar com a Selic/CDI (que são anuais), converta a taxa mensal aqui.</p>
  </section>
</div>

<div data-painel="vista" hidden>
  <section class="cartao">
    <h2>Desconto à vista × parcelado sem juros</h2>
    <div class="form-grade" id="vista">
      <label>Preço parcelado (total) <input name="preco" inputmode="decimal" value="1.200,00"></label>
      <label>Número de parcelas <input name="n" type="number" min="1" value="10"></label>
      <label>Preço à vista <input name="avista" inputmode="decimal" value="1.080,00"></label>
      <label>Seu dinheiro rende (% ao mês) <input name="rende" inputmode="decimal" value="0,85"></label>
    </div>
    <div id="vista-res" style="margin-top:14px"></div>
    <p class="dica">Compara o desconto com o quanto seu dinheiro renderia se ficasse aplicado pagando as parcelas aos poucos (1ª parcela no ato da compra).</p>
  </section>
</div>

<script>
(function () {
  const $ = (s) => document.querySelector(s);
  const num = (v) => App.numero(v) ?? 0;
  const R = App.reais;
  const pct = (n, d = 2) => (n * 100).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d }) + '%';
  const f2 = (n) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const val = (form, n) => form.querySelector(`[name=${n}]`).value;
  const mensal = (t, per) => (per === 'a' ? Math.pow(1 + t, 1 / 12) - 1 : t);

  document.querySelectorAll('#abas [data-aba]').forEach((b) => (b.onclick = () => {
    document.querySelectorAll('#abas [data-aba]').forEach((x) => x.classList.toggle('btn-primario', x === b));
    document.querySelectorAll('[data-painel]').forEach((p) => (p.hidden = p.dataset.painel !== b.dataset.aba));
  }));

  let tabelaTxt = '';
  function financiamento() {
    const F = $('#fin');
    const pv = num(val(F, 'valor')), i = mensal(num(val(F, 'taxa')) / 100, val(F, 'per')), n = Math.max(1, Math.min(600, +val(F, 'n') || 1)), sac = val(F, 'sis') === 'sac';
    let saldo = pv, totJ = 0, tot = 0, linhas = [];
    const pmt = i ? (pv * i) / (1 - Math.pow(1 + i, -n)) : pv / n;
    for (let k = 1; k <= n; k++) {
      const j = saldo * i;
      const amort = sac ? pv / n : pmt - j;
      const parc = amort + j;
      saldo = Math.max(0, saldo - amort);
      totJ += j; tot += parc;
      linhas.push([k, parc, j, amort, saldo]);
    }
    $('#fin-res').innerHTML = `<div><span class="rotulo">${sac ? '1ª parcela → última' : 'parcela'}</span><span class="valor">${sac ? R(linhas[0][1]) + ' → ' + R(linhas[n - 1][1]) : R(pmt)}</span></div>
      <div><span class="rotulo">total pago</span><span class="valor">${R(tot)}</span></div>
      <div><span class="rotulo">total de juros</span><span class="valor estado-ruim">${R(totJ)}</span></div>
      <div><span class="rotulo">juros ao ano (efetivo)</span><span class="valor">${pct(Math.pow(1 + i, 12) - 1)}</span></div>`;
    $('#fin-tab').innerHTML = '<thead><tr><th class="num">nº</th><th class="num">parcela</th><th class="num">juros</th><th class="num">amortização</th><th class="num">saldo devedor</th></tr></thead><tbody>' +
      linhas.map((l) => `<tr><td class="num mudo">${l[0]}</td><td class="num"><strong>${f2(l[1])}</strong></td><td class="num">${f2(l[2])}</td><td class="num">${f2(l[3])}</td><td class="num mudo">${f2(l[4])}</td></tr>`).join('') + '</tbody>';
    tabelaTxt = 'Nº\tParcela\tJuros\tAmortização\tSaldo\n' + linhas.map((l) => [l[0], f2(l[1]), f2(l[2]), f2(l[3]), f2(l[4])].join('\t')).join('\n');
  }
  $('#fin-copiar').onclick = (e) => App.copiar(tabelaTxt, e.target);

  const aliquotaIR = (meses) => (meses <= 6 ? 0.225 : meses <= 12 ? 0.2 : meses <= 24 ? 0.175 : 0.15);
  function investimento() {
    const F = $('#inv');
    const ini = num(val(F, 'ini')), ap = num(val(F, 'aporte')), i = mensal(num(val(F, 'taxa')) / 100, val(F, 'per')), n = Math.max(1, Math.min(720, +val(F, 'n') || 1)), ir = val(F, 'ir') === 'tab';
    let saldo = ini, investido = ini;
    const anos = [];
    for (let k = 1; k <= n; k++) {
      saldo = saldo * (1 + i) + ap;
      investido += ap;
      if (k % 12 === 0 || k === n) anos.push([k, investido, saldo]);
    }
    const lucro = saldo - investido;
    const imposto = ir ? lucro * aliquotaIR(n) : 0; // aproximação: alíquota pelo prazo total
    $('#inv-res').innerHTML = `<div><span class="rotulo">valor final (bruto)</span><span class="valor">${R(saldo)}</span></div>
      <div><span class="rotulo">total investido</span><span class="valor">${R(investido)}</span></div>
      <div><span class="rotulo">rendimento</span><span class="valor estado-ok">${R(lucro)}</span></div>
      <div><span class="rotulo">${ir ? `líquido (IR ${pct(aliquotaIR(n), 1)} sobre o lucro)` : 'líquido (isento)'}</span><span class="valor">${R(saldo - imposto)}</span></div>`;
    $('#inv-tab').innerHTML = '<thead><tr><th>após</th><th class="num">investido</th><th class="num">saldo</th><th class="num">rendimento</th></tr></thead><tbody>' +
      anos.map(([k, inv, s]) => `<tr><td>${k % 12 === 0 ? k / 12 + ' ano' + (k > 12 ? 's' : '') : k + ' meses'}</td><td class="num">${f2(inv)}</td><td class="num"><strong>${f2(s)}</strong></td><td class="num estado-ok">${f2(s - inv)}</td></tr>`).join('') + '</tbody>';
  }
  function taxa() {
    const F = $('#taxa');
    const t = num(val(F, 't')) / 100, de = val(F, 'de');
    const a = de === 'a' ? t : de === 'm' ? Math.pow(1 + t, 12) - 1 : Math.pow(1 + t, 252) - 1;
    const m = Math.pow(1 + a, 1 / 12) - 1, d = Math.pow(1 + a, 1 / 252) - 1;
    $('#taxa-res').innerHTML = `<div><span class="rotulo">ao mês</span><span class="valor">${pct(m, 4)}</span></div><div><span class="rotulo">ao ano</span><span class="valor">${pct(a, 2)}</span></div><div><span class="rotulo">ao dia útil</span><span class="valor">${pct(d, 5)}</span></div>`;
  }
  function vista() {
    const F = $('#vista');
    const preco = num(val(F, 'preco')), n = Math.max(1, +val(F, 'n') || 1), av = num(val(F, 'avista')), r = num(val(F, 'rende')) / 100;
    const parc = preco / n;
    let vp = 0; for (let k = 0; k < n; k++) vp += parc / Math.pow(1 + r, k); // 1ª no ato
    const desc = 1 - av / preco;
    const melhor = av < vp;
    $('#vista-res').innerHTML = `<div class="resumo"><div><span class="rotulo">parcela</span><span class="valor">${n}× ${R(parc)}</span></div>
      <div><span class="rotulo">desconto à vista</span><span class="valor">${pct(desc, 1)}</span></div>
      <div><span class="rotulo">parcelado vale hoje</span><span class="valor">${R(vp)}</span></div></div>
      <p class="${melhor ? 'estado-ok' : 'estado-alerta'}" style="margin:0">${melhor ? `Compensa pagar à vista: você economiza ${R(vp - av)} em relação a deixar o dinheiro rendendo.` : `Compensa parcelar e deixar o dinheiro rendendo: ganha ${R(av - vp)}.`}</p>`;
  }
  const calc = { fin: financiamento, inv: investimento, taxa, vista };
  for (const [id, fn] of Object.entries(calc)) { $('#' + id).addEventListener('input', fn); $('#' + id).addEventListener('change', fn); fn(); }
})();
</script>
<?php rodape(); ?>
