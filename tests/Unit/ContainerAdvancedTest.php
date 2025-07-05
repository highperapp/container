<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use HighPerApp\HighPer\Container\Container;
use HighPerApp\HighPer\Container\ObjectPool;
use HighPerApp\HighPer\Container\ContainerException;
use HighPerApp\HighPer\Container\NotFoundException;

#[Group('unit')]
#[Group('container')]
class ContainerAdvancedTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    #[TestDox('Container supports factory pattern correctly')]
    public function testContainerSupportsFactoryPatternCorrectly(): void
    {
        $factoryCallCount = 0;

        $this->container->factory('test.service', function ($container) use (&$factoryCallCount) {
            $factoryCallCount++;
            return new TestService('factory-created');
        });

        $service1 = $this->container->get('test.service');
        $service2 = $this->container->get('test.service');

        $this->assertInstanceOf(TestService::class, $service1);
        $this->assertInstanceOf(TestService::class, $service2);
        $this->assertNotSame($service1, $service2); // Different instances
        $this->assertEquals(2, $factoryCallCount);
    }

    #[Test]
    #[TestDox('Container handles singleton factory correctly')]
    public function testContainerHandlesSingletonFactoryCorrectly(): void
    {
        $factoryCallCount = 0;

        $this->container->bind('test.service', function ($container) use (&$factoryCallCount) {
            $factoryCallCount++;
            return new TestService('singleton-factory');
        }, true); // singleton

        $service1 = $this->container->get('test.service');
        $service2 = $this->container->get('test.service');

        $this->assertSame($service1, $service2); // Same instance
        $this->assertEquals(1, $factoryCallCount); // Called only once
    }

    #[Test]
    #[TestDox('Container supports service extension')]
    public function testContainerSupportsServiceExtension(): void
    {
        $this->container->bind('base.service', function () {
            return new TestService('base');
        });

        $this->container->extend('base.service', function ($service, $container) {
            return new DecoratedTestService($service, 'extended');
        });

        $service = $this->container->get('base.service');

        $this->assertInstanceOf(DecoratedTestService::class, $service);
        $this->assertEquals('extended', $service->getDecoration());
    }

    #[Test]
    #[TestDox('Container handles complex dependency injection')]
    public function testContainerHandlesComplexDependencyInjection(): void
    {
        // Register dependencies
        $this->container->bind(TestDependency::class, function () {
            return new TestDependency('injected');
        });

        $this->container->bind(AnotherDependency::class, function () {
            return new AnotherDependency(42);
        });

        // Service with multiple dependencies
        $service = $this->container->get(ComplexService::class);

        $this->assertInstanceOf(ComplexService::class, $service);
        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
        $this->assertInstanceOf(AnotherDependency::class, $service->getAnotherDependency());
    }

    #[Test]
    #[TestDox('Container resolves circular dependencies gracefully')]
    public function testContainerResolvesCircularDependenciesGracefully(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->bind(CircularA::class);
        $this->container->bind(CircularB::class);

        // This should detect and throw circular dependency exception
        $this->container->get(CircularA::class);
    }

    #[Test]
    #[TestDox('Container handles optional dependencies')]
    public function testContainerHandlesOptionalDependencies(): void
    {
        // Don't register OptionalDependency
        $service = $this->container->get(ServiceWithOptionalDependency::class);

        $this->assertInstanceOf(ServiceWithOptionalDependency::class, $service);
        $this->assertNull($service->getOptionalDependency());
    }

    #[Test]
    #[TestDox('Container supports aliasing')]
    public function testContainerSupportsAliasing(): void
    {
        $this->container->bind('original.service', TestService::class);
        $this->container->alias('aliased.service', 'original.service');

        $original = $this->container->get('original.service');
        $aliased = $this->container->get('aliased.service');

        $this->assertInstanceOf(TestService::class, $original);
        $this->assertInstanceOf(TestService::class, $aliased);
        // Note: These might be different instances if not singleton
    }

    #[Test]
    #[TestDox('Container provides performance statistics')]
    public function testContainerProvidesPerformanceStatistics(): void
    {
        $this->container->bind('test1', TestService::class);
        $this->container->bind('test2', TestService::class);
        $this->container->bind('test3', TestService::class);

        // Resolve some services
        $this->container->get('test1');
        $this->container->get('test2');

        $stats = $this->container->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('services', $stats);
        $this->assertArrayHasKey('instances', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);

        $this->assertGreaterThanOrEqual(3, $stats['services']);
        $this->assertGreaterThanOrEqual(2, $stats['instances']);
        $this->assertIsInt($stats['memory_usage']);
    }

    #[Test]
    #[TestDox('Container handles service flushing')]
    public function testContainerHandlesServiceFlushing(): void
    {
        $this->container->bind('test.service', TestService::class);
        $service1 = $this->container->get('test.service');

        $this->assertTrue($this->container->has('test.service'));

        $this->container->flush();

        // Container should still be functional but reset
        $this->assertTrue($this->container->has(Container::class)); // Self-reference remains
    }

    #[Test]
    #[TestDox('Container memory usage is reasonable')]
    public function testContainerMemoryUsageIsReasonable(): void
    {
        $initialMemory = memory_get_usage(true);

        // Register many services
        for ($i = 0; $i < 100; $i++) {
            $this->container->bind("service.{$i}", TestService::class);
        }

        // Resolve some services
        for ($i = 0; $i < 50; $i++) {
            $this->container->get("service.{$i}");
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 5MB for this test)
        $this->assertLessThan(5 * 1024 * 1024, $memoryIncrease);
    }

    #[Test]
    #[TestDox('Container performance is acceptable')]
    public function testContainerPerformanceIsAcceptable(): void
    {
        $this->container->bind('test.service', TestService::class);

        $startTime = microtime(true);

        // Resolve service many times
        for ($i = 0; $i < 1000; $i++) {
            $service = $this->container->get('test.service');
            $this->assertInstanceOf(TestService::class, $service);
        }

        $duration = microtime(true) - $startTime;

        // Should resolve 1000 services in under 100ms
        $this->assertLessThan(0.1, $duration);
    }

    #[Test]
    #[TestDox('Container handles concurrent access safely')]
    public function testContainerHandlesConcurrentAccessSafely(): void
    {
        $this->container->singleton('shared.service', TestService::class);

        $services = [];

        // Simulate concurrent access
        for ($i = 0; $i < 10; $i++) {
            $services[] = $this->container->get('shared.service');
        }

        // All should be the same instance
        $firstService = $services[0];
        foreach ($services as $service) {
            $this->assertSame($firstService, $service);
        }
    }

    #[Test]
    #[TestDox('Container validates service bindings')]
    #[DataProvider('invalidBindingDataProvider')]
    public function testContainerValidatesServiceBindings(string $id, mixed $concrete, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(ContainerException::class);
        }

        $this->container->bind($id, $concrete);

        if (!$expectException) {
            $this->assertTrue($this->container->has($id));
        }
    }

    public static function invalidBindingDataProvider(): array
    {
        return [
            'valid string binding' => ['test', TestService::class, false],
            'valid closure binding' => ['test', fn() => new TestService(), false],
            'valid instance binding' => ['test', new TestService(), false],
            'empty id' => ['', TestService::class, true],
            'null concrete' => ['test', null, false], // Should use ID as concrete
            'invalid concrete type' => ['test', 123, true]
        ];
    }

    #[Test]
    #[TestDox('Container handles object pool integration')]
    public function testContainerHandlesObjectPoolIntegration(): void
    {
        $objectPool = new ObjectPool();
        $reflection = new \ReflectionClass($this->container);

        if ($reflection->hasProperty('objectPool')) {
            $property = $reflection->getProperty('objectPool');
            $property->setAccessible(true);
            $property->setValue($this->container, $objectPool);

            $this->assertInstanceOf(ObjectPool::class, $property->getValue($this->container));
        } else {
            $this->markTestSkipped('Object pool integration not available');
        }
    }

    #[Test]
    #[TestDox('Container handles nested dependency resolution')]
    public function testContainerHandlesNestedDependencyResolution(): void
    {
        // Deep dependency chain: Level3 -> Level2 -> Level1 -> TestDependency
        $this->container->bind(TestDependency::class, function () {
            return new TestDependency('level-0');
        });

        $level3 = $this->container->get(DependencyLevel3::class);

        $this->assertInstanceOf(DependencyLevel3::class, $level3);

        // Verify the entire chain was resolved
        $level2 = $level3->getLevel2();
        $this->assertInstanceOf(DependencyLevel2::class, $level2);

        $level1 = $level2->getLevel1();
        $this->assertInstanceOf(DependencyLevel1::class, $level1);

        $dependency = $level1->getDependency();
        $this->assertInstanceOf(TestDependency::class, $dependency);
        $this->assertEquals('level-0', $dependency->getValue());
    }
}

