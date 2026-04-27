<?php

namespace App\Actions\Schools;

use App\Models\School;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
 *
 * White-label identity (logo + colors) is optional. When a logo file is
 * provided it is persisted to the public disk under schools/logos/. Color
 * defaults at the database layer mirror the platform's design tokens, so
 * passing nothing here keeps the UI visually identical to the legacy theme.
 */
class ProvisionSchoolAction
{
    /**
     * Creates a school and its first school_admin in an atomic transaction.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     email?: ?string,
     *     active?: bool,
     *     logo?: ?UploadedFile,
     *     primary_color?: ?string,
     *     secondary_color?: ?string,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string
     * }  $data
     * @return array{school: School, admin: User}
     *
     * @throws UniqueConstraintViolationException If slug or admin email already exists.
     */
    public function execute(array $data): array
    {
        $logoPath = null;
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            // Persist outside the transaction to avoid leaking files when the DB rolls back.
            // If the transaction fails below we'll clean it up explicitly.
            $logoPath = Storage::disk('public')->put('schools/logos', $data['logo']);
        }

        try {
            return DB::transaction(function () use ($data, $logoPath): array {
                $attributes = [
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'email' => $data['email'] ?? null,
                    'active' => $data['active'] ?? true,
                ];

                if ($logoPath !== null) {
                    $attributes['logo_url'] = $logoPath;
                }
                if (! empty($data['primary_color'])) {
                    $attributes['primary_color'] = $data['primary_color'];
                }
                if (! empty($data['secondary_color'])) {
                    $attributes['secondary_color'] = $data['secondary_color'];
                }

                $school = School::create($attributes);

                $admin = new User;
                $admin->name = $data['admin_name'];
                $admin->email = $data['admin_email'];
                $admin->password = Hash::make($data['admin_password']);
                $admin->role = 'school_admin';
                $admin->school_id = $school->id;
                $admin->save();

                Audit::log('school.provisioned', [
                    'school_id' => $school->id,
                    'slug' => $school->slug,
                    'admin_user_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'logo_uploaded' => $logoPath !== null,
                ]);

                return ['school' => $school, 'admin' => $admin];
            });
        } catch (\Throwable $e) {
            // Best-effort cleanup of an orphaned upload when provisioning fails.
            if ($logoPath !== null) {
                Storage::disk('public')->delete($logoPath);
            }
            throw $e;
        }
    }
}
