<?php

/**
 * Relatório "Top clientes" (últimos 12 meses).
 *
 * ATENÇÃO: implementação intencionalmente ingênua para fins do teste técnico.
 * Faz parte do desafio identificar e corrigir os problemas de performance aqui.
 */
class ReportController
{
    public function topClientes(): void
    {
        $pdo = Database::connection();
        $start = microtime(true);

        // COUNT(DISTINCT o.id) força o planner a agregar via sort; separar a
        // contagem de pedidos (sem distinct) do somatório permite HashAggregate
        // paralelo e evita ordenar as ~250k linhas de order_items.
        $topCustomers = $pdo->query('
            WITH spent AS (
                SELECT o.customer_id, SUM(oi.quantity * oi.unit_price) AS total_spent
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.created_at >= now() - interval \'12 months\'
                GROUP BY o.customer_id
                ORDER BY total_spent DESC
                LIMIT 20
            ),
            counts AS (
                SELECT customer_id, COUNT(*) AS orders_count
                FROM orders
                WHERE created_at >= now() - interval \'12 months\'
                  AND customer_id IN (SELECT customer_id FROM spent)
                GROUP BY customer_id
            )
            SELECT 
                c.id, c.name, c.email, c.city,
                s.total_spent, cnt.orders_count
            FROM spent s
            JOIN customers c ON c.id = s.customer_id
            JOIN counts cnt ON cnt.customer_id = s.customer_id
            ORDER BY s.total_spent DESC
        ')->fetchAll();

        foreach ($topCustomers as &$customer) {
            $customer['total_spent'] = (float) $customer['total_spent'];
            $customer['orders_count'] = (int) $customer['orders_count'];
        }
        unset($customer);

        $elapsedMs = round((microtime(true) - $start) * 1000);

        render('report', [
            'customers' => $topCustomers,
            'elapsedMs' => $elapsedMs,
        ]);
    }
}
