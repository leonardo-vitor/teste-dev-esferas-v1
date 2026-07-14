<?php

function render(string $view, array $data = []): void
{
    extract($data);

    ob_start();
    require __DIR__ . "/Views/{$view}.php";
    $content = ob_get_clean();

    require __DIR__ . '/Views/layout.php';
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}
