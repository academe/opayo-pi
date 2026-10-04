<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Demo;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Deleting demo/debug/ and the one marked line in bootstrap.php must leave the
 * bare integration. So no integration file may reach into debug/, and the old
 * all-in-one helper file must stay gone.
 */
class BareIntegrationTest extends TestCase
{
    private function integrationFiles(): array
    {
        $root = realpath(__DIR__ . '/../../demo');
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() === 'php' && ! str_contains($path, '/demo/debug/')) {
                $files[$path] = file_get_contents($path);
            }
        }

        return $files;
    }

    public function testOnlyBootstrapRequiresTheDebugLayerAndOnlyOnce()
    {
        foreach ($this->integrationFiles() as $path => $source) {
            $count = substr_count($source, 'debug/');
            $expected = str_ends_with($path, '/demo/bootstrap.php') ? 2 : 0;

            $this->assertSame($expected, $count, "$path references debug/ $count time(s)");
        }
    }

    public function testOldHelpersAreGone()
    {
        $this->assertFileDoesNotExist(__DIR__ . '/../../demo/shared.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../../demo/result.php');

        foreach ($this->integrationFiles() as $path => $source) {
            $this->assertStringNotContainsString('shared.php', $source, $path);
        }
    }
}
