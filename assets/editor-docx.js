// Editor visual de .docx: edita textos e estrutura mantendo a formatação original.
// O modelo vem do PHP (lib/docx.php). Cada item tem "src" apontando para o nó original no XML;
// duplicar algo aqui = o PHP clona aquele nó original, então a formatação vem junto.
(function () {
  const D = JSON.parse(document.getElementById('dados-editor').textContent);
  let modelo = D.modelo;
  let hash = D.hash;
  const ed = document.getElementById('editor');
  const status = document.getElementById('status');
  const mapa = new WeakMap(); // bloco do modelo -> elemento .par
  const pilha = [];
  let sujo = false;

  const PLAIN = (() => { const d = document.createElement('div'); try { d.contentEditable = 'plaintext-only'; } catch (e) { return false; } return d.contentEditable === 'plaintext-only'; })();
  const clone = (o) => JSON.parse(JSON.stringify(o));
  const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };

  function marcarSujo(v = true) {
    sujo = v;
    status.classList.toggle('sujo', v);
    if (v) status.textContent = '● Alterações não salvas';
  }

  // ------------------------------------------------------------------ formatação -> CSS
  function css(f) {
    if (!f) return '';
    let s = '';
    if (f.b) s += 'font-weight:bold;';
    if (f.i) s += 'font-style:italic;';
    const dec = [f.u ? 'underline' : '', f.s ? 'line-through' : ''].filter(Boolean).join(' ');
    if (dec) s += `text-decoration:${dec};`;
    if (f.sz) s += `font-size:${f.sz}pt;`;
    if (f.cor) s += `color:${f.cor};`;
    if (f.fonte) s += `font-family:"${f.fonte.replace(/"/g, '')}",Calibri,Arial,sans-serif;`;
    if (f.fundo) s += `background:${f.fundo};`;
    if (f.va === 'superscript') s += 'vertical-align:super;font-size:smaller;';
    if (f.va === 'subscript') s += 'vertical-align:sub;font-size:smaller;';
    if (f.caps) s += 'text-transform:uppercase;';
    return s;
  }

  // ------------------------------------------------------------------ renderização
  function render() {
    const y = window.scrollY;
    ed.innerHTML = '';
    const pg = modelo.pagina;
    const ordem = (p) => (p.nome.includes('header') ? 0 : p.nome === 'word/document.xml' ? 1 : 2);
    [...modelo.partes].sort((a, b) => ordem(a) - ordem(b)).forEach((parte) => {
      const sec = parte.nome !== 'word/document.xml';
      if (sec) ed.appendChild(el('div', 'parte-titulo', parte.rotulo));
      const folha = el('div', 'folha' + (sec ? ' secundaria' : ''));
      folha.style.width = pg.w / 20 + 'pt';
      folha.style.padding = `${pg.mt / 20}pt ${pg.md / 20}pt ${pg.mb / 20}pt ${pg.me / 20}pt`;
      if (!sec) folha.style.minHeight = pg.h / 20 + 'pt';
      folha.style.maxWidth = 'none';
      if (!sec && modelo.partes.length > 1) ed.appendChild(el('div', 'parte-titulo', 'Documento'));
      renderBlocos(parte.blocos, folha);
      ed.appendChild(folha);
    });
    window.scrollTo(0, y);
  }

  const OCULTOS = ['sectPr', 'tcPr', 'bookmarkStart', 'bookmarkEnd', 'proofErr', 'permStart', 'permEnd', 'commentRangeStart', 'commentRangeEnd'];

  function renderBlocos(lista, cont) {
    lista.forEach((b) => {
      if (b.t === 'p') cont.appendChild(renderPar(b, lista));
      else if (b.t === 'tbl') cont.appendChild(renderTabela(b, lista));
      else if (!OCULTOS.includes(b.n)) {
        const r = el('div', 'raw-bloco', '🔒 ' + (b.txt || `[${b.n}]`));
        r.title = 'Este trecho não é editável aqui, mas será mantido.';
        cont.appendChild(r);
      }
    });
  }

  function renderPar(b, lista) {
    const wrap = el('div', 'par' + (b.li ? ' lista-item' : ''));
    const txt = el('div', 'par-txt');
    txt.contentEditable = PLAIN ? 'plaintext-only' : 'true';
    txt.spellcheck = true;
    if (b.jc) txt.style.textAlign = b.jc;
    const prim = b.r.find((r) => !r.ro);
    if (prim && prim.f) {
      if (prim.f.sz) txt.style.fontSize = prim.f.sz + 'pt';
      if (prim.f.fonte) txt.style.fontFamily = `"${prim.f.fonte}",Calibri,Arial,sans-serif`;
    }
    b.r.forEach((r, i) => {
      if (r.ro) {
        let s;
        if (r.k === 'pb') s = el('span', 'marca-pb', '— quebra de página —');
        else if (r.k === 'img' && r.img) {
          s = el('img', 'img-doc');
          s.src = D.urls.midia + '&p=' + encodeURIComponent(r.img.p);
          if (r.img.w) s.style.width = r.img.w + 'pt';
          s.alt = 'imagem';
        } else if (r.k === 'img') s = el('span', 'chip', 'imagem');
        else if (r.k === 'fld' && !r.x) return;
        else s = el('span', 'run-ro', r.x || '');
        if (r.k === 'obj' && !r.x) s = el('span', 'chip', 'objeto');
        s.contentEditable = 'false';
        txt.appendChild(s);
        return;
      }
      const s = el('span', 'run', r.x);
      s.dataset.i = i;
      s.style.cssText = css(r.f);
      txt.appendChild(s);
    });
    wrap._b = b;
    wrap._lista = lista;
    mapa.set(b, wrap);

    const ferr = el('div', 'ferr-par');
    ferr.contentEditable = 'false';
    [['＋', 'Nova linha abaixo', () => inserirPar(b, lista, false)], ['⧉', 'Duplicar esta linha', () => inserirPar(b, lista, true)], ['✕', 'Remover esta linha', () => removerPar(b, lista)]].forEach(([t, title, fn]) => {
      const bt = el('button', null, t);
      bt.title = title;
      bt.type = 'button';
      bt.addEventListener('mousedown', (e) => e.preventDefault());
      bt.addEventListener('click', fn);
      ferr.appendChild(bt);
    });
    wrap.appendChild(ferr);
    wrap.appendChild(txt);
    return wrap;
  }

  function renderTabela(b, lista) {
    const wrap = el('div', 'tab-wrap');
    const ferr = el('div', 'ferr-tab');
    const nL = b.l.length;
    const nC = b.grid.length || (b.l[0] ? b.l[0].c.length : 0);
    ferr.innerHTML = `<strong>Tabela</strong>
      <label>Linhas <input type="number" min="1" max="500" value="${nL}" data-k="l"></label>
      <label>Colunas <input type="number" min="1" max="30" value="${nC}" data-k="c" ${b.m ? 'disabled title="Tabela com células mescladas: só dá para mudar linhas"' : ''}></label>
      <button type="button" data-k="aplicar">Aplicar</button>
      <button type="button" data-k="mais-l">＋ Linha</button>
      ${b.m ? '' : '<button type="button" data-k="mais-c">＋ Coluna</button>'}
      <button type="button" data-k="colar" title="Cole células copiadas do Excel/Google Planilhas">📋 Colar do Excel</button>
      ${D.planilhas && D.planilhas.length ? '<button type="button" data-k="importar">📥 Importar planilha</button>' : ''}
      <button type="button" data-k="limpar" title="Apaga o texto das linhas (menos a primeira)">🧹 Limpar</button>`;
    ferr.addEventListener('click', (e) => {
      const k = e.target.dataset.k;
      if (!k) return;
      if (k === 'aplicar') definirTamanho(b, +ferr.querySelector('[data-k=l]').value, +ferr.querySelector('[data-k=c]').value);
      if (k === 'mais-l') definirTamanho(b, b.l.length + 1, null);
      if (k === 'mais-c') definirTamanho(b, null, (b.grid.length || b.l[0].c.length) + 1);
      if (k === 'colar') colarExcel(b);
      if (k === 'importar') importarPlanilha(b);
      if (k === 'limpar') estrutura(() => b.l.slice(1).forEach(limparLinha));
    });
    ferr.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); ferr.querySelector('[data-k=aplicar]').click(); } });
    wrap.appendChild(ferr);

    const t = el('table', 'doc');
    const total = b.grid.reduce((a, x) => a + x, 0);
    if (total) {
      const cg = el('colgroup');
      b.grid.forEach((w) => { const c = el('col'); c.style.width = w / 20 + 'pt'; cg.appendChild(c); });
      cg.appendChild(el('col'));
      t.appendChild(cg);
      t.style.width = total / 20 + 26 + 'pt';
    }
    if (!b.m && nC > 1) {
      const tr = el('tr', 'ctrl-col');
      for (let c = 0; c < nC; c++) {
        const td = el('td', 'ctrl-linha');
        td.style.cssText = 'text-align:center;padding:0 0 2px!important;width:auto';
        const x = el('button', null, '✕');
        x.title = 'Remover esta coluna';
        x.type = 'button';
        x.onclick = () => removerColuna(b, c);
        const mais = el('button', null, '＋');
        mais.title = 'Inserir coluna depois desta';
        mais.type = 'button';
        mais.onclick = () => inserirColuna(b, c);
        const box = el('div', 'ferr-linha');
        box.style.cssText = 'flex-direction:row;justify-content:center;visibility:visible;opacity:.35';
        box.onmouseenter = () => (box.style.opacity = 1);
        box.onmouseleave = () => (box.style.opacity = 0.35);
        box.append(mais, x);
        td.appendChild(box);
        tr.appendChild(td);
      }
      t.appendChild(tr);
    }
    b.l.forEach((linha, li) => {
      const tr = el('tr');
      linha.c.forEach((cel) => {
        const td = el('td');
        if (cel.span > 1) td.colSpan = cel.span;
        if (cel.vm === 'continue') td.style.borderTopColor = 'transparent';
        if (cel.fundo) td.style.background = cel.fundo;
        renderBlocos(cel.b, td);
        tr.appendChild(td);
      });
      const ctrl = el('td', 'ctrl-linha');
      const box = el('div', 'ferr-linha');
      [['↑＋', 'Inserir linha acima', () => inserirLinha(b, li, true)], ['↓＋', 'Inserir linha abaixo', () => inserirLinha(b, li, false)], ['✕', 'Remover linha', () => removerLinha(b, li)]].forEach(([tx, title, fn]) => {
        const bt = el('button', null, tx);
        bt.title = title;
        bt.type = 'button';
        bt.onclick = fn;
        box.appendChild(bt);
      });
      ctrl.appendChild(box);
      tr.appendChild(ctrl);
      t.appendChild(tr);
    });
    wrap.appendChild(t);
    return wrap;
  }

  // ------------------------------------------------------------------ DOM -> modelo
  function textoDe(n) {
    let s = '';
    n.childNodes.forEach((c) => {
      if (c.nodeType === 3) s += c.data;
      else if (c.nodeName === 'BR') s += '\n';
      else if (c.nodeType === 1 && c.contentEditable !== 'false') {
        if ((c.nodeName === 'DIV' || c.nodeName === 'P') && s && !s.endsWith('\n')) s += '\n';
        s += textoDe(c);
      }
    });
    return s;
  }

  function lerPar(wrap) {
    const b = wrap._b;
    const txt = wrap.querySelector('.par-txt');
    const textos = b.r.map(() => '');
    const primeiro = b.r.findIndex((r) => !r.ro);
    let ultimo = null;
    txt.childNodes.forEach((n) => {
      if (n.nodeType === 3) { textos[ultimo ?? primeiro] += n.data; return; }
      if (n.nodeType !== 1) return;
      if (n.contentEditable === 'false') return;
      if (n.dataset && n.dataset.i !== undefined) { const i = +n.dataset.i; textos[i] += textoDe(n); ultimo = i; return; }
      if (n.nodeName === 'BR') { textos[ultimo ?? primeiro] += '\n'; return; }
      textos[ultimo ?? primeiro] += textoDe(n);
    });
    b.r.forEach((r, i) => { if (!r.ro) r.x = textos[i]; });
  }

  function sincronizar() {
    ed.querySelectorAll('.par').forEach(lerPar);
  }

  function estrutura(fn) {
    sincronizar();
    pilha.push(JSON.stringify(modelo));
    if (pilha.length > 60) pilha.shift();
    const r = fn();
    render();
    marcarSujo();
    return r;
  }

  // ------------------------------------------------------------------ cursor
  function focar(b, runIdx, offset, fim) {
    const wrap = mapa.get(b);
    if (!wrap) return;
    const txt = wrap.querySelector('.par-txt');
    txt.focus();
    const spans = [...txt.querySelectorAll('.run')];
    let alvo = runIdx != null ? txt.querySelector(`.run[data-i="${runIdx}"]`) : fim ? spans[spans.length - 1] : spans[0];
    const sel = getSelection();
    const rg = document.createRange();
    if (!alvo) { rg.selectNodeContents(txt); rg.collapse(!fim); sel.removeAllRanges(); sel.addRange(rg); return; }
    if (!alvo.firstChild) alvo.appendChild(document.createTextNode(''));
    const tn = alvo.firstChild;
    const len = tn.nodeType === 3 ? tn.length : 0;
    const off = fim && offset == null ? len : Math.min(offset || 0, len);
    if (tn.nodeType === 3) rg.setStart(tn, off); else rg.selectNodeContents(alvo);
    rg.collapse(true);
    sel.removeAllRanges();
    sel.addRange(rg);
  }

  const MARCA = '';
  /** Descobre em qual run/posição está o cursor dentro do parágrafo. */
  function posicaoCursor(wrap) {
    const sel = getSelection();
    if (!sel.rangeCount) return null;
    const rg = sel.getRangeAt(0);
    if (!rg.collapsed) rg.deleteContents();
    const m = document.createTextNode(MARCA);
    rg.insertNode(m);
    lerPar(wrap);
    m.remove();
    const b = wrap._b;
    for (let i = 0; i < b.r.length; i++) {
      const k = b.r[i].ro ? -1 : b.r[i].x.indexOf(MARCA);
      if (k >= 0) { b.r[i].x = b.r[i].x.replace(MARCA, ''); return { run: i, off: k }; }
    }
    return null;
  }

  function textoPar(b) { return b.r.filter((r) => !r.ro).map((r) => r.x).join(''); }

  // ------------------------------------------------------------------ operações em parágrafos
  function parVazio(b) {
    const n = clone(b);
    n.r.forEach((r) => { if (!r.ro) r.x = ''; });
    n.r = n.r.filter((r) => !r.ro || r.k === 'fld');
    if (!n.r.some((r) => !r.ro)) n.r.push({ src: -1, x: '', f: (b.r.find((r) => !r.ro) || {}).f || {} });
    return n;
  }

  function inserirPar(b, lista, duplicar) {
    let novo;
    estrutura(() => {
      novo = duplicar ? clone(b) : parVazio(b);
      lista.splice(lista.indexOf(b) + 1, 0, novo);
    });
    focar(novo, null, null, duplicar);
  }

  function podeRemover(b, lista) {
    return lista.filter((x) => x.t === 'p').length > 1 || lista.some((x) => x.t === 'tbl');
  }

  function removerPar(b, lista) {
    if (!podeRemover(b, lista)) { alert('Este é o único parágrafo deste local; não dá para remover. Apague o texto se quiser.'); return; }
    const i = lista.indexOf(b);
    const ant = lista.slice(0, i).reverse().find((x) => x.t === 'p');
    estrutura(() => lista.splice(i, 1));
    if (ant) focar(ant, null, null, true);
  }

  function aoTeclar(e) {
    const txt = e.target.closest('.par-txt');
    if (!txt) return;
    const wrap = txt.parentNode;
    const b = wrap._b;
    const lista = wrap._lista;
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
      e.preventDefault();
      sincronizar();
      const pos = posicaoCursor(wrap);
      if (!pos) return;
      let novo;
      estrutura(() => {
        novo = clone(b);
        b.r.forEach((r, i) => {
          if (r.ro) return;
          if (i < pos.run) novo.r[i].x = '';
          else if (i === pos.run) { novo.r[i].x = r.x.slice(pos.off); r.x = r.x.slice(0, pos.off); }
          else r.x = '';
        });
        const srcCursor = b.r[pos.run].src;
        const f = b.r[pos.run].f;
        // o que é fixo (imagens etc.) fica do lado em que estava
        novo.r = novo.r.filter((r, i) => !r.ro || i > pos.run || r.k === 'fld');
        b.r = b.r.filter((r, i) => !r.ro || i <= pos.run || r.k === 'fld');
        if (!novo.r.some((r) => !r.ro)) novo.r.push({ src: -1, x: '', f });
        lista.splice(lista.indexOf(b) + 1, 0, novo);
        novo._foco = Math.max(0, novo.r.findIndex((r) => !r.ro && r.src === srcCursor));
      });
      focar(novo, novo._foco, 0);
      delete novo._foco;
      return;
    }
    if (e.key === 'Enter' && e.shiftKey) {
      e.preventDefault();
      inserirTexto('\n');
      return;
    }
    if (e.key === 'Backspace') {
      const sel = getSelection();
      if (!sel.isCollapsed) return;
      const rg = sel.getRangeAt(0).cloneRange();
      rg.selectNodeContents(txt);
      rg.setEnd(sel.anchorNode, sel.anchorOffset);
      if (rg.toString().length > 0) return;
      const i = lista.indexOf(b);
      const ant = lista[i - 1];
      lerPar(wrap);
      if (!textoPar(b)) {
        if (!podeRemover(b, lista)) return;
        e.preventDefault();
        estrutura(() => lista.splice(i, 1));
        if (ant && ant.t === 'p') focar(ant, null, null, true);
        return;
      }
      if (ant && ant.t === 'p' && ant.r.some((r) => !r.ro)) {
        e.preventDefault();
        sincronizar();
        const ultimoIdx = ant.r.map((r, k) => (r.ro ? -1 : k)).filter((k) => k >= 0).pop();
        const tamAnt = ant.r[ultimoIdx].x.length;
        estrutura(() => {
          ant.r[ultimoIdx].x += textoPar(b);
          lista.splice(i, 1);
        });
        focar(ant, ultimoIdx, tamAnt);
      }
    }
  }

  function inserirTexto(s) {
    const sel = getSelection();
    if (!sel.rangeCount) return;
    const rg = sel.getRangeAt(0);
    rg.deleteContents();
    const n = document.createTextNode(s);
    rg.insertNode(n);
    rg.setStartAfter(n);
    rg.collapse(true);
    sel.removeAllRanges();
    sel.addRange(rg);
    marcarSujo();
  }

  ed.addEventListener('keydown', aoTeclar);
  ed.addEventListener('input', () => marcarSujo());
  ed.addEventListener('paste', (e) => {
    if (!e.target.closest('.par-txt')) return;
    e.preventDefault();
    const t = (e.clipboardData.getData('text/plain') || '').replace(/\r\n?/g, '\n');
    inserirTexto(t);
  });
  ed.addEventListener('drop', (e) => e.preventDefault());

  // ------------------------------------------------------------------ tabelas
  function limparBlocos(blocos) {
    let achouP = false;
    for (let i = blocos.length - 1; i >= 0; i--) {
      const x = blocos[i];
      if (x.t === 'tbl') x.l.forEach(limparLinha);
    }
    const out = [];
    blocos.forEach((x) => {
      if (x.t === 'p') {
        if (achouP) return;
        achouP = true;
        x.r.forEach((r) => { if (!r.ro) r.x = ''; });
        x.r = x.r.filter((r) => !r.ro || r.k === 'fld');
        if (!x.r.some((r) => !r.ro)) x.r.push({ src: -1, x: '', f: {} });
      }
      out.push(x);
    });
    blocos.splice(0, blocos.length, ...out);
  }
  function limparLinha(l) { l.c.forEach((c) => limparBlocos(c.b)); }
  function linhaTemTexto(l) { return l.c.some((c) => c.b.some((x) => x.t === 'p' && textoPar(x).trim())); }

  function reescalar(b, totalAntes) {
    const soma = b.grid.reduce((a, x) => a + x, 0);
    if (!soma || !totalAntes) return;
    b.grid = b.grid.map((w) => Math.round((w * totalAntes) / soma));
  }

  function definirTamanho(b, nL, nC) {
    sincronizar();
    if (nL && nL < b.l.length) {
      const perdidas = b.l.slice(nL).filter(linhaTemTexto).length;
      if (perdidas && !confirm(`Isso vai apagar ${perdidas} linha(s) com texto no final da tabela. Continuar?`)) return;
    }
    estrutura(() => {
      if (nL && nL >= 1) {
        while (b.l.length < nL) { const n = clone(b.l[b.l.length - 1]); limparLinha(n); b.l.push(n); }
        b.l.length = Math.min(b.l.length, nL);
      }
      if (nC && nC >= 1 && !b.m) {
        const total = b.grid.reduce((a, x) => a + x, 0);
        while (b.grid.length < nC) {
          b.grid.push(b.grid[b.grid.length - 1] || 1000);
          b.l.forEach((l) => { const c = clone(l.c[l.c.length - 1]); limparBlocos(c.b); l.c.push(c); });
        }
        while (b.grid.length > nC) { b.grid.pop(); b.l.forEach((l) => l.c.pop()); }
        reescalar(b, total);
      }
    });
  }

  function inserirLinha(b, i, acima) {
    estrutura(() => { const n = clone(b.l[i]); limparLinha(n); b.l.splice(acima ? i : i + 1, 0, n); });
  }
  function removerLinha(b, i) {
    if (b.l.length <= 1) { alert('A tabela precisa de pelo menos uma linha.'); return; }
    estrutura(() => b.l.splice(i, 1));
  }
  function inserirColuna(b, c) {
    estrutura(() => {
      const total = b.grid.reduce((a, x) => a + x, 0);
      b.grid.splice(c + 1, 0, b.grid[c]);
      b.l.forEach((l) => { const n = clone(l.c[c]); limparBlocos(n.b); l.c.splice(c + 1, 0, n); });
      reescalar(b, total);
    });
  }
  function removerColuna(b, c) {
    if (b.grid.length <= 1) return;
    estrutura(() => {
      const total = b.grid.reduce((a, x) => a + x, 0);
      b.grid.splice(c, 1);
      b.l.forEach((l) => l.c.splice(c, 1));
      reescalar(b, total);
    });
  }

  function textoCelula(cel, txt) {
    const p = cel.b.find((x) => x.t === 'p');
    if (!p) return;
    cel.b = cel.b.filter((x) => x.t !== 'p' || x === p);
    let pos = false;
    p.r.forEach((r) => { if (r.ro) return; r.x = pos ? '' : txt; pos = true; });
  }

  /** Preenche a tabela com uma matriz de textos. */
  function preencher(b, matriz, manterCab, ajustar) {
    const ini = manterCab ? 1 : 0;
    estrutura(() => {
      if (ajustar) {
        const n = Math.max(1, ini + matriz.length);
        while (b.l.length < n) { const x = clone(b.l[b.l.length - 1]); limparLinha(x); b.l.push(x); }
        b.l.length = n;
      }
      matriz.forEach((linha, i) => {
        const l = b.l[ini + i];
        if (!l) return;
        l.c.forEach((cel, j) => textoCelula(cel, (linha[j] ?? '').toString()));
      });
    });
  }

  async function colarExcel(b) {
    const corpo = el('div');
    corpo.innerHTML = `<p class="mudo pequeno">Copie as células no Excel / Google Planilhas e cole aqui (Ctrl+V). Cada linha copiada vira uma linha da tabela, na ordem das colunas.</p>
      <textarea rows="8" placeholder="Cole aqui..."></textarea>
      <label class="check"><input type="checkbox" class="cab" ${b.l.length > 1 ? 'checked' : ''}> Manter a 1ª linha da tabela (cabeçalho)</label>
      <label class="check"><input type="checkbox" class="aj" checked> Ajustar a quantidade de linhas</label>`;
    const r = await App.modal('Colar dados do Excel', corpo, [['Cancelar', null], ['Preencher tabela', (c) => ({ t: c.querySelector('textarea').value, cab: c.querySelector('.cab').checked, aj: c.querySelector('.aj').checked }), true]]);
    if (!r || !r.t.trim()) return;
    const matriz = r.t.replace(/\r\n?/g, '\n').replace(/\n+$/, '').split('\n').map((l) => l.split('\t'));
    preencher(b, matriz, r.cab, r.aj);
  }

  async function importarPlanilha(b) {
    const corpo = el('div');
    corpo.innerHTML = `<label>Arquivo <select class="arq">${D.planilhas.map((p) => `<option value="${p.rel}">${p.nome}</option>`).join('')}</select></label>
      <label>Aba <select class="aba"><option>carregando…</option></select></label>
      <div class="previa saida-texto pequeno" style="max-height:180px">…</div>
      <label class="check"><input type="checkbox" class="pular" checked> Pular a 1ª linha da planilha (títulos)</label>
      <label class="check"><input type="checkbox" class="cab" ${b.l.length > 1 ? 'checked' : ''}> Manter a 1ª linha da tabela (cabeçalho)</label>
      <label class="check"><input type="checkbox" class="vazias" checked> Ignorar linhas vazias</label>`;
    let dados = null;
    const selArq = corpo.querySelector('.arq');
    const selAba = corpo.querySelector('.aba');
    const previa = () => {
      const p = dados && dados.planilhas[+selAba.value];
      corpo.querySelector('.previa').textContent = p ? p.m.slice(0, 8).map((l) => l.join(' | ')).join('\n') + (p.m.length > 8 ? `\n… (${p.m.length} linhas)` : '') : '';
    };
    const carregar = async () => {
      selAba.innerHTML = '<option>carregando…</option>';
      const r = await fetch(D.urls.planilha + '&f=' + encodeURIComponent(selArq.value), { headers: { Accept: 'application/json' } });
      dados = await r.json();
      if (dados.erro) { corpo.querySelector('.previa').textContent = dados.erro; return; }
      selAba.innerHTML = dados.planilhas.map((p, i) => `<option value="${i}">${p.nome}</option>`).join('');
      selAba.value = dados.planilhas.length - 1;
      previa();
    };
    selArq.onchange = carregar;
    selAba.onchange = previa;
    carregar();
    const r = await App.modal('Importar planilha para a tabela', corpo, [['Cancelar', null], ['Importar', (c) => ({ aba: +selAba.value, pular: c.querySelector('.pular').checked, cab: c.querySelector('.cab').checked, vazias: c.querySelector('.vazias').checked }), true]]);
    if (!r || !dados || !dados.planilhas[r.aba]) return;
    let m = dados.planilhas[r.aba].m.slice(r.pular ? 1 : 0);
    if (r.vazias) m = m.filter((l) => l.some((v) => String(v).trim()));
    preencher(b, m, r.cab, true);
    const nome = dados.planilhas[r.aba].nome;
    status.textContent = `Importado da aba "${nome}". Dica: use Localizar/substituir para trocar o mês no título.`;
  }

  // ------------------------------------------------------------------ localizar / substituir
  function substituirPar(p, de, para, ci) {
    const idx = [];
    const ini = [];
    let texto = '';
    p.r.forEach((r, i) => { if (!r.ro) { idx.push(i); ini.push(texto.length); texto += r.x; } });
    const base = ci ? texto.toLowerCase() : texto;
    const alvo = ci ? de.toLowerCase() : de;
    const pos = [];
    for (let o = 0, k; (k = base.indexOf(alvo, o)) !== -1; o = k + alvo.length) pos.push(k);
    pos.reverse().forEach((k) => {
      const fim = k + de.length;
      let a = null, z = null;
      idx.forEach((ri, j) => {
        const s = ini[j], e = s + p.r[ri].x.length;
        if (a === null && k >= s && k < e) a = j;
        if (fim > s && fim <= e) z = j;
      });
      if (a === null || z === null) return;
      const ra = idx[a], rz = idx[z];
      const xa = p.r[ra].x, xz = p.r[rz].x;
      if (a === z) p.r[ra].x = xa.slice(0, k - ini[a]) + para + xa.slice(fim - ini[a]);
      else {
        p.r[ra].x = xa.slice(0, k - ini[a]) + para;
        for (let j = a + 1; j < z; j++) p.r[idx[j]].x = '';
        p.r[rz].x = xz.slice(fim - ini[z]);
      }
    });
    return pos.length;
  }
  function substituirBlocos(blocos, de, para, ci) {
    let n = 0;
    blocos.forEach((b) => {
      if (b.t === 'p') n += substituirPar(b, de, para, ci);
      else if (b.t === 'tbl') b.l.forEach((l) => l.c.forEach((c) => (n += substituirBlocos(c.b, de, para, ci))));
    });
    return n;
  }
  function contar(de, ci) {
    sincronizar();
    let n = 0;
    const tmp = clone(modelo);
    tmp.partes.forEach((p) => (n += substituirBlocos(p.blocos, de, de, ci)));
    return n;
  }

  const painel = document.getElementById('painel-busca');
  const inDe = document.getElementById('busca-de');
  const inPara = document.getElementById('busca-para');
  const inCi = document.getElementById('busca-maius');
  const info = document.getElementById('busca-info');
  const atualizarInfo = () => { info.textContent = inDe.value ? `${contar(inDe.value, !inCi.checked)} ocorrência(s)` : ''; };
  inDe.addEventListener('input', atualizarInfo);
  inCi.addEventListener('change', atualizarInfo);
  inPara.addEventListener('keydown', (e) => { if (e.key === 'Enter') document.querySelector('[data-acao=substituir-tudo]').click(); });

  // ------------------------------------------------------------------ salvar
  async function salvar(como) {
    sincronizar();
    status.textContent = 'Salvando…';
    const enviar = async (forcar) => {
      const r = await fetch(D.urls.salvar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ arquivo: D.arquivo, hash, modelo, como: como || null, forcar }),
      });
      const j = await r.json().catch(() => ({ erro: 'Resposta inválida do servidor.' }));
      if (r.status === 409 && j.conflito) {
        if (confirm(j.erro + '\n\nSalvar mesmo assim?')) return enviar(true);
        status.textContent = 'Não salvo.';
        return null;
      }
      if (!r.ok || j.erro) throw new Error(j.erro || 'Erro ao salvar.');
      return j;
    };
    try {
      const j = await enviar(false);
      if (!j) return;
      if (j.url) { marcarSujo(false); location.href = j.url; return; }
      hash = j.hash;
      marcarSujo(false);
      status.textContent = '✔ Salvo às ' + j.hora;
    } catch (e) {
      status.textContent = '✖ ' + e.message;
      alert('Erro ao salvar: ' + e.message);
    }
  }

  document.getElementById('barra').addEventListener('click', (e) => {
    const a = e.target.closest('[data-acao]');
    if (!a) return;
    const acao = a.dataset.acao;
    if (acao === 'salvar') salvar();
    if (acao === 'salvar-como') {
      const nome = prompt('Salvar uma cópia com o nome:', D.nome.replace(/\.docx$/i, '') + ' (cópia)');
      if (nome) salvar(nome);
    }
    if (acao === 'desfazer') {
      if (!pilha.length) { status.textContent = 'Nada para desfazer.'; return; }
      modelo = JSON.parse(pilha.pop());
      render();
      marcarSujo();
    }
    if (acao === 'buscar') { painel.hidden = !painel.hidden; if (!painel.hidden) inDe.focus(); }
    if (acao === 'imprimir') { sincronizar(); print(); }
  });
  document.querySelector('[data-acao=substituir-tudo]').addEventListener('click', () => {
    const de = inDe.value;
    if (!de) return;
    let n = 0;
    estrutura(() => modelo.partes.forEach((p) => (n += substituirBlocos(p.blocos, de, inPara.value, !inCi.checked))));
    info.textContent = `${n} substituição(ões) feita(s).`;
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); salvar(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') { e.preventDefault(); painel.hidden = false; inDe.focus(); inDe.select(); }
  });
  window.addEventListener('beforeunload', (e) => { if (sujo) { e.preventDefault(); e.returnValue = ''; } });

  const imp = el('button', 'btn btn-leve', '🖨 Imprimir');
  imp.dataset.acao = 'imprimir';
  status.before(imp);

  render();
})();
