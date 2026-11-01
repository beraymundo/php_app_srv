<?php
/**
 * Referências ABNT (NBR 6023:2018). Estado em dados/estado.json:
 *   trabalhos: {"Nome do trabalho": [{tipo, campos..., criado}]}, atual: nome do trabalho aberto
 */
/** @var Ferramenta $f */
/** @var string $a */
$estado = estado_ferramenta($f, $a);
inicio_ferramenta($f);
?>
<div class="colunas">
  <section class="cartao">
    <h2>Nova referência</h2>
    <form id="form" class="form-grade">
      <label class="largo">Tipo
        <select name="tipo">
          <option value="livro">Livro</option>
          <option value="capitulo">Capítulo de livro</option>
          <option value="artigo">Artigo de revista / periódico</option>
          <option value="site">Site / página da internet</option>
          <option value="tcc">TCC, dissertação ou tese</option>
          <option value="lei">Lei / legislação</option>
        </select>
      </label>
      <label class="largo" data-t="livro capitulo artigo site tcc">Autores (um por linha, nome completo)
        <textarea name="autores" rows="3" placeholder="Maria da Silva Souza&#10;João Pereira"></textarea>
      </label>
      <label class="check largo" data-t="livro site"><input type="checkbox" name="org"> são organizadores / editores (org.)</label>
      <label class="largo" data-t="lei">Jurisdição <input name="jurisdicao" placeholder="BRASIL"></label>
      <label class="largo" data-t="livro capitulo artigo site tcc lei"><span data-rot="titulo">Título</span> <input name="titulo"></label>
      <label class="largo" data-t="livro capitulo tcc site">Subtítulo (opcional) <input name="subtitulo"></label>
      <label class="largo" data-t="capitulo">Organizadores do livro (um por linha) <textarea name="orgs" rows="2"></textarea></label>
      <label class="largo" data-t="capitulo">Título do livro <input name="livro"></label>
      <label class="largo" data-t="artigo">Nome da revista <input name="revista"></label>
      <label data-t="livro capitulo">Edição (deixe vazio se 1ª) <input name="edicao" type="number" min="2"></label>
      <label data-t="livro capitulo artigo tcc site">Local (cidade) <input name="local" placeholder="Vitória"></label>
      <label data-t="livro capitulo">Editora <input name="editora"></label>
      <label data-t="artigo">Volume <input name="volume"></label>
      <label data-t="artigo">Número <input name="numero"></label>
      <label data-t="artigo lei">Mês (opcional) <select name="mes"><option value=""></option></select></label>
      <label data-t="lei">Dia <input name="dia" type="number" min="1" max="31"></label>
      <label data-t="livro capitulo artigo site tcc lei">Ano <input name="ano" inputmode="numeric"></label>
      <label data-t="capitulo artigo">Páginas <input name="paginas" placeholder="45-67"></label>
      <label data-t="tcc">Tipo do trabalho <select name="tipotcc"><option>Trabalho de Conclusão de Curso</option><option>Dissertação</option><option>Tese</option><option>Monografia</option></select></label>
      <label data-t="tcc">Grau e curso <input name="grau" placeholder="Bacharelado em Sistemas de Informação"></label>
      <label class="largo" data-t="tcc">Instituição <input name="instituicao" placeholder="Instituto Federal do Espírito Santo"></label>
      <label class="largo" data-t="lei">Ementa / descrição <input name="ementa" placeholder="Dispõe sobre..."></label>
      <label class="largo" data-t="lei">Publicação <input name="publicacao" placeholder="Diário Oficial da União: seção 1, Brasília, DF"></label>
      <label class="largo" data-t="livro capitulo artigo site tcc lei">Endereço (URL), se for on-line <input name="url" placeholder="https://..."></label>
      <label data-t="livro capitulo artigo site tcc lei">Acessado em <input name="acesso" type="date"></label>
      <label data-t="artigo">DOI (opcional) <input name="doi" placeholder="10.xxxx/xxxxx"></label>
    </form>
    <h2 style="margin-top:18px">Prévia</h2>
    <div class="saida-texto" id="previa" style="white-space:normal;font-family:inherit"></div>
    <p class="pequeno mudo" id="citacao"></p>
    <div class="grupo"><button class="btn btn-primario" id="add">+ Guardar na lista</button><button class="btn" id="copiar-um">Copiar</button><button class="btn btn-leve" id="limpar">Limpar campos</button></div>
  </section>
  <section class="cartao">
    <div class="cartao-topo">
      <h2>Lista</h2>
      <span class="status pequeno" id="st"></span>
    </div>
    <div class="grupo" style="margin-bottom:10px">
      <select id="trabalho" style="width:auto;flex:1"></select>
      <button class="btn btn-p" id="novo-trab">+ Novo trabalho</button>
    </div>
    <div id="lista"></div>
    <div class="grupo" style="margin-top:12px"><button class="btn" id="copiar-todas">Copiar todas (para o Word)</button></div>
    <p class="dica">Ao colar no Word, o negrito do título vem junto. As referências ficam em ordem alfabética, alinhadas à esquerda e em espaço simples, como pede a norma.</p>
  </section>
