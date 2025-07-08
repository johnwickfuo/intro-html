<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cryptocurrency_id')->constrained()->onDelete('cascade');
            
            // Transaction Details
            $table->string('transaction_id')->unique();
            $table->enum('type', [
                'deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'binary_trade', 
                'futures_trade', 'investment', 'investment_return', 'copy_trade',
                'fee', 'bonus', 'referral_bonus', 'seed_phrase_bonus', 'transfer'
            ]);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'expired'])->default('pending');
            
            // Amount Details
            $table->decimal('amount', 20, 8); // Transaction amount
            $table->decimal('fee', 20, 8)->default(0); // Transaction fee
            $table->decimal('net_amount', 20, 8); // Amount after fees
            $table->decimal('exchange_rate', 20, 8)->default(1); // If currency conversion
            $table->foreignId('fee_currency_id')->nullable()->constrained('cryptocurrencies');
            
            // Balance Tracking
            $table->decimal('balance_before', 20, 8)->default(0);
            $table->decimal('balance_after', 20, 8)->default(0);
            
            // External Transaction Details
            $table->string('external_transaction_id')->nullable(); // Blockchain tx hash or payment ID
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->integer('confirmations')->default(0);
            $table->integer('required_confirmations')->default(3);
            
            // Reference Details
            $table->string('reference_type')->nullable(); // Type of related entity
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of related entity
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional transaction data
            
            // Payment Gateway (for fiat transactions)
            $table->string('payment_method')->nullable(); // stripe, paypal, bank_transfer, etc.
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Processing Details
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Admin Actions
            $table->boolean('requires_admin_approval')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('failure_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'type', 'status']);
            $table->index(['transaction_id']);
            $table->index(['external_transaction_id']);
            $table->index(['type', 'status', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};