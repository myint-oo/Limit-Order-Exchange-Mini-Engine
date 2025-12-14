<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'amount',
        'locked_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'locked_amount' => 'decimal:8',
    ];

    /**
     * Get the user that owns the asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get available (unlocked) amount.
     */
    public function getAvailableAmountAttribute(): string
    {
        return bcsub($this->amount, $this->locked_amount, 8);
    }
}
