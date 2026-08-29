<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase16Slice1ProjectCostCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_project_and_cost_center_tables_exist_with_standalone_schema(): void
    {
        $this->assertTrue(Schema::hasTable('project'), 'Table [project] must exist');
        $this->assertTrue(Schema::hasTable('cost_center'), 'Table [cost_center] must exist');

        $projectColumns = Schema::getColumnListing('project');
        $costCenterColumns = Schema::getColumnListing('cost_center');

        // Check required columns
        $this->assertContains('id', $projectColumns);
        $this->assertContains('code', $projectColumns);
        $this->assertContains('name', $projectColumns);
        $this->assertContains('description', $projectColumns);
        $this->assertContains('status', $projectColumns);
        $this->assertContains('start_date', $projectColumns);
        $this->assertContains('end_date', $projectColumns);
        $this->assertContains('is_billable', $projectColumns);
        $this->assertContains('is_active', $projectColumns);
        $this->assertContains('lock_version', $projectColumns);
        $this->assertContains('created_by', $projectColumns);
        $this->assertContains('updated_by', $projectColumns);

        $this->assertContains('id', $costCenterColumns);
        $this->assertContains('code', $costCenterColumns);
        $this->assertContains('name', $costCenterColumns);
        $this->assertContains('description', $costCenterColumns);
        $this->assertContains('category', $costCenterColumns);
        $this->assertContains('is_active', $costCenterColumns);
        $this->assertContains('lock_version', $costCenterColumns);
        $this->assertContains('created_by', $costCenterColumns);
        $this->assertContains('updated_by', $costCenterColumns);

        // Strict non-negotiable check: No company_id, tenant_id, branch_id, department_id in standalone master data
        $bannedColumns = ['company_id', 'tenant_id', 'branch_id', 'department_id', 'customer_id', 'supplier_id', 'employee_id'];
        foreach ($bannedColumns as $banned) {
            $this->assertNotContains($banned, $projectColumns, "Table [project] must not contain [{$banned}]");
            $this->assertNotContains($banned, $costCenterColumns, "Table [cost_center] must not contain [{$banned}]");
        }
    }

    public function test_rbac_permissions_registered_for_projects_and_cost_centers(): void
    {
        $expectedProjectPerms = [
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'projects.export',
        ];

        $expectedCostCenterPerms = [
            'costCenters.view',
            'costCenters.create',
            'costCenters.edit',
            'costCenters.delete',
            'costCenters.export',
        ];

        foreach ($expectedProjectPerms as $perm) {
            $this->assertDatabaseHas('permissions', ['name' => $perm]);
        }

        foreach ($expectedCostCenterPerms as $perm) {
            $this->assertDatabaseHas('permissions', ['name' => $perm]);
        }
    }

    public function test_attachment_registry_contains_project_and_cost_center(): void
    {
        $entities = config('erp_attachments.entities');

        $this->assertArrayHasKey('project', $entities);
        $this->assertSame('project', $entities['project']['table']);
        $this->assertSame(['projects.view'], $entities['project']['permissions']['view']);
        $this->assertSame(['projects.edit', 'projects.create'], $entities['project']['permissions']['attach']);
        $this->assertSame(['projects.delete', 'projects.edit'], $entities['project']['permissions']['delete']);

        $this->assertArrayHasKey('cost_center', $entities);
        $this->assertSame('cost_center', $entities['cost_center']['table']);
        $this->assertSame(['costCenters.view'], $entities['cost_center']['permissions']['view']);
        $this->assertSame(['costCenters.edit', 'costCenters.create'], $entities['cost_center']['permissions']['attach']);
        $this->assertSame(['costCenters.delete', 'costCenters.edit'], $entities['cost_center']['permissions']['delete']);
    }

    public function test_unauthorized_user_cannot_access_or_modify_projects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects')->assertForbidden();
        $this->actingAs($user)->post('/projects', [
            'code' => 'PRJ-UNAUTH',
            'name' => ['en' => 'Unauthorized Project'],
            'status' => 'active',
        ])->assertForbidden();

        $project = Project::query()->create([
            'code' => 'PRJ-EXISTING',
            'name' => ['en' => 'Existing'],
            'status' => 'active',
        ]);

        $this->actingAs($user)->patch("/projects/{$project->id}", [
            'name' => ['en' => 'Modified'],
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($user)->delete("/projects/{$project->id}")->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_or_modify_cost_centers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cost-centers')->assertForbidden();
        $this->actingAs($user)->post('/cost-centers', [
            'code' => 'CC-UNAUTH',
            'name' => ['en' => 'Unauthorized CC'],
        ])->assertForbidden();

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-EXISTING',
            'name' => ['en' => 'Existing'],
        ]);

        $this->actingAs($user)->patch("/cost-centers/{$costCenter->id}", [
            'name' => ['en' => 'Modified'],
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($user)->delete("/cost-centers/{$costCenter->id}")->assertForbidden();
    }

    public function test_authorized_user_can_manage_projects_with_audit_logging(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.view', 'projects.create', 'projects.edit', 'projects.delete']);

        // 1. Index Inertia page
        $response = $this->actingAs($user)->get('/projects');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data')
            ->has('filters')
        );

        // 2. Create Project
        $postResponse = $this->actingAs($user)->post('/projects', [
            'code' => 'PRJ-ALPHA',
            'name' => ['en' => 'Project Alpha', 'ar' => 'مشروع ألفا'],
            'description' => 'First flagship operational project',
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_billable' => true,
            'is_active' => true,
        ]);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        $project = Project::query()->where('code', 'PRJ-ALPHA')->first();
        $this->assertNotNull($project);
        $this->assertSame('Project Alpha', $project->getTranslation('name', 'en'));
        $this->assertSame('مشروع ألفا', $project->getTranslation('name', 'ar'));
        $this->assertSame('active', $project->status);
        $this->assertTrue($project->is_billable);
        $this->assertSame(1, $project->lock_version);
        $this->assertSame($user->id, $project->created_by);

        // Verify audit log for create
        $createActivity = Activity::query()
            ->where('properties->entity_type', 'project')
            ->where('properties->entity_id', (string) $project->id)
            ->where('event', 'project.create')
            ->first();
        $this->assertNotNull($createActivity);

        // 3. Update Project
        $patchResponse = $this->actingAs($user)->patch("/projects/{$project->id}", [
            'name' => ['en' => 'Project Alpha Updated', 'ar' => 'مشروع ألفا المحدث'],
            'status' => 'on_hold',
            'lock_version' => 1,
        ]);
        $patchResponse->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Project Alpha Updated', $project->getTranslation('name', 'en'));
        $this->assertSame('on_hold', $project->status);
        $this->assertSame(2, $project->lock_version);
        $this->assertSame($user->id, $project->updated_by);

        // Verify audit log for update
        $updateActivity = Activity::query()
            ->where('properties->entity_type', 'project')
            ->where('properties->entity_id', (string) $project->id)
            ->where('event', 'project.update')
            ->first();
        $this->assertNotNull($updateActivity);

        // 4. Delete Project
        $deleteResponse = $this->actingAs($user)->delete("/projects/{$project->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('project', ['id' => $project->id]);

        // Verify audit log for delete
        $deleteActivity = Activity::query()
            ->where('properties->entity_type', 'project')
            ->where('properties->entity_id', (string) $project->id)
            ->where('event', 'project.delete')
            ->first();
        $this->assertNotNull($deleteActivity);
    }

    public function test_authorized_user_can_manage_cost_centers_with_audit_logging(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['costCenters.view', 'costCenters.create', 'costCenters.edit', 'costCenters.delete']);

        // 1. Index Inertia page
        $response = $this->actingAs($user)->get('/cost-centers');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CostCenters/Index')
            ->has('costCenters.data')
            ->has('filters')
        );

        // 2. Create Cost Center
        $postResponse = $this->actingAs($user)->post('/cost-centers', [
            'code' => 'CC-HQ-ADMIN',
            'name' => ['en' => 'Headquarters Administration', 'ar' => 'إدارة المقر الرئيسي'],
            'description' => 'HQ corporate administrative cost center',
            'category' => 'administrative',
            'is_active' => true,
        ]);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        $costCenter = CostCenter::query()->where('code', 'CC-HQ-ADMIN')->first();
        $this->assertNotNull($costCenter);
        $this->assertSame('Headquarters Administration', $costCenter->getTranslation('name', 'en'));
        $this->assertSame('إدارة المقر الرئيسي', $costCenter->getTranslation('name', 'ar'));
        $this->assertSame('administrative', $costCenter->category);
        $this->assertSame(1, $costCenter->lock_version);
        $this->assertSame($user->id, $costCenter->created_by);

        // Verify audit log for create
        $createActivity = Activity::query()
            ->where('properties->entity_type', 'cost_center')
            ->where('properties->entity_id', (string) $costCenter->id)
            ->where('event', 'cost_center.create')
            ->first();
        $this->assertNotNull($createActivity);

        // 3. Update Cost Center
        $patchResponse = $this->actingAs($user)->patch("/cost-centers/{$costCenter->id}", [
            'name' => ['en' => 'HQ Operations & Admin', 'ar' => 'عمليات وإدارة المقر الرئيسي'],
            'category' => 'operations',
            'lock_version' => 1,
        ]);
        $patchResponse->assertSessionHasNoErrors();

        $costCenter->refresh();
        $this->assertSame('HQ Operations & Admin', $costCenter->getTranslation('name', 'en'));
        $this->assertSame('operations', $costCenter->category);
        $this->assertSame(2, $costCenter->lock_version);

        // Verify audit log for update
        $updateActivity = Activity::query()
            ->where('properties->entity_type', 'cost_center')
            ->where('properties->entity_id', (string) $costCenter->id)
            ->where('event', 'cost_center.update')
            ->first();
        $this->assertNotNull($updateActivity);

        // 4. Delete Cost Center
        $deleteResponse = $this->actingAs($user)->delete("/cost-centers/{$costCenter->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('cost_center', ['id' => $costCenter->id]);

        // Verify audit log for delete
        $deleteActivity = Activity::query()
            ->where('properties->entity_type', 'cost_center')
            ->where('properties->entity_id', (string) $costCenter->id)
            ->where('event', 'cost_center.delete')
            ->first();
        $this->assertNotNull($deleteActivity);
    }

    public function test_project_date_validation_rejects_end_date_before_start_date(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.create']);

        $response = $this->actingAs($user)->post('/projects', [
            'code' => 'PRJ-INVALID-DATES',
            'name' => ['en' => 'Invalid Dates Project'],
            'status' => 'active',
            'start_date' => '2026-10-01',
            'end_date' => '2026-09-01', // Before start date!
        ]);

        $response->assertSessionHasErrors(['end_date']);
        $this->assertDatabaseMissing('project', ['code' => 'PRJ-INVALID-DATES']);
    }

    public function test_project_optimistic_locking_prevents_stale_update(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.edit']);

        $project = Project::query()->create([
            'code' => 'PRJ-LOCK-TEST',
            'name' => ['en' => 'Lock Test'],
            'status' => 'active',
            'lock_version' => 3,
        ]);

        $response = $this->actingAs($user)->patch("/projects/{$project->id}", [
            'name' => ['en' => 'Stale Update'],
            'lock_version' => 2, // Stale lock version!
        ]);

        $response->assertSessionHasErrors(['lock_version']);
        $this->assertSame('Lock Test', $project->fresh()->getTranslation('name', 'en'));
    }

    public function test_cost_center_optimistic_locking_prevents_stale_update(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['costCenters.edit']);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-LOCK-TEST',
            'name' => ['en' => 'Lock Test'],
            'lock_version' => 4,
        ]);

        $response = $this->actingAs($user)->patch("/cost-centers/{$costCenter->id}", [
            'name' => ['en' => 'Stale Update'],
            'lock_version' => 1, // Stale lock version!
        ]);

        $response->assertSessionHasErrors(['lock_version']);
        $this->assertSame('Lock Test', $costCenter->fresh()->getTranslation('name', 'en'));
    }

    public function test_duplicate_code_validation_is_enforced(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.create', 'costCenters.create']);

        Project::query()->create([
            'code' => 'PRJ-DUP',
            'name' => ['en' => 'Original Project'],
            'status' => 'active',
        ]);

        $res1 = $this->actingAs($user)->post('/projects', [
            'code' => 'PRJ-DUP',
            'name' => ['en' => 'Duplicate Project'],
            'status' => 'active',
        ]);
        $res1->assertSessionHasErrors(['code']);

        CostCenter::query()->create([
            'code' => 'CC-DUP',
            'name' => ['en' => 'Original CC'],
        ]);

        $res2 = $this->actingAs($user)->post('/cost-centers', [
            'code' => 'CC-DUP',
            'name' => ['en' => 'Duplicate CC'],
        ]);
        $res2->assertSessionHasErrors(['code']);
    }

    public function test_react_pages_contain_no_banned_native_elements(): void
    {
        $projectIndexFile = resource_path('js/Pages/Projects/Index.tsx');
        $costCenterIndexFile = resource_path('js/Pages/CostCenters/Index.tsx');

        $this->assertFileExists($projectIndexFile);
        $this->assertFileExists($costCenterIndexFile);

        $projectCode = file_get_contents($projectIndexFile);
        $costCenterCode = file_get_contents($costCenterIndexFile);

        foreach (['<select', '<option', 'type="date"', "type='date'", 'window.location.href'] as $banned) {
            $this->assertStringNotContainsString(
                $banned,
                $projectCode,
                "File [Projects/Index.tsx] contains banned token: {$banned}"
            );
            $this->assertStringNotContainsString(
                $banned,
                $costCenterCode,
                "File [CostCenters/Index.tsx] contains banned token: {$banned}"
            );
        }
    }
}
