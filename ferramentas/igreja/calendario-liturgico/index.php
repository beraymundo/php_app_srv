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
<script>
(function () {
  const $ = (s) => document.querySelector(s);
  const DIA = 864e5;
  const D = (y, m, d) => new Date(Date.UTC(y, m - 1, d));
  const add = (d, n) => new Date(d.getTime() + n * DIA);
  const dow = (d) => d.getUTCDay();
  const dif = (a, b) => Math.round((a - b) / DIA);
  const igual = (a, b) => a.getTime() === b.getTime();
  const domEmOuApos = (d) => add(d, (7 - dow(d)) % 7);
  const SEMANA = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const CORES = { roxo: '#6b3fa0', branco: '#ffffff', verde: '#2e7d32', vermelho: '#c62828', rosa: '#e991b8' };
  const fmt = (d) => `${String(d.getUTCDate()).padStart(2, '0')}/${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
  const fmtLongo = (d) => `${d.getUTCDate()} de ${MESES[d.getUTCMonth()]} de ${d.getUTCFullYear()}`;

  function pascoa(y) { // algoritmo de Meeus/Jones/Butcher
    const a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30, i = Math.floor(c / 4), k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451);
    const mes = Math.floor((h + l - 7 * m + 114) / 31), dia = ((h + l - 7 * m + 114) % 31) + 1;
    return D(y, mes, dia);
  }
  const advento1 = (y) => { const n = D(y, 12, 25); return add(n, -(dow(n) || 7) - 21); };

  const cache = {};
  function ano(y) {
    if (cache[y]) return cache[y];
    const p = pascoa(y), adv = advento1(y);
    const epif = domEmOuApos(D(y, 1, 2));
    const batismo = epif.getUTCDate() >= 7 ? add(epif, 1) : add(epif, 7);
    const ramos = add(p, -7), cinzas = add(p, -46);
    let sjose = D(y, 3, 19);
    if (sjose >= ramos && sjose <= add(p, 7)) sjose = add(ramos, -1);
    else if (dow(sjose) === 0) sjose = add(sjose, 1);
    let anunc = D(y, 3, 25);
    if (anunc >= ramos && anunc <= add(p, 7)) anunc = add(p, 8);
    else if (dow(anunc) === 0) anunc = add(anunc, 1);
    let imac = D(y, 12, 8); if (dow(imac) === 0) imac = add(imac, 1);
    let sfam = null; for (let d = 26; d <= 31; d++) if (dow(D(y, 12, d)) === 0) sfam = D(y, 12, d);
    if (!sfam) sfam = D(y, 12, 30);
    const k = {
      p, adv, epif, batismo, ramos, cinzas, pentecostes: add(p, 49), cristoRei: add(adv, -7),
      // [data, nome, cor, grau]  grau: M = móvel principal, S = solenidade, F = festa do Senhor / que substitui domingo comum
      festas: [
        [D(y, 1, 1), 'Santa Maria, Mãe de Deus', 'branco', 'S'],
        [epif, 'Epifania do Senhor', 'branco', 'S'],
        [batismo, 'Batismo do Senhor', 'branco', 'F'],
        [D(y, 2, 2), 'Apresentação do Senhor', 'branco', 'F'],
        [cinzas, 'Quarta-feira de Cinzas', 'roxo', 'M'],
        [sjose, 'São José, esposo da Virgem Maria', 'branco', 'S'],
        [anunc, 'Anunciação do Senhor', 'branco', 'S'],
        [ramos, 'Domingo de Ramos e da Paixão do Senhor', 'vermelho', 'M'],
        [add(p, -3), 'Quinta-feira Santa (Ceia do Senhor)', 'branco', 'M'],
        [add(p, -2), 'Sexta-feira da Paixão do Senhor', 'vermelho', 'M'],
        [add(p, -1), 'Sábado Santo (Vigília Pascal à noite)', 'branco', 'M'],
        [p, 'Domingo da Páscoa da Ressurreição', 'branco', 'M'],
        [add(p, 7), '2º Domingo da Páscoa (da Divina Misericórdia)', 'branco', 'M'],
        [add(p, 42), 'Ascensão do Senhor', 'branco', 'M'],
        [add(p, 49), 'Pentecostes', 'vermelho', 'M'],
        [add(p, 56), 'Santíssima Trindade', 'branco', 'M'],
        [add(p, 60), 'Santíssimo Corpo e Sangue de Cristo (Corpus Christi)', 'branco', 'M'],
        [add(p, 68), 'Sagrado Coração de Jesus', 'branco', 'M'],
        [D(y, 6, 24), 'Natividade de São João Batista', 'branco', 'S'],
        [domEmOuApos(D(y, 6, 29)), 'São Pedro e São Paulo, apóstolos', 'vermelho', 'S'],
        [D(y, 8, 6), 'Transfiguração do Senhor', 'branco', 'F'],
        [domEmOuApos(D(y, 8, 15)), 'Assunção de Nossa Senhora', 'branco', 'S'],
        [D(y, 9, 14), 'Exaltação da Santa Cruz', 'vermelho', 'F'],
        [D(y, 10, 12), 'Nossa Senhora da Conceição Aparecida', 'branco', 'S'],
        [domEmOuApos(D(y, 11, 1)), 'Todos os Santos', 'branco', 'S'],
        [D(y, 11, 2), 'Comemoração de Todos os Fiéis Defuntos (Finados)', 'roxo', 'S'],
        [D(y, 11, 9), 'Dedicação da Basílica do Latrão', 'branco', 'F'],
        [add(adv, -7), 'Nosso Senhor Jesus Cristo, Rei do Universo', 'branco', 'M'],
        [imac, 'Imaculada Conceição de Nossa Senhora', 'branco', 'S'],
        [D(y, 12, 25), 'Natal do Senhor', 'branco', 'S'],
        [sfam, 'Sagrada Família de Jesus, Maria e José', 'branco', 'F'],
      ],
    };
    return (cache[y] = k);
  }

  function info(d) {
    const y = d.getUTCFullYear(), k = ano(y);
    const anoLit = d >= k.adv ? y + 1 : y;
    const ciclo = ['C', 'A', 'B'][anoLit % 3];
    const par = anoLit % 2 === 0 ? 'II (par)' : 'I (ímpar)';
    const domingo = dow(d) === 0;
    let tempo, cor, nome, semana = null;

    if (d >= D(y, 12, 25) || d <= k.batismo) tempo = 'Natal';
    else if (d >= k.adv) tempo = 'Advento';
    else if (d < k.cinzas) tempo = 'Comum';
    else if (d < add(k.p, -3)) tempo = 'Quaresma';
    else if (d < k.p) tempo = 'Tríduo Pascal';
    else if (d <= k.pentecostes) tempo = 'Páscoa';
    else tempo = 'Comum';

    const domAnterior = add(d, -dow(d));
    if (tempo === 'Advento') {
      semana = 1 + Math.floor(dif(domAnterior, k.adv) / 7);
      cor = domingo && semana === 3 ? 'rosa' : 'roxo';
      nome = domingo ? `${semana}º Domingo do Advento` : (d >= D(y, 12, 17) ? `${SEMANA[dow(d)]}, ${d.getUTCDate()} de dezembro (semana que antecede o Natal)` : `${SEMANA[dow(d)]} da ${semana}ª Semana do Advento`);
    } else if (tempo === 'Natal') {
      cor = 'branco';
      nome = d.getUTCMonth() === 11 ? (d.getUTCDate() === 25 ? 'Natal do Senhor' : `${d.getUTCDate() - 24}º dia da Oitava do Natal`) : (domingo ? 'Domingo do Tempo do Natal' : `${SEMANA[dow(d)]} do Tempo do Natal`);
    } else if (tempo === 'Quaresma') {
      if (d < add(k.cinzas, 4)) { nome = `${SEMANA[dow(d)]} depois das Cinzas`; cor = 'roxo'; }
      else if (d >= k.ramos) { nome = domingo ? '' : `${SEMANA[dow(d)]} Santa`; cor = 'roxo'; }
      else {
        semana = 1 + Math.floor(dif(domAnterior, add(k.cinzas, 4)) / 7);
        cor = domingo && semana === 4 ? 'rosa' : 'roxo';
        nome = domingo ? `${semana}º Domingo da Quaresma` : `${SEMANA[dow(d)]} da ${semana}ª Semana da Quaresma`;
      }
    } else if (tempo === 'Tríduo Pascal') { cor = 'roxo'; nome = ''; }
    else if (tempo === 'Páscoa') {
      cor = 'branco';
      semana = 1 + Math.floor(dif(domAnterior, k.p) / 7);
      if (d < add(k.p, 8)) nome = domingo ? '' : `${SEMANA[dow(d)]} da Oitava da Páscoa`;
      else nome = domingo ? `${semana}º Domingo da Páscoa` : `${SEMANA[dow(d)]} da ${semana}ª Semana da Páscoa`;
    } else { // Tempo Comum
      cor = 'verde';
      if (d < k.cinzas) {
        const prim = add(k.batismo, 7 - dow(k.batismo) || 7); // primeiro domingo depois do Batismo = 2º domingo
        semana = 2 + Math.floor(dif(domAnterior, prim) / 7);
      } else semana = 34 - Math.floor(dif(k.cristoRei, domAnterior) / 7);
      nome = domingo ? `${semana}º Domingo do Tempo Comum` : `${SEMANA[dow(d)]} da ${semana}ª Semana do Tempo Comum`;
    }

    // celebrações que substituem o dia
    const fest = k.festas.find((x) => igual(x[0], d));
    let celebracao = null, substitui = false;
    if (fest) {
      const [, fn, fc, grau] = fest;
      const domingoPrivilegiado = domingo && ['Advento', 'Quaresma', 'Páscoa'].includes(tempo);
      if (grau === 'M' || !domingoPrivilegiado || tempo === 'Natal') {
        if (grau !== 'F' || !domingo || tempo === 'Comum' || tempo === 'Natal') { celebracao = fn; cor = fc; substitui = domingo && grau !== 'M' && tempo === 'Comum'; }
      }
    }
    return { d, tempo, cor, nome: celebracao || nome, base: substitui ? nome : null, ciclo, par, anoLit, domingo, semana };
  }

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
  window.Liturgia = { info, ano, D }; // para conferência no console
})();
</script>
<?php rodape(); ?>
