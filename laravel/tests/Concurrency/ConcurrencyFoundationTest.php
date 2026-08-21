<?php

namespace Tests\Concurrency;

use App\Models\Company;
use App\Models\User;
use App\Support\Auth\AuthGarbageCollector;
use App\Support\Concurrency\ConcurrencyConflictException;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Concurrency\IdempotencyConflictException;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrencyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_number_allocator_returns_unique_increasing_values(): void
    {
        $company = $this->createCompany();
        $allocator = app(NumberSequenceAllocator::class);

        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $values[] = $allocator->nextValue($company->id, 'sales.invoice');
        }

        sort($values);

        $this->assertSame(range(1, 100), $values);
    }

    public function test_idempotency_store_executes_side_effect_once_for_repeated_key(): void
    {
        $store = app(DatabaseIdempotencyStore::class);
        $executions = 0;
        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $result = $store->run(
                'test.operation',
                'repeatable-key',
                function () use (&$executions) {
                    $executions++;

                    return ['receipt' => 'created'];
                },
                null,
                'same-request',
                now()->addHour(),
            );

            $values[] = $result->value;
        }

        $this->assertSame(1, $executions);
        $this->assertTrue(collect($values)->every(fn (array $value): bool => $value === ['receipt' => 'created']));
    }

    public function test_idempotency_store_rejects_same_key_with_different_fingerprint(): void
    {
        $store = app(DatabaseIdempotencyStore::class);

        $store->run(
            'test.operation',
            'conflict-key',
            fn () => ['receipt' => 'created'],
            null,
            'request-a',
            now()->addHour(),
        );

        $this->expectException(IdempotencyConflictException::class);

        $store->run(
            'test.operation',
            'conflict-key',
            fn () => ['receipt' => 'duplicated'],
            null,
            'request-b',
            now()->addHour(),
        );
    }

    public function test_optimistic_lock_rejects_stale_updates(): void
    {
        $company = $this->createCompany();
        $lock = app(OptimisticLock::class);

        $nextVersion = $lock->update('company', ['id' => $company->id], 0, [
            'name' => json_encode(['en' => 'Updated Company', 'ar' => 'شركة محدثة'], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(1, $nextVersion);

        $this->expectException(ConcurrencyConflictException::class);

        $lock->update('company', ['id' => $company->id], 0, [
            'name' => json_encode(['en' => 'Stale Company', 'ar' => 'شركة قديمة'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function test_auth_garbage_collector_deletes_only_expired_records(): void
    {
        $expiredSessionId = 'expired-session';
        $activeSessionId = 'active-session';
        $expiredEmail = 'expired@example.com';
        $activeEmail = 'active@example.com';
        $expiredIdempotencyId = (string) Str::uuid();
        $activeIdempotencyId = (string) Str::uuid();

        DB::table('sessions')->insert([
            [
                'id' => $expiredSessionId,
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => '',
                'last_activity' => now()->subMinutes(180)->timestamp,
            ],
            [
                'id' => $activeSessionId,
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
        ]);

        DB::table('password_reset_tokens')->insert([
            [
                'email' => $expiredEmail,
                'token' => 'expired',
                'created_at' => now()->subMinutes(90),
            ],
            [
                'email' => $activeEmail,
                'token' => 'active',
                'created_at' => now(),
            ],
        ]);

        DB::table('idempotency_keys')->insert([
            [
                'id' => $expiredIdempotencyId,
                'operation' => 'expired',
                'key_hash' => hash('sha256', 'expired'),
                'key_scope' => 'global',
                'status' => 'completed',
                'response_json' => json_encode(['ok' => true], JSON_THROW_ON_ERROR),
                'created_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2),
                'expires_at' => now()->subDay(),
            ],
            [
                'id' => $activeIdempotencyId,
                'operation' => 'active',
                'key_hash' => hash('sha256', 'active'),
                'key_scope' => 'global',
                'status' => 'completed',
                'response_json' => json_encode(['ok' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'completed_at' => now(),
                'expires_at' => now()->addDay(),
            ],
        ]);

        $summary = app(AuthGarbageCollector::class)->collect(10);

        $this->assertSame(['sessions' => 1, 'password_reset_tokens' => 1, 'idempotency_keys' => 1], $summary);
        $this->assertDatabaseMissing('sessions', ['id' => $expiredSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $activeSessionId]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $expiredEmail]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $activeEmail]);
        $this->assertDatabaseMissing('idempotency_keys', ['id' => $expiredIdempotencyId]);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $activeIdempotencyId]);
    }

    public function test_notification_dedupe_key_prevents_duplicate_logical_notifications(): void
    {
        $company = $this->createCompany();
        $user = User::factory()->create();

        DB::table('notification')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'type' => 'test',
            'target_ref' => 'invoice:1',
            'dedupe_key' => 'invoice:1:posted',
            'at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('notification')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'type' => 'test',
            'target_ref' => 'invoice:1',
            'dedupe_key' => 'invoice:1:posted',
            'at' => now(),
        ]);
    }

    public function test_conflict_messages_are_localized(): void
    {
        app()->setLocale('en');
        $this->assertSame(
            'This record was changed by another user. Reload before saving.',
            (new ConcurrencyConflictException)->getMessage(),
        );

        app()->setLocale('ar');
        $this->assertSame(
            'تم تعديل هذا السجل بواسطة مستخدم آخر. أعد تحميله قبل الحفظ.',
            (new ConcurrencyConflictException)->getMessage(),
        );
    }

    private function createCompany(): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);
    }
}
