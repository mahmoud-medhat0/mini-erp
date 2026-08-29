<?php

namespace Tests\Feature;

use App\Application\Attachments\AttachmentService;
use App\Application\Notifications\NotificationService;
use App\Domain\Audit\AuditLogger;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttachmentAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_upload_persists_metadata_file_and_audit_record(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();
        $file = UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf');

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => $file,
            ])
            ->assertRedirect();

        $attachment = DB::table('attachment')->first();

        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->file_ref);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'attachment.upload',
        ]);
    }

    public function test_unauthenticated_attachment_requests_are_rejected(): void
    {
        $this->post('/attachments')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_without_entity_permission_cannot_upload_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $company = $this->company();

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attachment', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('attachments'));
    }

    public function test_unknown_attachment_entity_type_is_rejected(): void
    {
        $user = $this->userWithPermission('settings.configure');

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'sales_invoice',
                'entity_id' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf'),
            ])
            ->assertSessionHasErrors('entity_type');
    }

    public function test_missing_attachment_entity_is_rejected_safely(): void
    {
        $user = $this->userWithPermission('settings.configure');

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    public function test_authorized_user_can_download_attachment_for_allowed_entity(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();
        $attachmentId = (string) Str::uuid();
        $path = "attachments/company/{$company->id}/{$attachmentId}-demo.txt";

        Storage::disk('local')->put($path, 'demo');
        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'file_ref' => $path,
            'name' => 'demo.txt',
            'mime' => 'text/plain',
            'size' => 4,
            'uploaded_by' => $user->id,
            'at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get("/attachments/{$attachmentId}");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_user_without_entity_permission_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $company = $this->company();
        $attachmentId = (string) Str::uuid();
        $path = "attachments/company/{$company->id}/{$attachmentId}-demo.txt";

        Storage::disk('local')->put($path, 'demo');
        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'file_ref' => $path,
            'name' => 'demo.txt',
            'mime' => 'text/plain',
            'size' => 4,
            'uploaded_by' => null,
            'at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/attachments/{$attachmentId}")
            ->assertForbidden();
    }

    public function test_download_uses_stored_entity_reference_not_browser_overrides(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();
        $attachmentId = (string) Str::uuid();
        $path = "attachments/company/{$company->id}/{$attachmentId}-demo.txt";

        Storage::disk('local')->put($path, 'demo');
        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'unsupported',
            'entity_id' => 'hidden',
            'file_ref' => $path,
            'name' => 'demo.txt',
            'mime' => 'text/plain',
            'size' => 4,
            'uploaded_by' => null,
            'at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/attachments/{$attachmentId}?entity_type=company&entity_id={$company->id}")
            ->assertForbidden();
    }

    public function test_path_traversal_in_upload_filenames_and_display_names_are_sanitized_and_stay_in_private_root(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $file = UploadedFile::fake()->create('../../secret_contract.pdf', 10, 'application/pdf');

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => $file,
                'name' => '../../../../custom_display.pdf',
            ])
            ->assertRedirect();

        $attachment = DB::table('attachment')->where('entity_id', $company->id)->first();
        $this->assertNotNull($attachment);

        $this->assertStringNotContainsString('..', $attachment->file_ref);
        $this->assertStringNotContainsString('/', $attachment->name);
        $this->assertStringNotContainsString('\\', $attachment->name);
        $this->assertStringNotContainsString('..', $attachment->name);
        $this->assertTrue(str_starts_with($attachment->file_ref, "attachments/company/{$company->id}/"));

        Storage::disk('local')->assertExists($attachment->file_ref);

        $downloadResp = $this->actingAs($user)->get("/attachments/{$attachment->id}");
        $downloadResp->assertOk();
        $downloadResp->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_empty_extension_and_unsupported_extensions_are_rejected(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $noExtFile = UploadedFile::fake()->create('noext', 10, 'application/pdf');
        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => $noExtFile,
            ])
            ->assertStatus(422);

        $exeFile = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');
        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => $exeFile,
            ])
            ->assertStatus(422);
    }

    public function test_unsupported_mime_and_extension_mime_mismatch_are_rejected(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $mismatchFile = UploadedFile::fake()->create('spoofed.pdf', 10, 'application/x-msdownload');

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => $mismatchFile,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('attachment', 0);
    }

    public function test_private_storage_policy_is_preserved_and_attachments_are_never_placed_on_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->assertSame('local', config('erp_attachments.disk'));
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $this->actingAs($user)
            ->post('/attachments', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'file' => UploadedFile::fake()->create('private.pdf', 8, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = DB::table('attachment')->where('entity_id', $company->id)->first();
        $this->assertNotNull($attachment);

        Storage::disk('local')->assertExists($attachment->file_ref);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_delete_requires_authorization_before_file_and_db_mutation(): void
    {
        Storage::fake('local');

        $admin = $this->userWithPermission('settings.configure');
        $unauthorizedUser = User::factory()->create();
        $company = $this->company();

        $attachmentId = (string) Str::uuid();
        $path = "attachments/company/{$company->id}/{$attachmentId}-test.pdf";
        Storage::disk('local')->put($path, 'pdfcontent');

        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'file_ref' => $path,
            'name' => 'test.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by' => $admin->id,
            'at' => now(),
        ]);

        $this->actingAs($unauthorizedUser)
            ->delete("/attachments/{$attachmentId}")
            ->assertForbidden();

        $this->assertDatabaseHas('attachment', ['id' => $attachmentId]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_delete_writes_spatie_activity_log_evidence_with_attachment_metadata(): void
    {
        Storage::fake('local');

        $admin = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $attachmentId = (string) Str::uuid();
        $path = "attachments/company/{$company->id}/{$attachmentId}-document.pdf";
        Storage::disk('local')->put($path, 'pdf-data');

        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'file_ref' => $path,
            'name' => 'document.pdf',
            'mime' => 'application/pdf',
            'size' => 8,
            'uploaded_by' => $admin->id,
            'at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete("/attachments/{$attachmentId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('attachment', ['id' => $attachmentId]);
        Storage::disk('local')->assertMissing($path);

        $activity = Activity::query()->where('event', 'attachment.delete')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame('attachment.delete', $activity->event);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('company', $activity->properties['entity_type'] ?? null);
        $this->assertSame($company->id, $activity->properties['entity_id'] ?? null);
        $this->assertSame($attachmentId, $activity->properties['before']['attachment_id'] ?? null);
        $this->assertSame('document.pdf', $activity->properties['before']['name'] ?? null);
        $this->assertSame('application/pdf', $activity->properties['before']['mime'] ?? null);
        $this->assertSame(8, $activity->properties['before']['size'] ?? null);
    }

    public function test_attachment_download_with_suspicious_stored_file_ref_is_safely_blocked(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();
        $attachmentId = (string) Str::uuid();

        DB::table('attachment')->insert([
            'id' => $attachmentId,
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'file_ref' => '../../etc/passwd',
            'name' => 'passwd',
            'mime' => 'text/plain',
            'size' => 10,
            'uploaded_by' => $user->id,
            'at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/attachments/{$attachmentId}")
            ->assertForbidden();
    }

    public function test_attachment_upload_deletes_new_file_when_metadata_persistence_fails(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('settings.configure');
        $company = $this->company();

        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(
                int|string|null $actorId,
                string $action,
                string $entityType,
                string $entityId,
                ?array $before = null,
                ?array $after = null,
                ?string $reason = null,
                ?string $requestId = null,
                ?string $ip = null,
                ?string $device = null,
            ): void {
                throw new RuntimeException('audit failed');
            }
        });

        try {
            app(AttachmentService::class)->upload(
                UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf'),
                'company',
                $company->id,
                $user,
            );

            $this->fail('Expected attachment upload to rethrow the metadata failure.');
        } catch (RuntimeException) {
            // Expected path.
        }

        $this->assertDatabaseCount('attachment', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('attachments'));
    }

    public function test_notifications_are_deduped_and_marked_read_per_user(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $first = $service->create($user->id, 'system', 'demo:1', 'demo:1:system');
        $second = $service->create($user->id, 'system', 'demo:1', 'demo:1:system');

        $this->assertSame($first['id'], $second['id']);
        $this->assertCount(1, $service->listForUser($user->id));
        $this->assertTrue($service->markRead($user->id, $first['id']));
        $this->assertFalse($service->markRead($other->id, $first['id']));
        $this->assertTrue((bool) DB::table('notification')->where('id', $first['id'])->value('read'));
    }

    public function test_cross_user_notification_mark_read_does_not_update_another_users_notification(): void
    {
        $service = app(NotificationService::class);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notifA = $service->create($userA->id, 'task.assigned', 'task:100');
        $notifB = $service->create($userB->id, 'task.assigned', 'task:200');

        $this->actingAs($userA)->post("/notifications/{$notifB['id']}/read");

        $this->assertFalse((bool) DB::table('notification')->where('id', $notifB['id'])->value('read'));
        $this->assertFalse((bool) DB::table('notification')->where('id', $notifA['id'])->value('read'));
    }

    public function test_cross_user_notification_dedupe_does_not_suppress_notification_for_different_user(): void
    {
        $service = app(NotificationService::class);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notifA = $service->create($userA->id, 'contract.signed', 'doc:123', 'shared-dedupe-key');
        $notifB = $service->create($userB->id, 'contract.signed', 'doc:123', 'shared-dedupe-key');

        $this->assertNotSame($notifA['id'], $notifB['id']);
        $this->assertSame(1, DB::table('notification')->where('user_id', $userA->id)->count());
        $this->assertSame(1, DB::table('notification')->where('user_id', $userB->id)->count());
    }

    public function test_notification_controller_ignores_payload_user_id_and_uses_session_user_only(): void
    {
        $service = app(NotificationService::class);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notifA = $service->create($userA->id, 'system.alert', 'alert:1');
        $notifB = $service->create($userB->id, 'system.alert', 'alert:2');

        // User A tries to pass User B's user_id in payload for markAllRead
        $this->actingAs($userA)->post('/notifications/read-all', [
            'user_id' => $userB->id,
        ]);

        $this->assertTrue((bool) DB::table('notification')->where('id', $notifA['id'])->value('read'));
        $this->assertFalse((bool) DB::table('notification')->where('id', $notifB['id'])->value('read'));

        // User A tries to pass User B's user_id in payload for markRead
        $notifA2 = $service->create($userA->id, 'system.alert', 'alert:3');
        $this->actingAs($userA)->post("/notifications/{$notifA2['id']}/read", [
            'user_id' => $userB->id,
        ]);

        $this->assertTrue((bool) DB::table('notification')->where('id', $notifA2['id'])->value('read'));
        $this->assertFalse((bool) DB::table('notification')->where('id', $notifB['id'])->value('read'));
    }

    public function test_notification_service_validates_and_normalizes_type_and_target_ref(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();

        $longType = str_repeat('a', 150);
        $longTargetRef = str_repeat('b', 300);

        $notif = $service->create($user->id, $longType, $longTargetRef);

        $this->assertNotNull($notif);
        $this->assertSame(100, mb_strlen($notif['type']));
        $this->assertSame(255, mb_strlen($notif['target_ref']));
    }

    private function userWithPermission(string $permissionName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'ATTACHMENT_TESTER',
            'guard_name' => 'web',
            'is_template' => false,
        ]);
        $permission = Permission::query()->create([
            'name' => $permissionName,
            'guard_name' => 'web',
            'module' => Str::before($permissionName, '.'),
            'action' => Str::after($permissionName, '.'),
        ]);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => ['en' => 'MDS', 'ar' => 'MDS'],
            'base_currency' => 'EGP',
            'settings_json' => [],
        ]);
    }
}
