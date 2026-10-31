<?php
/** Calculadora de datas (tudo no navegador). */
/** @var Ferramenta $f */
inicio_ferramenta($f);
$hoje = date('Y-m-d');
$ini = date('Y-m-01', strtotime('first day of next month'));
$fim = date('Y-m-t', strtotime('first day of next month'));
?>
<div class="colunas">
  <section class="cartao">
    <h2>📏 Dias entre duas datas</h2>
    <div class="form-grade" id="dif">
      <label>De <input type="date" name="a" value="<?= $hoje ?>"></label>
      <label>Até <input type="date" name="b" value="<?= date('Y-12-25') ?>"></label>
    </div>
    <p class="destaque pequeno" id="dif-res" style="font-size:1.3rem"></p>
  </section>
  <section class="cartao">
    <h2>➕ Somar / subtrair dias</h2>
    <div class="form-grade" id="soma">
      <label>Data <input type="date" name="d" value="<?= $hoje ?>"></label>
      <label>Dias (use − para voltar) <input type="number" name="n" value="30"></label>
      <label class="check"><input type="checkbox" name="uteis"> Só dias úteis (seg–sex)</label>
    </div>
    <p class="destaque pequeno" id="soma-res" style="font-size:1.3rem"></p>
  </section>
</div>
<section class="cartao">
  <h2>📅 Listar dias da semana de um período</h2>
  <p class="mudo pequeno">Ex.: todos os domingos do mês que vem — útil para montar escalas.</p>
  <div class="form-grade" id="lst">
    <label>De <input type="date" name="a" value="<?= $ini ?>"></label>
    <label>Até <input type="date" name="b" value="<?= $fim ?>"></label>
    <label>Dias
      <select name="dias" multiple size="7">
        <?php foreach (DIAS_SEMANA as $i => $n): ?><option value="<?= $i ?>" <?= $i === 0 ? 'selected' : '' ?>><?= h(ucfirst($n)) ?></option><?php endforeach; ?>
      </select>
      <small class="mudo">Ctrl+clique para escolher vários</small>
    </label>
    <label>Formato
      <select name="fmt">
        <option value="dm">01/11</option>
        <option value="dmy">01/11/2026</option>
        <option value="ext">domingo, 1 de novembro</option>
        <option value="dm-sem">01/11 (domingo)</option>
      </select>
    </label>
  </div>
  <div class="cartao-topo" style="margin-top:10px"><span class="mudo" id="lst-qtd"></span><button class="btn" id="lst-copiar">📋 Copiar</button></div>
  <div class="saida-texto" id="lst-res"></div>
</section>
<script>
(function () {
  const MES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const SEM = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
  const dt = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d, 12); };
  const p2 = (n) => String(n).padStart(2, '0');
  const ext = (d) => `${SEM[d.getDay()]}, ${d.getDate()} de ${MES[d.getMonth()]} de ${d.getFullYear()}`;
  const v = (id, n) => document.querySelector(`#${id} [name=${n}]`);

  const dif = () => {
    if (!v('dif', 'a').value || !v('dif', 'b').value) return;
    const a = dt(v('dif', 'a').value), b = dt(v('dif', 'b').value);
    const dias = Math.round((b - a) / 864e5);
    let m = (b.getFullYear() - a.getFullYear()) * 12 + b.getMonth() - a.getMonth();
    if (b.getDate() < a.getDate()) m--;
    document.getElementById('dif-res').textContent = `${dias} dias · ${(Math.abs(dias) / 7).toFixed(1).replace('.', ',')} semanas · ${m} meses completos`;
  };
  const soma = () => {
    if (!v('soma', 'd').value) return;
    const d = dt(v('soma', 'd').value);
    let n = parseInt(v('soma', 'n').value) || 0;
    if (v('soma', 'uteis').checked) {
      const passo = n < 0 ? -1 : 1;
      while (n !== 0) { d.setDate(d.getDate() + passo); if (d.getDay() % 6 !== 0) n -= passo; }
    } else d.setDate(d.getDate() + n);
    document.getElementById('soma-res').textContent = `${p2(d.getDate())}/${p2(d.getMonth() + 1)}/${d.getFullYear()} — ${ext(d)}`;
  };
  const lst = () => {
    if (!v('lst', 'a').value || !v('lst', 'b').value) return;
    const a = dt(v('lst', 'a').value), b = dt(v('lst', 'b').value);
    const dias = [...v('lst', 'dias').selectedOptions].map((o) => +o.value);
    const fmt = v('lst', 'fmt').value;
    const out = [];
    let mesAtual = -1;
    for (const d = new Date(a); d <= b && out.length < 2000; d.setDate(d.getDate() + 1)) {
      if (!dias.includes(d.getDay())) continue;
      if (d.getMonth() !== mesAtual && (b - a) / 864e5 > 31) { if (out.length) out.push(''); out.push(MES[d.getMonth()].replace(/^./, (c) => c.toUpperCase())); mesAtual = d.getMonth(); }
      const dm = `${p2(d.getDate())}/${p2(d.getMonth() + 1)}`;
      out.push(fmt === 'dm' ? dm : fmt === 'dmy' ? `${dm}/${d.getFullYear()}` : fmt === 'ext' ? ext(d).replace(/ de \d{4}$/, '') : `${dm} (${SEM[d.getDay()]})`);
    }
    document.getElementById('lst-res').textContent = out.join('\n');
    document.getElementById('lst-qtd').textContent = `${out.filter((x) => /\d/.test(x)).length} dia(s)`;
  };
  ['dif', 'soma', 'lst'].forEach((id) => document.getElementById(id).addEventListener('input', { dif, soma, lst }[id]));
  document.getElementById('lst').addEventListener('change', lst);
  document.getElementById('lst-copiar').onclick = (e) => App.copiar(document.getElementById('lst-res').textContent, e.target);
  dif(); soma(); lst();
})();
</script>
<?php rodape(); ?>
