# Teste Técnico &mdash; Programador PHP FullStack (Esferas Software)

Ambiente Docker com PHP, PostgreSQL e Redis para o desafio técnico.

O enunciado do desafio está em [`DESAFIO.md`](DESAFIO.md).

## Como subir o ambiente

```bash
docker compose up -d --build
```

Serviços expostos:

| Serviço  | URL / porta                  |
|----------|-------------------------------|
| App      | http://localhost:8090          |
| Postgres | localhost:5433 (user/senha/db: `teste_esferas`) |
| Redis    | localhost:6390                 |
| Adminer  | http://localhost:8081 (system: PostgreSQL, server: `db`) |

O schema (`db/schema.sql`) é criado automaticamente na primeira subida do container `db`.

## Popular o banco com dados de teste

```bash
docker compose exec app php db/seed.php
```

Isso gera aproximadamente 5.000 clientes, 3.000 produtos, 200.000 pedidos (~500.000 itens de
pedido) e 60.000 avaliações. Leva alguns minutos.

## Recriar o banco do zero

```bash
docker compose down -v
docker compose up -d --build
docker compose exec app php db/seed.php
```

## Estrutura

```
docker/php/        Dockerfile e vhost do Apache
db/schema.sql       Schema inicial (sem índices adicionais — parte do desafio)
db/seed.php         Script de geração de massa de dados
src/public/         Document root (front controller index.php)
src/app/            Controllers, Views e classes de infraestrutura (Database, RedisClient)
```
