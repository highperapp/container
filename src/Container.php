<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * High-Performance PSR-11 Container
 *
 * Optimized for C10M concurrency with object pooling and memory optimization
 *
 * - Compile-time dependency graph resolution
 * - Pre-compiled service instantiation
 * - Singleton cache optimization
 * - Factory function caching
 * - Memory-efficient service resolution
 */
class Container implements ContainerInterface
{
    private array $services = [];
    private array $instances = [];
    private array $singletons = [];
    private array $factories = [];
    private array $aliases = [];
    private ObjectPool $objectPool;

    // Optimization Properties
    private array $preCompiledServices = [];    // Compile-time dependency resolution
    private array $singletonCache = [];         // Optimized singleton cache
    private array $factoryCache = [];          // Factory function cache
    private array $dependencyGraph = [];        // Pre-compiled dependency graph
    private array $instantiationStrategies = []; // Optimized instantiation strategies
    private bool $isCompiled = false;           // Compilation status
    private array $resolvingStack = [];         // Track current resolution stack

    public function __construct()
    {
        $this->objectPool = new ObjectPool();

        // Register self
        $this->instances[ContainerInterface::class] = $this;
        $this->instances[self::class] = $this;
    }

    /**
     * Compile container for optimized service resolution
     */
    public function compile(): void
    {
        if ($this->isCompiled) {
            return;
        }

        // Build dependency graph
        $this->buildDependencyGraph();

        // Pre-compile service instantiation strategies
        $this->compileInstantiationStrategies();

        // Optimize singleton cache
        $this->optimizeSingletonCache();

        // Cache factory functions
        $this->cacheFactoryFunctions();

        $this->isCompiled = true;
    }

    /**
     * Get service from container
     */
    public function get(string $id): mixed
    {
        // Ensure container is compiled for optimal performance
        if (!$this->isCompiled) {
            $this->compile();
        }

        // Check aliases first
        $id = $this->aliases[$id] ?? $id;

        // Ultra-fast singleton cache lookup
        if (isset($this->singletonCache[$id])) {
            return $this->singletonCache[$id];
        }

        // Return existing instance if singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is registered
        if (!$this->has($id)) {
            throw new NotFoundException("Service '{$id}' not found in container");
        }

        return $this->resolveOptimized($id);
    }

