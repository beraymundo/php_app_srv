# Central de Ferramentas

Abra em `http://localhost/php_app_srv/`. É PHP puro e roda no XAMPP sem nenhuma extensão extra.

## Organização

```
index.php            menu: categorias › ferramentas › ferramenta
config.php           senha opcional, regras de backup e lixeira
lib/                 núcleo (zip, editor docx/xlsx, gerador docx, layout)
assets/              css e js (editor visual no navegador)
ferramentas/
  <categoria>/_categoria.json          nome, descrição, ordem
  <categoria>/<ferramenta>/ferramenta.json
      modelos/     arquivos-base (o "Novo documento" faz uma cópia daqui)
      documentos/  arquivos de trabalho
      saidas/      o que os programas geram
      dados/       dados internos (ex.: lista de casais)
      backups/     versões anteriores, guardadas a cada salvamento
      lixeira/     arquivos excluídos (restauráveis)
old/                 o jeito antigo (scripts Python), intocado
```

## Criar uma ferramenta nova

**Documento (sem programar):** em qualquer categoria, clique em "Nova ferramenta de documento" e envie um .docx/.xlsx.
Para preencher campos automaticamente ao criar um documento, edite o `ferramenta.json`:

```json
"campos": [
  { "rotulo": "Nome", "buscar": "Fulano de Tal" },
  { "rotulo": "Data", "buscar": "26 de setembro de 2026", "antes": "Colatina (ES), ", "padrao": "{hoje_extenso}" }
]
```

`buscar` é o texto que está no modelo. `antes` e `depois` servem de contexto quando o mesmo texto aparece em mais de um lugar.
Marcadores aceitos em `padrao` e `nome_padrao`: `{hoje}`, `{hoje_extenso}`, `{mes}`, `{Mes}`, `{proximo_mes}`, `{Proximo_mes}`, `{ano}`.

**Programa próprio:** crie `ferramentas/<categoria>/<id>/ferramenta.json` com `"tipo": "app"` e um `index.php`.
O arquivo recebe `$f` (a ferramenta) e `$a` (a ação). Use `inicio_ferramenta($f)`, `lista_saidas($f)` e `rodape()`; veja `ferramentas/igreja/rifa/index.php` como exemplo.

**Programa com dados salvos (listas, cadastros):** chame `$estado = estado_ferramenta($f, $a);` no começo do `index.php`.
Ele devolve o conteúdo de `dados/estado.json` e atende o salvamento automático. Na página, passe o estado com
`<?= json_js($estado ?: new stdClass()) ?>` e grave com `App.autoSalvar($f->url(['a' => 'salvar_estado']), () => estado, elementoStatus)`.
Uma cópia de segurança vai para o histórico de versões a cada 6 horas. Exemplo: `ferramentas/casa/lista-compras/index.php`.

## Atalhos

| Tecla | O que faz |
|---|---|
| `/` ou `Ctrl`+`K` | busca rápida: abre qualquer ferramenta pelo nome |
| `Esc` ou `Backspace` | volta um nível (ferramenta → categoria → início) |
| `g` depois `i` | vai para o início |
| `?` | mostra os atalhos |

O tema (auto, claro, papel, escuro, preto) fica no canto do topo e é lembrado pelo navegador.
