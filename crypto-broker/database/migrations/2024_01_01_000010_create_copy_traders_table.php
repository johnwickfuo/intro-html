<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_traders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Trader Profile
            $table->string('trader_name');
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('country')->nullable();
            $table->integer('years_experience')->default(0);
            $table->json('trading_strategies')->nullable(); // Array of strategies
            
            // Performance Metrics
            $table->decimal('total_return_percentage', 10, 4)->default(0); // Overall return %
            $table->decimal('monthly_return_percentage', 10, 4)->default(0); // Monthly return %
            $table->decimal('weekly_return_percentage', 10, 4)->default(0); // Weekly return %
            $table->decimal('daily_return_percentage', 10, 4)->default(0); // Daily return %
            $table->decimal('win_rate', 5, 2)->default(0); // Win rate percentage
            $table->decimal('profit_factor', 8, 4)->default(0); // Profit factor
            $table->decimal('max_drawdown', 10, 4)->default(0); // Maximum drawdown %
            $table->decimal('sharpe_ratio', 8, 4)->default(0); // Risk-adjusted return
            $table->integer('total_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            
            // Copying Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('accepting_copiers')->default(true);
            $table->integer('max_copiers')->default(1000);
            $table->integer('current_copiers')->default(0);
            $table->decimal('min_copy_amount', 15, 8)->default(100);
            $table->decimal('max_copy_amount', 15, 8)->default(10000);
            $table->decimal('profit_sharing_percentage', 5, 2)->default(10); // Trader's cut
            $table->decimal('monthly_fee', 15, 8)->default(0); // Fixed monthly fee
            
            // Statistics Period
            $table->timestamp('stats_start_date'); // When performance tracking started
            $table->timestamp('last_trade_at')->nullable();
            $table->decimal('total_volume_traded', 20, 8)->default(0);
            $table->decimal('total_profit_generated', 20, 8)->default(0);
            $table->decimal('total_fees_earned', 20, 8)->default(0);
            
            // Ranking and Verification
            $table->integer('ranking')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Risk Management
            $table->decimal('risk_score', 5, 2)->default(5); // 1-10 scale
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->decimal('avg_trade_duration_hours', 10, 2)->default(0);
            $table->json('preferred_pairs')->nullable(); // Preferred trading pairs
            
            $table->timestamps();
            
            $table->index(['is_active', 'accepting_copiers']);
            $table->index(['ranking', 'total_return_percentage']);
            $table->index(['win_rate', 'total_trades']);
            $table->index(['risk_level', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_traders');
    }
};