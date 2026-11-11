# Design

## Decisões

- O problema de "Quem somos" vem da especificidade do CSS: `ul.list.tight` (3 colunas) vencia a regra de uma coluna do `@media (max-width:900px)`, que usava só `ul.list`. A correção inclui `ul.list.tight` na mesma regra.
- As logos eram prints de avatares redondos sobre fundo cinza, com o círculo deslocado e, em algumas, cortado embaixo. Cada imagem foi recortada no círculo e centralizada sobre fundo branco. O CSS mostra a logo em moldura redonda. O parâmetro de cache passou de `?v=5` para `?v=6`, para o navegador buscar as imagens novas.
- Na proposta, a tabela de serviços continua sendo tabela. No celular, o CSS transforma cada linha em bloco, e o rótulo vem de `data-label` em cada célula. O JavaScript que lê as linhas não muda.
- Os botões do topo usam uma classe própria (`proposal-actions`), para não alterar o `.top-bar` das outras telas.
- No pop-up de serviço, cada coluna usa flex vertical e o rótulo ocupa o espaço que sobra. Assim os campos ficam alinhados, com rótulo de uma ou duas linhas.

## Fora do escopo

- Ajustar o `.top-bar` das demais telas do sistema.
- Trocar as logos por versões originais dos clientes.
