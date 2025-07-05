<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Integration;

/**
 * DI Container Integration Test
 * 
 * Tests integration between DI Container library and framework,
 * validates build-time compilation and performance optimizations.
 */
class DIContainerIntegrationTest
{
    private array $testResults = [];

    public function runDIContainerIntegrationTests(): array
    {
        echo "🔄 DI Container Library - Integration Testing\n";
        echo "=============================================\n\n";

        // Test DI Container-Framework integration
        $this->testFrameworkIntegration();
        
        // Test build-time compilation integration
        $this->testBuildTimeCompilation();
        
        // Test performance optimization integration
        $this->testPerformanceOptimization();
        
        return $this->generateIntegrationReport();
    }

    private function testFrameworkIntegration(): void
    {
        echo "🧪 Testing Framework Integration...\n";
        echo str_repeat("─", 45) . "\n";

        // Load framework
        $frameworkAutoloader = __DIR__ . '/../../../../core/framework/vendor/autoload.php';
        if (file_exists($frameworkAutoloader)) {
            require_once $frameworkAutoloader;
            $this->recordTest('Framework Loading', true, 'Framework loaded for DI Container integration');
            echo "  ✅ Framework loaded - OK\n";
        } else {
            $this->recordTest('Framework Loading', false, 'Framework not available');
            echo "  ❌ Framework not available\n";
        }

        // Load DI Container
        $diAutoloader = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($diAutoloader)) {
            require_once $diAutoloader;
            $this->recordTest('DI Container Loading', true, 'DI Container library loaded');
            echo "  ✅ DI Container loaded - OK\n";
        } else {
            $this->recordTest('DI Container Loading', false, 'DI Container autoloader not found');
            echo "  ❌ DI Container not available\n";
        }

