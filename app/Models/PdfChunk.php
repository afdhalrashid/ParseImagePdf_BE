<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfChunk extends Model
{
    protected $fillable = [
        'pdf_upload_id',
        'chunk_number',
        'chunk_size',
        'chunk_hash',
        'stored_path',
        'status',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function pdfUpload(): BelongsTo
    {
        return $this->belongsTo(PdfUpload::class);
    }

    public function getChunkSizeMbAttribute(): float
    {
        return round($this->chunk_size / 1048576, 2); // Convert bytes to MB
    }

    public function isUploaded(): bool
    {
        return $this->status === 'uploaded';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function scopeByUpload($query, $uploadId)
    {
        return $query->where('pdf_upload_id', $uploadId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
