<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cryptocurrency extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'logo_url',
        'current_price',
        'price_change_24h',
        'price_change_percentage_24h',
        'market_cap',
        'volume_24h',
        'market_cap_rank',
        'circulating_supply',
        'total_supply',
        'max_supply',
        'ath',
        'atl',
        'last_updated',
        'is_active',
        'spot_trading_enabled',
        'futures_trading_enabled',
        'binary_trading_enabled',
        'min_trade_amount',
        'max_trade_amount',
        'trading_fee_percentage',
        'deposits_enabled',
        'withdrawals_enabled',
        'min_deposit',
        'min_withdrawal',
        'withdrawal_fee',
        'network',
        'contract_address',
        'required_confirmations',
    ];

    protected $casts = [
        'current_price' => 'decimal:8',
        'price_change_24h' => 'decimal:4',
        'price_change_percentage_24h' => 'decimal:4',
        'market_cap' => 'decimal:2',
        'volume_24h' => 'decimal:2',
        'circulating_supply' => 'decimal:2',
        'total_supply' => 'decimal:2',
        'max_supply' => 'decimal:2',
        'ath' => 'decimal:8',
        'atl' => 'decimal:8',
        'last_updated' => 'datetime',
        'is_active' => 'boolean',
        'spot_trading_enabled' => 'boolean',
        'futures_trading_enabled' => 'boolean',
        'binary_trading_enabled' => 'boolean',
        'min_trade_amount' => 'decimal:8',
        'max_trade_amount' => 'decimal:8',
        'trading_fee_percentage' => 'decimal:4',
        'deposits_enabled' => 'boolean',
        'withdrawals_enabled' => 'boolean',
        'min_deposit' => 'decimal:8',
        'min_withdrawal' => 'decimal:8',
        'withdrawal_fee' => 'decimal:8',
    ];

    // Relationships
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function baseTradingPairs()
    {
        return $this->hasMany(TradingPair::class, 'base_currency_id');
    }

    public function quoteTradingPairs()
    {
        return $this->hasMany(TradingPair::class, 'quote_currency_id');
    }

    public function userInvestments()
    {
        return $this->hasMany(UserInvestment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSpotTradingEnabled($query)
    {
        return $query->where('spot_trading_enabled', true);
    }

    public function scopeFuturesTradingEnabled($query)
    {
        return $query->where('futures_trading_enabled', true);
    }

    public function scopeBinaryTradingEnabled($query)
    {
        return $query->where('binary_trading_enabled', true);
    }

    public function scopeDepositsEnabled($query)
    {
        return $query->where('deposits_enabled', true);
    }

    public function scopeWithdrawalsEnabled($query)
    {
        return $query->where('withdrawals_enabled', true);
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return number_format($this->current_price, 8);
    }

    public function getFormattedMarketCapAttribute()
    {
        if ($this->market_cap >= 1000000000) {
            return number_format($this->market_cap / 1000000000, 2) . 'B';
        } elseif ($this->market_cap >= 1000000) {
            return number_format($this->market_cap / 1000000, 2) . 'M';
        } elseif ($this->market_cap >= 1000) {
            return number_format($this->market_cap / 1000, 2) . 'K';
        }
        return number_format($this->market_cap, 2);
    }

    public function getPriceChangeColorAttribute()
    {
        return $this->price_change_percentage_24h >= 0 ? 'text-green-500' : 'text-red-500';
    }

    public function getPriceChangeIconAttribute()
    {
        return $this->price_change_percentage_24h >= 0 ? '▲' : '▼';
    }

    // Helper Methods
    public function updatePrice($price, $change24h = null, $changePercentage24h = null)
    {
        $this->current_price = $price;
        if ($change24h !== null) {
            $this->price_change_24h = $change24h;
        }
        if ($changePercentage24h !== null) {
            $this->price_change_percentage_24h = $changePercentage24h;
        }
        $this->last_updated = now();
        $this->save();

        // Update all trading pairs that use this currency
        $this->baseTradingPairs()->update(['current_price' => $price, 'last_price_update' => now()]);
    }

    public function canTrade($type = 'spot')
    {
        if (!$this->is_active) return false;

        switch ($type) {
            case 'spot':
                return $this->spot_trading_enabled;
            case 'futures':
                return $this->futures_trading_enabled;
            case 'binary':
                return $this->binary_trading_enabled;
            default:
                return false;
        }
    }

    public function canDeposit()
    {
        return $this->is_active && $this->deposits_enabled;
    }

    public function canWithdraw()
    {
        return $this->is_active && $this->withdrawals_enabled;
    }

    public function getMinimumTradeAmount($type = 'spot')
    {
        return $this->min_trade_amount;
    }

    public function getMaximumTradeAmount($type = 'spot')
    {
        return $this->max_trade_amount;
    }

    public function getTradingFee()
    {
        return $this->trading_fee_percentage;
    }

    public function getWithdrawalFee()
    {
        return $this->withdrawal_fee;
    }

    public static function getStableCoin()
    {
        return self::where('symbol', 'USDT')->first() ?? 
               self::where('symbol', 'USDC')->first() ?? 
               self::where('symbol', 'USD')->first();
    }

    public static function getBitcoin()
    {
        return self::where('symbol', 'BTC')->first();
    }

    public static function getEthereum()
    {
        return self::where('symbol', 'ETH')->first();
    }
}