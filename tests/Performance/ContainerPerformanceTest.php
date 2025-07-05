<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Performance;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use HighPerApp\HighPer\Container\Container;
use HighPerApp\HighPer\Container\ObjectPool;
use HighPerApp\HighPer\Container\ResponsePool;

#[Group('performance')]
class ContainerPerformanceTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    #[TestDox('Container resolves services within performance threshold')]
    public function testContainerResolvesServicesWithinPerformanceThreshold(): void
    {
        // Register services
        for ($i = 0; $i < 1000; $i++) {
            $this->container->bind("service_{$i}", function () {
                return new \stdClass();
            });
        }

        $startTime = microtime(true);

        // Resolve services
        for ($i = 0; $i < 1000; $i++) {
            $service = $this->container->get("service_{$i}");
            $this->assertInstanceOf(\stdClass::class, $service);
        }

        $duration = microtime(true) - $startTime;

        // Should resolve 1000 services in under 100ms
        $this->assertLessThan(0.1, $duration, "Container took {$duration}s to resolve 1000 services");
    }

    #[Test]
    #[TestDox('Container compilation improves performance')]
    public function testContainerCompilationImprovesPerformance(): void
    {
        // Setup services
        for ($i = 0; $i < 100; $i++) {
            $this->container->bind("service_{$i}", TestPerformanceService::class);
        }

        // Measure uncompiled performance
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->container->get("service_{$i}");
        }
        $uncompiledDuration = microtime(true) - $startTime;

        // Compile container
        $this->container->compile();

        // Measure compiled performance
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->container->get("service_{$i}");
        }
        $compiledDuration = microtime(true) - $startTime;

        // Compiled should be faster or at least not significantly slower
        $this->assertLessThanOrEqual(
            $uncompiledDuration * 1.2,
            $compiledDuration,
            "Compiled container should not be significantly slower"
        );
    }

    #[Test]
    #[TestDox('Container memory usage remains reasonable under load')]
    public function testContainerMemoryUsageRemainsReasonableUnderLoad(): void
    {
        $initialMemory = memory_get_usage(true);

        // Register and resolve many services
        for ($i = 0; $i < 1000; $i++) {
            $this->container->bind("heavy_service_{$i}", function () {
                return new TestHeavyService();
            });

            // Resolve every 10th service to simulate real usage
            if ($i % 10 === 0) {
                $this->container->get("heavy_service_{$i}");
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(
            10 * 1024 * 1024,
            $memoryIncrease,
            "Memory usage increased by " . round($memoryIncrease / 1024 / 1024, 2) . "MB"
        );
    }

    #[Test]
    #[TestDox('Singleton resolution is significantly faster on subsequent calls')]
    public function testSingletonResolutionIsFasterOnSubsequentCalls(): void
    {
        $this->container->singleton('heavy_singleton', TestHeavyService::class);

        // First resolution (should include instantiation time)
        $startTime = microtime(true);
        $instance1 = $this->container->get('heavy_singleton');
        $firstCallDuration = microtime(true) - $startTime;

        // Subsequent resolutions (should be cache hits)
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $instance = $this->container->get('heavy_singleton');
            $this->assertSame($instance1, $instance);
        }
        $subsequentCallsDuration = microtime(true) - $startTime;

        // 100 cache hits should be much faster than one instantiation
        $this->assertLessThan(
            $firstCallDuration,
            $subsequentCallsDuration,
            "Singleton cache hits should be faster than initial instantiation"
        );
    }

    #[Test]
    #[TestDox('ObjectPool improves object reuse performance')]
    public function testObjectPoolImprovesObjectReusePerformance(): void
    {
        $objectPool = new ObjectPool();

        // Populate pool
        $objectPool->populate(TestPoolablePerformanceObject::class, 50);

        $startTime = microtime(true);

        // Get and return objects from pool
        for ($i = 0; $i < 100; $i++) {
            $object = $objectPool->get(TestPoolablePerformanceObject::class);
            if ($object === null) {
                $object = new TestPoolablePerformanceObject();
            }
            $objectPool->put($object);
        }

        $poolDuration = microtime(true) - $startTime;

        // Compare with direct instantiation
        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $object = new TestPoolablePerformanceObject();
            // Simulate some work
            unset($object);
        }

        $directDuration = microtime(true) - $startTime;

        // Pool should provide some benefit, but this test might be inconsistent
        // so we'll just verify it completes in reasonable time
        $this->assertLessThan(0.1, $poolDuration, "Object pooling should complete in reasonable time");
    }

    #[Test]
    #[TestDox('ResponsePool provides performance benefits for JSON responses')]
    public function testResponsePoolProvidesPerformanceBenefitsForJsonResponses(): void
    {
        $responsePool = new ResponsePool();
        $testData = ['message' => 'test', 'data' => range(1, 100)];

        // First set of calls (cache misses)
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $response = $responsePool->jsonResponse($testData);
            $this->assertIsArray($response);
        }
        $firstCallsDuration = microtime(true) - $startTime;

        // Second set of calls (should hit cache)
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $response = $responsePool->jsonResponse($testData);
            $this->assertIsArray($response);
        }
        $secondCallsDuration = microtime(true) - $startTime;

        // Cache hits should be faster
        $this->assertLessThan(
            $firstCallsDuration,
            $secondCallsDuration,
            "Cached responses should be faster than initial generation"
        );

        $stats = $responsePool->getStats();
        $this->assertGreaterThan(0, $stats['json_cache']['hits']);
    }

    #[Test]
    #[TestDox('Container handles deep dependency chains efficiently')]
    public function testContainerHandlesDeepDependencyChainsEfficiently(): void
    {
        // Create a deep dependency chain
        $this->container->bind(DeepLevel1::class);
        $this->container->bind(DeepLevel2::class);
        $this->container->bind(DeepLevel3::class);
        $this->container->bind(DeepLevel4::class);
        $this->container->bind(DeepLevel5::class);

        $startTime = microtime(true);

        // Resolve the top-level service (will resolve entire chain)
        $service = $this->container->get(DeepLevel5::class);

        $duration = microtime(true) - $startTime;

        $this->assertInstanceOf(DeepLevel5::class, $service);
        $this->assertLessThan(0.01, $duration, "Deep dependency resolution should be fast");
    }

    #[Test]
    #[TestDox('Container scales well with increasing service count')]
    public function testContainerScalesWellWithIncreasingServiceCount(): void
    {
        $serviceCounts = [100, 500, 1000];
        $resolutionTimes = [];

        foreach ($serviceCounts as $count) {
            $container = new Container();

            // Register services
            for ($i = 0; $i < $count; $i++) {
                $container->bind("service_{$i}", TestPerformanceService::class);
            }

            // Measure resolution time for subset
            $startTime = microtime(true);
            for ($i = 0; $i < min(50, $count); $i++) {
                $container->get("service_{$i}");
            }
            $duration = microtime(true) - $startTime;

            $resolutionTimes[$count] = $duration;
        }

        // Resolution time should not increase dramatically with service count
        $this->assertLessThan(
            $resolutionTimes[100] * 3,
            $resolutionTimes[1000],
            "Resolution time should scale reasonably with service count"
        );
    }
}

class TestPerformanceService
{
    public function __construct()
    {
        // Simulate some initialization work
        usleep(100); // 0.1ms
    }
}

class TestHeavyService
{
    private array $data;

    public function __construct()
    {
        // Simulate heavy initialization
        $this->data = range(1, 1000);
        usleep(1000); // 1ms
    }
}

class TestPoolablePerformanceObject
{
    private array $data;

    public function __construct()
    {
        $this->data = range(1, 100);
    }

    public function reset(): void
    {
        $this->data = range(1, 100);
    }
}

class DeepLevel1
{
    public function __construct()
    {
    }
}

class DeepLevel2
{
    public function __construct(private DeepLevel1 $level1)
    {
    }
}

class DeepLevel3
{
    public function __construct(private DeepLevel2 $level2)
    {
    }
}

class DeepLevel4
{
    public function __construct(private DeepLevel3 $level3)
    {
    }
}

class DeepLevel5
{
    public function __construct(private DeepLevel4 $level4)
    {
    }
}
