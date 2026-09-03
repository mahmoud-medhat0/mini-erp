<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Spatie Translatable columns reach the React pages as objects like
 * {"en":"Pieces","ar":"قطع"} — never as strings.
 *
 * Rendering one directly as a JSX child throws React error #31, which unmounts
 * the tree and leaves the user a blank page. Every such value must go through
 * getLocalizedName(value, locale).
 *
 * The page prop types are what let this slip past TypeScript: they declare
 * `name: string` while the backend sends an object, so the compiler sees a legal
 * string render. This guard checks the rendered output instead.
 *
 * Targeting is by *entity*, not by field name. `description` on JournalEntry or
 * CustomerInvoice is an ordinary column; only the entities listed below carry
 * translated values, so only reads off those are flagged.
 */
class TranslatableDisplayGuardTest extends TestCase
{
    /**
     * Maps a page variable holding a translatable record to the fields that are
     * actually translated on that model.
     *
     * Field names alone are not enough: `description` is translated on Product
     * and RentableItem, but is an ordinary column on FixedAsset, JournalEntry,
     * and every sales/purchasing document line.
     */
    private const TRANSLATABLE_ENTITY_FIELDS = [
        'account' => ['name'],
        'acc' => ['name', 'bank_name'],
        'accountGroup' => ['name'],
        'accountType' => ['name'],
        'accountCategory' => ['name'],
        'bankAccount' => ['name', 'bank_name'],
        'cashAccount' => ['name'],
        'budget' => ['name'],
        'costCenter' => ['name'],
        'project' => ['name'],
        'customer' => ['name'],
        'supplier' => ['name'],
        'employee' => ['name'],
        'expenseCategory' => ['name'],
        'fixedAsset' => ['name'],
        'asset' => ['name'],
        'fixedAssetCategory' => ['name'],
        'fixedAssetLocation' => ['name'],
        'payrollComponent' => ['name'],
        'product' => ['name', 'description'],
        'prod' => ['name', 'description'],
        'productCategory' => ['name', 'description'],
        'rentableItem' => ['name', 'description'],
        'stockLocation' => ['name'],
        'warehouse' => ['name'],
        'uom' => ['name'],
        'unitOfMeasure' => ['name'],
        'statementLine' => ['name'],
        'financialStatementLine' => ['name'],
        // Relation keys as they appear in Inertia payloads.
        'gl_account' => ['name'],
        'glAccount' => ['name'],
        'bank_account' => ['name', 'bank_name'],
        'bankAccount' => ['name', 'bank_name'],
        'cash_account' => ['name'],
        'cashAccount' => ['name'],
        'source_account' => ['name'],
        'destination_account' => ['name'],
        'product_category' => ['name', 'description'],
        'productCategory' => ['name', 'description'],
        'unit_of_measure' => ['name'],
        'unitOfMeasure' => ['name'],
        'cat' => ['name', 'description'],
        'output_tax_account' => ['name'],
        'input_tax_account' => ['name'],
        'row' => ['bank_name'],
        'recon' => ['bank_name'],
    ];

    /** Every field this guard knows how to check. */
    private const TRANSLATABLE_FIELDS = ['name', 'description', 'bank_name'];

    /**
     * Reads that look like a translatable entity but are not.
     *
     * Each entry states why it is exempt.
     */
    private const ALLOWED_RENDERS = [
        // Attachment records store a plain uploaded filename.
        'att.name',
        'attachment.name',
        'file.name',
        // Tour guide steps are UI copy from the dictionary.
        'step.name',
        'step.description',
        // Notification payloads carry a plain title.
        'notification.name',
    ];

    public function test_pages_do_not_render_translatable_entity_fields_without_the_locale_helper(): void
    {
        $violations = [];

        foreach ($this->frontendFiles() as $path) {
            $source = (string) file_get_contents($path);
            $relative = $this->relativePath($path);

            foreach ($this->unsafeRenders($source) as $violation) {
                $violations[] = "{$relative}:{$violation}";
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "Translatable entity fields must render through getLocalizedName(value, locale).\n".
            "Rendering the raw {en, ar} object throws React error #31 and blanks the page.\n\n".
            implode("\n", $violations)
        );
    }

    public function test_guard_tracks_the_models_rather_than_a_frozen_field_list(): void
    {
        $discovered = [];

        foreach ((array) glob(app_path('Models/*.php')) as $file) {
            if (! is_string($file)) {
                continue;
            }

            $source = (string) file_get_contents($file);

            if (! str_contains($source, 'HasTranslations')) {
                continue;
            }

            if (! preg_match('/translatable\s*=\s*\[(.*?)\]/s', $source, $matches)) {
                continue;
            }

            preg_match_all("/'([a-z_]+)'/", $matches[1], $found);
            $discovered = array_merge($discovered, $found[1] ?? []);
        }

        $discovered = array_values(array_unique($discovered));

        sort($discovered);
        $tracked = self::TRANSLATABLE_FIELDS;
        sort($tracked);

        $this->assertSame(
            $tracked,
            $discovered,
            'The models declare translatable fields this guard does not track. '.
            'Update TRANSLATABLE_FIELDS and re-check the pages that render them.'
        );
    }

    /** @return list<string> */
    private function frontendFiles(): array
    {
        $files = [];

        foreach (['Pages', 'Components'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path('js/'.$directory))
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'tsx') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $path): string
    {
        return str_replace(
            [resource_path('js').DIRECTORY_SEPARATOR, '\\'],
            ['', '/'],
            $path
        );
    }

    /**
     * Finds `{entity.name}` style JSX renders that skip the helper.
     *
     * @return list<string>
     */
    private function unsafeRenders(string $source): array
    {
        $found = [];

        foreach (self::TRANSLATABLE_ENTITY_FIELDS as $entity => $fields) {
            $fieldPattern = implode('|', array_map('preg_quote', $fields));
            // Matches both `{a.b.name}` JSX children and `${a.b.name}` template
            // interpolation. The latter prints "[object Object]" rather than
            // crashing, which is just as wrong and much easier to miss.
            // Matches `{a.b.name}`, `${a.b.name}` interpolation, and
            // `{a.b.name || fallback}` — all render the raw object.
            $pattern = '/\$?\{\s*((?:[A-Za-z_$][A-Za-z0-9_$]*\??\.)*?'
                .preg_quote($entity, '/')
                .'\??\.('.$fieldPattern.'))\s*(?:\}|\|\|)/';

            foreach (explode('
', $source) as $index => $line) {
                if (! preg_match_all($pattern, $line, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $expression) {
                    $normalized = str_replace(['?.', ' '], ['.', ''], $expression);

                    if (in_array($normalized, self::ALLOWED_RENDERS, true)) {
                        continue;
                    }

                    $found[($index + 1).'|'.$expression] = ($index + 1).' renders {'.$expression.'}';
                }
            }
        }

        return array_values($found);
    }
}
