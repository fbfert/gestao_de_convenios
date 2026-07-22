## Data Model

Adicionar `lancamento_print_templates`:

- `tenant_id`
- `chave`, inicialmente `registro_sessoes`
- `nome`
- `html`
- `ativo`

A chave é única por tenant para permitir outros templates no futuro sem multiplicar endpoints agora.

## Placeholder Engine

O editor aceitará HTML livre e placeholders no formato `{{campo}}`.

Campos simples:

- `{{guia_numero}}`
- `{{clinica}}`
- `{{paciente}}`
- `{{numero_cartao}}`
- `{{profissional_executante}}`
- `{{terapia_aplicada}}`
- `{{data_impressao}}`

Bloco repetível:

```html
{{#sessoes}}
  <tr>
    <td>{{numero}}</td>
    <td>{{data_sessao}}</td>
    <td>{{hora_inicio}}</td>
    <td>{{hora_fim}}</td>
    <td>{{acompanhante}}</td>
    <td>{{resumo_atividades}}</td>
  </tr>
{{/sessoes}}
```

No modelo em branco, o frontend renderiza linhas vazias numeradas. Na tela de templates, o preview usa dados de exemplo.

## Security

O HTML é editável por operadores autenticados no tenant. O preview e a impressão usam `iframe sandbox` sem scripts para reduzir execução indevida de JavaScript. Placeholders são escapados antes de inserir dados dinâmicos.