    /**
     * Check if service exists
     */
    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        return isset($this->services[$id])
            || isset($this->instances[$id])
            || isset($this->factories[$id])
            || class_exists($id)
            || interface_exists($id);
    }

    /**
     * Register a service definition
     */
    public function bind(string $id, mixed $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $id;
        }

        $this->services[$id] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];

        // Remove existing instance if re-binding singleton
        if ($singleton && isset($this->instances[$id])) {
            unset($this->instances[$id]);
        }

        // Invalidate compilation when new service is added
        $this->isCompiled = false;
    }

    /**
     * Register a singleton service
     */
    public function singleton(string $id, mixed $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Register a factory function
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Register an existing instance
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Register an alias
     */
    public function alias(string $alias, string $concrete): void
    {
        $this->aliases[$alias] = $concrete;
    }

    /**
     * Extend a service definition
     */
    public function extend(string $id, callable $callback): void
    {
        if (!$this->has($id)) {
            throw new NotFoundException("Cannot extend unknown service '{$id}'");
        }

        $original = $this->services[$id] ?? null;

        $this->factory($id, function () use ($id, $callback, $original) {
            if ($original) {
                // Create a temporary container to avoid infinite recursion
                $tempService = $this->services[$id];
                unset($this->services[$id]);
                $instance = $this->get($tempService['concrete']);
                $this->services[$id] = $tempService;
            } else {
                $instance = new \stdClass();
            }
            return $callback($instance, $this);
        });
    }

    /**
     * Optimized service resolution
     */
    private function resolveOptimized(string $id): mixed
    {
        // Phase 1: Pre-compiled service lookup
        if (isset($this->preCompiledServices[$id])) {
            return $this->executeCompiledResolution($id);
        }

        // Phase 2: Cached factory function lookup
        if (isset($this->factoryCache[$id])) {
            $instance = $this->factoryCache[$id]($this);

            if (isset($this->services[$id]) && $this->services[$id]['singleton']) {
                $this->singletonCache[$id] = $instance;
                $this->instances[$id] = $instance;
            }

            return $instance;
        }

        //Optimized instantiation strategy
        if (isset($this->instantiationStrategies[$id])) {
            return $this->executeInstantiationStrategy($id);
        }

        //Fallback to standard resolution
        return $this->resolve($id);
    }

    /**
     * Legacy service resolution for backward compatibility
     */
    private function resolve(string $id): mixed
    {
        // Check for factory first
        if (isset($this->factories[$id])) {
            $instance = $this->factories[$id]($this);

            if (isset($this->services[$id]) && $this->services[$id]['singleton']) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        }

        // Get service definition
        $service = $this->services[$id] ?? ['concrete' => $id, 'singleton' => false];
        $concrete = $service['concrete'];

        // Handle callable
        if (is_callable($concrete)) {
            $instance = $concrete($this);
        } else {
            $instance = $this->build($concrete);
        }

        // Store singleton instance
        if ($service['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Build class instance with dependency injection
     */
    private function build(string $class): object
    {
        // Check for circular dependency
        if (in_array($class, $this->resolvingStack)) {
            throw new ContainerException("Circular dependency detected while resolving '{$class}'");
        }

        $this->resolvingStack[] = $class;

        try {
            /** @var \ReflectionClass<object> $reflection */
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            array_pop($this->resolvingStack);
            throw new ContainerException("Cannot reflect class '{$class}': {$e->getMessage()}");
        }

        if (!$reflection->isInstantiable()) {
            array_pop($this->resolvingStack);
            throw new ContainerException("Class '{$class}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            array_pop($this->resolvingStack);
            return $this->objectPool->get($class) ?? new $class();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());
        $instance = $reflection->newInstanceArgs($dependencies);

        array_pop($this->resolvingStack);
        return $instance;
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve parameter '{$parameter->getName()}' without type hint"
                    );
                }
                continue;
            }

            if ($type instanceof \ReflectionUnionType) {
                throw new ContainerException(
                    "Union types are not supported for parameter '{$parameter->getName()}'"
                );
            }

            if (!($type instanceof \ReflectionNamedType)) {
                throw new ContainerException(
                    "Unsupported parameter type for '{$parameter->getName()}'"
                );
            }

            $typeName = $type->getName();

            // Handle built-in types
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot auto-wire built-in type '{$typeName}' for parameter '{$parameter->getName()}'"
                    );
                }
                continue;
            }

            // Resolve class dependency
            try {
                $dependencies[] = $this->get($typeName);
            } catch (NotFoundExceptionInterface $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new ContainerException(
                        "Cannot resolve dependency '{$typeName}' for parameter '{$parameter->getName()}'"
                    );
                }
            }
        }

        return $dependencies;
    }

    /**
     * Get all bound services
     */
    public function getBindings(): array
    {
        return array_keys($this->services);
    }

    /**
     * Clear all services and instances
     */
    public function flush(): void
    {
        $this->services = [];
        $this->instances = [
            ContainerInterface::class => $this,
            self::class => $this
        ];
        $this->factories = [];
        $this->aliases = [];
        $this->objectPool->clear();
    }

    /**
     * Build dependency graph for compile-time optimization
     */
    private function buildDependencyGraph(): void
    {
        foreach ($this->services as $id => $service) {
            if (is_string($service['concrete']) && class_exists($service['concrete'])) {
                try {
                    $reflection = new \ReflectionClass($service['concrete']);
                    $constructor = $reflection->getConstructor();

                    if ($constructor) {
                        $dependencies = [];
                        foreach ($constructor->getParameters() as $parameter) {
                            $type = $parameter->getType();
                            if ($type && !$type->isBuiltin() && $type instanceof \ReflectionNamedType) {
                                $dependencies[] = $type->getName();
                            }
                        }
                        $this->dependencyGraph[$id] = $dependencies;
                    } else {
                        $this->dependencyGraph[$id] = [];
                    }
                } catch (\ReflectionException $e) {
                    $this->dependencyGraph[$id] = [];
                }
            }
        }
    }

    /**
     * Compile instantiation strategies
     */
    private function compileInstantiationStrategies(): void
    {
        foreach ($this->services as $id => $service) {
            $strategy = $this->analyzeInstantiationStrategy($id, $service);
            if ($strategy) {
                $this->instantiationStrategies[$id] = $strategy;
            }
        }
    }

    /**
     * Analyze best instantiation strategy for a service
     */
    private function analyzeInstantiationStrategy(string $id, array $service): ?array
    {
        $concrete = $service['concrete'];

        if (is_callable($concrete)) {
            return [
                'type' => 'callable',
                'callable' => $concrete,
                'singleton' => $service['singleton']
            ];
        }

        if (is_string($concrete) && class_exists($concrete)) {
            $dependencies = $this->dependencyGraph[$id] ?? [];
            return [
                'type' => 'class',
                'class' => $concrete,
                'dependencies' => $dependencies,
                'singleton' => $service['singleton']
            ];
        }

        return null;
    }

    /**
     * Optimize singleton cache
     */
    private function optimizeSingletonCache(): void
    {
        // Pre-populate singleton cache with existing instances
        foreach ($this->instances as $id => $instance) {
            $this->singletonCache[$id] = $instance;
        }
    }

    /**
     * Cache factory functions for faster access
     */
    private function cacheFactoryFunctions(): void
    {
        $this->factoryCache = $this->factories;
    }

    /**
     * Execute pre-compiled service resolution
     */
    private function executeCompiledResolution(string $id): mixed
    {
        $compiled = $this->preCompiledServices[$id];

        // Execute compiled resolution logic
        if ($compiled['type'] === 'singleton' && isset($this->singletonCache[$id])) {
            return $this->singletonCache[$id];
        }

        // Create instance using compiled strategy
        $instance = $this->createInstanceFromCompiled($compiled);

        if ($compiled['singleton']) {
            $this->singletonCache[$id] = $instance;
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Execute optimized instantiation strategy
     */
    private function executeInstantiationStrategy(string $id): mixed
    {
        $strategy = $this->instantiationStrategies[$id];

        if ($strategy['type'] === 'callable') {
            $instance = $strategy['callable']($this);
        } elseif ($strategy['type'] === 'class') {
            $instance = $this->buildOptimized($strategy['class'], $strategy['dependencies']);
        } else {
            return $this->resolve($id);
        }

        if ($strategy['singleton']) {
            $this->singletonCache[$id] = $instance;
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Optimized class building with pre-compiled dependencies
     */
    private function buildOptimized(string $class, array $dependencies): object
    {
        if (empty($dependencies)) {
            return $this->objectPool->get($class) ?? new $class();
        }

        $resolvedDependencies = [];
        foreach ($dependencies as $dependency) {
            $resolvedDependencies[] = $this->get($dependency);
        }

        try {
            /** @var \ReflectionClass<object> $reflection */
            $reflection = new \ReflectionClass($class);
            return $reflection->newInstanceArgs($resolvedDependencies);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Cannot instantiate class '{$class}': {$e->getMessage()}");
        }
    }

    /**
     * Create instance from compiled strategy
     */
    private function createInstanceFromCompiled(array $compiled): mixed
    {
        // This would contain the actual compiled instantiation logic
        // For now, fallback to standard resolution
        return $this->resolve($compiled['id'] ?? '');
    }

    /**
     * Get memory usage statistics
     */
    public function getStats(): array
    {
        return [
            'services' => count($this->services),
            'instances' => count($this->instances),
            'factories' => count($this->factories),
            'aliases' => count($this->aliases),
            'memory_usage' => memory_get_usage(true),
            'object_pool_stats' => $this->objectPool->getStats(),
            // Phase 2 Optimization Stats
            'is_compiled' => $this->isCompiled,
            'pre_compiled_services' => count($this->preCompiledServices),
            'singleton_cache_size' => count($this->singletonCache),
            'factory_cache_size' => count($this->factoryCache),
            'dependency_graph_size' => count($this->dependencyGraph),
            'instantiation_strategies' => count($this->instantiationStrategies),
            'optimizations_enabled' => [
                'dependency_graph_built' => !empty($this->dependencyGraph),
                'instantiation_strategies_compiled' => !empty($this->instantiationStrategies),
                'singleton_cache_optimized' => !empty($this->singletonCache),
                'factory_functions_cached' => !empty($this->factoryCache)
            ]
        ];
    }
}
