<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('image_url')->nullable();
            
            // Investment Terms
            $table->decimal('min_amount', 20, 8); // Minimum investment amount
            $table->decimal('max_amount', 20, 8); // Maximum investment amount
            $table->decimal('interest_rate', 8, 4); // Annual interest rate percentage
            $table->integer('duration_days'); // Investment duration in days
            $table->enum('interest_type', ['simple', 'compound'])->default('simple');
            $table->enum('payout_frequency', ['daily', 'weekly', 'monthly', 'maturity'])->default('maturity');
            
            // Supported Currencies
            $table->json('supported_currencies'); // Array of cryptocurrency IDs
            
            // Plan Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_renew_enabled')->default(false);
            $table->boolean('early_withdrawal_allowed')->default(false);
            $table->decimal('early_withdrawal_penalty', 5, 2)->default(0); // Penalty percentage
            $table->integer('max_investments_per_user')->nullable(); // Limit per user
            $table->decimal('total_investment_limit', 20, 8)->nullable(); // Total plan limit
            $table->decimal('current_total_invested', 20, 8)->default(0);
            
            // Risk and Features
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('guaranteed_returns')->default(false);
            $table->text('terms_and_conditions')->nullable();
            $table->json('features')->nullable(); // Additional features array
            
            // Admin Settings
            $table->integer('display_order')->default(0);
            $table->timestamp('launch_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            
            $table->index(['is_active', 'display_order']);
            $table->index(['min_amount', 'max_amount']);
            $table->index(['duration_days', 'interest_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_plans');
    }
};