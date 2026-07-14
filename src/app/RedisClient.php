<?php

class RedisClient
{
    private static ?Redis $instance = null;

    public static function connection(): Redis
    {
        if (self::$instance === null) {
            $host = getenv('REDIS_HOST') ?: 'redis';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);

            $redis = new Redis();
            $redis->connect($host, $port);

            self::$instance = $redis;
        }

        return self::$instance;
    }
}
