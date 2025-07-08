<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Copier
            $table->foreignId('copy_trader_id')->constrained()->onDelete('cascade'); // Trader being copied
            
            // Subscription Details
            $table->string('subscription_id')->unique();
            $table->decimal('copy_amount', 15, 8); // Amount allocated for copying
            $table->decimal('max_copy_amount', 15, 8); // Maximum amount per trade
            $table->decimal('remaining_balance', 15, 8); // Remaining balance for copying
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            
            // Risk Management
            $table->decimal('stop_loss_percentage', 5, 2)->default(0); // Stop loss at X% loss
            $table->decimal('take_profit_percentage', 5, 2)->default(0); // Take profit at X% gain
            $table->decimal('max_daily_loss', 15, 8)->default(0); // Max loss per day
            $table->decimal('max_open_trades', 5, 0)->default(10); // Max simultaneous trades
            $table->decimal('copy_ratio', 5, 4)->default(1); // Ratio to copy (0.1 = 10% of trader's position)
            
            // Performance Tracking
            $table->decimal('total_copied_amount', 20, 8)->default(0);
            $table->decimal('total_profit_loss', 20, 8)->default(0);
            $table->decimal('current_profit_loss', 20, 8)->default(0);
            $table->decimal('roi_percentage', 10, 4)->default(0);
            $table->integer('total_copied_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            
            // Fee Tracking
            $table->decimal('total_fees_paid', 20, 8)->default(0);
            $table->decimal('profit_share_paid', 20, 8)->default(0);
            $table->decimal('monthly_fees_paid', 20, 8)->default(0);
            $table->timestamp('last_fee_payment')->nullable();
            $table->timestamp('next_fee_payment')->nullable();
            
            // Timing
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_trade_copied_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            
            // Settings
            $table->json('copy_settings')->nullable(); // Additional copy settings
            $table->json('excluded_pairs')->nullable(); // Trading pairs to exclude
            $table->boolean('copy_spot_trades')->default(true);
            $table->boolean('copy_futures_trades')->default(true);
            $table->boolean('copy_binary_trades')->default(false);
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['copy_trader_id', 'status']);
            $table->index(['subscription_id']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_subscriptions');
    }
};