<?php
/** Ferramentas de texto (tudo no navegador). */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<section class="cartao">
  <textarea id="t" rows="12" placeholder="Cole ou digite o texto aqui..."></textarea>
  <p class="mudo pequeno" id="cont"></p>
  <div class="grupo" id="botoes">
    <button class="btn" data-f="maius">MAIÚSCULAS</button>
    <button class="btn" data-f="minus">minúsculas</button>
    <button class="btn" data-f="titulo">Primeira Letra Maiúscula</button>
    <button class="btn" data-f="frase">Primeira da frase</button>
    <button class="btn" data-f="acentos">Tirar acentos</button>
    <button class="btn" data-f="espacos">Tirar espaços extras</button>
    <button class="btn" data-f="vazias">Tirar linhas vazias</button>
    <button class="btn" data-f="repetidas">Tirar linhas repetidas</button>
    <button class="btn" data-f="ordenar">Ordenar A→Z</button>
    <button class="btn" data-f="ordenarZ">Ordenar Z→A</button>
    <button class="btn" data-f="inverter">Inverter linhas</button>
    <button class="btn" data-f="numerar">Numerar linhas</button>
    <button class="btn" data-f="juntar">Juntar linhas com vírgula</button>
    <button class="btn" data-f="separar">Separar vírgulas em linhas</button>
    <button class="btn" data-f="emails">Extrair e-mails</button>
    <button class="btn" data-f="telefones">Extrair telefones</button>
  </div>
  <div class="grupo" style="margin-top:12px">
    <button class="btn btn-primario" id="copiar">Copiar</button>
    <button class="btn" id="desfazer">Desfazer</button>
    <button class="btn" id="baixar">Baixar .txt</button>
    <button class="btn btn-leve" id="limpar">Limpar</button>
  </div>
</section>
<script>
(function () {
  const t = document.getElementById('t');
  const hist = [];
  const linhas = (s) => s.split(/\r?\n/);
  const semAcento = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '');
  const F = {
    maius: (s) => s.toLocaleUpperCase('pt-BR'),
    minus: (s) => s.toLocaleLowerCase('pt-BR'),
    titulo: (s) => s.toLocaleLowerCase('pt-BR').replace(/(^|[\s\-(/"'])([\p{L}])/gu, (m, a, b) => a + b.toLocaleUpperCase('pt-BR')).replace(/ (De|Da|Do|Das|Dos|E|Em|A|O|Para|Por|Com) /g, (m, w) => ' ' + w.toLowerCase() + ' '),
    frase: (s) => s.toLocaleLowerCase('pt-BR').replace(/(^\s*|[.!?]\s+|\n\s*)(\p{L})/gu, (m, a, b) => a + b.toLocaleUpperCase('pt-BR')),
    acentos: semAcento,
    espacos: (s) => linhas(s).map((l) => l.replace(/[ \t]+/g, ' ').trim()).join('\n'),
    vazias: (s) => linhas(s).filter((l) => l.trim()).join('\n'),
    repetidas: (s) => { const v = new Set(); return linhas(s).filter((l) => { const k = semAcento(l.trim().toLowerCase()); if (v.has(k)) return false; v.add(k); return true; }).join('\n'); },
    ordenar: (s) => linhas(s).sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' })).join('\n'),
    ordenarZ: (s) => linhas(s).sort((a, b) => b.localeCompare(a, 'pt-BR', { sensitivity: 'base' })).join('\n'),
    inverter: (s) => linhas(s).reverse().join('\n'),
    numerar: (s) => { let n = 0; return linhas(s).map((l) => (l.trim() ? `${++n}. ${l}` : l)).join('\n'); },
    juntar: (s) => linhas(s).map((l) => l.trim()).filter(Boolean).join(', '),
    separar: (s) => s.split(/\s*[,;]\s*/).filter(Boolean).join('\n'),
    emails: (s) => [...new Set(s.match(/[\w.+-]+@[\w-]+\.[\w.-]+/g) || [])].join('\n'),
    telefones: (s) => [...new Set((s.match(/(\(?\d{2}\)?\s*)?9?\d{4}[-\s]?\d{4}/g) || []).map((x) => x.trim()))].join('\n'),
  };
  const contar = () => {
    const s = t.value;
    const pal = (s.match(/[\p{L}\p{N}]+/gu) || []).length;
    document.getElementById('cont').textContent = `${s.length} caracteres · ${s.replace(/\s/g, '').length} sem espaços · ${pal} palavras · ${s ? linhas(s).length : 0} linhas`;
  };
  t.addEventListener('input', contar);
  document.getElementById('botoes').onclick = (e) => {
    const k = e.target.dataset.f;
    if (!k) return;
    hist.push(t.value);
    t.value = F[k](t.value);
    contar();
  };
  document.getElementById('desfazer').onclick = () => { if (hist.length) { t.value = hist.pop(); contar(); } };
  document.getElementById('copiar').onclick = (e) => App.copiar(t.value, e.target);
  document.getElementById('baixar').onclick = () => App.baixarTexto('texto.txt', t.value);
  document.getElementById('limpar').onclick = () => { hist.push(t.value); t.value = ''; contar(); t.focus(); };
  contar();
})();
</script>
<?php rodape(); ?>
