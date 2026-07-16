-- Índices para o relatório de top clientes (ReportController::topClientes).
-- Schema original só tem PKs; sem eles o relatório faz seq scan em orders e
-- order_items inteiros (200k / 500k linhas) a cada execução.

-- Filtra pedidos recentes (created_at >= now() - 12 months) sem seq scan.
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders (created_at);

-- Contagem de pedidos por cliente já filtrados por data (usado no lookup
-- final, apenas para os 20 clientes vencedores).
CREATE INDEX IF NOT EXISTS idx_orders_customer_id_created_at ON orders (customer_id, created_at);

-- Index-only scan no join com orders, evita ir ao heap pra pegar
-- quantity/unit_price durante a agregação.
CREATE INDEX IF NOT EXISTS idx_order_items_order_id_covering ON order_items (order_id) INCLUDE (quantity, unit_price);
