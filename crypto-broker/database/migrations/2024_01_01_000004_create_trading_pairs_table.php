<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_pairs', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // BTC/USDT, ETH/USD, etc.
            $table->foreignId('base_currency_id')->constrained('cryptocurrencies')->onDelete('cascade');
            $table->foreignId('quote_currency_id')->constrained('cryptocurrencies')->onDelete('cascade');
            
            // Current Market Data
            $table->decimal('current_price', 20, 8)->default(0);
            $table->decimal('price_change_24h', 10, 4)->default(0);
            $table->decimal('price_change_percentage_24h', 10, 4)->default(0);
            $table->decimal('high_24h', 20, 8)->default(0);
            $table->decimal('low_24h', 20, 8)->default(0);
            $table->decimal('volume_24h', 20, 8)->default(0);
            $table->timestamp('last_price_update')->nullable();
            
            // Trading Configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('spot_trading_enabled')->default(true);
            $table->boolean('futures_trading_enabled')->default(true);
            $table->boolean('binary_trading_enabled')->default(true);
            
            // Trading Limits
            $table->decimal('min_trade_amount', 20, 8)->default(0.001);
            $table->decimal('max_trade_amount', 20, 8)->default(1000000);
            $table->decimal('min_price', 20, 8)->default(0.00000001);
            $table->decimal('max_price', 20, 8)->default(1000000);
            $table->integer('price_precision')->default(8); // Decimal places for price
            $table->integer('quantity_precision')->default(8); // Decimal places for quantity
            
            // Fees
            $table->decimal('maker_fee', 5, 4)->default(0.001); // 0.1%
            $table->decimal('taker_fee', 5, 4)->default(0.001); // 0.1%
            
            // Binary Trading Settings
            $table->decimal('binary_min_amount', 15, 8)->default(10);
            $table->decimal('binary_max_amount', 15, 8)->default(10000);
            $table->decimal('binary_payout_percentage', 5, 2)->default(85); // 85% payout
            $table->json('binary_timeframes')->nullable(); // [30, 60, 300, 900, 3600] seconds
            
            // Futures Trading Settings
            $table->integer('max_leverage')->default(100);
            $table->decimal('maintenance_margin', 5, 4)->default(0.005); // 0.5%
            $table->decimal('liquidation_fee', 5, 4)->default(0.005); // 0.5%
            
            $table->timestamps();
            
            $table->index(['symbol', 'is_active']);
            $table->index(['base_currency_id', 'quote_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_pairs');
    }
};