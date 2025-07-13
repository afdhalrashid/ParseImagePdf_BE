<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaskingJob extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'original_file_path',
        'original_filename',
        'words_to_mask',
        'algorithms',
        'status'
    ];

    protected $casts = [
        'words_to_mask' => 'array',
        'algorithms' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that owns the masking job
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the masking results for the job
     */
    public function results(): HasMany
    {
        return $this->hasMany(MaskingResult::class, 'masking_job_id');
    }
}
