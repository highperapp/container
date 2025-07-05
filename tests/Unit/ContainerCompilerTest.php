<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use HighPerApp\HighPer\Container\ContainerCompiler;

#[Group('unit')]
#[Group('compiler')]
class ContainerCompilerTest extends TestCase
{
    private ContainerCompiler $compiler;
    private string $testCachePath;

    protected function setUp(): void
    {
        $this->testCachePath = sys_get_temp_dir() . '/test_highper_container_' . uniqid();
        $this->compiler = new ContainerCompiler($this->testCachePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCachePath)) {
            unlink($this->testCachePath);
        }
    }

    #[Test]
    #[TestDox('ContainerCompiler can compile simple service definitions')]
    public function testContainerCompilerCanCompileSimpleServiceDefinitions(): void
    {
        $definitions = [
            'test_service' => [
                'class' => 'stdClass',
                'dependencies' => []
            ]
        ];

        $compiled = $this->compiler->compileContainer($definitions);

        $this->assertIsString($compiled);
        $this->assertStringContainsString('<?php', $compiled);
        $this->assertStringContainsString('test_service', $compiled);
        $this->assertStringContainsString('stdClass', $compiled);
    }

    #[Test]
    #[TestDox('ContainerCompiler can compile services with dependencies')]
    public function testContainerCompilerCanCompileServicesWithDependencies(): void
    {
        $definitions = [
            'dependency_service' => [
                'class' => 'DateTime',
                'dependencies' => []
            ],
            'main_service' => [
                'class' => 'ArrayObject',
                'dependencies' => ['dependency_service']
            ]
        ];

        $compiled = $this->compiler->compileContainer($definitions);

        $this->assertStringContainsString('dependency_service', $compiled);
        $this->assertStringContainsString('main_service', $compiled);
        $this->assertStringContainsString('$container->get(\'dependency_service\')', $compiled);
    }

    #[Test]
    #[TestDox('ContainerCompiler validates compiled code')]
    public function testContainerCompilerValidatesCompiledCode(): void
    {
        $definitions = [
            'test_service' => [
                'class' => 'stdClass',
                'dependencies' => []
            ]
        ];

        $compiled = $this->compiler->compileContainer($definitions);
        $isValid = $this->compiler->validateCompiled($compiled);

        $this->assertTrue($isValid);
    }

    #[Test]
    #[TestDox('ContainerCompiler provides compilation statistics')]
    public function testContainerCompilerProvidesCompilationStatistics(): void
    {
        $definitions = [
            'service1' => ['class' => 'stdClass', 'dependencies' => []],
            'service2' => ['class' => 'DateTime', 'dependencies' => []],
        ];

        $this->compiler->compileContainer($definitions);
        $stats = $this->compiler->getStats();

        $this->assertArrayHasKey('compiled', $stats);
        $this->assertArrayHasKey('cache_hits', $stats);
        $this->assertArrayHasKey('cache_exists', $stats);
        $this->assertArrayHasKey('cache_size', $stats);
        $this->assertEquals(1, $stats['compiled']);
    }

    #[Test]
    #[TestDox('ContainerCompiler checks if compiler is available')]
    public function testContainerCompilerChecksIfCompilerIsAvailable(): void
    {
        $this->assertTrue($this->compiler->isAvailable());
    }

    #[Test]
    #[TestDox('ContainerCompiler can clear cache')]
    public function testContainerCompilerCanClearCache(): void
    {
        $definitions = ['test' => ['class' => 'stdClass', 'dependencies' => []]];
        $this->compiler->compileContainer($definitions);

        $this->assertTrue(file_exists($this->testCachePath));
        $this->assertTrue($this->compiler->clearCache());
        $this->assertFalse(file_exists($this->testCachePath));
    }

    #[Test]
    #[TestDox('ContainerCompiler can warm cache')]
    public function testContainerCompilerCanWarmCache(): void
    {
        $definitions = ['test' => ['class' => 'stdClass', 'dependencies' => []]];
        $this->compiler->compileContainer($definitions);

        $this->assertTrue($this->compiler->warmCache());

        $stats = $this->compiler->getStats();
        $this->assertGreaterThan(0, $stats['cache_hits']);
    }

    #[Test]
    #[TestDox('ContainerCompiler provides cache path')]
    public function testContainerCompilerProvidesCachePath(): void
    {
        $this->assertEquals($this->testCachePath, $this->compiler->getCachePath());
    }

    #[Test]
    #[TestDox('ContainerCompiler handles empty definitions')]
    public function testContainerCompilerHandlesEmptyDefinitions(): void
    {
        $compiled = $this->compiler->compileContainer([]);

        $this->assertIsString($compiled);
        $this->assertStringContainsString('<?php', $compiled);
        $this->assertStringContainsString('return [', $compiled);
    }

    #[Test]
    #[TestDox('ContainerCompiler creates cache directory if needed')]
    public function testContainerCompilerCreatesCacheDirectoryIfNeeded(): void
    {
        $nestedPath = sys_get_temp_dir() . '/nested/path/cache_' . uniqid();
        $compiler = new ContainerCompiler($nestedPath);

        $this->assertTrue($compiler->isAvailable());

        // Clean up
        if (file_exists($nestedPath)) {
            unlink($nestedPath);
        }
        $parentDir = dirname($nestedPath);
        if (is_dir($parentDir)) {
            rmdir($parentDir);
            rmdir(dirname($parentDir));
        }
    }
}