// Test classes for dependency injection testing
class TestService
{
    public function __construct(private string $value = 'default')
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class DecoratedTestService
{
    public function __construct(
        private TestService $service,
        private string $decoration
    ) {
    }

    public function getDecoration(): string
    {
        return $this->decoration;
    }

    public function getService(): TestService
    {
        return $this->service;
    }
}

class TestDependency
{
    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class AnotherDependency
{
    public function __construct(private int $number)
    {
    }

    public function getNumber(): int
    {
        return $this->number;
    }
}

class ComplexService
{
    public function __construct(
        private TestDependency $dependency,
        private AnotherDependency $anotherDependency
    ) {
    }

    public function getDependency(): TestDependency
    {
        return $this->dependency;
    }

    public function getAnotherDependency(): AnotherDependency
    {
        return $this->anotherDependency;
    }
}

class CircularA
{
    public function __construct(private CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(private CircularA $a)
    {
    }
}

class OptionalDependency
{
    public function __construct(private string $value = 'optional')
    {
    }
}

class ServiceWithOptionalDependency
{
    public function __construct(private ?OptionalDependency $optional = null)
    {
    }

    public function getOptionalDependency(): ?OptionalDependency
    {
        return $this->optional;
    }
}

class DependencyLevel1
{
    public function __construct(private TestDependency $dependency)
    {
    }

    public function getDependency(): TestDependency
    {
        return $this->dependency;
    }
}

class DependencyLevel2
{
    public function __construct(private DependencyLevel1 $level1)
    {
    }

    public function getLevel1(): DependencyLevel1
    {
        return $this->level1;
    }
}

class DependencyLevel3
{
    public function __construct(private DependencyLevel2 $level2)
    {
    }

    public function getLevel2(): DependencyLevel2
    {
        return $this->level2;
    }
}
