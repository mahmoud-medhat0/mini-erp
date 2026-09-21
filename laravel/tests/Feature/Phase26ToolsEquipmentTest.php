<?php

namespace Tests\Feature;

use App\Application\Equipment\ToolCategoryService;
use App\Application\Equipment\ToolService;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolMovement;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 26 - Tools & Equipment custody. A standalone operational register
 * (no mandatory GL impact) for internal tool/equipment custody, distinct
 * from Rentals (external, billed) and Fixed Assets (capitalized/depreciated).
 */
class Phase26ToolsEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private Employee $employeeA;

    private Employee $employeeB;

    private ToolCategory $category;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->withoutVite();

        $this->actor = User::factory()->create();

        $this->branchA = Branch::query()->create([
            'code' => 'EQ-BR-A',
            'name' => ['en' => 'Branch A', 'ar' => 'فرع أ'],
            'is_active' => true,
        ]);

        $this->branchB = Branch::query()->create([
            'code' => 'EQ-BR-B',
            'name' => ['en' => 'Branch B', 'ar' => 'فرع ب'],
            'is_active' => true,
        ]);

        $this->employeeA = Employee::query()->create([
            'code' => 'EQ-EMP-A',
            'name' => ['en' => 'Employee A', 'ar' => 'موظف أ'],
            'branch_id' => $this->branchA->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'currency' => 'EGP',
            'base_salary_minor' => 100000,
            'payment_method' => 'bank',
        ]);

        $this->employeeB = Employee::query()->create([
            'code' => 'EQ-EMP-B',
            'name' => ['en' => 'Employee B', 'ar' => 'موظف ب'],
            'branch_id' => $this->branchB->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'currency' => 'EGP',
            'base_salary_minor' => 100000,
            'payment_method' => 'bank',
        ]);

        $this->category = app(ToolCategoryService::class)->create([
            'code' => 'EQ-CAT-POWER',
            'name' => ['en' => 'Power tools', 'ar' => 'أدوات كهربائية'],
        ]);
    }

    public function test_full_custody_lifecycle_records_a_movement_for_every_transition(): void
    {
        $toolService = app(ToolService::class);

        $tool = $toolService->create([
            'code' => 'EQ-TOOL-001',
            'name' => ['en' => 'Cordless drill', 'ar' => 'مفك كهربائي لاسلكي'],
            'tool_category_id' => $this->category->id,
            'serial_number' => 'SN-0001',
            'quantity' => 1,
            'branch_id' => $this->branchA->id,
        ]);

        $this->assertSame('available', $tool->status);
        $this->assertNull($tool->custodian_employee_id);

        $tool = $toolService->issue($tool->id, $this->employeeA->id, null, 'Assigned for site work', $this->actor->id);
        $this->assertSame('issued', $tool->status);
        $this->assertSame($this->employeeA->id, $tool->custodian_employee_id);

        $this->expectExceptionMessageMatchesOnDelete($tool->id);

        $tool = $toolService->transfer($tool->id, $this->branchB->id, $this->employeeB->id, 'Reassigned to branch B', $this->actor->id);
        $this->assertSame($this->employeeB->id, $tool->custodian_employee_id);
        $this->assertSame($this->branchB->id, $tool->branch_id);
        $this->assertSame('issued', $tool->status);

        $tool = $toolService->markStatus($tool->id, 'maintenance', 'Motor overheating', $this->actor->id);
        $this->assertSame('maintenance', $tool->status);
        $this->assertSame($this->employeeB->id, $tool->custodian_employee_id, 'custody is preserved while under maintenance');

        $tool = $toolService->markStatus($tool->id, 'available', 'Repaired and returned to stock', $this->actor->id);
        $this->assertSame('available', $tool->status);
        $this->assertNull($tool->custodian_employee_id);

        $movementTypes = ToolMovement::query()
            ->where('tool_id', $tool->id)
            ->orderBy('created_at')
            ->pluck('event_type')
            ->all();

        $this->assertSame(['created', 'issued', 'transferred', 'status_changed', 'status_changed'], $movementTypes);

        $toolService->delete($tool->id, $this->actor->id);
        $this->assertNull(Tool::query()->find($tool->id));
    }

    public function test_only_available_tools_can_be_issued(): void
    {
        $toolService = app(ToolService::class);
        $tool = $toolService->create([
            'code' => 'EQ-TOOL-002',
            'name' => ['en' => 'Angle grinder', 'ar' => 'صنفرة زاوية'],
            'tool_category_id' => $this->category->id,
        ]);

        $toolService->issue($tool->id, $this->employeeA->id, null, null, $this->actor->id);

        $this->expectException(ValidationException::class);
        $toolService->issue($tool->id, $this->employeeB->id, null, null, $this->actor->id);
    }

    public function test_issued_tools_cannot_be_deleted(): void
    {
        $toolService = app(ToolService::class);
        $tool = $toolService->create([
            'code' => 'EQ-TOOL-003',
            'name' => ['en' => 'Welding machine', 'ar' => 'ماكينة لحام'],
            'tool_category_id' => $this->category->id,
        ]);
        $toolService->issue($tool->id, $this->employeeA->id, null, null, $this->actor->id);

        $this->expectException(ValidationException::class);
        $toolService->delete($tool->id, $this->actor->id);
    }

    public function test_retired_tools_cannot_change_status_again(): void
    {
        $toolService = app(ToolService::class);
        $tool = $toolService->create([
            'code' => 'EQ-TOOL-004',
            'name' => ['en' => 'Old compressor', 'ar' => 'ضاغط قديم'],
            'tool_category_id' => $this->category->id,
        ]);
        $tool = $toolService->markStatus($tool->id, 'retired', 'Decommissioned', $this->actor->id);
        $this->assertSame('retired', $tool->status);

        $this->expectException(ValidationException::class);
        $toolService->markStatus($tool->id, 'available', null, $this->actor->id);
    }

    public function test_duplicate_serial_number_is_rejected(): void
    {
        $toolService = app(ToolService::class);
        $toolService->create([
            'code' => 'EQ-TOOL-005',
            'name' => ['en' => 'Ladder', 'ar' => 'سلم'],
            'tool_category_id' => $this->category->id,
            'serial_number' => 'SN-DUP',
        ]);

        $this->expectException(ValidationException::class);
        $toolService->create([
            'code' => 'EQ-TOOL-006',
            'name' => ['en' => 'Ladder 2', 'ar' => 'سلم 2'],
            'tool_category_id' => $this->category->id,
            'serial_number' => 'SN-DUP',
        ]);
    }

    public function test_tool_routes_require_equipment_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');

        $this->actingAs($viewer)->get('/equipment/tools')->assertForbidden();

        $manager = User::factory()->create();
        $manager->givePermissionTo(['equipment.view', 'equipment.create']);

        $this->actingAs($manager)
            ->get('/equipment/tools')
            ->assertOk();

        $this->actingAs($manager)->post('/equipment/tools', [
            'code' => 'EQ-TOOL-007',
            'name' => ['en' => 'Impact wrench', 'ar' => 'مفتاح صدمي'],
            'tool_category_id' => $this->category->id,
            'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tool', ['code' => 'EQ-TOOL-007', 'status' => 'available']);

        // Creating tools does not implicitly grant delete.
        $tool = Tool::query()->where('code', 'EQ-TOOL-007')->firstOrFail();
        $this->actingAs($manager)->delete("/equipment/tools/{$tool->id}")->assertForbidden();
    }

    private function expectExceptionMessageMatchesOnDelete(string $toolId): void
    {
        try {
            app(ToolService::class)->delete($toolId, $this->actor->id);
            $this->fail('Expected deleting an issued tool to throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tool', $exception->errors());
        }
    }
}
