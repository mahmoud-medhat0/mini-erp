<?php

namespace Tests\Feature;

use App\Application\Notifications\NotificationService;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class M9AttachmentsAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, RbacSeeder::class, PermissionSeeder::class]);
        Storage::fake('local');
    }

    // --- 1. ATTACHMENT TESTS ---

    public function test_authorized_user_can_upload_attachment_to_supported_entity(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $attachment = DB::table('attachment')->where('entity_id', $companyId)->first();
        $this->assertNotNull($attachment);
        $this->assertEquals('company', $attachment->entity_type);
        $this->assertEquals('contract.pdf', $attachment->name);
        $this->assertEquals('application/pdf', $attachment->mime);
        $this->assertEquals($admin->id, $attachment->uploaded_by);

        Storage::disk('local')->assertExists($attachment->file_ref);
    }

    public function test_authorized_user_can_download_and_delete_attachment(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $file,
        ]);

        $attachment = DB::table('attachment')->where('entity_id', $companyId)->first();
        $this->assertNotNull($attachment);

        // Download
        $downloadResp = $this->actingAs($admin)->get(route('attachments.show', $attachment->id));
        $downloadResp->assertStatus(200);

        // Delete
        $deleteResp = $this->actingAs($admin)->delete(route('attachments.destroy', $attachment->id));
        $deleteResp->assertRedirect();

        $this->assertNull(DB::table('attachment')->where('id', $attachment->id)->first());
        Storage::disk('local')->assertMissing($attachment->file_ref);
    }

    public function test_unauthorized_user_cannot_download_or_delete_attachment(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $file,
        ]);

        $attachment = DB::table('attachment')->where('entity_id', $companyId)->first();

        $regularUser = User::factory()->create();

        // Download attempt
        $this->actingAs($regularUser)->get(route('attachments.show', $attachment->id))->assertStatus(403);

        // Delete attempt
        $this->actingAs($regularUser)->delete(route('attachments.destroy', $attachment->id))->assertStatus(403);
    }

    public function test_attachment_permissions_are_limited_to_configured_entity_permission_map(): void
    {
        $companyManager = User::factory()->create();
        $companyManager->givePermissionTo('settings.company');

        $userManager = User::factory()->create();
        $userManager->givePermissionTo('users.configure');

        $settingsManager = User::factory()->create();
        $settingsManager->givePermissionTo('settings.configure');

        $targetUser = User::factory()->create();
        $companyId = (string) Str::uuid();

        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($userManager)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => UploadedFile::fake()->create('company.pdf', 100, 'application/pdf'),
        ])->assertForbidden();

        $this->actingAs($settingsManager)->post(route('attachments.store'), [
            'entity_type' => 'user',
            'entity_id' => (string) $targetUser->id,
            'file' => UploadedFile::fake()->create('user.pdf', 100, 'application/pdf'),
        ])->assertForbidden();

        $this->actingAs($companyManager)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => UploadedFile::fake()->create('company.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseCount('attachment', 1);
    }

    public function test_unknown_entity_type_or_missing_entity_id_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');

        // Unknown entity type
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'invalid_entity_type',
            'entity_id' => (string) Str::uuid(),
            'file' => $file,
        ])->assertSessionHasErrors('entity_type');

        // Missing entity id in table
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => (string) Str::uuid(), // Non-existent company ID
            'file' => $file,
        ])->assertStatus(404);
    }

    public function test_unsupported_extension_and_oversize_files_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Executable script extension
        $badFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $badFile,
        ])->assertStatus(422);

        // Oversized file (> 10MB)
        $largeFile = UploadedFile::fake()->create('huge.pdf', 12000, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $largeFile,
        ])->assertSessionHasErrors('file');
    }

    public function test_allowed_extension_with_unsupported_mime_type_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $spoofedFile = UploadedFile::fake()->create('spoofed.pdf', 100, 'application/x-msdownload');

        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $spoofedFile,
        ])->assertStatus(422);

        $this->assertDatabaseCount('attachment', 0);
    }

    public function test_path_traversal_attempts_in_filenames_are_sanitized(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $companyId = (string) Str::uuid();
        DB::table('company')->insert([
            'id' => $companyId,
            'name' => json_encode(['en' => 'Test Corp', 'ar' => 'شركة فحص']),
            'base_currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('../../secret.pdf', 100, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'file' => $file,
        ]);

        $attachment = DB::table('attachment')->where('entity_id', $companyId)->first();
        $this->assertNotNull($attachment);
        $this->assertStringNotContainsString('..', $attachment->file_ref);
        $this->assertStringNotContainsString('..', $attachment->name);
    }

    public function test_opening_balance_attachment_uses_opening_balance_entity_not_fiscal_year(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('accounting.opening_balances');

        $fiscalYearId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $openingBalanceId = (string) Str::uuid();

        DB::table('fiscal_year')->insert([
            'id' => $fiscalYearId,
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        DB::table('account')->insert([
            'id' => $accountId,
            'code' => '1100',
            'name' => json_encode(['en' => 'Cash', 'ar' => 'نقدية']),
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('opening_balance')->insert([
            'id' => $openingBalanceId,
            'fiscal_year_id' => $fiscalYearId,
            'account_id' => $accountId,
            'debit_minor' => 10000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'draft',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'opening_balance',
            'entity_id' => $fiscalYearId,
            'file' => UploadedFile::fake()->create('wrong-target.pdf', 100, 'application/pdf'),
        ])->assertNotFound();

        $this->actingAs($admin)->post(route('attachments.store'), [
            'entity_type' => 'opening_balance',
            'entity_id' => $openingBalanceId,
            'file' => UploadedFile::fake()->create('opening-balance.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('attachment', [
            'entity_type' => 'opening_balance',
            'entity_id' => $openingBalanceId,
            'name' => 'opening-balance.pdf',
        ]);
    }

    // --- 2. NOTIFICATION TESTS ---

    public function test_notification_service_creates_and_dedupes_notifications(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $notif1 = $service->create($user->id, 'role.assigned', 'role:ACCOUNTANT');
        $this->assertNotNull($notif1);

        // Duplicate attempt
        $notif2 = $service->create($user->id, 'role.assigned', 'role:ACCOUNTANT');
        $this->assertEquals($notif1['id'], $notif2['id']);

        $this->assertEquals(1, DB::table('notification')->where('user_id', $user->id)->count());
    }

    public function test_user_can_query_and_mark_only_own_notifications(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $service = app(NotificationService::class);

        $notifA = $service->create($userA->id, 'test.type', 'ref:1');
        $notifB = $service->create($userB->id, 'test.type', 'ref:2');

        // User A list
        $userANotifs = $service->listForUser($userA->id);
        $this->assertCount(1, $userANotifs);
        $this->assertEquals($notifA['id'], $userANotifs->first()->id);

        // User A marks User B's notification read
        $this->actingAs($userA)->post(route('notifications.read', $notifB['id']));

        // User B's notification should remain unread
        $this->assertFalse((bool) DB::table('notification')->where('id', $notifB['id'])->value('read'));

        // User A mark-all only affects User A
        $service->markAllRead($userA->id);
        $this->assertTrue((bool) DB::table('notification')->where('id', $notifA['id'])->value('read'));
        $this->assertFalse((bool) DB::table('notification')->where('id', $notifB['id'])->value('read'));
    }

    public function test_role_assignment_and_revocation_trigger_notifications(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.configure');

        $targetUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'ACCOUNTANT', 'guard_name' => 'web']);

        // Assign Role
        $this->actingAs($admin)->post(route('settings.users.roles.assign'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('notification', [
            'user_id' => $targetUser->id,
            'type' => 'role.assigned',
            'target_ref' => 'role:ACCOUNTANT',
        ]);

        // Revoke Role
        $this->actingAs($admin)->delete(route('settings.users.roles.revoke'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('notification', [
            'user_id' => $targetUser->id,
            'type' => 'role.revoked',
            'target_ref' => 'role:ACCOUNTANT',
        ]);
    }

    // --- 3. REGRESSION & SCHEMA INVARIANT TESTS ---

    public function test_m9_schema_and_config_invariants(): void
    {
        $this->assertFalse(Schema::hasColumn('attachment', 'company_id'), 'attachment table must not have company_id');
        $this->assertFalse(Schema::hasColumn('attachment', 'branch_id'), 'attachment table must not have branch_id');
        $this->assertFalse(Schema::hasColumn('attachment', 'tenant_id'), 'attachment table must not have tenant_id');

        $this->assertFalse(Schema::hasColumn('notification', 'company_id'), 'notification table must not have company_id');
        $this->assertFalse(Schema::hasColumn('notification', 'branch_id'), 'notification table must not have branch_id');
        $this->assertFalse(Schema::hasColumn('notification', 'tenant_id'), 'notification table must not have tenant_id');

        $this->assertFalse(Schema::hasColumn('audit_log', 'company_id'), 'audit_log table must not have company_id');
        $this->assertFalse(Schema::hasColumn('audit_log', 'branch_id'), 'audit_log table must not have branch_id');

        $this->assertFalse(config('permission.teams'), 'Spatie teams must remain disabled');
    }
}