</div>
<script>
(function () {
  const est = Object.assign({ trabalhos: {}, atual: '' }, <?= json_js($estado ?: new stdClass()) ?>);
  if (Array.isArray(est.trabalhos) || !Object.keys(est.trabalhos).length) est.trabalhos = { 'Meu trabalho': [] };
  if (!est.trabalhos[est.atual]) est.atual = Object.keys(est.trabalhos)[0];
  const $ = (s) => document.querySelector(s);
  const form = $('#form');
  const salvar = App.autoSalvar(<?= json_js($f->url(['a' => 'salvar_estado'])) ?>, () => est, $('#st'));
  const MESES = ['jan.', 'fev.', 'mar.', 'abr.', 'maio', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.'];
  const MESES_EXT = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  form.mes.innerHTML += MESES_EXT.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const SUFIXOS = /^(junior|júnior|jr\.?|filho|neto|sobrinho|segundo|terceiro)$/i;

  // "Maria da Silva Souza" -> "SOUZA, Maria da Silva"; "Souza Júnior" fica junto
  function autor(nome) {
    nome = nome.trim().replace(/\s+/g, ' ');
    if (!nome) return '';
    if (nome.includes(',')) { const [s, n] = nome.split(',', 2); return `${s.trim().toUpperCase()}, ${n.trim()}`; }
    const p = nome.split(' ');
    if (p.length === 1) return p[0].toUpperCase();
    let sob = p.pop();
    if (SUFIXOS.test(sob) && p.length > 1) sob = p.pop() + ' ' + sob;
    return `${sob.toUpperCase()}, ${p.join(' ')}`;
  }
  function autores(txt, sufixo) {
    const l = String(txt || '').split('\n').map((s) => s.trim()).filter(Boolean).map(autor);
    if (!l.length) return '';
    const s = l.length > 3 ? `${l[0]} et al` : l.join('; ');
    return s + (sufixo || '') + '.';
  }
  const sobrenome1 = (txt) => { const a = String(txt || '').split('\n').map((s) => s.trim()).filter(Boolean); if (!a.length) return ''; return autor(a[0]).split(',')[0] + (a.length > 3 ? ' et al.' : a.length === 2 ? '; ' + autor(a[1]).split(',')[0] : a.length === 3 ? '; ' + autor(a[1]).split(',')[0] + '; ' + autor(a[2]).split(',')[0] : ''); };
  const acesso = (d) => { if (!d) return ''; const [y, m, dd] = d.split('-').map(Number); return ` Acesso em: ${dd} ${MESES[m - 1]} ${y}.`; };
  const online = (r) => (r.url ? ` Disponível em: ${esc(r.url)}.` + acesso(r.acesso) : '');
  const tit = (r) => `<strong>${esc(r.titulo)}</strong>${r.subtitulo ? ': ' + esc(r.subtitulo) : ''}`;
  const pontoFinal = (s) => s.replace(/\.\s*\./g, '.').replace(/\s+/g, ' ').trim();

  function montar(r) {
    const ed = r.edicao && +r.edicao > 1 ? ` ${r.edicao}. ed.` : '';
    const imp = `${esc(r.local || '[S. l.]')}: ${esc(r.editora || '[s. n.]')}, ${esc(r.ano || '[20--]')}.`;
    let s = '';
    switch (r.tipo) {
      case 'livro':
        s = `${autores(r.autores, r.org ? ' (org.)' : '')} ${tit(r)}.${ed} ${imp}${online(r)}`; break;
      case 'capitulo':
        s = `${autores(r.autores)} ${esc(r.titulo)}${r.subtitulo ? ': ' + esc(r.subtitulo) : ''}. <em>In</em>: ${autores(r.orgs, ' (org.)')} <strong>${esc(r.livro)}</strong>.${ed} ${imp}${r.paginas ? ' p. ' + esc(r.paginas.replace(/\s*[-–]\s*/, '-')) + '.' : ''}${online(r)}`;
        s = s.replace(/\.\. /g, '. '); break;
      case 'artigo': {
        const partes = [`<strong>${esc(r.revista)}</strong>`];
        if (r.local) partes.push(esc(r.local));
        if (r.volume) partes.push('v. ' + esc(r.volume));
        if (r.numero) partes.push('n. ' + esc(r.numero));
        if (r.paginas) partes.push('p. ' + esc(r.paginas.replace(/\s*[-–]\s*/, '-')));
        partes.push((r.mes ? MESES[r.mes - 1] + ' ' : '') + esc(r.ano || '[20--]'));
        s = `${autores(r.autores)} ${esc(r.titulo)}. ${partes.join(', ')}.${r.doi ? ' DOI: ' + esc(r.doi) + '.' : ''}${online(r)}`; break;
      }
      case 'site':
        s = `${autores(r.autores, r.org ? ' (org.)' : '')} ${tit(r)}.${r.local ? ' ' + esc(r.local) + ',' : ''} ${esc(r.ano || '[20--]')}.${online(r)}`; break;
      case 'tcc':
        s = `${autores(r.autores)} ${tit(r)}. ${esc(r.ano)}. ${esc(r.tipotcc)}${r.grau ? ' (' + esc(r.grau) + ')' : ''} – ${esc(r.instituicao)}, ${esc(r.local)}, ${esc(r.ano)}.${online(r)}`; break;
      case 'lei': {
        const data = [r.dia, r.mes ? MESES_EXT[r.mes - 1] : '', r.ano].filter(Boolean).join(' de ');
        s = `${esc((r.jurisdicao || 'BRASIL').toUpperCase())}. <strong>${esc(r.titulo)}</strong>${data ? ', de ' + esc(data) : ''}. ${esc(r.ementa)}${r.publicacao ? '. ' + esc(r.publicacao) : ''}${r.ano ? ', ' + esc(r.ano) : ''}.${online(r)}`; break;
      }
    }
    return pontoFinal(s.replace(/^\s*\.\s*/, ''));
  }
  function citacao(r) {
    const ano = r.ano || 's.d.';
    if (r.tipo === 'lei') return `(${(r.jurisdicao || 'BRASIL').toUpperCase()}, ${ano})`;
    const s = sobrenome1(r.autores);
    if (!s) return `(${(r.titulo || '').split(' ')[0].toUpperCase()}..., ${ano})`;
    return `(${s}, ${ano})`;
  }
  const lerForm = () => { const r = {}; new FormData(form).forEach((v, k) => (r[k] = typeof v === 'string' ? v.trim() : v)); r.org = form.org.checked; return r; };
  function atualizarForm() {
    const t = form.tipo.value;
    form.querySelectorAll('[data-t]').forEach((el) => (el.hidden = !el.dataset.t.split(' ').includes(t)));
    form.querySelector('[data-rot=titulo]').textContent = t === 'capitulo' ? 'Título do capítulo' : t === 'artigo' ? 'Título do artigo' : t === 'lei' ? 'Título (ex.: Lei nº 9.394)' : 'Título';
    previa();
  }
  function previa() {
    const r = lerForm();
    $('#previa').innerHTML = r.titulo ? montar(r) : '<span class="mudo">Preencha os campos para ver a referência.</span>';
    const cit = citacao(r);
    const m = cit.match(/^\((.*), ([^,]+)\)$/);
    const narrativa = m ? m[1].toLowerCase().replace(/(^|[\s;])(\p{L})/gu, (x, a, l) => a + l.toUpperCase()).replace(/ Et Al\./, ' et al.').split('; ').reduce((acc, n, i, arr) => (i === 0 ? n : acc + (i === arr.length - 1 ? ' e ' : ', ') + n), '') + ` (${m[2]})` : '';
    $('#citacao').textContent = r.titulo ? `Citação no texto: ${cit}` + (narrativa ? ` — ou, na frase: ${narrativa}` : '') : '';
  }
  form.addEventListener('input', previa);
  form.tipo.addEventListener('change', atualizarForm);
  if (!form.acesso.value) form.acesso.value = new Date().toISOString().slice(0, 10);

  const ordenar = (l) => l.slice().sort((a, b) => montar(a).replace(/<[^>]+>/g, '').localeCompare(montar(b).replace(/<[^>]+>/g, ''), 'pt-BR'));
  function desenharLista() {
    $('#trabalho').innerHTML = Object.keys(est.trabalhos).map((n) => `<option ${n === est.atual ? 'selected' : ''}>${esc(n)}</option>`).join('');
    const l = est.trabalhos[est.atual] || [];
    const ord = ordenar(l);
    $('#lista').innerHTML = ord.length ? ord.map((r) => `<div class="modelo"><div style="margin-bottom:6px">${montar(r)}</div>
      <div class="grupo"><span class="mudo pequeno">${esc(citacao(r))}</span><span style="margin-left:auto"></span>
      <button class="btn btn-p" data-ed="${l.indexOf(r)}">Editar</button><button class="btn btn-p btn-leve" data-rm="${l.indexOf(r)}">Remover</button></div></div>`).join('')
      : '<p class="mudo">Nenhuma referência neste trabalho.</p>';
  }
  async function copiarHtml(html, botao) {
    const texto = html.replace(/<br>/g, '\n').replace(/<\/p>/g, '\n').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"');
    const corpo = `<div style="font-family:'Times New Roman',serif;font-size:12pt">${html}</div>`;
    try {
      await navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([corpo], { type: 'text/html' }), 'text/plain': new Blob([texto], { type: 'text/plain' }) })]);
      const t = botao.textContent; botao.textContent = 'Copiado'; setTimeout(() => (botao.textContent = t), 1400);
    } catch (e) { App.copiar(texto, botao); }
  }
  let editando = null;
  $('#add').onclick = () => {
    const r = lerForm();
    if (!r.titulo) return alert('Preencha pelo menos o título.');
    const l = est.trabalhos[est.atual];
    if (editando !== null) { l[editando] = r; editando = null; $('#add').textContent = '+ Guardar na lista'; }
    else l.push(r);
    desenharLista(); salvar();
  };
  $('#copiar-um').onclick = (e) => { const r = lerForm(); if (r.titulo) copiarHtml(montar(r), e.target); };
  $('#copiar-todas').onclick = (e) => copiarHtml(ordenar(est.trabalhos[est.atual]).map((r) => `<p style="margin:0 0 12pt;text-align:left">${montar(r)}</p>`).join(''), e.target);
  $('#limpar').onclick = () => { const t = form.tipo.value; form.reset(); form.tipo.value = t; form.acesso.value = new Date().toISOString().slice(0, 10); editando = null; $('#add').textContent = '+ Guardar na lista'; atualizarForm(); };
  $('#lista').addEventListener('click', (e) => {
    const l = est.trabalhos[est.atual];
    if (e.target.dataset.rm !== undefined) { if (confirm('Remover esta referência?')) { l.splice(+e.target.dataset.rm, 1); desenharLista(); salvar(); } }
    if (e.target.dataset.ed !== undefined) {
      editando = +e.target.dataset.ed;
      const r = l[editando];
      form.reset();
      for (const [k, v] of Object.entries(r)) { if (form[k]) { if (form[k].type === 'checkbox') form[k].checked = !!v; else form[k].value = v; } }
      $('#add').textContent = 'Salvar alteração';
      atualizarForm();
      form.scrollIntoView({ behavior: 'smooth' });
    }
  });
  $('#trabalho').onchange = (e) => { est.atual = e.target.value; desenharLista(); salvar(); };
  $('#novo-trab').onclick = () => { const n = prompt('Nome do trabalho:'); if (n && !est.trabalhos[n.trim()]) { est.trabalhos[n.trim()] = []; est.atual = n.trim(); desenharLista(); salvar(); } };
  atualizarForm();
  desenharLista();
})();
</script>
<?php rodape(); ?>
