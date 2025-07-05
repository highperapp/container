<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use HighPerApp\HighPer\Container\ObjectPool;

#[Group('unit')]
#[Group('object-pool')]
class ObjectPoolTest extends TestCase
{
    private ObjectPool $objectPool;

    protected function setUp(): void
    {
        $this->objectPool = new ObjectPool();
    }

    #[Test]
    #[TestDox('ObjectPool can store and retrieve objects')]
    public function testObjectPoolCanStoreAndRetrieveObjects(): void
    {
        $testObject = new TestPoolableObject();

        $this->assertTrue($this->objectPool->put($testObject));

        $retrievedObject = $this->objectPool->get(TestPoolableObject::class);

        $this->assertSame($testObject, $retrievedObject);
    }

    #[Test]
    #[TestDox('ObjectPool returns null for empty pool')]
    public function testObjectPoolReturnsNullForEmptyPool(): void
    {
        $result = $this->objectPool->get(TestPoolableObject::class);

        $this->assertNull($result);
    }

    #[Test]
    #[TestDox('ObjectPool respects max pool size')]
    public function testObjectPoolRespectsMaxPoolSize(): void
    {
        $pool = new ObjectPool(2);

        $object1 = new TestPoolableObject();
        $object2 = new TestPoolableObject();
        $object3 = new TestPoolableObject();

        $this->assertTrue($pool->put($object1));
        $this->assertTrue($pool->put($object2));
        $this->assertFalse($pool->put($object3)); // Should exceed max size
    }

    #[Test]
    #[TestDox('ObjectPool calls reset method if available')]
    public function testObjectPoolCallsResetMethodIfAvailable(): void
    {
        $resetableObject = new ResettableObject();
        $resetableObject->setValue('test');

        $this->objectPool->put($resetableObject);
        $retrieved = $this->objectPool->get(ResettableObject::class);

        $this->assertSame($resetableObject, $retrieved);
        $this->assertEquals('reset', $retrieved->getValue());
    }

    #[Test]
    #[TestDox('ObjectPool can clear all pools')]
    public function testObjectPoolCanClearAllPools(): void
    {
        $this->objectPool->put(new TestPoolableObject());
        $this->objectPool->put(new ResettableObject());

        $this->objectPool->clear();

        $this->assertNull($this->objectPool->get(TestPoolableObject::class));
        $this->assertNull($this->objectPool->get(ResettableObject::class));
    }

    #[Test]
    #[TestDox('ObjectPool can clear specific class pool')]
    public function testObjectPoolCanClearSpecificClassPool(): void
    {
        $testObject = new TestPoolableObject();
        $resetableObject = new ResettableObject();

        $this->objectPool->put($testObject);
        $this->objectPool->put($resetableObject);

        $this->objectPool->clearClass(TestPoolableObject::class);

        $this->assertNull($this->objectPool->get(TestPoolableObject::class));
        $this->assertSame($resetableObject, $this->objectPool->get(ResettableObject::class));
    }

    #[Test]
    #[TestDox('ObjectPool provides correct statistics')]
    public function testObjectPoolProvidesCorrectStatistics(): void
    {
        $this->objectPool->put(new TestPoolableObject());
        $this->objectPool->put(new TestPoolableObject());

        $stats = $this->objectPool->getStats();

        $this->assertArrayHasKey('total_pools', $stats);
        $this->assertArrayHasKey('total_objects', $stats);
        $this->assertArrayHasKey('max_pool_size', $stats);
        $this->assertEquals(1, $stats['total_pools']);
        $this->assertEquals(2, $stats['total_objects']);
    }

    #[Test]
    #[TestDox('ObjectPool can populate with objects')]
    public function testObjectPoolCanPopulateWithObjects(): void
    {
        $this->objectPool->populate(TestPoolableObject::class, 5);

        $stats = $this->objectPool->getStats();
        $this->assertEquals(5, $stats['total_objects']);

        // Verify we can retrieve populated objects
        for ($i = 0; $i < 5; $i++) {
            $object = $this->objectPool->get(TestPoolableObject::class);
            $this->assertInstanceOf(TestPoolableObject::class, $object);
        }
    }

    #[Test]
    #[TestDox('ObjectPool throws exception for invalid class in populate')]
    public function testObjectPoolThrowsExceptionForInvalidClassInPopulate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' does not exist");

        $this->objectPool->populate('NonExistentClass', 1);
    }

    #[Test]
    #[TestDox('ObjectPool handles pool size queries')]
    public function testObjectPoolHandlesPoolSizeQueries(): void
    {
        $this->assertEquals(0, $this->objectPool->getPoolSize(TestPoolableObject::class));

        $this->objectPool->put(new TestPoolableObject());
        $this->assertEquals(1, $this->objectPool->getPoolSize(TestPoolableObject::class));

        $this->objectPool->get(TestPoolableObject::class);
        $this->assertEquals(0, $this->objectPool->getPoolSize(TestPoolableObject::class));
    }

    #[Test]
    #[TestDox('ObjectPool tracks if class has pool')]
    public function testObjectPoolTracksIfClassHasPool(): void
    {
        $this->assertFalse($this->objectPool->hasPool(TestPoolableObject::class));

        $this->objectPool->put(new TestPoolableObject());
        $this->assertTrue($this->objectPool->hasPool(TestPoolableObject::class));
    }
}

class TestPoolableObject
{
    public string $value = 'test';
}

class ResettableObject
{
    private string $value = 'initial';

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function reset(): void
    {
        $this->value = 'reset';
    }
}
