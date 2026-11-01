<?php
/**
 * Aniversários. Estado em dados/estado.json:
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
      <h2>Próximos</h2>
      <select id="filtro-grupo" style="width:auto"><option value="">todos</option></select>
    </div>
    <table class="dados" id="proximos"></table>
  </section>
  <section class="cartao" id="gerador">
    <h2>Mensagem de parabéns</h2>
    <div class="form-grade">
      <label>Para <input id="m-nome" list="lista-nomes" placeholder="Nome da pessoa"></label>
      <label>Estilo
        <select id="m-estilo">
          <option value="carinhoso">carinhoso</option>
          <option value="amizade">amizade</option>
          <option value="familia">família</option>
          <option value="formal">formal (trabalho, conhecidos)</option>
          <option value="religioso">com bênção</option>
          <option value="divertido">divertido</option>
          <option value="curto">curto</option>
        </select>
      </label>
      <label class="check"><input type="checkbox" id="m-idade"> citar a idade</label>
    </div>
    <datalist id="lista-nomes"></datalist>
    <textarea id="m-texto" rows="6" style="margin-top:12px"></textarea>
    <div class="grupo" style="margin-top:8px">
      <button class="btn" id="m-outra">Outra mensagem</button>
      <button class="btn btn-primario" id="m-copiar">Copiar</button>
      <a class="btn" id="m-whats" target="_blank" rel="noopener" hidden>Enviar no WhatsApp</a>
    </div>
  </section>
</div>
<div class="colunas">
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Do mês</h2>
      <div class="grupo"><select id="mes" style="width:auto"></select><button class="btn btn-p" id="copiar-mes">Copiar lista</button></div>
    </div>
    <table class="dados" id="do-mes"></table>
  </section>
  <section class="cartao">
    <div class="cartao-topo"><h2>Cadastrar</h2><span class="status pequeno" id="st"></span></div>
    <form id="nova" class="form-grade">
      <label class="largo">Nome <input name="nome" required></label>
      <label>Data (dia/mês ou dia/mês/ano) <input name="data" required placeholder="15/03 ou 15/03/1980"></label>
      <label>Grupo <input name="grupo" list="grupos" placeholder="família, amigos, trabalho..."></label>
      <label class="largo">Telefone (opcional, para o WhatsApp) <input name="tel" inputmode="tel" placeholder="(27) 99999-9999"></label>
      <div class="largo grupo"><button class="btn btn-primario">+ Cadastrar</button><button type="button" class="btn" data-abrir="#importar">Colar uma lista</button></div>
    </form>
    <datalist id="grupos"></datalist>
    <form id="importar" class="painel-oculto" style="flex-direction:column;align-items:stretch">
      <p class="mudo pequeno" style="margin:0">Uma pessoa por linha: <code>Nome; dd/mm</code> (pode ter ano, grupo e telefone: <code>Nome; dd/mm/aaaa; grupo; telefone</code>). Também aceita colunas copiadas do Excel.</p>
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

  // ------------------------------------------------------------------ mensagens
  // {nome} = primeiro nome; [idade: ... # ...] só aparece quando a idade é citada (# vira o número)
  const MODELOS = {
    carinhoso: [
      'Feliz aniversário, {nome}! Que seu novo ano seja cheio de saúde, alegria e momentos bonitos ao lado de quem você ama.[idade: Parabéns pelos seus # anos!] Um abraço apertado!',
      '{nome}, hoje o dia é seu! Desejo que nunca falte motivo para sorrir e que todos os seus sonhos se realizem. Parabéns[idade: pelos # anos]!',
      'Parabéns, {nome}! Que a vida te retribua todo o carinho que você espalha por aí. Muitas felicidades[idade: nesses # anos]!',
      'Muitas felicidades, {nome}! Que este novo ciclo traga paz, saúde e muitas conquistas. Você merece tudo de melhor!',
    ],
    amizade: [
      'Feliz aniversário, {nome}! Obrigado por essa amizade tão especial. Que venham muitos anos de risadas e histórias juntos!',
      '{nome}, parabéns! Amigo assim a gente quer por perto a vida toda. Aproveite muito o seu dia[idade: e os seus # anos]!',
      'Parabéns, {nome}! Que seu dia seja tão incrível quanto você. Conte sempre comigo!',
    ],
    familia: [
      'Feliz aniversário, {nome}! Que bom ter você na nossa família. Desejo muita saúde, paz e alegria[idade: nos seus # anos]. Te amo!',
      '{nome}, parabéns pelo seu dia! Você é muito importante para todos nós. Que Deus te dê muitos anos de vida e felicidade.',
      'Parabéns, {nome}! Obrigado por tudo que você faz por nós. Que o seu novo ano seja leve e cheio de amor.',
    ],
    formal: [
      'Prezado(a) {nome}, receba meus sinceros parabéns pelo seu aniversário. Desejo saúde, sucesso e muitas realizações.',
      'Feliz aniversário, {nome}! Que este novo ano traga muitas conquistas pessoais e profissionais. Parabéns!',
      'Parabéns pelo seu dia, {nome}! Desejo um ano repleto de saúde, paz e prosperidade.',
    ],
    religioso: [
      'Feliz aniversário, {nome}! Que Deus abençoe sua vida com saúde, paz e muita alegria[idade: nesses # anos]. Parabéns!',
      '{nome}, parabéns! Que o Senhor guie seus passos e ilumine cada dia deste novo ano de vida.',
      'Parabéns, {nome}! Agradeço a Deus pela sua vida e peço que Ele te cubra de bênçãos hoje e sempre.',
      'Feliz aniversário, {nome}! Que Nossa Senhora te proteja e que Deus realize os desejos do seu coração.',
    ],
    divertido: [
      'Parabéns, {nome}! Mais um ano de experiência acumulada (não estamos contando, prometo[idade:... ok, são #]). Aproveite muito!',
      'Feliz aniversário, {nome}! Hoje está liberado: bolo, docinho e nenhuma culpa. Muitas felicidades!',
      '{nome}, parabéns! Você não envelhece, só fica mais sábio(a) e com mais velas no bolo. Feliz dia!',
    ],
    curto: [
      'Feliz aniversário, {nome}! Muitas felicidades!',
      'Parabéns, {nome}! Tudo de bom pra você!',
      'Parabéns pelo seu dia, {nome}! Muita saúde e alegria!',
    ],
  };
  let variacao = 0;
  const primeiroNome = (n) => String(n || '').trim().split(/\s+/)[0] || '';
  function montarMensagem() {
    const nome = $('#m-nome').value.trim();
    const p = est.pessoas.find((x) => x.nome.toLowerCase() === nome.toLowerCase());
    const lista = MODELOS[$('#m-estilo').value];
    let txt = lista[variacao % lista.length];
    const idade = p && p.ano ? idadeEm(p, proximo(p)) : null;
    const citar = $('#m-idade').checked && idade;
    txt = txt.replace(/\[idade:([^\]]*)\]/g, (_, trecho) => (citar ? trecho.replace('#', idade) : ''));
    txt = txt.replace(/\{nome\}/g, primeiroNome(nome) || 'querido(a)');
    $('#m-texto').value = txt;
    const tel = p && p.tel ? p.tel.replace(/\D/g, '').replace(/^55/, '') : '';
    const w = $('#m-whats');
    w.hidden = !tel;
    if (tel) w.href = `https://wa.me/55${tel}?text=${encodeURIComponent(txt)}`;
  }
  $('#m-texto').addEventListener('input', () => {
    const w = $('#m-whats');
    if (!w.hidden) w.href = w.href.replace(/\?text=.*$/, '?text=' + encodeURIComponent($('#m-texto').value));
  });
  ['#m-nome', '#m-estilo', '#m-idade'].forEach((s) => $(s).addEventListener('change', () => { variacao = 0; montarMensagem(); }));
  $('#m-outra').onclick = () => { variacao++; montarMensagem(); };
  $('#m-copiar').onclick = (e) => App.copiar($('#m-texto').value, e.target);
  function mensagemPara(pid, rolar = true) {
    const p = est.pessoas.find((x) => x.id === pid);
    if (!p) return;
    $('#m-nome').value = p.nome;
    $('#m-idade').checked = !!p.ano;
    variacao = Math.floor(Math.random() * 10);
    montarMensagem();
    if (rolar) $('#gerador').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ------------------------------------------------------------------ datas
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
  const idadeEm = (p, data) => (p.ano ? data.getFullYear() - p.ano : null);
  const quando = (dd) => (dd === 0 ? 'hoje' : dd === 1 ? 'amanhã' : `em ${dd} dias`);

  $('#mes').innerHTML = MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  $('#mes').value = hoje.getMonth() + 1;

  function desenhar() {
    const grupos = [...new Set(est.pessoas.map((p) => p.grupo).filter(Boolean))].sort();
    $('#grupos').innerHTML = grupos.map((g) => `<option value="${esc(g)}">`).join('');
    $('#lista-nomes').innerHTML = est.pessoas.map((p) => `<option value="${esc(p.nome)}">`).join('');
    const fg = $('#filtro-grupo'), atualG = fg.value;
    fg.innerHTML = '<option value="">todos</option>' + grupos.map((g) => `<option ${g === atualG ? 'selected' : ''}>${esc(g)}</option>`).join('');
    const g = fg.value;
    const doGrupo = est.pessoas.filter((p) => !g || p.grupo === g);

    const hojeL = est.pessoas.filter((p) => diasAte(p) === 0);
    const semana = est.pessoas.filter((p) => diasAte(p) <= 7);
    $('#resumo').innerHTML = `<div><span class="rotulo">hoje</span><span class="valor">${hojeL.length ? hojeL.map((p) => esc(primeiroNome(p.nome))).join(', ') : '—'}</span></div>
      <div><span class="rotulo">próximos 7 dias</span><span class="valor">${semana.length}</span></div>
      <div><span class="rotulo">este mês</span><span class="valor">${est.pessoas.filter((p) => p.mes === hoje.getMonth() + 1).length}</span></div>
      <div><span class="rotulo">cadastrados</span><span class="valor">${est.pessoas.length}</span></div>`;

    const prox = doGrupo.slice().sort((a, b) => diasAte(a) - diasAte(b)).filter((p) => diasAte(p) <= 45).slice(0, 12);
    $('#proximos').innerHTML = prox.length ? prox.map((p) => {
      const d = proximo(p), dd = diasAte(p), i = idadeEm(p, d);
      return `<tr><td class="num" style="width:60px">${p2(p.dia)}/${p2(p.mes)}</td>
        <td><strong>${esc(p.nome)}</strong>${p.grupo ? ` <span class="tag">${esc(p.grupo)}</span>` : ''}<div class="mudo pequeno">${SEMANA[d.getDay()]}${i !== null ? ` · faz ${i} anos` : ''}</div></td>
        <td class="num ${dd === 0 ? 'estado-ok' : dd <= 7 ? '' : 'mudo'}">${quando(dd)}</td>
        <td class="num"><button class="btn btn-p" data-msg="${p.id}">Mensagem</button></td></tr>`;
    }).join('') : `<tr><td class="mudo">${est.pessoas.length ? 'Nenhum aniversário nos próximos 45 dias.' : 'Cadastre os aniversários ao lado.'}</td></tr>`;

    const m = +$('#mes').value;
    const doMes = doGrupo.filter((p) => p.mes === m).sort((a, b) => a.dia - b.dia || a.nome.localeCompare(b.nome));
    $('#do-mes').innerHTML = doMes.length ? doMes.map((p) => `<tr><td class="num" style="width:60px">${p2(p.dia)}/${p2(p.mes)}</td><td>${esc(p.nome)}${p.grupo ? ` <span class="tag">${esc(p.grupo)}</span>` : ''}</td><td class="num"><button class="btn btn-p btn-leve" data-msg="${p.id}">Mensagem</button></td></tr>`).join('')
      : '<tr><td class="mudo">Ninguém neste mês.</td></tr>';

    const q = $('#busca').value.trim().toLowerCase();
    const todos = est.pessoas.filter((p) => !q || (p.nome + ' ' + (p.grupo || '')).toLowerCase().includes(q)).sort((a, b) => a.mes - b.mes || a.dia - b.dia);
    $('#total').textContent = `(${est.pessoas.length})`;
    $('#todos').innerHTML = '<thead><tr><th>nome</th><th>data</th><th>grupo</th><th>telefone</th><th></th></tr></thead><tbody>' + todos.map((p) => `<tr data-id="${p.id}">
      <td><input data-k="nome" value="${esc(p.nome)}"></td>
      <td style="width:130px"><input data-k="data" value="${p2(p.dia)}/${p2(p.mes)}${p.ano ? '/' + p.ano : ''}"></td>
      <td style="width:160px"><input data-k="grupo" value="${esc(p.grupo)}" list="grupos"></td>
      <td style="width:160px"><input data-k="tel" value="${esc(p.tel)}"></td>
      <td class="num"><button class="btn btn-p btn-leve" data-rm title="Remover">×</button></td></tr>`).join('') + '</tbody>';
  }
  document.addEventListener('click', (e) => { const pid = e.target.dataset && e.target.dataset.msg; if (pid) mensagemPara(pid); });
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
    let n = 0; const erros = [];
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
    if (k === 'data') { const d = lerData(e.target.value); if (d) Object.assign(p, d); else return alert('Data inválida.'); }
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
  $('#copiar-mes').onclick = (e) => {
    const m = +$('#mes').value, g = $('#filtro-grupo').value;
    const l = est.pessoas.filter((p) => p.mes === m && (!g || p.grupo === g)).sort((a, b) => a.dia - b.dia);
    if (!l.length) return;
    App.copiar(`*Aniversários de ${MESES[m - 1]}*${g ? ' (' + g + ')' : ''}\n\n` + l.map((p) => `${p2(p.dia)}/${p2(p.mes)} – ${p.nome}`).join('\n'), e.target);
  };
  desenhar();
  const aniversarianteHoje = est.pessoas.find((p) => diasAte(p) === 0);
  if (aniversarianteHoje) mensagemPara(aniversarianteHoje.id, false); else montarMensagem();
})();
</script>
<?php rodape(); ?>
