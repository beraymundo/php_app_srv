// Comportamentos comuns a todas as páginas.
window.App = window.App || {};

// Comportamentos que dependem da página: rodam quando o HTML terminar de carregar.
document.addEventListener('DOMContentLoaded', function () {
  // [data-abrir="#id"] mostra/esconde um painel
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-abrir]');
    if (!b) return;
    e.preventDefault();
    const alvo = document.querySelector(b.dataset.abrir);
    if (!alvo) return;
    alvo.classList.toggle('aberto');
    if (alvo.classList.contains('aberto')) {
      const campo = alvo.querySelector('input:not([type=hidden]), textarea, select');
      if (campo) campo.focus();
      alvo.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  });

  // formulários com confirmação / renomear
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirmar && !confirm(f.dataset.confirmar)) e.preventDefault();
    if (f.dataset.renomear !== undefined) {
      const novo = prompt('Novo nome:', f.dataset.renomear);
      if (!novo) { e.preventDefault(); return; }
      f.querySelector('[name=novo]').value = novo;
    }
  });

  // seletor de tema: auto (segue o sistema), claro, papel, escuro, preto
  const seletorTema = document.getElementById('tema');
  if (seletorTema) {
    seletorTema.value = document.documentElement.dataset.theme || 'auto';
    seletorTema.addEventListener('change', () => {
      const t = seletorTema.value;
      if (t === 'auto') delete document.documentElement.dataset.theme;
      else document.documentElement.dataset.theme = t;
      try { t === 'auto' ? localStorage.removeItem('tema') : localStorage.setItem('tema', t); } catch (e) {}
    });
  }

  const semMovimento = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nav = performance.getEntriesByType('navigation')[0];
  const voltando = nav && nav.type === 'back_forward';

  // título "digitado" (só quando a página é aberta, não ao voltar)
  const h1 = document.querySelector('.titulo-pagina h1');
  if (h1 && !semMovimento && !voltando && h1.children.length === 0) {
    const texto = h1.textContent;
    const passo = Math.max(8, Math.min(28, 320 / texto.length));
    h1.setAttribute('aria-label', texto);
    h1.style.minHeight = h1.offsetHeight + 'px';
    h1.textContent = '';
    h1.classList.add('digitando');
    let i = 0;
    const t = setInterval(() => {
      h1.textContent = texto.slice(0, ++i);
      if (i >= texto.length) { clearInterval(t); h1.classList.remove('digitando'); h1.style.minHeight = ''; }
    }, passo);
  }
  if (voltando) document.documentElement.classList.add('voltando');

  // barra de carregamento: aparece só se a próxima página demorar
  const carga = document.getElementById('carga');
  let tCarga = null;
  const mostrarCarga = () => { clearTimeout(tCarga); tCarga = setTimeout(() => carga && carga.classList.add('ativa'), 150); };
  const esconderCarga = () => { clearTimeout(tCarga); if (carga) carga.classList.remove('ativa'); };
  window.addEventListener('pageshow', esconderCarga);
  const ehDownload = (url) => /[?&]a=(baixar|baixar_versao|zip|pdf|ver)(&|$)/.test(url);
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (a.target || a.hasAttribute('download') || a.origin !== location.origin || a.getAttribute('href').startsWith('#') || a.href.startsWith('javascript:') || ehDownload(a.href)) return;
    mostrarCarga();
    setTimeout(esconderCarga, 15000);
  });
  document.addEventListener('submit', (e) => {
    setTimeout(() => {
      if (e.defaultPrevented) return;
      mostrarCarga();
      setTimeout(esconderCarga, 15000); // downloads gerados por formulário não trocam de página
    }, 0);
  });
  App.carga = (ligar) => (ligar ? mostrarCarga() : esconderCarga());

  // ---------------------------------------------------------------- busca rápida (paleta) e atalhos
  const dadosNav = document.getElementById('dados-nav');
  const itens = dadosNav ? JSON.parse(dadosNav.textContent) : [];
  const normalizar = (s) => String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const buscar = (q) => {
    const termos = normalizar(q).split(/\s+/).filter(Boolean);
    if (!termos.length) return itens;
    return itens
      .map((it) => {
        const nome = normalizar(it.nome), resto = normalizar(it.cat + ' ' + it.desc + ' ' + it.id);
        let pontos = 0;
        for (const t of termos) {
          if (nome.startsWith(t)) pontos += 3;
          else if (nome.includes(t)) pontos += 2;
          else if (resto.includes(t)) pontos += 1;
          else return null;
        }
        return [pontos, it];
      })
      .filter(Boolean)
      .sort((a, b) => b[0] - a[0])
      .map((x) => x[1]);
  };
  const desenharResultados = (lista, ul, sel) => {
    ul.innerHTML = '';
    lista.slice(0, 30).forEach((it, i) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = it.url;
      a.className = i === sel ? 'sel' : '';
      a.innerHTML = '<span class="r-cat"></span><span class="r-nome"></span><span class="r-desc"></span>';
      a.querySelector('.r-cat').textContent = it.catId + '/';
      a.querySelector('.r-nome').textContent = it.nome;
      a.querySelector('.r-desc').textContent = it.desc;
      li.appendChild(a);
      ul.appendChild(li);
    });
    if (!lista.length) ul.innerHTML = '<li class="r-vazio">nenhuma ferramenta encontrada</li>';
  };
  const ligarBusca = (input, ul, aoMudar) => {
    let sel = 0, lista = [];
    const atualizar = () => { lista = buscar(input.value); sel = 0; desenharResultados(lista, ul, sel); aoMudar && aoMudar(input.value); };
    input.addEventListener('input', atualizar);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const n = Math.min(lista.length, 30);
        if (!n) return;
        sel = (sel + (e.key === 'ArrowDown' ? 1 : n - 1)) % n;
        desenharResultados(lista, ul, sel);
        const s = ul.querySelector('.sel');
        if (s) s.scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter' && lista[sel]) {
        e.preventDefault();
        mostrarCarga();
        location.href = lista[sel].url;
      }
    });
    return atualizar;
  };

  // busca na página inicial
  const buscaInicio = document.getElementById('busca-inicio');
  if (buscaInicio) {
    const ul = document.getElementById('busca-resultados');
    const grade = document.getElementById('grade-inicio');
    ligarBusca(buscaInicio, ul, (q) => {
      const ativo = q.trim() !== '';
      ul.hidden = !ativo;
      grade.hidden = ativo;
    });
  }

  // paleta: "/" ou Ctrl+K em qualquer página
  let paleta = null;
  const abrirPaleta = () => {
    if (paleta) return;
    paleta = document.createElement('div');
    paleta.className = 'modal-fundo paleta-fundo';
    paleta.innerHTML = '<div class="paleta" role="dialog" aria-label="Buscar ferramenta"><label class="paleta-campo"><span class="prompt">&gt;</span><input placeholder="buscar ferramenta..." autocomplete="off" spellcheck="false"></label><ul class="resultados"></ul><div class="paleta-rodape">↑↓ navegar · enter abrir · esc fechar</div></div>';
    document.body.appendChild(paleta);
    const input = paleta.querySelector('input');
    ligarBusca(input, paleta.querySelector('ul'))();
    input.focus();
    paleta.addEventListener('mousedown', (e) => { if (e.target === paleta) fecharPaleta(); });
  };
  const fecharPaleta = () => { if (paleta) { paleta.remove(); paleta = null; } };

  const ajuda = () => App.modal('Atalhos de teclado',
    '<table class="tabela-simples atalhos">' +
    '<tr><td><kbd>/</kbd> ou <kbd>Ctrl</kbd>+<kbd>K</kbd></td><td>buscar e abrir uma ferramenta</td></tr>' +
    '<tr><td><kbd>Esc</kbd> ou <kbd>Backspace</kbd></td><td>voltar um nível (ferramenta → categoria → início)</td></tr>' +
    '<tr><td><kbd>g</kbd> depois <kbd>i</kbd></td><td>ir para o início</td></tr>' +
    '<tr><td><kbd>?</kbd></td><td>mostrar esta ajuda</td></tr></table>', [['Fechar', null, true]]);

  const editando = (el) => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
  let ultimaTecla = '';
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); paleta ? fecharPaleta() : abrirPaleta(); return; }
    if (paleta) { if (e.key === 'Escape') { e.preventDefault(); fecharPaleta(); } return; }
    if (e.ctrlKey || e.metaKey || e.altKey || editando(e.target) || document.querySelector('.modal-fundo')) return;
    if (e.key === '/') { e.preventDefault(); if (buscaInicio) buscaInicio.focus(); else abrirPaleta(); return; }
    if (e.key === '?') { e.preventDefault(); ajuda(); return; }
    if (ultimaTecla === 'g' && e.key === 'i') { location.href = 'index.php'; return; }
    ultimaTecla = e.key;
    if (e.key === 'Escape' || e.key === 'Backspace') {
      // primeiro fecha o que estiver aberto
      const menu = document.querySelector('details.menu[open]');
      const painel = document.querySelector('.painel-oculto.aberto');
      if (menu) { menu.removeAttribute('open'); return; }
      if (painel) { painel.classList.remove('aberto'); return; }
      if (document.getElementById('editor')) return; // no editor, use o botão Voltar
      const links = document.querySelectorAll('.trilha a');
      const pai = links[links.length - 1];
      if (pai) { e.preventDefault(); mostrarCarga(); location.href = pai.href; }
    }
  });

  // fecha menus <details class="menu"> ao clicar fora
  document.addEventListener('click', (e) => {
    document.querySelectorAll('details.menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });
});

// Utilidades compartilhadas
Object.assign(window.App, {
  /** "1.234,56" ou "1234.56" -> 1234.56 */
  numero(s) {
    s = String(s).trim().replace(/[R$\s]/g, '');
    if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, ''); // 2.000.000
    else if (/,\d{1,2}$/.test(s) || (s.includes(',') && !s.includes('.'))) s = s.replace(/\./g, '').replace(',', '.');
    else s = s.replace(/,/g, '');
    const n = parseFloat(s);
    return isNaN(n) ? null : n;
  },
  /** Número por extenso em português. moeda=true -> "mil e duzentos reais e cinquenta centavos" */
  extenso(n, moeda) {
    const u = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    const d = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    const c = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    const ate999 = (x) => {
      if (x === 100) return 'cem';
      const p = [];
      const ce = Math.floor(x / 100), r = x % 100;
      if (ce) p.push(c[ce]);
      if (r) p.push(r < 20 ? u[r] : d[Math.floor(r / 10)] + (r % 10 ? ' e ' + u[r % 10] : ''));
      return p.join(' e ');
    };
    const inteiro = (x) => {
      if (x === 0) return 'zero';
      const esc = [['', ''], ['mil', 'mil'], ['milhão', 'milhões'], ['bilhão', 'bilhões'], ['trilhão', 'trilhões']];
      const g = [];
      while (x > 0) { g.push(x % 1000); x = Math.floor(x / 1000); }
      const partes = [];
      for (let i = g.length - 1; i >= 0; i--) {
        if (!g[i]) continue;
        const s = i === 1 && g[i] === 1 ? 'mil' : ate999(g[i]) + (i ? ' ' + esc[i][g[i] === 1 ? 0 : 1] : '');
        partes.push([s, g[i]]);
      }
      return partes.reduce((out, [s, v], k) => (k === 0 ? s : out + (k === partes.length - 1 && (v < 100 || v % 100 === 0) ? ' e ' : ' ') + s), '');
    };
    if (n == null || isNaN(n)) return '';
    const neg = n < 0 ? 'menos ' : '';
    n = Math.abs(n);
    if (!moeda) {
      const [i, dec] = String(n).split('.');
      return neg + inteiro(+i) + (dec ? ' vírgula ' + (dec.startsWith('0') ? dec.split('').map((x) => u[+x]).join(' ') : inteiro(+dec)) : '');
    }
    let reais = Math.floor(n + 1e-9);
    let cent = Math.round((n - reais) * 100);
    if (cent === 100) { reais++; cent = 0; }
    const r = reais ? inteiro(reais) + (reais >= 1e6 && reais % 1e6 === 0 ? ' de' : '') + (reais === 1 ? ' real' : ' reais') : '';
    const ct = cent ? inteiro(cent) + (cent === 1 ? ' centavo' : ' centavos') : '';
    return neg + ([r, ct].filter(Boolean).join(' e ') || 'zero real');
  },
  /** POST JSON e devolve a resposta JSON (lança erro se o servidor responder {erro}). */
  async api(url, dados) {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(dados) });
    const j = await r.json().catch(() => ({ erro: 'Resposta inválida do servidor.' }));
    if (!r.ok || j.erro) throw new Error(j.erro || 'Erro ' + r.status);
    return j;
  },
  /**
   * Salvamento automático de um estado JSON: chame salvar() a cada mudança;
   * grava depois de uma pausa e mostra o status no elemento indicado.
   */
  autoSalvar(url, obterDados, statusEl, espera = 700) {
    let t = null;
    const st = (txt) => { if (statusEl) statusEl.textContent = txt; };
    const gravar = async () => {
      try { const j = await App.api(url, obterDados()); st('salvo ' + (j.hora || '')); } catch (e) { st('erro: ' + e.message); }
    };
    return () => { st('* salvando...'); clearTimeout(t); t = setTimeout(gravar, espera); };
  },
  /** Formata número como moeda brasileira. */
  reais(n) { return (Number(n) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); },
  baixarTexto(nome, texto) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([texto], { type: 'text/plain;charset=utf-8' }));
    a.download = nome;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  },
  copiar(texto, botao) {
    const ok = () => { if (botao) { const t = botao.textContent; botao.textContent = 'Copiado'; setTimeout(() => (botao.textContent = t), 1400); } };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(texto).then(ok);
    else { const ta = document.createElement('textarea'); ta.value = texto; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); ok(); }
  },
  modal(titulo, html, botoes) {
    return new Promise((resolve) => {
      const fundo = document.createElement('div');
      fundo.className = 'modal-fundo';
      fundo.innerHTML = `<div class="modal" role="dialog"><h3></h3><div class="modal-corpo"></div><div class="grupo"></div></div>`;
      fundo.querySelector('h3').textContent = titulo;
      const corpo = fundo.querySelector('.modal-corpo');
      if (typeof html === 'string') corpo.innerHTML = html; else corpo.appendChild(html);
      const grupo = fundo.querySelector('.grupo');
      const fechar = (v) => { fundo.remove(); resolve(v); };
      (botoes || [['Cancelar', null], ['OK', true, true]]).forEach(([rot, val, prim]) => {
        const b = document.createElement('button');
        b.className = 'btn' + (prim ? ' btn-primario' : '');
        b.textContent = rot;
        b.onclick = () => fechar(typeof val === 'function' ? val(corpo) : val);
        grupo.appendChild(b);
      });
      fundo.addEventListener('mousedown', (e) => { if (e.target === fundo) fechar(null); });
      fundo.addEventListener('keydown', (e) => { if (e.key === 'Escape') fechar(null); });
      document.body.appendChild(fundo);
      const campo = corpo.querySelector('input, textarea, select');
      (campo || grupo.lastChild).focus();
    });
  },
});
