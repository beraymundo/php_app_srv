<?php
/**
 * Consumo de energia por aparelho. Estado em dados/estado.json:
 *   tarifa: R$/kWh (com impostos), aparelhos: [{nome, w, h (horas/dia), d (dias/mês), q (quantidade), fator (0-1, ciclo do motor)}]
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="resumo">
  <div><span class="rotulo">consumo estimado / mês</span><span class="valor" id="r-kwh"></span></div>
  <div><span class="rotulo">custo estimado / mês</span><span class="valor" id="r-custo"></span></div>
  <div><span class="rotulo">por dia</span><span class="valor" id="r-dia"></span></div>
  <div><label>Tarifa R$/kWh (com impostos) <input id="tarifa" inputmode="decimal"></label></div>
</div>
<section class="cartao">
  <div class="cartao-topo">
    <h2>Aparelhos</h2>
    <div class="grupo">
      <select id="preset" style="width:auto"><option value="">+ adicionar aparelho comum...</option></select>
      <button class="btn" id="novo">+ Em branco</button>
      <span class="status pequeno" id="st"></span>
    </div>
  </div>
  <div class="tabela-rolagem">
    <table class="dados">
      <thead><tr><th>aparelho</th><th class="num">potência (W)</th><th class="num">qtd</th><th class="num">horas/dia</th><th class="num">dias/mês</th><th class="num">kWh/mês</th><th class="num">R$/mês</th><th style="width:16%">peso na conta</th><th></th></tr></thead>
      <tbody id="linhas"></tbody>
    </table>
  </div>
  <p class="dica">A potência está na etiqueta do aparelho (W). Geladeira e ar-condicionado não ficam ligados o tempo todo: o motor liga e desliga, por isso eles usam um "fator de uso" menor que 100% (já incluso nos modelos). A tarifa com impostos está na sua conta de luz (valor total ÷ kWh consumidos).</p>
</section>
<script>
(function () {
  const PRESETS = [
    ['Geladeira', 150, 24, 30, 0.35], ['Freezer', 130, 24, 30, 0.4], ['Chuveiro elétrico', 5500, 0.5, 30, 1], ['Ar-condicionado 9000 BTU', 900, 8, 30, 0.6],
    ['Ar-condicionado 12000 BTU', 1200, 8, 30, 0.6], ['Ventilador', 80, 8, 30, 1], ['TV LED 43"', 90, 5, 30, 1], ['Computador (desktop)', 200, 6, 22, 1],
    ['Notebook', 60, 6, 22, 1], ['Máquina de lavar', 500, 1, 12, 1], ['Ferro de passar', 1200, 0.5, 8, 1], ['Micro-ondas', 1300, 0.25, 30, 1],
    ['Lâmpada LED 9W', 9, 6, 30, 1], ['Lâmpada fluorescente 20W', 20, 6, 30, 1], ['Bomba d\'água', 750, 1, 30, 1], ['Secador de cabelo', 1800, 0.2, 15, 1],
    ['Air fryer', 1500, 0.5, 20, 1], ['Roteador Wi-Fi', 10, 24, 30, 1], ['Videogame', 150, 2, 20, 1], ['Forno elétrico', 1800, 0.5, 8, 1],
  ];
  const est = Object.assign({ tarifa: 0.95, aparelhos: [] }, <?= json_js($estado ?: new stdClass()) ?>);
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const n2 = (n, d = 2) => (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
  $('#preset').innerHTML += PRESETS.map((p, i) => `<option value="${i}">${p[0]} (${p[1]} W)</option>`).join('');
  $('#tarifa').value = n2(est.tarifa, 2);
  const kwh = (a) => (a.w * (a.q || 1) * a.h * a.d * (a.fator ?? 1)) / 1000;

  function desenhar() {
    const tot = est.aparelhos.reduce((s, a) => s + kwh(a), 0);
    const max = Math.max(0.0001, ...est.aparelhos.map(kwh));
    $('#linhas').innerHTML = est.aparelhos.map((a, i) => {
      const k = kwh(a);
      return `<tr data-i="${i}">
        <td><input data-k="nome" value="${esc(a.nome)}">${(a.fator ?? 1) < 1 ? `<div class="mudo pequeno">fator de uso ${Math.round(a.fator * 100)}%</div>` : ''}</td>
        <td class="num"><input data-k="w" value="${a.w}" inputmode="decimal" style="width:80px;text-align:right"></td>
        <td class="num"><input data-k="q" value="${a.q || 1}" type="number" min="1" style="width:60px"></td>
        <td class="num"><input data-k="h" value="${n2(a.h, a.h % 1 ? 2 : 0)}" inputmode="decimal" style="width:70px;text-align:right"></td>
        <td class="num"><input data-k="d" value="${a.d}" type="number" min="0" max="31" style="width:64px"></td>
        <td class="num">${n2(k, 1)}</td>
        <td class="num"><strong>${n2(k * est.tarifa)}</strong></td>
        <td><div class="barra-progresso"><span style="width:${(k / max) * 100}%"></span></div><div class="mudo pequeno">${tot ? Math.round((k / tot) * 100) : 0}%</div></td>
        <td class="num"><button class="btn btn-p btn-leve" data-rm="${i}" title="Remover">×</button></td></tr>`;
    }).join('') || '<tr><td colspan="9" class="mudo">Adicione aparelhos pelo menu acima.</td></tr>';
    $('#r-kwh').textContent = n2(tot, 0) + ' kWh';
    $('#r-custo').textContent = App.reais(tot * est.tarifa);
    $('#r-dia').textContent = App.reais((tot * est.tarifa) / 30);
  }
  $('#linhas').addEventListener('change', (e) => {
    const tr = e.target.closest('tr'), k = e.target.dataset.k;
    if (!tr || !k) return;
    const a = est.aparelhos[tr.dataset.i];
    a[k] = k === 'nome' ? e.target.value : (App.numero(e.target.value) ?? 0);
    desenhar(); salvar();
  });
  $('#linhas').addEventListener('click', (e) => { const i = e.target.dataset.rm; if (i !== undefined) { est.aparelhos.splice(+i, 1); desenhar(); salvar(); } });
  $('#preset').onchange = (e) => {
    const p = PRESETS[e.target.value];
    if (p) { est.aparelhos.push({ nome: p[0], w: p[1], h: p[2], d: p[3], q: 1, fator: p[4] }); desenhar(); salvar(); }
    e.target.value = '';
  };
  $('#novo').onclick = () => { est.aparelhos.push({ nome: 'Aparelho', w: 100, h: 1, d: 30, q: 1, fator: 1 }); desenhar(); salvar(); };
  $('#tarifa').onchange = (e) => { est.tarifa = App.numero(e.target.value) ?? est.tarifa; e.target.value = n2(est.tarifa); desenhar(); salvar(); };
  desenhar();
})();
</script>
<?php rodape(); ?>
