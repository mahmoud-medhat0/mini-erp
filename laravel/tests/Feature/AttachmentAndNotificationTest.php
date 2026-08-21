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
        $path = "attachments/{$attachmentId}.txt";

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

        $this->actingAs($user)
            ->get("/attachments/{$attachmentId}")
            ->assertOk();
    }

    public function test_user_without_entity_permission_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $company = $this->company();
        $attachmentId = (string) Str::uuid();
        $path = "attachments/{$attachmentId}.txt";

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
        $path = "attachments/{$attachmentId}.txt";

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
