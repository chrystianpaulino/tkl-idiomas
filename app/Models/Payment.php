<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'lesson_package_id',
        'registered_by',
        'amount',
        'currency',
        'method',
        'paid_at',
        'notes',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount'  => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lessonPackage(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
