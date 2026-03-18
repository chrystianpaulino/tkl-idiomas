<?php

namespace App\Actions\Schools;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a new school tenant with its first administrator account.
 *
 * In the SaaS model, a school cannot exist without an operator. This action
 * creates both the school record AND the first school_admin user in a single
 * atomic transaction -- guaranteeing the tenant is always fully operational
 * after creation.
 *
 * The generated school_admin receives a temporary password. In production this
 * should trigger an email invitation; for now the password is returned to the
 * caller so it can be displayed or forwarded.
 */
class ProvisionSchoolAction
{
    /**
     * Creates a school and its first school_admin in an atomic transaction.
     *
     * @param  array{name: string, slug: string, email: string, admin_name: string, admin_email: string, admin_password: string}  $data
     * @return array{school: School, admin: User}
     *
     * @throws UniqueConstraintViolationException If slug or admin email already exists.
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $school = School::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'],
                'active' => true,
            ]);

            $admin = new User;
            $admin->name = $data['admin_name'];
            $admin->email = $data['admin_email'];
            $admin->password = Hash::make($data['admin_password']);
            $admin->role = 'school_admin';
            $admin->school_id = $school->id;
            $admin->save();

            Log::info('School provisioned', [
                'school_id' => $school->id,
                'slug' => $school->slug,
                'admin_email' => $admin->email,
            ]);

            return ['school' => $school, 'admin' => $admin];
        });
    }
}
