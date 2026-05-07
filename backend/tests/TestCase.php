<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ensure all application roles exist in the DB before each test.
     *
     * Spatie's assignRole() throws if the role row doesn't exist.
     * We seed them here so every test can call assignRole() freely.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        $roles = [
            'superadmin',
            'admin_sm',
            'sb_owner',
            'sb_employee',
            'bb',
            'sub_franchise_owner',
            'sub_franchise_admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
