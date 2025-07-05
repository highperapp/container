<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

/**
 * Container Compiler - Build-time DI Compilation
 *
 * Compiles container definitions at build time for maximum runtime performance.
 * Eliminates reflection overhead and provides O(1) service resolution.
 *
 */
class ContainerCompiler
{
    private string $cachePath;
    private array $stats = ['compiled' => 0, 'cache_hits' => 0];

    public function __construct(string $cachePath = '/tmp/highper_container_cache')
    {
        $this->cachePath = $cachePath;
        if (!is_dir(dirname($this->cachePath))) {
            mkdir(dirname($this->cachePath), 0755, true);
        }
    }

    public function compileContainer(array $definitions): string
    {
        $code = "<?php\n\n// HighPer Container - Compiled Service Definitions\n";
        $code .= "// Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        $code .= "return [\n";

        foreach ($definitions as $id => $definition) {
            $code .= $this->compileDefinition($id, $definition);
        }

        $code .= "];\n";

        file_put_contents($this->cachePath, $code);
        $this->stats['compiled']++;

        return $code;
    }

    private function compileDefinition(string $id, array $definition): string
    {
        $className = $definition['class'] ?? $id;
        $dependencies = $definition['dependencies'] ?? [];

        $code = "    '{$id}' => function(\$container) {\n";

        if (empty($dependencies)) {
            $code .= "        return new {$className}();\n";
        } else {
            $depCode = array_map(fn($dep) => "\$container->get('{$dep}')", $dependencies);
            $code .= "        return new {$className}(" . implode(', ', $depCode) . ");\n";
        }

        $code .= "    },\n";
        return $code;
    }

    public function isAvailable(): bool
    {
        return is_writable(dirname($this->cachePath));
    }

    public function getStats(): array
    {
        return array_merge($this->stats, [
            'cache_exists' => file_exists($this->cachePath),
            'cache_size' => file_exists($this->cachePath) ? filesize($this->cachePath) : 0,
            'cache_modified' => file_exists($this->cachePath) ? filemtime($this->cachePath) : 0
        ]);
    }

    public function clearCache(): bool
    {
        return file_exists($this->cachePath) ? unlink($this->cachePath) : true;
    }

    public function warmCache(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }
        $this->stats['cache_hits']++;
        return true;
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }
    public function validateCompiled(string $code): bool
    {
        return strpos($code, '<?php') === 0 && strpos($code, 'return [') !== false;
    }
}
