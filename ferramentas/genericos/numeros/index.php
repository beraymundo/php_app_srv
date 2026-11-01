<?php
/** Sequência de números — versão web do antigo numeros.py (com mais opções). */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<section class="cartao">
  <form class="form-grade" id="f" onsubmit="return false">
    <label>Primeiro número <input type="number" name="ini" value="1"></label>
    <label>Último número <input type="number" name="fim" value="100"></label>
    <label>De quanto em quanto <input type="number" name="passo" value="1" min="1"></label>
    <label>Separador
      <select name="sep">
        <option value="  ">dois espaços (como o antigo)</option>
        <option value=" ">espaço</option>
        <option value=", ">vírgula</option>
        <option value="; ">ponto e vírgula</option>
        <option value="\n">um por linha</option>
        <option value="\t">tabulação (colar no Excel em colunas)</option>
      </select>
    </label>
    <label>Zeros à esquerda (dígitos) <input type="number" name="zeros" value="0" min="0" max="10"></label>
    <label>Texto antes <input name="pre" placeholder="Ex.: Nº "></label>
    <label>Texto depois <input name="suf"></label>
    <label>Números por linha <input type="number" name="porLinha" value="0" min="0"><small class="mudo">0 = sem quebrar</small></label>
    <label class="check"><input type="checkbox" name="embaralhar"> Embaralhar</label>
    <label class="check"><input type="checkbox" name="inverter"> Ordem decrescente</label>
  </form>
</section>
<section class="cartao">
  <div class="cartao-topo">
    <h2>Resultado <small class="mudo" id="qtd"></small></h2>
    <div class="grupo">
      <button class="btn btn-primario" id="copiar">Copiar</button>
      <button class="btn" id="baixar">Baixar .txt</button>
    </div>
  </div>
  <div class="saida-texto" id="saida"></div>
</section>
<script>
(function () {
  const f = document.getElementById('f');
  const gerar = () => {
    const v = (n) => f.elements[n].value;
    let ini = parseInt(v('ini')), fim = parseInt(v('fim')), passo = Math.max(1, parseInt(v('passo')) || 1);
    if (isNaN(ini) || isNaN(fim)) return '';
    if (ini > fim) [ini, fim] = [fim, ini];
    if ((fim - ini) / passo > 200000) return 'Quantidade grande demais (máx. 200.000).';
    let nums = [];
    for (let n = ini; n <= fim; n += passo) nums.push(n);
    if (f.elements.inverter.checked) nums.reverse();
    if (f.elements.embaralhar.checked) for (let i = nums.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [nums[i], nums[j]] = [nums[j], nums[i]]; }
    const z = parseInt(v('zeros')) || 0;
    const sep = v('sep').replace('\\n', '\n').replace('\\t', '\t');
    const itens = nums.map((n) => v('pre') + (z ? String(n).padStart(z, '0') : n) + v('suf'));
    document.getElementById('qtd').textContent = `(${itens.length} números)`;
    const pl = parseInt(v('porLinha')) || 0;
    if (!pl) return itens.join(sep);
    const linhas = [];
    for (let i = 0; i < itens.length; i += pl) linhas.push(itens.slice(i, i + pl).join(sep));
    return linhas.join('\n');
  };
  const atualizar = () => (document.getElementById('saida').textContent = gerar());
  f.addEventListener('input', atualizar);
  document.getElementById('copiar').onclick = (e) => App.copiar(document.getElementById('saida').textContent, e.target);
  document.getElementById('baixar').onclick = () => App.baixarTexto('numeros.txt', document.getElementById('saida').textContent);
  atualizar();
})();
</script>
<?php rodape(); ?>
