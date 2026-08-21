<?php

namespace Tests\Feature;

use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\Catalog\ProductCategoryService;
use App\Application\Catalog\ProductService;
use App\Application\Catalog\UnitOfMeasureService;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ReceivableEntry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase4Slice1CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'uom.view',
            'uom.create',
            'uom.edit',
            'uom.delete',
        ]);
    }

    public function test_catalog_migrations_create_expected_tables_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('unit_of_measure'));
        $this->assertTrue(Schema::hasTable('product_category'));
        $this->assertTrue(Schema::hasTable('product'));

        $this->assertTrue(Schema::hasColumns('product', [
            'id', 'code', 'name', 'type', 'unit_of_measure_id', 'product_category_id',
            'status', 'is_sales_enabled', 'is_purchase_enabled', 'lock_version',
        ]));
    }

    public function test_no_tenant_company_branch_columns_exist_in_catalog_tables(): void
    {
        $prohibitedColumns = ['company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch'];
        $catalogTables = ['unit_of_measure', 'product_category', 'product'];

        foreach ($catalogTables as $table) {
            foreach ($prohibitedColumns as $col) {
                $this->assertFalse(
                    Schema::hasColumn($table, $col),
                    "Prohibited column [{$col}] was found in table [{$table}]."
                );
            }
        }
    }

    public function test_uom_and_category_seeders_are_idempotent(): void
    {
        $uomCount1 = UnitOfMeasure::count();
        $catCount1 = ProductCategory::count();

        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->assertEquals($uomCount1, UnitOfMeasure::count());
        $this->assertEquals($catCount1, ProductCategory::count());
    }

    public function test_product_creation_validation_and_normalization(): void
    {
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $cat = ProductCategory::query()->where('code', 'RAW')->firstOrFail();

        // 1. Successful product creation
        $product = $productService->create([
            'code' => ' prd-101 ',
            'name' => ['en' => 'Test Item 101', 'ar' => 'صنف تجريبي 101'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'product_category_id' => $cat->id,
        ], $this->adminUser->id);

        $this->assertEquals('PRD-101', $product->code);
        $this->assertEquals('stock', $product->type);
        $this->assertEquals('active', $product->status);

        // 2. Duplicate SKU rejected
        $this->expectException(ValidationException::class);
        $productService->create([
            'code' => 'PRD-101',
            'name' => ['en' => 'Duplicate Item', 'ar' => 'صنف مكرر'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
        ], $this->adminUser->id);
    }

    public function test_invalid_product_type_rejected(): void
    {
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();

        $this->expectException(ValidationException::class);
        $productService->create([
            'code' => 'PRD-INVALID',
            'name' => ['en' => 'Invalid Type', 'ar' => 'نوع غير صالح'],
            'type' => 'unsupported_type',
            'unit_of_measure_id' => $uom->id,
        ], $this->adminUser->id);
    }

    public function test_uom_in_use_cannot_be_deleted(): void
    {
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        /** @var UnitOfMeasureService $uomService */
        $uomService = app(UnitOfMeasureService::class);

        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $productService->create([
            'code' => 'PRD-UOM-LOCK',
            'name' => ['en' => 'UOM Locked Item', 'ar' => 'صنف مقيد'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
        ], $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $uomService->delete($uom->id, $this->adminUser->id);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        /** @var ProductCategoryService $catService */
        $catService = app(ProductCategoryService::class);

        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $cat = ProductCategory::query()->where('code', 'RAW')->firstOrFail();

        $productService->create([
            'code' => 'PRD-CAT-LOCK',
            'name' => ['en' => 'Category Locked Item', 'ar' => 'صنف مقيد بالتصنيف'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'product_category_id' => $cat->id,
        ], $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $catService->delete($cat->id, $this->adminUser->id);
    }

    public function test_audit_entries_are_written_through_spatie_activitylog(): void
    {
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();

        $product = $productService->create([
            'code' => 'PRD-AUDIT-1',
            'name' => ['en' => 'Audit Test Item', 'ar' => 'صنف اختبار التدقيق'],
            'type' => 'service',
            'unit_of_measure_id' => $uom->id,
        ], $this->adminUser->id);

        $activityCount = Activity::query()
            ->where('properties->entity_type', 'product')
            ->where('properties->entity_id', $product->id)
            ->count();

        $this->assertGreaterThanOrEqual(1, $activityCount);
    }

    public function test_rbac_permissions_registered(): void
    {
        $permissions = [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'uom.view',
            'uom.create',
            'uom.edit',
            'uom.delete',
        ];

        foreach ($permissions as $perm) {
            $this->assertTrue(
                Permission::query()->where('name', $perm)->exists(),
                "Permission [{$perm}] must be registered in the permissions table."
            );
        }
    }

    public function test_attachment_registry_supports_product(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);

        $allowedTypes = $authorizer->allowedEntityTypes();
        $this->assertContains('product', $allowedTypes);
    }

    public function test_catalog_operations_create_zero_accounting_or_subledger_entries(): void
    {
        $journalsBefore = JournalEntry::count();
        $ledgersBefore = LedgerEntry::count();
        $receivablesBefore = ReceivableEntry::count();

        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();

        $productService->create([
            'code' => 'PRD-NO-ACC',
            'name' => ['en' => 'No Accounting Item', 'ar' => 'صنف بدون قيد محاسبي'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
        ], $this->adminUser->id);

        $this->assertEquals($journalsBefore, JournalEntry::count());
        $this->assertEquals($ledgersBefore, LedgerEntry::count());
        $this->assertEquals($receivablesBefore, ReceivableEntry::count());
    }

    public function test_inertia_catalog_pages_render_successfully(): void
    {
        $response1 = $this->actingAs($this->adminUser)->get('/catalog/uoms');
        $response1->assertStatus(200);
        $response1->assertInertia(fn ($page) => $page->component('Catalog/UnitsOfMeasure'));

        $response2 = $this->actingAs($this->adminUser)->get('/catalog/categories');
        $response2->assertStatus(200);
        $response2->assertInertia(fn ($page) => $page->component('Catalog/ProductCategories'));

        $response3 = $this->actingAs($this->adminUser)->get('/catalog/products');
        $response3->assertStatus(200);
        $response3->assertInertia(fn ($page) => $page->component('Catalog/Products'));
    }
}
