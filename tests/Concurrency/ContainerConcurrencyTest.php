<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Concurrency;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use HighPerApp\HighPer\Container\Container;
use HighPerApp\HighPer\Container\ObjectPool;
use HighPerApp\HighPer\Container\ResponsePool;

#[Group('concurrency')]
class ContainerConcurrencyTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    #[TestDox('Container handles concurrent singleton access safely')]
    public function testContainerHandlesConcurrentSingletonAccessSafely(): void
    {
        $this->container->singleton('shared_service', SharedService::class);

        $instances = [];

        // Simulate concurrent access by rapid successive calls
        for ($i = 0; $i < 100; $i++) {
            $instances[] = $this->container->get('shared_service');
        }

        // All instances should be the same object
        $firstInstance = $instances[0];
        foreach ($instances as $instance) {
            $this->assertSame($firstInstance, $instance, 'All singleton instances should be identical');
        }

        // Verify singleton counter (if SharedService has one)
        $this->assertEquals(1, SharedService::getInstanceCount(), 'Only one instance should have been created');
    }

    #[Test]
    #[TestDox('Container compilation is thread-safe')]
    public function testContainerCompilationIsThreadSafe(): void
    {
        // Register services that will be compiled
        for ($i = 0; $i < 50; $i++) {
            $this->container->bind("service_{$i}", ConcurrentTestService::class);
        }

        // Simulate concurrent compilation attempts
        $compiled1 = null;
        $compiled2 = null;

        // First compilation
        $this->container->compile();
        $stats1 = $this->container->getStats();

        // Second compilation (should be idempotent)
        $this->container->compile();
        $stats2 = $this->container->getStats();

        $this->assertEquals($stats1['is_compiled'], $stats2['is_compiled']);
        $this->assertTrue($stats2['is_compiled']);
    }

    #[Test]
    #[TestDox('ObjectPool handles concurrent access without corruption')]
    public function testObjectPoolHandlesConcurrentAccessWithoutCorruption(): void
    {
        $objectPool = new ObjectPool(10);

        // Pre-populate with objects
        for ($i = 0; $i < 5; $i++) {
            $objectPool->put(new ConcurrentTestObject($i));
        }

        $retrievedObjects = [];
        $returnedObjects = [];

        // Simulate concurrent get/put operations
        for ($i = 0; $i < 20; $i++) {
            $object = $objectPool->get(ConcurrentTestObject::class);
            if ($object !== null) {
                $retrievedObjects[] = $object;

                // Return half of them back
                if ($i % 2 === 0) {
                    $objectPool->put($object);
                    $returnedObjects[] = $object;
                }
            }
        }

        $stats = $objectPool->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_objects', $stats);

        // Pool should maintain consistency
        $poolSize = $objectPool->getPoolSize(ConcurrentTestObject::class);
        $this->assertGreaterThanOrEqual(0, $poolSize);
        $this->assertLessThanOrEqual(10, $poolSize); // Should not exceed max pool size
    }

    #[Test]
    #[TestDox('ResponsePool maintains cache consistency under concurrent access')]
    public function testResponsePoolMaintainsCacheConsistencyUnderConcurrentAccess(): void
    {
        $responsePool = new ResponsePool();
        $testData = ['concurrent' => 'test', 'id' => 12345];

        $responses = [];

        // Simulate concurrent JSON response generation
        for ($i = 0; $i < 50; $i++) {
            $responses[] = $responsePool->jsonResponse($testData);
        }

        // All responses should be identical due to caching
        $firstResponse = $responses[0];
        foreach ($responses as $response) {
            $this->assertEquals($firstResponse, $response, 'Cached responses should be identical');
        }

        $stats = $responsePool->getStats();
        $this->assertGreaterThan(0, $stats['json_cache']['hits'], 'Should have cache hits');
        $this->assertGreaterThan(0, $stats['json_cache']['hit_ratio'], 'Should have positive hit ratio');
    }

    #[Test]
    #[TestDox('Container handles concurrent service binding safely')]
    public function testContainerHandlesConcurrentServiceBindingSafely(): void
    {
        $services = [];

        // Simulate concurrent service binding and resolution
        for ($i = 0; $i < 25; $i++) {
            $serviceName = "concurrent_service_{$i}";

            $this->container->bind($serviceName, function () use ($i) {
                return new ConcurrentTestService($i);
            });

            // Immediately try to resolve
            $services[$serviceName] = $this->container->get($serviceName);
        }

        // Verify all services were bound and resolved correctly
        $this->assertCount(25, $services);

        foreach ($services as $serviceName => $service) {
            $this->assertInstanceOf(ConcurrentTestService::class, $service);

            // Resolve again to ensure consistency
            $secondResolve = $this->container->get($serviceName);
            $this->assertInstanceOf(ConcurrentTestService::class, $secondResolve);
        }
    }

    #[Test]
    #[TestDox('Container maintains singleton integrity under rapid access')]
    public function testContainerMaintainsSingletonIntegrityUnderRapidAccess(): void
    {
        $this->container->singleton('rapid_singleton', RapidAccessService::class);

        $instances = [];
        $accessCounts = [];

        // Rapid successive access
        for ($i = 0; $i < 100; $i++) {
            $instance = $this->container->get('rapid_singleton');
            $instances[] = $instance;

            // Call a method that increments internal counter
            $accessCounts[] = $instance->incrementAndGetCount();
        }

        // All instances should be the same
        $firstInstance = $instances[0];
        foreach ($instances as $instance) {
            $this->assertSame($firstInstance, $instance);
        }

        // Counter should reflect all accesses
        $this->assertEquals(100, $firstInstance->getCount());

        // Each call should have incremented the counter
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals($i + 1, $accessCounts[$i]);
        }
    }

    #[Test]
    #[TestDox('Container handles concurrent factory execution safely')]
    public function testContainerHandlesConcurrentFactoryExecutionSafely(): void
    {
        $factoryCallCount = 0;

        $this->container->factory('factory_service', function ($container) use (&$factoryCallCount) {
            $factoryCallCount++;

            // Simulate some work in factory
            usleep(100); // 0.1ms

            return new ConcurrentTestService($factoryCallCount);
        });

        $services = [];

        // Concurrent factory calls
        for ($i = 0; $i < 20; $i++) {
            $services[] = $this->container->get('factory_service');
        }

        // All services should be valid instances
        foreach ($services as $service) {
            $this->assertInstanceOf(ConcurrentTestService::class, $service);
        }

        // Factory should have been called for each request
        $this->assertEquals(20, $factoryCallCount);

        // All instances should be different (factory pattern)
        for ($i = 0; $i < count($services) - 1; $i++) {
            for ($j = $i + 1; $j < count($services); $j++) {
                $this->assertNotSame($services[$i], $services[$j]);
            }
        }
    }

    #[Test]
    #[TestDox('Container statistics remain consistent under concurrent operations')]
    public function testContainerStatisticsRemainConsistentUnderConcurrentOperations(): void
    {
        // Perform various concurrent operations
        for ($i = 0; $i < 10; $i++) {
            $this->container->bind("bind_service_{$i}", ConcurrentTestService::class);
            $this->container->singleton("singleton_service_{$i}", ConcurrentTestService::class);
            $this->container->factory("factory_service_{$i}", function () use ($i) {
                return new ConcurrentTestService($i);
            });
        }

        // Resolve some services
        for ($i = 0; $i < 5; $i++) {
            $this->container->get("bind_service_{$i}");
            $this->container->get("singleton_service_{$i}");
            $this->container->get("factory_service_{$i}");
        }

        $stats = $this->container->getStats();

        // Verify statistics integrity
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('services', $stats);
        $this->assertArrayHasKey('instances', $stats);
        $this->assertArrayHasKey('factories', $stats);

        $this->assertGreaterThanOrEqual(20, $stats['services']); // 10 bind + 10 singleton
        $this->assertGreaterThanOrEqual(5, $stats['instances']); // At least 5 resolved singletons
        $this->assertGreaterThanOrEqual(10, $stats['factories']); // 10 factory services
    }
}

class SharedService
{
    private static int $instanceCount = 0;
    private int $id;

    public function __construct()
    {
        self::$instanceCount++;
        $this->id = self::$instanceCount;
    }

    public static function getInstanceCount(): int
    {
        return self::$instanceCount;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

class ConcurrentTestService
{
    public function __construct(private int $value = 0)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class ConcurrentTestObject
{
    public function __construct(private int $id = 0)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function reset(): void
    {
        $this->id = 0;
    }
}

class RapidAccessService
{
    private int $count = 0;

    public function incrementAndGetCount(): int
    {
        return ++$this->count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
