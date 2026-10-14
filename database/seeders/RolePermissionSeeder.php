<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Roles::PERMISSIONS as $name => $label) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Roles::rolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $this->createUsers();
    }

    private function createUsers(): void
    {
        $password = config('epsv.demo_password');

        $accounts = [
            ['Direction EPSV', config('epsv.admin_email'), 'Direction', Roles::DIRECTION],
            ['Jean Mabiala', 'prof.mabiala@epsv.cg', 'Professeur de mathématiques', Roles::PROFESSEUR],
            ['Clarisse Kimbembe', 'prof.kimbembe@epsv.cg', 'Professeure de français', Roles::PROFESSEUR],
            ['Pierre Ngoma', 'prof.ngoma@epsv.cg', 'Professeur d\'histoire-géographie', Roles::PROFESSEUR],
            ['Paul Samba', 'surveillant@epsv.cg', 'Surveillant général', Roles::SURVEILLANT],
            ['Marie Loubaki', 'secretariat@epsv.cg', 'Secrétaire', Roles::SECRETARIAT],
            ['Technicien EPSV', 'admin@epsv.cg', 'Administrateur technique', Roles::ADMIN_TECHNIQUE],
        ];

        foreach ($accounts as [$name, $email, $jobTitle, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'job_title' => $jobTitle,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ],
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
