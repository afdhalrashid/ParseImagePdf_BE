<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaskingResult extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'masking_job_id',
        'algorithm_name',
        'library_used',
        'status',
        'processing_time',
        'file_size',
        'words_masked_count',
        'masked_file_path',
        'error_message'
    ];

    protected $casts = [
        'processing_time' => 'integer',
        'file_size' => 'integer',
        'words_masked_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the masking job that owns the result
     */
    public function maskingJob(): BelongsTo
    {
        return $this->belongsTo(MaskingJob::class, 'masking_job_id');
    }
}
