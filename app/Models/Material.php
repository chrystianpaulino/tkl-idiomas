<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'uploaded_by',
        'title',
        'file_path',
        'description',
    ];

    protected $appends = ['download_url'];

    // TODO(review): No guard for empty file_path. Validate non-empty at write boundary (UploadMaterialAction). - nil-safety-reviewer, 2026-03-12, Severity: Medium
    public function getDownloadUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
