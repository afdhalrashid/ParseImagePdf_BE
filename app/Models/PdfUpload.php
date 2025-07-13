<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfUpload extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_filename',
        'file_hash',
        'file_size',
        'mime_type',
        'status',
        'is_chunked',
        'total_chunks',
        'uploaded_chunks',
        'upload_session_id',
        'extracted_text',
        'metadata',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'is_chunked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(PdfChunk::class);
    }

    public function getFileSizeMbAttribute(): float
    {
        return round($this->file_size / 1048576, 2); // Convert bytes to MB
    }

    public function getUploadProgressAttribute(): float
    {
        if (!$this->is_chunked || $this->total_chunks === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        return round(($this->uploaded_chunks / $this->total_chunks) * 100, 2);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
