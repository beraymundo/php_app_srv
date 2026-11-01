<?php
/**
 * Aniversariantes. Estado em dados/estado.json:
 *   pessoas: [{id, nome, dia, mes, ano (opcional), grupo, tel}]
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="resumo" id="resumo"></div>
<div class="colunas">
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Aniversariantes de</h2>
      <div class="grupo">
        <select id="mes" style="width:auto"></select>
        <select id="filtro-grupo" style="width:auto"><option value="">todos os grupos</option></select>
      </div>
    </div>
    <table class="dados" id="do-mes"></table>
    <div class="grupo" style="margin-top:12px">
      <button class="btn btn-primario" id="copiar">Copiar texto do mês</button>
      <label class="check pequeno"><input type="checkbox" id="com-grupo"> incluir grupo</label>
    </div>
  </section>
  <section class="cartao">
    <div class="cartao-topo"><h2>Cadastrar</h2><span class="status pequeno" id="st"></span></div>
    <form id="nova" class="form-grade">
      <label class="largo">Nome <input name="nome" required></label>
      <label>Data (dia/mês ou dia/mês/ano) <input name="data" required placeholder="15/03 ou 15/03/1980"></label>
      <label>Grupo <input name="grupo" list="grupos" placeholder="Ex.: dizimistas"></label>
      <label class="largo">Telefone (opcional) <input name="tel" inputmode="tel"></label>
      <div class="largo grupo"><button class="btn btn-primario">+ Cadastrar</button><button type="button" class="btn" data-abrir="#importar">Colar uma lista</button></div>
    </form>
    <datalist id="grupos"></datalist>
    <form id="importar" class="painel-oculto" style="flex-direction:column;align-items:stretch">
      <p class="mudo pequeno" style="margin:0">Uma pessoa por linha: <code>Nome; dd/mm</code> (pode ter ano e grupo: <code>Nome; dd/mm/aaaa; grupo</code>). Também aceita colunas copiadas do Excel.</p>
      <textarea name="txt" rows="6"></textarea>
      <div><button class="btn btn-primario">Importar</button></div>
    </form>
  </section>
</div>
<section class="cartao">
  <div class="cartao-topo"><h2>Todos <small class="mudo" id="total"></small></h2><input id="busca" placeholder="filtrar por nome ou grupo..." style="max-width:280px"></div>
  <div class="tabela-rolagem"><table class="dados" id="todos"></table></div>
</section>
<script>
(function () {
  const est = Object.assign({ pessoas: [] }, <?= json_js($estado ?: new stdClass()) ?>);
  const $ = (s) => document.querySelector(s);
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const p2 = (n) => String(n).padStart(2, '0');
  const id = () => Math.random().toString(36).slice(2, 9);
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  $('#mes').innerHTML = MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  $('#mes').value = hoje.getMonth() + 1;

  function lerData(s) {
    const m = String(s).trim().match(/^(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2,4}))?$/);
    if (!m) return null;
    const dia = +m[1], mes = +m[2];
    let ano = m[3] ? +m[3] : null;
    if (ano && ano < 100) ano += ano > 30 ? 1900 : 2000;
    if (mes < 1 || mes > 12 || dia < 1 || dia > 31) return null;
    return { dia, mes, ano };
  }
  const proximo = (p) => { let d = new Date(hoje.getFullYear(), p.mes - 1, p.dia); if (d < hoje) d = new Date(hoje.getFullYear() + 1, p.mes - 1, p.dia); return d; };
  const diasAte = (p) => Math.round((proximo(p) - hoje) / 864e5);
  const idade = (p, ano) => (p.ano ? ano - p.ano : null);

  function desenhar() {
    const grupos = [...new Set(est.pessoas.map((p) => p.grupo).filter(Boolean))].sort();
    $('#grupos').innerHTML = grupos.map((g) => `<option value="${esc(g)}">`).join('');
    const fg = $('#filtro-grupo'), atualG = fg.value;
    fg.innerHTML = '<option value="">todos os grupos</option>' + grupos.map((g) => `<option ${g === atualG ? 'selected' : ''}>${esc(g)}</option>`).join('');

    const hojeL = est.pessoas.filter((p) => diasAte(p) === 0);
    const semana = est.pessoas.filter((p) => diasAte(p) <= 7);
    const prox = est.pessoas.slice().sort((a, b) => diasAte(a) - diasAte(b))[0];
    $('#resumo').innerHTML = `<div><span class="rotulo">hoje</span><span class="valor">${hojeL.length ? hojeL.map((p) => esc(p.nome.split(' ')[0])).join(', ') : '—'}</span></div>
      <div><span class="rotulo">próximos 7 dias</span><span class="valor">${semana.length}</span></div>
      <div><span class="rotulo">próximo</span><span class="valor" style="font-size:1rem">${prox ? `${esc(prox.nome)} <span class="mudo">${p2(prox.dia)}/${p2(prox.mes)}</span>` : '—'}</span></div>
      <div><span class="rotulo">cadastrados</span><span class="valor">${est.pessoas.length}</span></div>`;

    const m = +$('#mes').value, g = fg.value;
    const doMes = est.pessoas.filter((p) => p.mes === m && (!g || p.grupo === g)).sort((a, b) => a.dia - b.dia || a.nome.localeCompare(b.nome));
    const anoRef = m < hoje.getMonth() + 1 ? hoje.getFullYear() + 1 : hoje.getFullYear();
    $('#do-mes').innerHTML = doMes.length ? doMes.map((p) => {
      const d = new Date(anoRef, m - 1, p.dia), dd = diasAte(p), i = idade(p, anoRef);
      return `<tr><td class="num" style="width:60px">${p2(p.dia)}/${p2(p.mes)}</td><td class="mudo" style="width:40px">${SEMANA[d.getDay()]}</td>
        <td><strong>${esc(p.nome)}</strong>${p.grupo ? ` <span class="tag">${esc(p.grupo)}</span>` : ''}</td>
        <td class="num mudo">${i !== null ? i + ' anos' : ''}</td>
        <td class="num ${dd === 0 ? 'estado-ok' : 'mudo'}">${dd === 0 ? 'hoje' : dd <= 31 ? 'em ' + dd + 'd' : ''}</td></tr>`;
    }).join('') : '<tr><td class="mudo">Ninguém neste mês.</td></tr>';

    const q = $('#busca').value.trim().toLowerCase();
    const todos = est.pessoas.filter((p) => !q || (p.nome + ' ' + (p.grupo || '')).toLowerCase().includes(q)).sort((a, b) => a.mes - b.mes || a.dia - b.dia);
    $('#total').textContent = `(${est.pessoas.length})`;
    $('#todos').innerHTML = '<thead><tr><th>nome</th><th>data</th><th>grupo</th><th>telefone</th><th></th></tr></thead><tbody>' + todos.map((p) => `<tr data-id="${p.id}">
      <td><input data-k="nome" value="${esc(p.nome)}"></td>
      <td style="width:130px"><input data-k="data" value="${p2(p.dia)}/${p2(p.mes)}${p.ano ? '/' + p.ano : ''}"></td>
      <td style="width:170px"><input data-k="grupo" value="${esc(p.grupo)}" list="grupos"></td>
      <td style="width:160px"><input data-k="tel" value="${esc(p.tel)}"></td>
      <td class="num">${p.tel ? `<a class="btn btn-p" target="_blank" rel="noopener" href="https://wa.me/55${esc(p.tel.replace(/\D/g, '').replace(/^55/, ''))}">WhatsApp</a>` : ''} <button class="btn btn-p btn-leve" data-rm title="Remover">×</button></td></tr>`).join('') + '</tbody>';
  }
  $('#nova').addEventListener('submit', (e) => {
    e.preventDefault();
    const fm = e.target, d = lerData(fm.data.value);
    if (!d) return alert('Data inválida. Use dd/mm ou dd/mm/aaaa.');
    est.pessoas.push({ id: id(), nome: fm.nome.value.trim(), ...d, grupo: fm.grupo.value.trim(), tel: fm.tel.value.trim() });
    const g = fm.grupo.value; fm.reset(); fm.grupo.value = g; fm.nome.focus();
    desenhar(); salvar();
  });
  $('#importar').addEventListener('submit', (e) => {
    e.preventDefault();
    let n = 0, erros = [];
    e.target.txt.value.split('\n').map((l) => l.trim()).filter(Boolean).forEach((l) => {
      const partes = l.split(/\t|;/).map((x) => x.trim());
      const iData = partes.findIndex((x) => lerData(x));
      if (iData < 0) { erros.push(l); return; }
      const d = lerData(partes[iData]);
      const resto = partes.filter((_, i) => i !== iData);
      est.pessoas.push({ id: id(), nome: resto[0] || '?', ...d, grupo: resto[1] || '', tel: resto[2] || '' });
      n++;
    });
    e.target.txt.value = erros.join('\n');
    alert(`${n} pessoa(s) importada(s).` + (erros.length ? ` ${erros.length} linha(s) sem data válida ficaram na caixa.` : ''));
    desenhar(); salvar();
  });
  $('#todos').addEventListener('change', (e) => {
    const p = est.pessoas.find((x) => x.id === e.target.closest('tr').dataset.id), k = e.target.dataset.k;
    if (k === 'data') { const d = lerData(e.target.value); if (d) Object.assign(p, d, { ano: d.ano }); else return alert('Data inválida.'); }
    else p[k] = e.target.value.trim();
    desenhar(); salvar();
  });
  $('#todos').addEventListener('click', (e) => {
    if (!e.target.hasAttribute('data-rm')) return;
    const p = est.pessoas.find((x) => x.id === e.target.closest('tr').dataset.id);
    if (confirm(`Remover ${p.nome}?`)) { est.pessoas = est.pessoas.filter((x) => x !== p); desenhar(); salvar(); }
  });
  $('#busca').addEventListener('input', desenhar);
  $('#mes').onchange = desenhar;
  $('#filtro-grupo').onchange = desenhar;
  $('#copiar').onclick = (e) => {
    const m = +$('#mes').value, g = $('#filtro-grupo').value, cg = $('#com-grupo').checked;
    const l = est.pessoas.filter((p) => p.mes === m && (!g || p.grupo === g)).sort((a, b) => a.dia - b.dia);
    if (!l.length) return;
    const txt = `*Aniversariantes de ${MESES[m - 1]}*${g ? ' — ' + g : ''}\n\n` + l.map((p) => `${p2(p.dia)}/${p2(p.mes)} – ${p.nome}${cg && p.grupo ? ' (' + p.grupo + ')' : ''}`).join('\n') + '\n\nParabéns! Que Deus abençoe cada um com saúde e paz.';
    App.copiar(txt, e.target);
  };
  desenhar();
})();
</script>
<?php rodape(); ?>
