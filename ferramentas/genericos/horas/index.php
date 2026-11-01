<?php
/** Calculadora de horas. Tudo no navegador. */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<div class="colunas">
  <section class="cartao">
    <h2>Somar horas</h2>
    <p class="mudo pequeno">Uma duração por linha: <code>1:30</code>, <code>45min</code>, <code>2h</code>, <code>1,5</code> (horas decimais). Comece com <code>-</code> para subtrair.</p>
    <textarea id="lista" rows="10" placeholder="8:00&#10;7:45&#10;-0:30&#10;2h15"></textarea>
    <div class="resumo" style="margin-top:12px" id="soma-res"></div>
  </section>
  <section class="cartao">
    <h2>Entrada e saída</h2>
    <p class="mudo pequeno">Horas trabalhadas no dia, descontando intervalos. Se a saída for menor que a entrada, conta como virada da meia-noite.</p>
    <table class="dados" id="ponto"><thead><tr><th>entrada</th><th>saída</th><th class="num">duração</th><th></th></tr></thead><tbody></tbody></table>
    <div class="grupo" style="margin-top:8px"><button class="btn btn-p" id="mais">+ Período</button></div>
    <div class="resumo" style="margin-top:12px" id="ponto-res"></div>
    <label style="max-width:220px">Jornada esperada <input id="jornada" value="8:00"></label>
  </section>
</div>
<section class="cartao">
  <h2>Converter</h2>
  <div class="linha-form">
    <label>hh:mm <input id="c-hhmm" value="7:45"></label>
    <label>horas decimais <input id="c-dec" value="7,75"></label>
    <label>minutos <input id="c-min" value="465"></label>
  </div>
</section>
<script>
(function () {
  const $ = (s) => document.querySelector(s);
  // devolve minutos (pode ser negativo) ou null
  function lerDuracao(s) {
    s = String(s).trim().toLowerCase().replace(/\s+/g, '');
    if (!s) return null;
    let neg = false;
    if (s[0] === '-' || s[0] === '−') { neg = true; s = s.slice(1); } else if (s[0] === '+') s = s.slice(1);
    let m = null, r;
    if ((r = s.match(/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/))) m = +r[1] * 60 + +r[2] + (r[3] ? +r[3] / 60 : 0);
    else if ((r = s.match(/^(\d+(?:[.,]\d+)?)h(?:(\d{1,2})(?:m|min)?)?$/))) m = parseFloat(r[1].replace(',', '.')) * 60 + (r[2] ? +r[2] : 0);
    else if ((r = s.match(/^(\d+)(?:m|min)$/))) m = +r[1];
    else if ((r = s.match(/^\d+(?:[.,]\d+)?$/))) m = parseFloat(s.replace(',', '.')) * 60;
    return m === null ? null : neg ? -m : m;
  }
  const hhmm = (min) => { const neg = min < 0; min = Math.round(Math.abs(min)); return (neg ? '-' : '') + Math.floor(min / 60) + ':' + String(min % 60).padStart(2, '0'); };
  const dec = (min) => (min / 60).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

  function somar() {
    let tot = 0, ruins = 0;
    $('#lista').value.split('\n').forEach((l) => { if (!l.trim()) return; const m = lerDuracao(l); if (m === null) ruins++; else tot += m; });
    $('#soma-res').innerHTML = `<div><span class="rotulo">total</span><span class="valor">${hhmm(tot)}</span></div><div><span class="rotulo">em horas decimais</span><span class="valor">${dec(tot)}</span></div>` +
      (ruins ? `<div><span class="rotulo">linhas não entendidas</span><span class="valor estado-ruim">${ruins}</span></div>` : '');
  }
  $('#lista').addEventListener('input', somar);

  const corpo = $('#ponto tbody');
  const linha = (e = '', s = '') => { const tr = document.createElement('tr'); tr.innerHTML = `<td><input type="time" value="${e}"></td><td><input type="time" value="${s}"></td><td class="num"></td><td class="num"><button class="btn btn-p btn-leve" title="Remover">×</button></td>`; corpo.appendChild(tr); };
  function ponto() {
    let tot = 0;
    corpo.querySelectorAll('tr').forEach((tr) => {
      const [e, s] = [...tr.querySelectorAll('input')].map((i) => lerDuracao(i.value));
      let d = e !== null && s !== null ? s - e : null;
      if (d !== null && d < 0) d += 24 * 60;
      tr.children[2].textContent = d === null ? '' : hhmm(d);
      if (d) tot += d;
    });
    const jor = lerDuracao($('#jornada').value) || 0, saldo = tot - jor;
    $('#ponto-res').innerHTML = `<div><span class="rotulo">trabalhado</span><span class="valor">${hhmm(tot)}</span></div>
      <div><span class="rotulo">${saldo >= 0 ? 'hora extra' : 'faltando'}</span><span class="valor ${saldo >= 0 ? 'estado-ok' : 'estado-ruim'}">${hhmm(Math.abs(saldo))}</span></div>`;
  }
  corpo.addEventListener('input', ponto);
  corpo.addEventListener('click', (e) => { if (e.target.tagName === 'BUTTON') { e.target.closest('tr').remove(); ponto(); } });
  $('#mais').onclick = () => { linha(); ponto(); };
  $('#jornada').addEventListener('input', ponto);
  linha('08:00', '12:00'); linha('13:00', '17:00');

  const conv = (de) => {
    let m = null;
    if (de === 'hhmm') m = lerDuracao($('#c-hhmm').value);
    if (de === 'dec') m = lerDuracao(($('#c-dec').value || '').replace(/h$/, ''));
    if (de === 'min') m = parseFloat($('#c-min').value);
    if (m === null || isNaN(m)) return;
    if (de !== 'hhmm') $('#c-hhmm').value = hhmm(m);
    if (de !== 'dec') $('#c-dec').value = dec(m);
    if (de !== 'min') $('#c-min').value = Math.round(m);
  };
  $('#c-hhmm').addEventListener('input', () => conv('hhmm'));
  $('#c-dec').addEventListener('input', () => conv('dec'));
  $('#c-min').addEventListener('input', () => conv('min'));
  somar(); ponto();
})();
</script>
<?php rodape(); ?>
