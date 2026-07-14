# Desafio Técnico &mdash; Programador PHP FullStack

Obrigado pelo interesse na Esferas Software! Este teste simula, em pequena escala, os dois
tipos de problema mais comuns no nosso produto core: **consultas lentas no PostgreSQL** e
**dados recalculados sem necessidade que poderiam estar em cache no Redis**.

Não existe pegadinha escondida: os dois problemas abaixo são visíveis navegando na aplicação.

## Contexto

O ambiente é uma aplicação PHP simples (sem framework), rodando em Docker com PostgreSQL e
Redis, já com uma massa de dados grande o suficiente para os problemas de performance se
manifestarem de verdade (não é só teórico).

Veja o [`README.md`](README.md) para instruções de como subir o ambiente e popular o banco.

## Problema 1 &mdash; Relatório de Clientes lento

Acesse **`/relatorio/top-clientes`**. A página lista os 20 clientes que mais gastaram nos
últimos 12 meses. O tempo de geração aparece no topo da página — com a massa de dados do seed
padrão, essa página pode levar mais de 30 segundos para carregar (sim, é bem perceptível).

**O que fazer:**

1. Descubra a causa raiz da lentidão (dica: `EXPLAIN ANALYZE` é seu amigo, e o código de
   `ReportController::topClientes()` também merece uma leitura atenta).
2. Corrija o problema, seja reescrevendo a consulta, adicionando índice(s), ou ambos.
3. O resultado retornado (clientes, valores, contagem de pedidos) **não pode mudar** — só a
   performance.
4. Índices novos devem ser adicionados via um script SQL versionado em `db/` (por exemplo,
   `db/indexes.sql`), não aplicados manualmente só no seu ambiente.

**Meta de performance:** menos de 300ms para gerar a página com a massa de dados do seed
padrão (a página mostra o tempo, então você mesmo consegue validar).

## Problema 2 &mdash; Catálogo de Produtos sem cache

Acesse **`/catalogo`**. A cada requisição, a aplicação recalcula do zero, sobre a base
inteira, a média de avaliação e a quantidade vendida de cada produto — mesmo que esses dados
não mudem a cada segundo.

**O que fazer:**

1. Implemente uma camada de cache com Redis usando a estratégia **Cache-Aside**: na leitura,
   tenta o cache primeiro; em caso de *miss*, consulta o banco e popula o cache com um TTL
   razoável.
2. Trate a invalidação corretamente: existe um endpoint `POST /produtos/{id}` (usado pelo botão
   "Salvar" na tela do catálogo) que atualiza preço/estoque de um produto. Depois de uma
   atualização, o catálogo **não pode continuar mostrando dado desatualizado** por causa do
   cache (nem exigir esperar o TTL expirar).
3. Pense em como suas chaves de cache lidam com os filtros de categoria da página.

Não existe uma "resposta única certa" aqui — estamos avaliando a estratégia (chave de cache,
TTL, invalidação, e por que você escolheu esse caminho).

## O que entregar

- O código alterado (pode ser um `git diff`/branch, ou o projeto inteiro zipado).
- Um arquivo `SOLUCAO.md` curto explicando:
  - O que causava a lentidão no Problema 1 e o que foi feito (inclua um antes/depois do tempo
    de resposta, e se possível do plano de execução).
  - Qual estratégia de cache foi usada no Problema 2 e por quê (TTL escolhido, formato da
    chave, como a invalidação foi resolvida).
  - Qualquer trade-off ou suposição que você tenha feito.

## Regras

- Pode pesquisar, usar documentação e ferramentas de IA como apoio — só esteja preparado
  para explicar suas decisões numa conversa técnica depois.
- Tempo sugerido: até 3-4 horas. Não é uma prova cronometrada; prefira uma solução completa
  e bem explicada a tentar cobrir mais do que consegue justificar.
- Fique à vontade para ajustar HTML/CSS/JS das páginas se isso ajudar a demonstrar sua
  solução (não é o foco da avaliação, mas conhecimentos básicos de frontend contam como
  diferencial).

Qualquer dúvida sobre o enunciado, pode perguntar antes de começar.

Boa sorte!
