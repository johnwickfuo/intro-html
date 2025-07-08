<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binary_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            
            // Trade Details
            $table->string('trade_id')->unique();
            $table->enum('direction', ['call', 'put']); // call = up, put = down
            $table->decimal('amount', 15, 8); // Trade amount
            $table->integer('duration_seconds'); // Trade duration
            $table->decimal('entry_price', 20, 8); // Price at trade start
            $table->decimal('exit_price', 20, 8)->nullable(); // Price at trade end
            $table->timestamp('entry_time');
            $table->timestamp('expiry_time');
            $table->timestamp('exit_time')->nullable();
            
            // Payout Calculation
            $table->decimal('payout_percentage', 5, 2); // e.g., 85%
            $table->decimal('potential_profit', 15, 8); // Calculated profit if win
            $table->decimal('actual_profit', 15, 8)->default(0); // Actual profit/loss
            
            // Trade Status
            $table->enum('status', ['active', 'won', 'lost', 'cancelled', 'refunded'])->default('active');
            $table->enum('result', ['pending', 'win', 'loss', 'tie'])->default('pending');
            $table->boolean('is_demo')->default(false);
            
            // Additional Data
            $table->json('price_history')->nullable(); // Store price movements during trade
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['trading_pair_id', 'entry_time']);
            $table->index(['expiry_time', 'status']);
            $table->index(['trade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binary_trades');
    }
};