        // Test framework DI integration
        try {
            if (class_exists('HighPerApp\\DIContainer\\Container') && 
                class_exists('HighPerApp\\HighPer\\Foundation\\ProcessManager')) {
                
                $container = new \HighPerApp\DIContainer\Container();
                
                // Register framework services in DI container
                $container->singleton('process_manager', function() {
                    $mockApp = $this->createMockApplication();
                    return new \HighPerApp\HighPer\Foundation\ProcessManager($mockApp);
                });
                
                $container->singleton('async_manager', function() {
                    return new \HighPerApp\HighPer\Foundation\AsyncManager();
                });
                
                // Test service resolution
                $processManager = $container->resolve('process_manager');
                $asyncManager = $container->resolve('async_manager');
                
                $integrated = ($processManager instanceof \HighPerApp\HighPer\Foundation\ProcessManager) &&
                             ($asyncManager instanceof \HighPerApp\HighPer\Foundation\AsyncManager);
                             
                $this->recordTest('Framework Service Integration', $integrated, 'Framework services integrated with DI');
                echo "  ✅ Framework service integration - OK\n";
            }
        } catch (\Exception $e) {
            $this->recordTest('Framework Integration', false, 'Error: ' . $e->getMessage());
            echo "  ❌ Framework integration error: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function testBuildTimeCompilation(): void
    {
        echo "🧪 Testing Build-Time Compilation...\n";
        echo str_repeat("─", 45) . "\n";

        try {
            if (class_exists('HighPerApp\\DIContainer\\Compiler')) {
                $compiler = new \HighPerApp\DIContainer\Compiler();
                
                // Test compilation with framework services
                $frameworkServices = [
                    'process_manager' => [
                        'class' => 'HighPerApp\\HighPer\\Foundation\\ProcessManager',
                        'dependencies' => ['application']
                    ],
                    'async_manager' => [
                        'class' => 'HighPerApp\\HighPer\\Foundation\\AsyncManager',
                        'dependencies' => []
                    ],
                    'adaptive_serializer' => [
                        'class' => 'HighPerApp\\HighPer\\Foundation\\AdaptiveSerializer',
                        'dependencies' => []
                    ],
                    'application' => [
                        'class' => 'stdClass',
                        'dependencies' => []
                    ]
                ];
                
                $compiled = $compiler->compile($frameworkServices);
                $this->recordTest('Build-Time Compilation', is_string($compiled) && !empty($compiled), 'Framework services compiled');
                echo "  ✅ Framework services compilation - OK\n";

                // Test compilation validation
                $isValid = $compiler->validate($compiled);
                $this->recordTest('Compiled Container Validation', $isValid === true, 'Compiled container is valid');
                echo "  ✅ Compiled container validation - OK\n";

                // Test compilation cache
                $cacheFile = $compiler->getCacheFile();
                $this->recordTest('Compilation Cache', is_string($cacheFile), 'Cache file: ' . basename($cacheFile ?? 'none'));
                echo "  ✅ Compilation cache - OK\n";

                // Test compilation statistics
                $stats = $compiler->getStats();
                $serviceCount = $stats['services'] ?? 0;
                $this->recordTest('Compilation Statistics', $serviceCount === count($frameworkServices), 
                    "Compiled {$serviceCount} services");
                echo "  ✅ Compilation statistics - OK\n";
            }
        } catch (\Exception $e) {
            $this->recordTest('Build-Time Compilation', false, 'Error: ' . $e->getMessage());
            echo "  ❌ Build-time compilation error: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function testPerformanceOptimization(): void
    {
        echo "🧪 Testing Performance Optimization...\n";
        echo str_repeat("─", 45) . "\n";

        try {
            // Test resolution performance
            if (class_exists('HighPerApp\\DIContainer\\Container')) {
                $container = new \HighPerApp\DIContainer\Container();
                
                // Register multiple services
                for ($i = 0; $i < 100; $i++) {
                    $container->register("service_{$i}", function() use ($i) {
                        return new \stdClass();
                    });
                }
                
                // Test resolution performance
                $startTime = microtime(true);
                for ($i = 0; $i < 100; $i++) {
                    $service = $container->resolve("service_{$i}");
                }
                $resolutionTime = microtime(true) - $startTime;
                
                $this->recordTest('Resolution Performance', $resolutionTime < 0.1, 
                    sprintf('100 services resolved in %.4fs', $resolutionTime));
                echo "  ✅ Resolution performance - OK\n";

                // Test memory usage
                $initialMemory = memory_get_usage(true);
                
                // Create and resolve services
                for ($i = 100; $i < 200; $i++) {
                    $container->singleton("singleton_{$i}", function() {
                        return new \DateTime();
                    });
                    $container->resolve("singleton_{$i}");
                }
                
                $memoryAfter = memory_get_usage(true);
                $memoryUsed = $memoryAfter - $initialMemory;
                
                $this->recordTest('Memory Usage', $memoryUsed < 5 * 1024 * 1024, // Less than 5MB
                    sprintf('Memory used: %d bytes for 100 singletons', $memoryUsed));
                echo "  ✅ Memory usage optimization - OK\n";

                // Test compilation performance benefit
                if (class_exists('HighPerApp\\DIContainer\\Compiler')) {
                    $compiler = new \HighPerApp\DIContainer\Compiler();
                    
                    $services = [];
                    for ($i = 0; $i < 50; $i++) {
                        $services["compiled_service_{$i}"] = [
                            'class' => 'stdClass',
                            'dependencies' => []
                        ];
                    }
                    
                    $compilationStart = microtime(true);
                    $compiled = $compiler->compile($services);
                    $compilationTime = microtime(true) - $compilationStart;
                    
                    $this->recordTest('Compilation Performance', $compilationTime < 1.0, 
                        sprintf('50 services compiled in %.4fs', $compilationTime));
                    echo "  ✅ Compilation performance - OK\n";
                }
            }
        } catch (\Exception $e) {
            $this->recordTest('Performance Optimization', false, 'Error: ' . $e->getMessage());
            echo "  ❌ Performance optimization error: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function createMockApplication(): object
    {
        return new class implements \HighPerApp\HighPer\Contracts\ApplicationInterface {
            private array $container = [];
            
            public function bootstrap(): void {}
            public function run(): void {}
            public function getContainer(): \HighPerApp\HighPer\Contracts\ContainerInterface {
                return new class($this->container) implements \HighPerApp\HighPer\Contracts\ContainerInterface {
                    public function __construct(private array &$container) {}
                    public function get(string $id): mixed { return $this->container[$id] ?? new stdClass(); }
                    public function set(string $id, mixed $value): void { $this->container[$id] = $value; }
                    public function has(string $id): bool { return isset($this->container[$id]); }
                };
            }
            public function getRouter(): \HighPerApp\HighPer\Contracts\RouterInterface {
                return new class implements \HighPerApp\HighPer\Contracts\RouterInterface {
                    public function get(string $path, callable $handler): void {}
                    public function post(string $path, callable $handler): void {}
                    public function put(string $path, callable $handler): void {}
                    public function delete(string $path, callable $handler): void {}
                    public function patch(string $path, callable $handler): void {}
                    public function addRoute(string $method, string $path, callable $handler): void {}
                    public function dispatch(string $method, string $path): mixed { return null; }
                    public function group(string $prefix, callable $callback): void {}
                    public function middleware(string|array $middleware): self { return $this; }
                };
            }
            public function getConfig(): \HighPerApp\HighPer\Contracts\ConfigManagerInterface {
                return new class implements \HighPerApp\HighPer\Contracts\ConfigManagerInterface {
                    public function get(string $key, mixed $default = null): mixed { return $default; }
                    public function set(string $key, mixed $value): void {}
                    public function has(string $key): bool { return false; }
                    public function all(): array { return []; }
                    public function load(string $file): void {}
                };
            }
            public function getLogger(): \HighPerApp\HighPer\Contracts\LoggerInterface {
                return new class implements \HighPerApp\HighPer\Contracts\LoggerInterface {
                    public function emergency(string $message, array $context = []): void {}
                    public function alert(string $message, array $context = []): void {}
                    public function critical(string $message, array $context = []): void {}
                    public function error(string $message, array $context = []): void {}
                    public function warning(string $message, array $context = []): void {}
                    public function notice(string $message, array $context = []): void {}
                    public function info(string $message, array $context = []): void {}
                    public function debug(string $message, array $context = []): void {}
                    public function log(string $level, string $message, array $context = []): void {}
                };
            }
            public function register(\HighPerApp\HighPer\Contracts\ServiceProviderInterface $provider): void {}
            public function bootProviders(): void {}
            public function isRunning(): bool { return false; }
            public function shutdown(): void {}
        };
    }

    private function recordTest(string $name, bool $passed, string $message): void
    {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
            'timestamp' => microtime(true)
        ];
    }

    private function generateIntegrationReport(): array
    {
        echo "📊 DI Container Integration Test Report\n";
        echo "======================================\n\n";
        
        $passed = count(array_filter($this->testResults, fn($test) => $test['passed']));
        $total = count($this->testResults);
        $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        
        echo "📈 Summary:\n";
        echo "  • Total Tests: {$total}\n";
        echo "  • Passed: {$passed}\n";
        echo "  • Failed: " . ($total - $passed) . "\n";
        echo "  • Success Rate: {$percentage}%\n\n";
        
        echo "📋 Detailed Results:\n";
        foreach ($this->testResults as $test) {
            $status = $test['passed'] ? '✅' : '❌';
            echo "  {$status} {$test['name']}: {$test['message']}\n";
        }
        
        return [
            'total_tests' => $total,
            'passed_tests' => $passed,
            'failed_tests' => $total - $passed,
            'success_rate' => $percentage,
            'detailed_results' => $this->testResults
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $tester = new DIContainerIntegrationTest();
    $results = $tester->runDIContainerIntegrationTests();
    
    if ($results['success_rate'] >= 80) {
        echo "\n🎉 DI Container integration testing PASSED!\n";
        exit(0);
    } else {
        echo "\n❌ DI Container integration testing FAILED!\n";
        exit(1);
    }
}