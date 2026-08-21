<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Traits\HasRoles;

class SpatiePermissionFoundationTest extends TestCase
{
    public function test_user_model_is_ready_for_spatie_roles(): void
    {
        $traits = class_uses_recursive(User::class);

        $this->assertArrayHasKey(HasRoles::class, $traits);
    }
}
