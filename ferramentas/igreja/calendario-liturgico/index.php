<?php
/**
 * Calendário litúrgico (rito romano, com as transferências usadas no Brasil:
 * Epifania, Ascensão, São Pedro e São Paulo, Assunção e Todos os Santos vão para o domingo).
 * Tudo é calculado no navegador; nada é gravado.
 */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<section class="cartao">
  <div class="cartao-topo">
    <h2>Dia</h2>
    <div class="grupo"><input type="date" id="dia" style="width:auto"><button class="btn btn-leve" id="hoje">hoje</button></div>
  </div>
  <div id="info-dia"></div>
</section>
<div class="colunas">
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Datas do ano</h2>
      <div class="grupo"><button class="btn btn-p" id="ano-ant">←</button><strong id="ano-txt"></strong><button class="btn btn-p" id="ano-prox">→</button></div>
    </div>
    <table class="dados" id="moveis"></table>
  </section>
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Domingos e solenidades</h2>
      <div class="grupo">
        <select id="mes" style="width:auto"></select>
        <button class="btn btn-p" id="copiar">Copiar</button>
      </div>
    </div>
    <table class="dados" id="domingos"></table>
  </section>
</div>
<p class="dica">Calculado pelas normas do calendário romano com as transferências da CNBB. Memórias de santos e casos especiais de cada diocese não aparecem; para a liturgia do dia completa, confira o Diretório da Liturgia da CNBB.</p>
<script src="assets/liturgia.js?v=<?= filemtime(RAIZ . '/assets/liturgia.js') ?>"></script>
<script>
(function () {
  const $ = (s) => document.querySelector(s);
  const { info, ano, D, add, dow, igual, CORES, SEMANA, MESES } = window.Liturgia;
  const fmt = (d) => `${String(d.getUTCDate()).padStart(2, '0')}/${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
  const fmtLongo = (d) => `${d.getUTCDate()} de ${MESES[d.getUTCMonth()]} de ${d.getUTCFullYear()}`;

  // ------------------------------------------------------------------ interface
  const hojeUTC = () => { const t = new Date(); return D(t.getFullYear(), t.getMonth() + 1, t.getDate()); };
  const quadrado = (c) => `<span class="cor-lit" style="background:${CORES[c]}"></span>`;
  function mostrarDia(d) {
    const i = info(d);
    $('#dia').value = d.toISOString().slice(0, 10);
    $('#info-dia').innerHTML = `<div class="resumo">
      <div><span class="rotulo">${SEMANA[dow(d)].toLowerCase()}, ${fmtLongo(d)}</span><span class="valor" style="font-size:1.05rem">${i.nome}</span>${i.base ? `<span class="mudo pequeno">no lugar de: ${i.base}</span>` : ''}</div>
      <div><span class="rotulo">tempo litúrgico</span><span class="valor">${i.tempo}</span></div>
      <div><span class="rotulo">cor</span><span class="valor">${quadrado(i.cor)}${i.cor}</span></div>
      <div><span class="rotulo">ano litúrgico ${i.anoLit}</span><span class="valor">ciclo ${i.ciclo} · ${i.par.split(' ')[0]}</span><span class="mudo pequeno">domingos: ano ${i.ciclo} · semana: ano ${i.par}</span></div></div>`;
  }
  let anoSel = hojeUTC().getUTCFullYear();
  function mostrarAno() {
    const k = ano(anoSel);
    $('#ano-txt').textContent = anoSel;
    const linhas = [
      ['Epifania do Senhor', k.epif], ['Batismo do Senhor', k.batismo], ['Quarta-feira de Cinzas', k.cinzas], ['Domingo de Ramos', k.ramos],
      ['Páscoa', k.p], ['Ascensão do Senhor', add(k.p, 42)], ['Pentecostes', k.pentecostes], ['Santíssima Trindade', add(k.p, 56)],
      ['Corpus Christi', add(k.p, 60)], ['Cristo Rei', k.cristoRei], ['1º Domingo do Advento', k.adv],
    ];
    $('#moveis').innerHTML = linhas.map(([n, d]) => `<tr><td>${n}</td><td class="num">${fmt(d)}</td><td class="mudo">${SEMANA[dow(d)].toLowerCase()}</td></tr>`).join('') +
      `<tr><td colspan="3" class="mudo pequeno">Até o 1º domingo do Advento: ano ${info(add(k.adv, -1)).ciclo}. Depois: ano ${info(k.adv).ciclo}.</td></tr>`;
    mostrarDomingos();
  }
  function listaDoMes(m) {
    const out = [];
    for (let d = D(anoSel, m + 1, 1); d.getUTCMonth() === m; d = add(d, 1)) {
      const i = info(d);
      const fest = ano(anoSel).festas.find((x) => igual(x[0], d));
      if (i.domingo || (fest && fest[3] !== 'F' && i.nome === fest[1]) || (fest && i.nome === fest[1] && fest[3] === 'F')) out.push(i);
    }
    return out;
  }
  function mostrarDomingos() {
    const m = +$('#mes').value;
    const l = listaDoMes(m);
    $('#domingos').innerHTML = l.map((i) => `<tr><td class="num" style="width:56px">${fmt(i.d)}</td><td>${i.nome}${i.domingo ? ` <span class="mudo pequeno">(ano ${i.ciclo})</span>` : ` <span class="mudo pequeno">(${SEMANA[dow(i.d)].toLowerCase()})</span>`}</td><td style="width:110px">${quadrado(i.cor)}${i.cor}</td></tr>`).join('');
  }
  $('#mes').innerHTML = MESES.map((n, i) => `<option value="${i}">${n}</option>`).join('');
  $('#mes').value = hojeUTC().getUTCMonth();
  $('#mes').onchange = mostrarDomingos;
  $('#copiar').onclick = (e) => {
    const m = +$('#mes').value;
    const txt = `*Domingos e solenidades — ${MESES[m]} de ${anoSel}*\n` + listaDoMes(m).map((i) => `${fmt(i.d)} – ${i.nome} (${i.cor})`).join('\n');
    App.copiar(txt, e.target);
  };
  $('#ano-ant').onclick = () => { anoSel--; mostrarAno(); };
  $('#ano-prox').onclick = () => { anoSel++; mostrarAno(); };
  $('#dia').onchange = (e) => { if (e.target.value) { const [y, m, d] = e.target.value.split('-').map(Number); mostrarDia(D(y, m, d)); } };
  $('#hoje').onclick = () => mostrarDia(hojeUTC());
  mostrarDia(hojeUTC());
  mostrarAno();
})();
</script>
<?php rodape(); ?>
