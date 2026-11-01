<?php
/** Valor por extenso (tudo no navegador). */
/** @var Ferramenta $f */
inicio_ferramenta($f);
?>
<section class="cartao">
  <div class="form-grade">
    <label>Valor ou número <input id="v" value="1.250,50" autofocus></label>
    <label class="check"><input type="radio" name="tipo" value="moeda" checked> Em reais (R$)</label>
    <label class="check"><input type="radio" name="tipo" value="num"> Número simples</label>
    <label class="check"><input type="checkbox" id="maius"> MAIÚSCULAS</label>
  </div>
  <p class="destaque" id="r" style="font-size:1.4rem;font-weight:600"></p>
  <div class="grupo" style="justify-content:center">
    <button class="btn btn-primario" id="copiar">Copiar</button>
    <button class="btn" id="copiar2">Copiar "R$ X (por extenso)"</button>
  </div>
</section>
<script>
(function () {
  const v = document.getElementById('v');
  const r = document.getElementById('r');
  let texto = '', comValor = '';
  const at = () => {
    const n = App.numero(v.value);
    const moeda = document.querySelector('[name=tipo]:checked').value === 'moeda';
    texto = n === null ? '' : App.extenso(n, moeda);
    if (document.getElementById('maius').checked) texto = texto.toLocaleUpperCase('pt-BR');
    comValor = n === null ? '' : (moeda ? 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : n.toLocaleString('pt-BR')) + ` (${texto})`;
    r.textContent = texto || '—';
  };
  document.addEventListener('input', at);
  document.addEventListener('change', at);
  document.getElementById('copiar').onclick = (e) => App.copiar(texto, e.target);
  document.getElementById('copiar2').onclick = (e) => App.copiar(comValor, e.target);
  at();
})();
</script>
<?php rodape(); ?>
