<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuota extends Model
{
    protected $fillable = [
        'user_id',
        'used_storage',
        'max_storage',
        'is_premium',
        'premium_expires_at',
    ];

    protected $casts = [
        'premium_expires_at' => 'datetime',
        'is_premium' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canUpload(int $fileSize): bool
    {
        return ($this->used_storage + $fileSize) <= $this->max_storage;
    }

    public function getRemainingStorageAttribute(): int
    {
        return max(0, $this->max_storage - $this->used_storage);
    }

    public function getUsedStorageMbAttribute(): float
    {
        return round($this->used_storage / 1048576, 2); // Convert bytes to MB
    }

    public function getMaxStorageMbAttribute(): float
    {
        return round($this->max_storage / 1048576, 2); // Convert bytes to MB
    }
}
