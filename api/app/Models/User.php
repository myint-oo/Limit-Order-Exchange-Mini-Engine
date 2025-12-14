<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:8',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['locked_balance'];

    /**
     * Get the locked USD balance from open buy orders.
     */
    public function getLockedBalanceAttribute(): string
    {
        return $this->orders()
            ->where('status', 'open')
            ->where('side', 'buy')
            ->sum('locked_funds') ?? '0';
    }

    /**
     * Get user's assets.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get user's orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get user's trades (as buyer or seller).
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buyer_id')
            ->orWhere('seller_id', $this->id);
    }

    /**
     * Get trades where user was the buyer.
     */
    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buyer_id');
    }

    /**
     * Get trades where user was the seller.
     */
    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'seller_id');
    }
}
