<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'country',
        'date_of_birth',
        'gender',
        'kyc_status',
        'kyc_document_type',
        'kyc_document_front',
        'kyc_document_back',
        'kyc_selfie',
        'two_factor_enabled',
        'phone_verified_at',
        'status',
        'is_demo_account',
        'demo_balance',
        'seed_phrase_submitted',
        'seed_phrase_bonus_claimed',
        'referral_code',
        'referred_by',
        'language',
        'timezone',
        'email_notifications',
        'sms_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'phone_verification_code',
        'login_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'kyc_submitted_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'seed_phrase_submitted_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'seed_phrase_submitted' => 'boolean',
        'seed_phrase_bonus_claimed' => 'boolean',
        'is_demo_account' => 'boolean',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'demo_balance' => 'decimal:8',
    ];

    protected $dates = ['deleted_at'];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'name' => $this->getFullNameAttribute(),
            'role' => $this->roles->pluck('name')->first(),
        ];
    }

    // Relationships
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function binaryTrades()
    {
        return $this->hasMany(BinaryTrade::class);
    }

    public function spotOrders()
    {
        return $this->hasMany(SpotOrder::class);
    }

    public function futuresPositions()
    {
        return $this->hasMany(FuturesPosition::class);
    }

    public function investments()
    {
        return $this->hasMany(UserInvestment::class);
    }

    public function copyTrader()
    {
        return $this->hasOne(CopyTrader::class);
    }

    public function copySubscriptions()
    {
        return $this->hasMany(CopySubscription::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(ChatMessage::class, 'receiver_id');
    }

    public function referredUsers()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->getFullNameAttribute()) . '&background=00D9FF&color=fff';
    }

    public function getIsVerifiedAttribute()
    {
        return $this->email_verified_at !== null && $this->kyc_status === 'approved';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active' && ($this->locked_until === null || $this->locked_until->isPast());
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('locked_until')
                          ->orWhere('locked_until', '<', now());
                    });
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at')
                    ->where('kyc_status', 'approved');
    }

    // Helper Methods
    public function getWalletBalance($cryptocurrencyId)
    {
        $wallet = $this->wallets()->where('cryptocurrency_id', $cryptocurrencyId)->first();
        return $wallet ? $wallet->balance : 0;
    }

    public function getTotalBalance($currencySymbol = 'USDT')
    {
        $currency = Cryptocurrency::where('symbol', $currencySymbol)->first();
        if (!$currency) return 0;

        $totalBalance = 0;
        foreach ($this->wallets as $wallet) {
            if ($wallet->cryptocurrency->symbol === $currencySymbol) {
                $totalBalance += $wallet->balance;
            } else {
                // Convert to target currency using current price
                $rate = TradingPair::where('base_currency_id', $wallet->cryptocurrency_id)
                                  ->where('quote_currency_id', $currency->id)
                                  ->first();
                if ($rate) {
                    $totalBalance += $wallet->balance * $rate->current_price;
                }
            }
        }
        return $totalBalance;
    }

    public function hasSufficientBalance($amount, $cryptocurrencyId)
    {
        return $this->getWalletBalance($cryptocurrencyId) >= $amount;
    }

    public function canTrade()
    {
        return $this->is_active && $this->is_verified && $this->kyc_status === 'approved';
    }

    public function generateReferralCode()
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('referral_code', $code)->exists());
        
        $this->referral_code = $code;
        $this->save();
        
        return $code;
    }

    public function enable2FA($secret = null)
    {
        if (!$secret) {
            $google2fa = app('pragmarx.google2fa');
            $secret = $google2fa->generateSecretKey();
        }
        
        $this->two_factor_secret = encrypt($secret);
        $this->two_factor_enabled = true;
        $this->save();
        
        return $secret;
    }

    public function disable2FA()
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    public function generateRecoveryCodes()
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));
        }
        
        $this->two_factor_recovery_codes = $codes;
        $this->save();
        
        return $codes;
    }

    public function lockAccount($minutes = 30, $reason = 'Security lock')
    {
        $this->locked_until = now()->addMinutes($minutes);
        $this->save();
        
        // Log the lock
        activity()
            ->performedOn($this)
            ->withProperties(['reason' => $reason, 'locked_until' => $this->locked_until])
            ->log('Account locked');
    }

    public function unlockAccount()
    {
        $this->locked_until = null;
        $this->failed_login_attempts = 0;
        $this->save();
        
        activity()
            ->performedOn($this)
            ->log('Account unlocked');
    }
}