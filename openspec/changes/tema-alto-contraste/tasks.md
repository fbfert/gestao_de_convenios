## 1. Medição

- [x] 1.1 Simular deuteranopia, protanopia e tritanopia (matrizes de Machado et al.) sobre a paleta
      padrão e medir os dez pares semânticos.
- [x] 1.2 Registrar o achado: `sucesso`/`perigo` a 18 em deuteranopia e 14 em protanopia.

## 2. Paleta

- [x] 2.1 Redesenhar na oposição azul/laranja; `info` vira neutro para liberar matiz.
- [x] 2.2 Separar canais: texto escuro para 4,5:1, borda cromática para 3:1.
- [x] 2.3 Fechar o portão duplo — separação sob as três formas E contraste WCAG.

## 3. Tema

- [x] 3.1 Bloco `[data-theme='contraste']` redefinindo só o nível 2, mais o espelho da paleta nativa.
- [x] 3.2 Espessura em regra própria, fora de `@layer`, sobre quem já tem borda.
- [x] 3.3 Borda de papel nos chips de estado; anel de foco de 3px.

## 4. Alternância

- [x] 4.1 Store, persistência e aplicação antes da primeira pintura.
- [x] 4.2 Botão na barra e dentro do painel do celular, com rótulo em texto e `aria-pressed`.

## 5. Contrato

- [x] 5.1 A verificação de contraste passa a percorrer todo bloco `[data-theme]` declarado.
- [x] 5.2 As bordas cromáticas entram na lista de pares com piso de 3:1.
- [x] 5.3 Provar que reprova, degradando uma cor do tema novo.
