<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

/**
 * Object Pool for Memory Optimization
 *
 * Reuses objects to reduce memory allocation overhead in high-concurrency scenarios
 */
class ObjectPool
{
    private array $pools = [];
    private array $poolSizes = [];
    private int $maxPoolSize;
    private array $stats = [];

    public function __construct(int $maxPoolSize = 100)
    {
        $this->maxPoolSize = $maxPoolSize;
    }

    /**
     * Get object from pool or create new one
     */
    public function get(string $class): ?object
    {
        if (!isset($this->pools[$class]) || empty($this->pools[$class])) {
            return null;
        }

        $object = array_pop($this->pools[$class]);
        $this->poolSizes[$class]--;

        // Reset object if it has a reset method
        if (is_object($object) && method_exists($object, 'reset')) {
            $object->reset();
        }

        $this->stats[$class]['retrieved'] = ($this->stats[$class]['retrieved'] ?? 0) + 1;

        return $object;
    }

    /**
     * Return object to pool
     */
    public function put(object $object): bool
    {
        $class = get_class($object);

        if (!isset($this->pools[$class])) {
            $this->pools[$class] = [];
            $this->poolSizes[$class] = 0;
        }

        // Don't exceed max pool size
        if ($this->poolSizes[$class] >= $this->maxPoolSize) {
            return false;
        }

        $this->pools[$class][] = $object;
        $this->poolSizes[$class]++;

        $this->stats[$class]['returned'] = ($this->stats[$class]['returned'] ?? 0) + 1;

        return true;
    }

    /**
     * Clear all pools
     */
    public function clear(): void
    {
        $this->pools = [];
        $this->poolSizes = [];
        $this->stats = [];
    }

    /**
     * Clear pool for specific class
     */
    public function clearClass(string $class): void
    {
        unset($this->pools[$class], $this->poolSizes[$class], $this->stats[$class]);
    }

    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        $totalObjects = array_sum($this->poolSizes);
        $poolCount = count($this->pools);

        return [
            'total_pools' => $poolCount,
            'total_objects' => $totalObjects,
            'max_pool_size' => $this->maxPoolSize,
            'pools' => array_map(function ($class) {
                return [
                    'size' => $this->poolSizes[$class] ?? 0,
                    'retrieved' => $this->stats[$class]['retrieved'] ?? 0,
                    'returned' => $this->stats[$class]['returned'] ?? 0,
                ];
            }, array_flip(array_keys($this->pools)))
        ];
    }

    /**
     * Get pool size for specific class
     */
    public function getPoolSize(string $class): int
    {
        return $this->poolSizes[$class] ?? 0;
    }

    /**
     * Check if class is pooled
     */
    public function hasPool(string $class): bool
    {
        return isset($this->pools[$class]);
    }

    /**
     * Pre-populate pool with objects
     */
    public function populate(string $class, int $count): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class '{$class}' does not exist");
        }

        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class '{$class}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \InvalidArgumentException("Class '{$class}' requires constructor parameters");
        }

        for ($i = 0; $i < $count; $i++) {
            $object = new $class();
            if (!$this->put($object)) {
                break; // Pool is full
            }
        }
    }
}
