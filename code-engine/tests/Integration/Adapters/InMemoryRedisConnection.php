<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Contracts\Redis\Connection as ConnectionContract;

/**
 * Conexión Redis en memoria para tests de integración.
 * Reemplaza la conexión Redis real por un array PHP,
 * evitando mockear y sin depender de Docker/servidor Redis.
 *
 * Solo implementa los métodos que CachedFileMemory realmente usa: get, set.
 */
class InMemoryRedisConnection extends Connection implements ConnectionContract
{
    private array $data = [];

    public function __construct()
    {
        // No necesitamos un cliente \Redis real.
    }

    public function get($key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value, $expireResolution = null, $expireTTL = null, $flag = null): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    /**
     * Limpia todos los datos en memoria (útil entre tests).
     */
    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * Sobrescribe el método command para evitar llamar a $this->client (que es un \Redis real).
     * Delegamos get/set a nuestros métodos internos, todo lo demás lanza excepción.
     */
    public function command($method, array $parameters = []): mixed
    {
        return match ($method) {
            'get' => $this->get($parameters[0] ?? null),
            'set' => $this->set($parameters[0] ?? null, $parameters[1] ?? null),
            default => throw new \RuntimeException("Redis in-memory: method '$method' not implemented for tests"),
        };
    }

    public function createSubscription($channels, \Closure $callback, $method = 'subscribe'): void
    {
        throw new \RuntimeException('Redis in-memory: subscribe/psubscribe not implemented for tests');
    }
}