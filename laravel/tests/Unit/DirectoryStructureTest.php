<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DirectoryStructureTest extends TestCase
{
    public function test_migration_layer_directories_exist(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/Domain/Money',
            'app/Application/Company',
            'app/Infrastructure/Repositories',
            'app/Http/Requests',
            'resources/js/Components',
            'resources/js/Pages',
            'tests/Invariants',
        ] as $path) {
            $this->assertDirectoryExists($root.DIRECTORY_SEPARATOR.$path);
        }
    }
}
