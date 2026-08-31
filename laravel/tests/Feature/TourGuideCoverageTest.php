<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class TourGuideCoverageTest extends TestCase
{
    public function test_every_inertia_page_is_covered_by_the_tour_guide(): void
    {
        $layoutSource = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));

        $this->assertStringContainsString("import TourGuide from './TourGuide';", $layoutSource);
        $this->assertStringContainsString('<TourGuide', $layoutSource);
        $this->assertStringContainsString('pageKey={page.component}', $layoutSource);
        $this->assertStringContainsString('data-tour="sidebar"', $layoutSource);
        $this->assertStringContainsString('data-tour="topbar"', $layoutSource);
        $this->assertStringContainsString('data-tour="page-content"', $layoutSource);

        $pageCount = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js/Pages')),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            $pageCount++;
            $source = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace('\\', '/', $file->getPathname());

            if (str_ends_with($relativePath, '/Pages/Auth/Login.tsx')) {
                $this->assertStringContainsString('<TourGuide', $source);
                $this->assertStringContainsString('variant="login"', $source);
                $this->assertStringContainsString('data-tour="login-form"', $source);

                continue;
            }

            $this->assertStringContainsString(
                '<AppLayout',
                $source,
                "{$relativePath} must use AppLayout so the global tour button is available.",
            );
        }

        $this->assertGreaterThan(100, $pageCount, 'Expected the complete Inertia page catalog to be scanned.');
    }

    public function test_arabic_and_english_tour_dictionaries_have_matching_keys(): void
    {
        $english = json_decode(
            (string) file_get_contents(resource_path('js/locales/en.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $arabic = json_decode(
            (string) file_get_contents(resource_path('js/locales/ar.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $englishKeys = $this->nestedKeys($english['app']['tour']);
        $arabicKeys = $this->nestedKeys($arabic['app']['tour']);

        sort($englishKeys);
        sort($arabicKeys);

        $this->assertSame($englishKeys, $arabicKeys);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function nestedKeys(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $keys[] = $path;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->nestedKeys($value, $path)];
            }
        }

        return $keys;
    }
}
