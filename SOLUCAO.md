# SOLUCAO.md

## Problema 1 - Relatório de Clientes lento

### Causa raiz

`ReportController::topClientes()` fazia N+1 queries: 1 `SELECT` trazendo todos os
5.000 clientes e, dentro de um `foreach`, mais uma query agregando `orders` +
`order_items` por cliente - 5.001 roundtrips ao Postgres. Cada uma dessas
subqueries também rodava sem índice de suporte (schema tinha só PKs), então
cada uma delas varria pedaços grandes de `orders`/`order_items`. O `ORDER BY` e
`LIMIT 20` finais eram feitos em PHP (`usort` + `array_slice`) depois de já ter
processado os 5.000 clientes.

### O que foi feito

1. **Reescrita da query** para uma única consulta com duas CTEs:
   - `spent`: agrega `SUM(quantity * unit_price)` por `customer_id` direto no
     join `orders ⋈ order_items`, já com `ORDER BY total_spent DESC LIMIT 20` -
     o trabalho pesado (~250k linhas de `order_items` no período) é feito uma
     vez só, agregando 200k `orders`/500k `order_items` inteiros só nessa
     etapa.
   - `counts`: conta pedidos (`COUNT(*)`, sem `DISTINCT`) só para os 20
     clientes que já venceram em `spent` - evita reprocessar a base inteira
     duas vezes.
   - Detalhe importante: a query original usava `COUNT(DISTINCT o.id)`. Isso
     força o planner do Postgres a agregar via `GroupAggregate` (com sort),
     que não paraleliza. Trocar por `COUNT(*)` numa CTE separada (pedidos já
     são únicos, não precisa de distinct) libera `HashAggregate` paralelo -
     essa mudança sozinha derrubou o tempo de ~460ms para ~110–280ms nos testes.
2. **Índices novos** em `db/indexes.sql` (aplicado via
   `docker-entrypoint-initdb.d`, montado no `docker-compose.yml`):
   - `idx_orders_created_at (created_at)` - filtra pedidos dos últimos 12
     meses sem seq scan em `orders`.
   - `idx_orders_customer_id_created_at (customer_id, created_at)` - usado no
     lookup final de `counts`, index-only scan para os 20 clientes vencedores.
   - `idx_order_items_order_id_covering (order_id) INCLUDE (quantity,
     unit_price)` - permite index-only scan no join com `orders`, sem ir ao
     heap pegar `quantity`/`unit_price`.

### Antes / depois

| Métrica | Antes | Depois                                                                                               |
|---|---|------------------------------------------------------------------------------------------------------|
| Tempo de página (`elapsedMs` exibido) | > 30.000ms | ~110–280ms                                                                                           |
| Nº de queries ao Postgres | 5.001 | 1                                                                                                    |
| Plano de execução (gargalo) | 5.000× `Seq/Index Scan` por cliente + `GroupAggregate` sequencial | `Parallel Hash Join` + `Finalize HashAggregate` (2 workers) sobre `orders ⋈ order_items`, `LIMIT 20` |

Resultado (clientes, `total_spent`, `orders_count`) validado linha a linha
contra a lógica antiga antes da troca - sem mudança de dado, só de
performance. Meta do desafio era < 300ms; ficou em ~110–280ms, com margem.

---

## Problema 2 - Catálogo de Produtos sem cache

### Causa raiz

`CatalogController::fetchCatalog()` recalculava, a cada request, `AVG(rating)`
sobre 60.000 `product_reviews` e `SUM(quantity)` sobre 500.000 `order_items`,
mesmo esses agregados mudando raramente (só quando alguém compra ou avalia).

### Estratégia de cache - Cache-Aside com versionamento

- **Chave:** `catalog:v{gen}:{categoria|all}`, onde `{gen}` é um contador
  inteiro (`catalog:gen`) mantido no Redis e `{categoria|all}` cobre o filtro
  de categoria da página (uma entrada por categoria consultada + uma para
  "todas").
- **Leitura (`fetchCatalog`):** lê `gen` atual, monta a chave, tenta `GET`. Em
  hit, faz `json_decode` e retorna direto - sem tocar o Postgres. Em miss,
  roda a query (`queryCatalog`), grava com `SETEX` e retorna.
- **TTL:** 300s (5 min). Como os dados mudam "esporadicamente" (conforme o
  enunciado), o TTL aqui é só uma rede de segurança contra cache eterno - não
  é o mecanismo principal de invalidação.
- **Invalidação (`update`):** depois do `UPDATE` em `products`, um `INCR
  catalog:gen`. Isso muda o prefixo de **todas** as chaves de catálogo de uma
  vez (todas as categorias + "all"), então a próxima leitura de qualquer
  filtro dá miss e recalcula - sem esperar o TTL expirar.

### Por que versionamento em vez de deletar chave por chave

Um `UPDATE` em um produto pode afetar tanto o cache "all" quanto o cache da
categoria específica desse produto - e não dá pra saber, sem outra query, quais
outras chaves de categoria existem/foram populadas. As alternativas seriam:

- `KEYS catalog:*` + `DEL`: funciona, mas `KEYS` é O(n) e bloqueia o Redis em
  produção (não escala com mais chaves).
- Manter um `SET` com a lista de chaves ativas para dar `SREM`/`DEL`
  granular: mais correto por categoria, mas adiciona complexidade
  (manutenção do índice de chaves) para um ganho pequeno, já que o catálogo
  inteiro cabe fácil em memória e o custo de recalcular é baixo comparado ao
  Problema 1.

Bump de contador é O(1), não precisa de `KEYS`/`SCAN`, e invalida tudo
atomicamente sem tracking extra. Chaves da geração antiga (`catalog:v3:*`)
ficam órfãs no Redis e somem sozinhas quando o TTL expira - não há limpeza
ativa necessária.

### Trade-offs / suposições

- Cache guarda o resultado já formatado (JSON do `fetchAll`), não os agregados
  crus - mais simples, mas se o schema de `products` mudar com frequência, o
  payload cacheado também muda de formato (aceitável aqui, catálogo é
  read-mostly).
- `categories()` (lista de categorias para o filtro) não entrou no cache: é
  uma query barata (`SELECT DISTINCT category`) e não é o gargalo citado no
  desafio.
- Cache-aside assume que uma pequena janela de leitura durante o
  recalculo (entre o miss e o `SETEX`) é aceitável - não há lock distribuído
  contra cache stampede. Dado o volume do teste (não há tráfego concorrente
  massivo), não foi considerado necessário.
