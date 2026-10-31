// Comportamentos comuns a todas as páginas.
(function () {
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

  // fecha menus <details class="menu"> ao clicar fora
  document.addEventListener('click', (e) => {
    document.querySelectorAll('details.menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });
})();

// Utilidades compartilhadas
window.App = {
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
  baixarTexto(nome, texto) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([texto], { type: 'text/plain;charset=utf-8' }));
    a.download = nome;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  },
  copiar(texto, botao) {
    const ok = () => { if (botao) { const t = botao.textContent; botao.textContent = '✔ Copiado'; setTimeout(() => (botao.textContent = t), 1400); } };
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
};
