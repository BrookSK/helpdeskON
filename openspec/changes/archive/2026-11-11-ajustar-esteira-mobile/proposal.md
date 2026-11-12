# Proposal

## Why

Nos testes de 2026-11-11, a Júlia apontou problemas da esteira comercial no celular:
- Na apresentação, os cards de "Quem somos" ficam em 3 colunas estreitas, com palavras cortadas.
- As logos dos clientes aparecem fora de centro, com círculos cortados.
- Na proposta, os botões do topo ficam empurrados para a direita.
- Na tabela de serviços, os campos Qtd, Horas e Valor ficam embaixo dos títulos, numa tabela com rolagem lateral.
- No catálogo de serviços, os campos de horas e valores ficam desalinhados, e o aviso de custo interno ocupa muito espaço.

## What Changes

- Apresentação: até 900 px de largura, os cards numerados de "Quem somos" ficam em uma coluna.
- Logos dos clientes refeitas: círculo centralizado, sem corte. Na página, aparecem em moldura redonda.
- Proposta, até 767 px:
  - Os botões do topo ficam numa grade de 2 colunas, ocupando a largura.
  - Cada serviço vira um bloco, com Qtd, Horas, Valor e Total ao lado do rótulo.
- Catálogo de serviços: horas, valor/hora e custo/hora ficam com os campos alinhados. O aviso de custo interno vira uma linha só, abaixo dos três campos.
- No computador, nada muda.

## Capabilities

### New Capabilities
- `esteira-mobile`: telas da esteira comercial legíveis no celular.

### Modified Capabilities

Nenhuma.

## Impact

- `app/views/commercial/presentation_public.php`: CSS dos cards e das logos.
- `public/assets/proposal/clients/*.jpg`: logos recortadas em círculo.
- `app/views/commercial/proposal_form.php`: CSS para celular, classes e rótulos das colunas.
- `app/views/commercial/services.php`: campos do pop-up de serviço.
- Sem migration e sem mudança de regra de negócio.
