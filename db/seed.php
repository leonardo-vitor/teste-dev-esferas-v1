<?php

/**
 * Seeder do desafio técnico.
 *
 * Gera um volume de dados grande o suficiente para os problemas de
 * performance (query lenta / cache) se manifestarem de forma realista.
 *
 * Uso (dentro do container app):
 *   php db/seed.php
 */

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'teste_esferas';
$user = getenv('DB_USER') ?: 'teste_esferas';
$pass = getenv('DB_PASSWORD') ?: 'teste_esferas';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

const TOTAL_CUSTOMERS = 5000;
const TOTAL_PRODUCTS = 3000;
const TOTAL_ORDERS = 200000;
const MAX_ITEMS_PER_ORDER = 4;
const TOTAL_REVIEWS = 60000;
const BATCH_SIZE = 2000;

$cities = ['São Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Curitiba', 'Porto Alegre', 'Salvador', 'Recife', 'Fortaleza'];
$categories = ['Eletrônicos', 'Casa', 'Moda', 'Esporte', 'Livros', 'Brinquedos', 'Beleza', 'Mercado'];

function batchInsert(PDO $pdo, string $table, array $columns, array $rows): void
{
    if (!$rows) {
        return;
    }

    $placeholdersRow = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
    $placeholders = implode(',', array_fill(0, count($rows), $placeholdersRow));
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES ' . $placeholders;

    $stmt = $pdo->prepare($sql);
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }
    $stmt->execute($values);
}

echo "Limpando tabelas...\n";
$pdo->exec('TRUNCATE TABLE product_reviews, order_items, orders, products, customers RESTART IDENTITY CASCADE');

echo "Gerando " . TOTAL_CUSTOMERS . " clientes...\n";
$firstNames = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Fábio', 'Gabriela', 'Hugo', 'Ivone', 'João', 'Karina', 'Lucas', 'Marina', 'Nelson', 'Otávio', 'Paula', 'Quésia', 'Rafael', 'Sônia', 'Tiago'];
$lastNames = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Lima'];

$rows = [];
for ($i = 1; $i <= TOTAL_CUSTOMERS; $i++) {
    $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    $email = 'cliente' . $i . '@example.com';
    $city = $cities[array_rand($cities)];
    $daysAgo = random_int(30, 1500);
    $rows[] = [$name, $email, $city, "now() - interval '{$daysAgo} days'"];

    if (count($rows) >= BATCH_SIZE || $i === TOTAL_CUSTOMERS) {
        insertCustomersBatch($pdo, $rows);
        $rows = [];
    }
}

function insertCustomersBatch(PDO $pdo, array $rows): void
{
    $valuesSql = [];
    $params = [];
    foreach ($rows as $row) {
        [$name, $email, $city, $createdAtExpr] = $row;
        $valuesSql[] = "(?, ?, ?, {$createdAtExpr})";
        $params[] = $name;
        $params[] = $email;
        $params[] = $city;
    }
    $sql = 'INSERT INTO customers (name, email, city, created_at) VALUES ' . implode(',', $valuesSql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

echo "Gerando " . TOTAL_PRODUCTS . " produtos...\n";
$productNouns = ['Fone de Ouvido', 'Cadeira', 'Camiseta', 'Bola', 'Livro', 'Boneco', 'Creme', 'Arroz', 'Notebook', 'Mochila', 'Tênis', 'Panela', 'Luminária', 'Bicicleta', 'Relógio'];

$rows = [];
for ($i = 1; $i <= TOTAL_PRODUCTS; $i++) {
    $name = $productNouns[array_rand($productNouns)] . ' ' . strtoupper(substr(md5((string) $i), 0, 5));
    $category = $categories[array_rand($categories)];
    $price = round(random_int(1000, 500000) / 100, 2);
    $stock = random_int(0, 500);
    $rows[] = [$name, $category, $price, $stock];

    if (count($rows) >= BATCH_SIZE || $i === TOTAL_PRODUCTS) {
        batchInsert($pdo, 'products', ['name', 'category', 'price', 'stock'], $rows);
        $rows = [];
    }
}

echo "Gerando " . TOTAL_ORDERS . " pedidos e itens...\n";
$orderRows = [];
$orderCount = 0;
$itemsBuffer = [];

for ($i = 1; $i <= TOTAL_ORDERS; $i++) {
    $customerId = random_int(1, TOTAL_CUSTOMERS);
    $daysAgo = random_int(0, 730); // até 2 anos atrás, para o filtro de "últimos 12 meses" ter efeito real
    $orderRows[] = [$customerId, "now() - interval '{$daysAgo} days'"];

    if (count($orderRows) >= BATCH_SIZE || $i === TOTAL_ORDERS) {
        $firstId = insertOrdersBatch($pdo, $orderRows);
        $n = count($orderRows);

        for ($j = 0; $j < $n; $j++) {
            $orderId = $firstId + $j;
            $itemsCount = random_int(1, MAX_ITEMS_PER_ORDER);
            for ($k = 0; $k < $itemsCount; $k++) {
                $productId = random_int(1, TOTAL_PRODUCTS);
                $quantity = random_int(1, 5);
                $unitPrice = round(random_int(1000, 500000) / 100, 2);
                $itemsBuffer[] = [$orderId, $productId, $quantity, $unitPrice];
            }
        }

        if (count($itemsBuffer) >= BATCH_SIZE) {
            batchInsert($pdo, 'order_items', ['order_id', 'product_id', 'quantity', 'unit_price'], $itemsBuffer);
            $itemsBuffer = [];
        }

        $orderRows = [];
        $orderCount += $n;
        echo "  {$orderCount}/" . TOTAL_ORDERS . " pedidos\n";
    }
}

if ($itemsBuffer) {
    batchInsert($pdo, 'order_items', ['order_id', 'product_id', 'quantity', 'unit_price'], $itemsBuffer);
}

function insertOrdersBatch(PDO $pdo, array $rows): int
{
    $valuesSql = [];
    $params = [];
    foreach ($rows as $row) {
        [$customerId, $createdAtExpr] = $row;
        $valuesSql[] = "(?, 'completed', {$createdAtExpr})";
        $params[] = $customerId;
    }
    $sql = 'INSERT INTO orders (customer_id, status, created_at) VALUES ' . implode(',', $valuesSql) . ' RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $firstId = (int) $stmt->fetchColumn();

    return $firstId;
}

echo "Gerando " . TOTAL_REVIEWS . " avaliações de produtos...\n";
$comments = ['Ótimo produto!', 'Chegou rápido.', 'Qualidade mediana.', 'Recomendo.', 'Não gostei muito.', 'Superou expectativas.', 'Custo-benefício bom.', null];

$rows = [];
for ($i = 1; $i <= TOTAL_REVIEWS; $i++) {
    $productId = random_int(1, TOTAL_PRODUCTS);
    $rating = random_int(1, 5);
    $comment = $comments[array_rand($comments)];
    $rows[] = [$productId, $rating, $comment];

    if (count($rows) >= BATCH_SIZE || $i === TOTAL_REVIEWS) {
        batchInsert($pdo, 'product_reviews', ['product_id', 'rating', 'comment'], $rows);
        $rows = [];
    }
}

echo "Seed concluído.\n";
echo "Resumo:\n";
foreach (['customers', 'products', 'orders', 'order_items', 'product_reviews'] as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    echo "  {$table}: {$count}\n";
}
