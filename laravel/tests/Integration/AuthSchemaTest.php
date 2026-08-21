<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_contains_the_authentication_profile_fields(): void
    {
        foreach (['locale', 'theme', 'is_active', 'mfa_enabled'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
        }

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        $this->assertTrue($indexes->contains('users_is_active_index'));
    }

    public function test_new_users_receive_secure_authentication_defaults(): void
    {
        $user = User::query()->create([
            'name' => 'Auth User',
            'email' => 'auth@example.com',
            'password' => 'secret-password',
        ])->refresh();

        $this->assertSame('en', $user->locale);
        $this->assertSame('system', $user->theme);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->mfa_enabled);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertNotSame('secret-password', $user->password);
    }

    public function test_authentication_preferences_are_mass_assignable_and_cast(): void
    {
        $user = User::factory()->create([
            'locale' => 'ar',
            'theme' => 'dark',
            'is_active' => false,
            'mfa_enabled' => true,
        ]);

        $this->assertSame('ar', $user->locale);
        $this->assertSame('dark', $user->theme);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->mfa_enabled);
    }
}
