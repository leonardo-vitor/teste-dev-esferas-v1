<?php

/**
 * Catálogo de produtos.
 *
 * ATENÇÃO: a cada requisição os agregados de avaliação e vendas são
 * recalculados do zero sobre as tabelas inteiras, mesmo que esses dados
 * só mudem de forma esporádica. Faz parte do desafio introduzir uma
 * camada de cache (Redis) para essa leitura, incluindo a invalidação
 * correta quando um produto é atualizado em update().
 */
class CatalogController
{
    public function index(): void
    {
        $category = !empty($_GET['category']) ? $_GET['category'] : null;
        $start = microtime(true);

        $products = $this->fetchCatalog($category);

        $elapsedMs = round((microtime(true) - $start) * 1000);

        render('catalog', [
            'products' => $products,
            'category' => $category,
            'elapsedMs' => $elapsedMs,
            'categories' => $this->categories(),
        ]);
    }

    public function update(int $id): void
    {
        $pdo = Database::connection();

        $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float) $_POST['price'] : null;
        $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int) $_POST['stock'] : null;

        $stmt = $pdo->prepare('
            UPDATE products
            SET price = COALESCE(:price, price),
                stock = COALESCE(:stock, stock)
            WHERE id = :id
        ');
        $stmt->execute(['price' => $price, 'stock' => $stock, 'id' => $id]);

        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Produto atualizado. Se o catálogo estiver em cache, ele precisa refletir esta mudança.',
        ]);
    }

    private function fetchCatalog(?string $category): array
    {
        $pdo = Database::connection();

        $sql = '
            SELECT
                p.id,
                p.name,
                p.category,
                p.price,
                p.stock,
                COALESCE(rv.avg_rating, 0) AS avg_rating,
                COALESCE(rv.reviews_count, 0) AS reviews_count,
                COALESCE(sales.total_sold, 0) AS total_sold
            FROM products p
            LEFT JOIN (
                SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS reviews_count
                FROM product_reviews
                GROUP BY product_id
            ) rv ON rv.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS total_sold
                FROM order_items
                GROUP BY product_id
            ) sales ON sales.product_id = p.id
        ';

        $params = [];
        if ($category) {
            $sql .= ' WHERE p.category = :category';
            $params['category'] = $category;
        }

        $sql .= ' ORDER BY p.name LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function categories(): array
    {
        $pdo = Database::connection();

        return $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }
}
