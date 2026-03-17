<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A teaching material (PDF, video, document, etc.) uploaded to a class.
 *
 * Files are stored on the 'public' disk under the 'materials/' directory.
 * The download_url accessor is automatically appended to JSON serialization
 * so the frontend always receives a ready-to-use URL.
 *
 * @property int $id
 * @property int $class_id
 * @property int $uploaded_by         Foreign key to the User who uploaded this material
 * @property string $title
 * @property string $file_path        Relative path within the 'public' storage disk
 * @property string|null $description
 * @property int|null $school_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read string $download_url  Full URL to download the file from public storage
 * @property-read TurmaClass $turmaClass
 * @property-read User $uploader
 */
class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'uploaded_by',
        'title',
        'file_path',
        'description',
        'school_id',
    ];

    protected $appends = ['download_url'];

    /**
     * Build the full public URL for this material's file.
     *
     * @see UploadMaterialAction Ensures file_path is always set at creation time
     */
    // TODO(review): No guard for empty file_path. Validate non-empty at write boundary (UploadMaterialAction). - nil-safety-reviewer, 2026-03-12, Severity: Medium
    public function getDownloadUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * The class this material belongs to.
     */
    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    /**
     * The admin or professor who uploaded this material.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
