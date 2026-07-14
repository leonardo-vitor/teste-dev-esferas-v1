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

        $customers = $pdo->query('SELECT id, name, email, city FROM customers')->fetchAll();

        foreach ($customers as &$customer) {
            $stmt = $pdo->prepare('
                SELECT
                    COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS total_spent,
                    COUNT(DISTINCT o.id) AS orders_count
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.customer_id = :customer_id
                  AND o.created_at >= now() - interval \'12 months\'
            ');
            $stmt->execute(['customer_id' => $customer['id']]);
            $totals = $stmt->fetch();

            $customer['total_spent'] = (float) $totals['total_spent'];
            $customer['orders_count'] = (int) $totals['orders_count'];
        }
        unset($customer);

        usort($customers, fn ($a, $b) => $b['total_spent'] <=> $a['total_spent']);
        $topCustomers = array_slice($customers, 0, 20);

        $elapsedMs = round((microtime(true) - $start) * 1000);

        render('report', [
            'customers' => $topCustomers,
            'elapsedMs' => $elapsedMs,
        ]);
    }
}
