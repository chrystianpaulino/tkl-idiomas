<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Multi-tenancy root entity representing a language school on the platform.
 *
 * Every tenant-scoped model (User, LessonPackage, Payment, etc.) references
 * a School via school_id. Global scopes for tenant isolation are NOT yet active,
 * so queries must manually filter by school_id where needed.
 *
 * @property int $id
 * @property string $name Display name of the school
 * @property string $slug URL-safe identifier, unique across the platform
 * @property string|null $email Contact email for the school administration
 * @property bool $active Whether the school is currently active on the platform
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, User> $users
 */
class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * All users (admins, professors, students) belonging to this school.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
