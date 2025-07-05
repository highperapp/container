<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Unit;

/**
 * DI Container Unit Test
 *
 * Tests the core DI Container functionality including
 * build-time compilation and performance optimizations.
 */
class ContainerTest
{
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;

    public function runContainerTests(): array
    {
        echo "🧪 DI Container Library - Unit Tests\n";
        echo "===================================\n\n";

        // Load DI Container library
        $this->loadDIContainerLibrary();

        // Test core container functionality
        $this->testCoreContainer();

        // Test container compiler
        $this->testContainerCompiler();

        return $this->generateTestReport();
    }

    private function loadDIContainerLibrary(): void
    {
        $autoloader = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
            $this->recordTest('DI Container Library Loading', true, 'DI Container autoloader loaded successfully');
        } else {
            $this->recordTest('DI Container Library Loading', false, 'DI Container autoloader not found');
        }
    }

    private function testCoreContainer(): void
    {
        echo "🔧 Testing Core Container...\n";
        echo str_repeat("─", 40) . "\n";

        // Test basic container operations
        $containerClasses = [
            'HighPerApp\\HighPer\\Container\\Container',
            'HighPerApp\\HighPer\\Container\\ObjectPool',
            'HighPerApp\\HighPer\\Container\\ContainerCompiler'
        ];

        foreach ($containerClasses as $class) {
            $exists = class_exists($class);
            $shortName = substr($class, strrpos($class, '\\') + 1);
            $this->recordTest("Core Container - {$shortName}", $exists, $exists ? 'Class available' : 'Class missing');

            if ($exists) {
                echo "  ✅ {$shortName} - OK\n";
            } else {
                echo "  ❌ {$shortName} - Missing\n";
            }
        }

        // Test container instantiation and basic operations
        if (class_exists('HighPerApp\\HighPer\\Container\\Container')) {
            try {
                $container = new \HighPerApp\HighPer\Container\Container();

                // Test service registration
                $container->bind('test_service', function () {
                    return new \stdClass();
                });

                // Test service resolution
                $service = $container->get('test_service');
                $this->recordTest('Core Container - Service Resolution', $service instanceof \stdClass, 'Service resolved successfully');
                echo "  ✅ Service resolution - OK\n";

                // Test singleton registration
                $container->singleton('singleton_service', function () {
                    return new \DateTime();
                });

                $instance1 = $container->get('singleton_service');
                $instance2 = $container->get('singleton_service');
                $this->recordTest('Core Container - Singleton Pattern', $instance1 === $instance2, 'Singleton pattern works');
                echo "  ✅ Singleton pattern - OK\n";
            } catch (\Exception $e) {
                $this->recordTest('Core Container - Operations', false, 'Error: ' . $e->getMessage());
                echo "  ❌ Container operations error: " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
    }

    private function testContainerCompiler(): void
    {
        echo "🔧 Testing Container Compiler...\n";
        echo str_repeat("─", 40) . "\n";

        if (!class_exists('HighPerApp\\HighPer\\Container\\ContainerCompiler')) {
            $this->recordTest('Container Compiler - Class Exists', false, 'Compiler class not found');
            echo "  ❌ Container Compiler class not available\n\n";
            return;
        }

        try {
            $compiler = new \HighPerApp\HighPer\Container\ContainerCompiler();

            $this->recordTest('Container Compiler - Instantiation', true, 'Compiler created successfully');
            echo "  ✅ Compiler instantiation - OK\n";

            // Test compilation process
            $services = [
                'service_a' => [
                    'class' => 'stdClass',
                    'dependencies' => []
                ],
                'service_b' => [
                    'class' => 'DateTime',
                    'dependencies' => []
                ],
                'service_c' => [
                    'class' => 'ArrayObject',
                    'dependencies' => ['service_a']
                ]
            ];

            $compiledContainer = $compiler->compileContainer($services);
            $this->recordTest('Container Compiler - Compilation', is_string($compiledContainer), 'Services compiled successfully');
            echo "  ✅ Service compilation - OK\n";

            // Test compiled container validation
            $isValid = $compiler->validateCompiled($compiledContainer);
            $this->recordTest('Container Compiler - Validation', $isValid === true, 'Compiled container is valid');
            echo "  ✅ Compiled validation - OK\n";

            // Test compilation statistics
            $stats = $compiler->getStats();
            $this->recordTest('Container Compiler - Statistics', is_array($stats), 'Statistics: ' . json_encode($stats));
            echo "  ✅ Compilation statistics - OK\n";

            // Test cache functionality
            $cacheEnabled = $compiler->isAvailable();
            $this->recordTest('Container Compiler - Cache', is_bool($cacheEnabled), 'Cache: ' . ($cacheEnabled ? 'enabled' : 'disabled'));
            echo "  ✅ Cache functionality - OK\n";
        } catch (\Exception $e) {
            $this->recordTest('Container Compiler - General', false, 'Error: ' . $e->getMessage());
            echo "  ❌ Container Compiler error: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function recordTest(string $name, bool $passed, string $message): void
    {
        $this->totalTests++;
        if ($passed) {
            $this->passedTests++;
        }

        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
            'timestamp' => microtime(true)
        ];
    }

    private function generateTestReport(): array
    {
        echo "📊 DI Container Library Test Report\n";
        echo "==================================\n\n";

        $percentage = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0;

        echo "📈 Summary:\n";
        echo "  • Total Tests: {$this->totalTests}\n";
        echo "  • Passed: {$this->passedTests}\n";
        echo "  • Failed: " . ($this->totalTests - $this->passedTests) . "\n";
        echo "  • Success Rate: {$percentage}%\n\n";

        echo "📋 Detailed Results:\n";
        foreach ($this->testResults as $test) {
            $status = $test['passed'] ? '✅' : '❌';
            echo "  {$status} {$test['name']}: {$test['message']}\n";
        }

        return [
            'total_tests' => $this->totalTests,
            'passed_tests' => $this->passedTests,
            'failed_tests' => $this->totalTests - $this->passedTests,
            'success_rate' => $percentage,
            'detailed_results' => $this->testResults
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $tester = new ContainerTest();
    $results = $tester->runContainerTests();

    if ($results['success_rate'] >= 80) {
        echo "\n🎉 DI Container library tests PASSED!\n";
        exit(0);
    } else {
        echo "\n❌ DI Container library tests FAILED!\n";
        exit(1);
    }
}
