# esteira-mobile Specification

## Purpose
Telas da esteira comercial (apresentação, proposta e catálogo de serviços) legíveis e utilizáveis no celular.

## Requirements

### Requirement: Apresentação legível no celular
Em telas de até 900 px, a apresentação SHALL mostrar os cards numerados de "Quem somos" em uma coluna. As logos dos clientes SHALL aparecer centralizadas, em moldura redonda e sem corte.

#### Scenario: Quem somos no celular
- **WHEN** a apresentação é aberta numa tela de 375 px
- **THEN** os cards "Consultoria estratégica comercial", "Inteligência de mercado" e "Tecnologia sob medida" aparecem um embaixo do outro

#### Scenario: Logos dos clientes
- **WHEN** a seção de clientes é exibida
- **THEN** cada logo aparece num círculo centralizado, sem fundo cinza e sem parte cortada

### Requirement: Proposta utilizável no celular
Em telas de até 767 px, a edição da proposta SHALL mostrar os botões do topo numa grade que ocupa a largura da tela. Cada serviço SHALL aparecer como um bloco, com Qtd, Horas, Valor e Total na mesma linha do rótulo. Em telas maiores, a tela MUST continuar com a tabela de serviços.

#### Scenario: Botões do topo no celular
- **WHEN** a proposta é aberta numa tela de 375 px
- **THEN** Voltar, Ver PDF, Ver proposta online, Salvar e Enviar ao cliente ocupam a largura, em 2 colunas

#### Scenario: Serviço no celular
- **WHEN** a proposta tem um serviço e é aberta numa tela de 375 px
- **THEN** o rótulo "Qtd" aparece ao lado do campo de quantidade, sem rolagem lateral

#### Scenario: Computador
- **WHEN** a proposta é aberta numa tela de 1280 px
- **THEN** os serviços aparecem na tabela com cabeçalho Qtd, Horas, Valor e Total

### Requirement: Pop-up de serviço alinhado
O pop-up de serviço do catálogo SHALL mostrar os campos de horas estimadas, valor/hora e custo/hora alinhados na mesma altura, qualquer que seja o tamanho dos rótulos. O aviso de que o custo é interno SHALL ocupar uma linha abaixo dos três campos.

#### Scenario: Rótulo em duas linhas
- **WHEN** o pop-up é aberto numa tela de 375 px e "Horas estimadas" quebra em duas linhas
- **THEN** os três campos continuam alinhados na mesma altura
