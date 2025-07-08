<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('investment_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('cryptocurrency_id')->constrained()->onDelete('cascade');
            
            // Investment Details
            $table->string('investment_id')->unique();
            $table->decimal('amount', 20, 8); // Investment amount
            $table->decimal('interest_rate', 8, 4); // Locked-in interest rate
            $table->integer('duration_days'); // Locked-in duration
            $table->enum('interest_type', ['simple', 'compound']);
            $table->enum('payout_frequency', ['daily', 'weekly', 'monthly', 'maturity']);
            
            // Dates and Status
            $table->timestamp('start_date');
            $table->timestamp('maturity_date');
            $table->timestamp('last_payout_date')->nullable();
            $table->timestamp('next_payout_date')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled', 'withdrawn_early'])->default('active');
            
            // Financial Tracking
            $table->decimal('total_expected_return', 20, 8); // Total expected return
            $table->decimal('total_paid_out', 20, 8)->default(0); // Total amount paid so far
            $table->decimal('remaining_payout', 20, 8); // Remaining amount to be paid
            $table->decimal('current_value', 20, 8); // Current investment value
            $table->integer('payouts_completed')->default(0);
            $table->integer('total_payouts_expected');
            
            // Auto Renewal
            $table->boolean('auto_renew')->default(false);
            $table->integer('renewal_count')->default(0);
            $table->foreignId('renewed_from_investment_id')->nullable()->constrained('user_investments');
            
            // Early Withdrawal
            $table->boolean('early_withdrawal_requested')->default(false);
            $table->timestamp('withdrawal_requested_at')->nullable();
            $table->decimal('withdrawal_penalty', 20, 8)->default(0);
            $table->text('withdrawal_reason')->nullable();
            
            // Additional Data
            $table->json('payout_history')->nullable(); // Array of payout records
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['investment_plan_id', 'status']);
            $table->index(['maturity_date', 'status']);
            $table->index(['next_payout_date', 'status']);
            $table->index(['investment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_investments');
    }
};