<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPackage extends Model
{
    use HasFactory;

    // NOTE: 'used_lessons' is intentionally NOT in $fillable. Modify only via RegisterLessonAction/DeleteLessonAction.
    protected $fillable = [
        'student_id',
        'total_lessons',
        'price',
        'currency',
        'purchased_at',
        'expires_at',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    // Scope: active packages (not exhausted and not expired)

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereColumn('used_lessons', '<', 'total_lessons')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    // Computed remaining lessons

    public function getRemainingAttribute(): int
    {
        return max(0, $this->total_lessons - $this->used_lessons);
    }

    // Status helpers

    public function isExhausted(): bool
    {
        return $this->used_lessons >= $this->total_lessons;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return !$this->isExhausted() && !$this->isExpired();
    }

    // Relationships

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'package_id');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class, 'lesson_package_id');
    }

    public function isPaid(): bool
    {
        return $this->payment()->exists();
    }

    public function scopeNeedingPayment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('price')
                     ->whereDoesntHave('payment');
    }
}
