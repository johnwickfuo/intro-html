<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cryptocurrencies', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique(); // BTC, ETH, etc.
            $table->string('name'); // Bitcoin, Ethereum, etc.
            $table->string('logo_url')->nullable();
            $table->decimal('current_price', 20, 8)->default(0);
            $table->decimal('price_change_24h', 10, 4)->default(0);
            $table->decimal('price_change_percentage_24h', 10, 4)->default(0);
            $table->decimal('market_cap', 20, 2)->nullable();
            $table->decimal('volume_24h', 20, 2)->nullable();
            $table->integer('market_cap_rank')->nullable();
            $table->decimal('circulating_supply', 20, 2)->nullable();
            $table->decimal('total_supply', 20, 2)->nullable();
            $table->decimal('max_supply', 20, 2)->nullable();
            $table->decimal('ath', 20, 8)->nullable(); // All time high
            $table->decimal('atl', 20, 8)->nullable(); // All time low
            $table->timestamp('last_updated')->nullable();
            
            // Trading Configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('spot_trading_enabled')->default(true);
            $table->boolean('futures_trading_enabled')->default(true);
            $table->boolean('binary_trading_enabled')->default(true);
            $table->decimal('min_trade_amount', 15, 8)->default(0.00001);
            $table->decimal('max_trade_amount', 15, 8)->default(1000000);
            $table->decimal('trading_fee_percentage', 5, 4)->default(0.001); // 0.1%
            
            // Deposit/Withdrawal
            $table->boolean('deposits_enabled')->default(true);
            $table->boolean('withdrawals_enabled')->default(true);
            $table->decimal('min_deposit', 15, 8)->default(0.001);
            $table->decimal('min_withdrawal', 15, 8)->default(0.001);
            $table->decimal('withdrawal_fee', 15, 8)->default(0);
            $table->string('network')->nullable(); // BTC, ETH, BSC, etc.
            $table->string('contract_address')->nullable();
            $table->integer('required_confirmations')->default(3);
            
            $table->timestamps();
            
            $table->index(['symbol', 'is_active']);
            $table->index(['market_cap_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cryptocurrencies');
    }
};