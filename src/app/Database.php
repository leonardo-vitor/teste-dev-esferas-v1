<?php

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '5432';
            $name = getenv('DB_NAME') ?: 'teste_esferas';
            $user = getenv('DB_USER') ?: 'teste_esferas';
            $pass = getenv('DB_PASSWORD') ?: 'teste_esferas';

            self::$instance = new PDO(
                "pgsql:host={$host};port={$port};dbname={$name}",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return self::$instance;
    }
}
