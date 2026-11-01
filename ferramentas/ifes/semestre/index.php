<?php
/**
 * Notas e faltas do semestre. Estado em dados/estado.json:
 *   cfg: {aprov: média para aprovação, final: média após prova final, escala, limite: % de faltas permitido}
 *   disc: [{id, nome, aulas, faltas, avals: [{nome, vale, nota|null}]}]
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<section class="cartao">
  <div class="cartao-topo">
    <h2>Regras</h2>
    <span class="status pequeno" id="st"></span>
  </div>
  <div class="form-grade" id="cfg">
    <label>Nota máxima da disciplina <input data-c="escala" type="number" min="1"></label>
    <label>Média para aprovação direta <input data-c="aprov" type="number" min="0"></label>
    <label>Média mínima depois da final <input data-c="final" type="number" min="0"></label>
    <label>Limite de faltas (%) <input data-c="limite" type="number" min="0" max="100"></label>
  </div>
  <p class="dica">Padrão comum no IFES: aprovação com 60 de 100 e frequência mínima de 75% (até 25% de faltas). A média depois da final é (média do semestre + prova final) ÷ 2. Confira no regulamento (ROD) do seu curso e ajuste acima se for diferente.</p>
</section>
<div class="cartao-topo" style="margin-bottom:12px">
  <h2 style="margin:0">Disciplinas</h2>
  <form id="nova" class="grupo">
    <input name="nome" placeholder="Nome da disciplina" required style="width:240px">
    <input name="aulas" type="number" min="1" placeholder="Total de aulas" title="Total de aulas (períodos) no semestre" style="width:150px">
    <button class="btn btn-primario">+ Adicionar</button>
  </form>
</div>
<div id="lista"></div>
<script>
(function () {
  const est = Object.assign({ cfg: {}, disc: [] }, <?= json_js($estado ?: new stdClass()) ?>);
  est.cfg = Object.assign({ escala: 100, aprov: 60, final: 50, limite: 25 }, est.cfg);
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const n1 = (n) => (Math.round(n * 10) / 10).toLocaleString('pt-BR');
  const id = () => Math.random().toString(36).slice(2, 9);

  document.querySelectorAll('#cfg [data-c]').forEach((i) => {
    i.value = est.cfg[i.dataset.c];
    i.onchange = () => { est.cfg[i.dataset.c] = +i.value || 0; desenhar(); salvar(); };
  });

  function calc(d) {
    const c = est.cfg;
    const valeTotal = d.avals.reduce((s, a) => s + (+a.vale || 0), 0);
    const feitas = d.avals.filter((a) => a.nota !== null && a.nota !== '' && a.nota !== undefined);
    const valeFeito = feitas.reduce((s, a) => s + (+a.vale || 0), 0);
    const obtido = feitas.reduce((s, a) => s + (+a.nota || 0), 0);
    const base = Math.max(valeTotal, c.escala); // se as avaliações não somam a nota máxima, conta sobre a máxima
    const mediaAtual = valeFeito ? (obtido / valeFeito) * c.escala : null;   // aproveitamento até agora
    const pontosParaAprov = (c.aprov / c.escala) * base - obtido;
    const restante = base - valeFeito;
    return { valeTotal, valeFeito, obtido, mediaAtual, pontosParaAprov, restante, base };
  }
  function blocoNotas(d) {
    const c = est.cfg, r = calc(d);
    let situ;
    if (r.pontosParaAprov <= 0) situ = `<span class="estado-ok">aprovado por nota (${n1(r.obtido)} pontos)</span>`;
    else if (r.restante <= 0) {
      const mp = (r.obtido / r.base) * c.escala;
      const pf = 2 * c.final - mp;
      situ = pf > c.escala ? `<span class="estado-ruim">reprovado por nota (média ${n1(mp)})</span>`
        : `<span class="estado-alerta">vai para a final: precisa de ${n1(Math.max(0, pf))} na prova final</span>`;
    } else {
      const pct = (r.pontosParaAprov / r.restante) * 100;
      const cls = pct > 100 ? 'estado-ruim' : pct > 70 ? 'estado-alerta' : 'estado-ok';
      situ = `<span class="${cls}">faltam ${n1(r.pontosParaAprov)} pontos dos ${n1(r.restante)} restantes (${Math.round(pct)}% de aproveitamento)</span>` +
        (pct > 100 ? ' <span class="mudo">— não dá mais para passar direto; vai depender da final</span>' : '');
    }
    const linhas = d.avals.map((a, i) => `<tr data-a="${i}">
      <td><input data-k="nome" value="${esc(a.nome)}" placeholder="Avaliação"></td>
      <td class="num"><input data-k="vale" value="${esc(a.vale)}" inputmode="decimal" style="width:80px;text-align:right"></td>
      <td class="num"><input data-k="nota" value="${a.nota === null || a.nota === undefined ? '' : esc(String(a.nota).replace('.', ','))}" inputmode="decimal" placeholder="—" style="width:80px;text-align:right"></td>
      <td class="num"><button class="btn btn-p btn-leve" data-rma="${i}" title="Remover">×</button></td></tr>`).join('');
    return `<table class="dados"><thead><tr><th>avaliação</th><th class="num">vale</th><th class="num">nota</th><th></th></tr></thead>
      <tbody>${linhas || '<tr><td colspan="4" class="mudo">Nenhuma avaliação ainda.</td></tr>'}</tbody>
      <tfoot><tr><td>total</td><td class="num">${n1(r.valeTotal)}</td><td class="num">${n1(r.obtido)}</td><td></td></tr></tfoot></table>
      <div class="grupo" style="margin-top:8px"><button class="btn btn-p" data-adda>+ Avaliação</button>
      ${r.valeTotal && Math.abs(r.valeTotal - c.escala) > 0.01 ? `<span class="mudo pequeno">as avaliações somam ${n1(r.valeTotal)} de ${c.escala}</span>` : ''}</div>
      <p style="margin:10px 0 0">${situ}</p>`;
  }
  function blocoFaltas(d) {
    const c = est.cfg;
    if (!d.aulas) return `<p class="mudo">Informe o total de aulas do semestre para calcular.</p><label>Total de aulas <input data-k="aulas" type="number" min="1"></label>`;
    const limite = Math.floor((d.aulas * c.limite) / 100);
    const resta = limite - d.faltas;
    const pct = d.aulas ? (d.faltas / d.aulas) * 100 : 0;
    const cls = resta < 0 ? 'estado-ruim' : resta <= Math.max(2, limite * 0.2) ? 'estado-alerta' : 'estado-ok';
    const msg = resta < 0 ? `passou do limite em ${-resta} falta${resta === -1 ? '' : 's'}` : resta === 0 ? 'não pode faltar mais nenhuma aula' : `ainda pode faltar ${resta} aula${resta === 1 ? '' : 's'}`;
    return `<div class="resumo" style="grid-template-columns:repeat(3,1fr);margin-bottom:10px">
        <div><span class="rotulo">faltas</span><span class="valor">${d.faltas}</span></div>
        <div><span class="rotulo">limite</span><span class="valor">${limite}</span></div>
        <div><span class="rotulo">frequência</span><span class="valor">${n1(100 - pct)}%</span></div></div>
      <div class="barra-progresso ${resta < 0 ? 'ruim' : ''}"><span style="width:${Math.min(100, limite ? (d.faltas / limite) * 100 : 100)}%"></span></div>
      <p class="${cls}" style="margin:8px 0">${msg}</p>
      <div class="grupo"><button class="btn btn-p" data-f="1">+1 falta</button><button class="btn btn-p" data-f="2">+2 (aula dupla)</button><button class="btn btn-p btn-leve" data-f="-1">−1</button>
      <label class="check pequeno mudo" style="margin-left:auto">total de aulas <input data-k="aulas" type="number" min="1" value="${d.aulas}" style="width:80px"></label></div>`;
  }
  function desenhar() {
    $('#lista').innerHTML = est.disc.length ? est.disc.map((d) => `<section class="cartao" data-d="${d.id}">
      <div class="cartao-topo"><h2>${esc(d.nome)}</h2>
        <details class="menu"><summary class="btn btn-p" title="Mais opções">...</summary><div class="menu-itens">
          <button data-ren>Renomear</button><button class="perigo" data-rmd>Remover disciplina</button></div></details></div>
      <div class="colunas"><div>${blocoNotas(d)}</div><div>${blocoFaltas(d)}</div></div></section>`).join('')
      : '<div class="cartao vazio"><p>Adicione as disciplinas do semestre acima.</p><p class="mudo pequeno">O total de aulas costuma ser a carga horária ÷ duração da aula (ex.: 60 h com aulas de 50 min = 72 aulas).</p></div>';
  }
  const disc = (el) => est.disc.find((x) => x.id === el.closest('[data-d]').dataset.d);
  $('#lista').addEventListener('change', (e) => {
    const k = e.target.dataset.k;
    if (!k) return;
    const d = disc(e.target), tr = e.target.closest('[data-a]');
    if (tr) {
      const av = d.avals[tr.dataset.a];
      if (k === 'nome') av.nome = e.target.value;
      else if (k === 'nota') av.nota = e.target.value.trim() === '' ? null : App.numero(e.target.value);
      else av[k] = App.numero(e.target.value) ?? 0;
    } else if (k === 'aulas') d.aulas = Math.max(0, +e.target.value || 0);
    desenhar(); salvar();
  });
  $('#lista').addEventListener('click', (e) => {
    const t = e.target;
    if (!t.closest('[data-d]')) return;
    const d = disc(t);
    if (t.dataset.f) { d.faltas = Math.max(0, d.faltas + +t.dataset.f); }
    else if (t.hasAttribute('data-adda')) { d.avals.push({ nome: `Avaliação ${d.avals.length + 1}`, vale: 0, nota: null }); }
    else if (t.dataset.rma !== undefined) { d.avals.splice(+t.dataset.rma, 1); }
    else if (t.hasAttribute('data-ren')) { const n = prompt('Novo nome:', d.nome); if (n) d.nome = n.trim(); }
    else if (t.hasAttribute('data-rmd')) { if (!confirm(`Remover "${d.nome}"?`)) return; est.disc = est.disc.filter((x) => x !== d); }
    else return;
    desenhar(); salvar();
  });
  $('#nova').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target;
    est.disc.push({ id: id(), nome: fm.nome.value.trim(), aulas: +fm.aulas.value || 0, faltas: 0, avals: [] });
    fm.reset(); desenhar(); salvar();
  });
  desenhar();
})();
</script>
<?php rodape(); ?>
