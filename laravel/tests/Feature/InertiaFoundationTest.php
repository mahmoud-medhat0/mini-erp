<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_page_renders_through_inertia(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $company = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);
        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);
        $company->users()->attach($user->id);

        $this->actingAs($user);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Foundation')
                ->where('status', 'M5 tenant auth foundation')
                ->where('database', 'not_checked')
                ->where('auth.user.email', $user->email)
                ->where('tenant.company.id', $company->id)
                ->where('tenant.branch.id', $branch->id)
                ->has('notifications')
                ->etc());
    }
}
