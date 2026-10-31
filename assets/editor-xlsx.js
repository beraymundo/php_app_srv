// Editor simples de .xlsx: edita valores das células e a quantidade de linhas, mantendo os estilos.
(function () {
  const D = JSON.parse(document.getElementById('dados-editor').textContent);
  const ed = document.getElementById('editor');
  const status = document.getElementById('status');
  let hash = D.hash;
  let ativa = Math.max(0, D.planilhas.length - 1);
  let sujo = false;
  const estado = D.planilhas.map((p) => ({ edicoes: {}, linhas: Math.max(1, p.linhas), linhasOrig: Math.max(1, p.linhas) }));

  document.querySelector('[data-acao=desfazer]')?.remove();

  const letra = (n) => { let s = ''; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
  const marcarSujo = (v = true) => { sujo = v; status.classList.toggle('sujo', v); if (v) status.textContent = '● Alterações não salvas'; };

  function render() {
    ed.innerHTML = '';
    const abas = document.createElement('div');
    abas.className = 'abas';
    D.planilhas.forEach((p, i) => {
      const b = document.createElement('button');
      b.className = 'aba' + (i === ativa ? ' ativa' : '');
      b.textContent = p.nome + (Object.keys(estado[i].edicoes).length || estado[i].linhas !== estado[i].linhasOrig ? ' •' : '');
      b.onclick = () => { ativa = i; render(); };
      abas.appendChild(b);
    });
    ed.appendChild(abas);

    const p = D.planilhas[ativa];
    const st = estado[ativa];
    const cols = Math.max(1, p.colunas);

    const ferr = document.createElement('div');
    ferr.className = 'ferr-planilha';
    ferr.innerHTML = `<label class="check">Linhas usadas <input type="number" min="1" value="${st.linhas}"></label>
      <button class="btn btn-p" data-k="ap">Aplicar</button>
      <button class="btn btn-p" data-k="mais">＋ Linha</button>
      <button class="btn btn-p" data-k="menos">− Última linha</button>
      <span class="mudo pequeno">Linhas novas copiam o estilo da última linha preenchida.</span>`;
    ferr.onclick = (e) => {
      const k = e.target.dataset.k;
      if (!k) return;
      let n = st.linhas;
      if (k === 'ap') n = +ferr.querySelector('input').value;
      if (k === 'mais') n++;
      if (k === 'menos') n--;
      if (n < 1 || n === st.linhas) return;
      if (n < st.linhas) {
        let cheias = 0;
        for (let l = n + 1; l <= st.linhas; l++) for (let c = 1; c <= cols; c++) { const v = valor(letra(c) + l); if (v) { cheias++; break; } }
        if (cheias && !confirm(`${cheias} linha(s) com dados serão apagadas. Continuar?`)) return;
      }
      st.linhas = n;
      marcarSujo();
      render();
    };
    ed.appendChild(ferr);

    const wrap = document.createElement('div');
    wrap.className = 'grade-wrap';
    const t = document.createElement('table');
    t.className = 'planilha';
    let h = '<tr><th class="lin"></th>';
    for (let c = 1; c <= cols; c++) h += `<th>${letra(c)}</th>`;
    t.innerHTML = h + '</tr>';
    for (let l = 1; l <= st.linhas; l++) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<th class="lin">${l}</th>`;
      for (let c = 1; c <= cols; c++) {
        const ref = letra(c) + l;
        const cel = p.cel[ref];
        const td = document.createElement('td');
        const inp = document.createElement('input');
        inp.value = valor(ref);
        inp.dataset.ref = ref;
        inp.dataset.l = l;
        inp.dataset.c = c;
        if (cel && cel.ro) { inp.readOnly = true; td.className = 'ro'; inp.title = 'Fórmula (não editável)'; }
        if (cel && (cel.k === 'd' || cel.k === 't')) inp.placeholder = cel.k === 'd' ? 'dd/mm' : 'hh:mm';
        if (ref in st.edicoes) td.classList.add('mudou');
        td.appendChild(inp);
        tr.appendChild(td);
      }
      t.appendChild(tr);
    }
    t.addEventListener('input', (e) => {
      const r = e.target.dataset.ref;
      if (!r) return;
      const orig = p.cel[r] ? p.cel[r].v : '';
      if (e.target.value === orig) delete st.edicoes[r]; else st.edicoes[r] = e.target.value;
      e.target.parentNode.classList.toggle('mudou', r in st.edicoes);
      marcarSujo();
    });
    t.addEventListener('keydown', (e) => {
      const i = e.target;
      if (!i.dataset.ref) return;
      const mover = (dl, dc) => { const n = t.querySelector(`input[data-l="${+i.dataset.l + dl}"][data-c="${+i.dataset.c + dc}"]`); if (n) { e.preventDefault(); n.focus(); n.select(); } };
      if (e.key === 'Enter' || e.key === 'ArrowDown') mover(1, 0);
      if (e.key === 'ArrowUp') mover(-1, 0);
    });
    wrap.appendChild(t);
    ed.appendChild(wrap);
  }

  function valor(ref) {
    const st = estado[ativa];
    if (ref in st.edicoes) return st.edicoes[ref];
    const c = D.planilhas[ativa].cel[ref];
    return c ? c.v : '';
  }

  async function salvar(como) {
    status.textContent = 'Salvando…';
    const planilhas = estado.map((s, i) => ({ i, cel: s.edicoes, linhas: s.linhas !== s.linhasOrig ? s.linhas : null }))
      .filter((p) => Object.keys(p.cel).length || p.linhas);
    const enviar = async (forcar) => {
      const r = await fetch(D.urls.salvar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ arquivo: D.arquivo, hash, planilhas, como: como || null, forcar }),
      });
      const j = await r.json().catch(() => ({ erro: 'Resposta inválida do servidor.' }));
      if (r.status === 409 && j.conflito) return confirm(j.erro + '\n\nSalvar mesmo assim?') ? enviar(true) : null;
      if (!r.ok || j.erro) throw new Error(j.erro || 'Erro ao salvar.');
      return j;
    };
    try {
      const j = await enviar(false);
      if (!j) { status.textContent = 'Não salvo.'; return; }
      marcarSujo(false);
      location.href = j.url || location.href;
    } catch (e) {
      status.textContent = '✖ ' + e.message;
      alert('Erro ao salvar: ' + e.message);
    }
  }

  document.getElementById('barra').addEventListener('click', (e) => {
    const a = e.target.closest('[data-acao]');
    if (!a) return;
    if (a.dataset.acao === 'salvar') salvar();
    if (a.dataset.acao === 'salvar-como') {
      const nome = prompt('Salvar uma cópia com o nome:', D.nome.replace(/\.xlsx$/i, '') + ' (cópia)');
      if (nome) salvar(nome);
    }
  });
  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); salvar(); } });
  window.addEventListener('beforeunload', (e) => { if (sujo) { e.preventDefault(); e.returnValue = ''; } });
  render();
})();
