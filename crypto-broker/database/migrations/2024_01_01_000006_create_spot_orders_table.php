<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            
            // Order Details
            $table->string('order_id')->unique();
            $table->enum('type', ['market', 'limit', 'stop_loss', 'take_profit']);
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('quantity', 20, 8); // Amount to buy/sell
            $table->decimal('price', 20, 8)->nullable(); // Price per unit (null for market orders)
            $table->decimal('stop_price', 20, 8)->nullable(); // For stop orders
            $table->decimal('total_amount', 20, 8); // Total value of order
            
            // Execution Details
            $table->decimal('filled_quantity', 20, 8)->default(0);
            $table->decimal('remaining_quantity', 20, 8);
            $table->decimal('average_price', 20, 8)->default(0);
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->foreignId('fee_currency_id')->nullable()->constrained('cryptocurrencies');
            
            // Status and Timing
            $table->enum('status', ['pending', 'partial', 'filled', 'cancelled', 'rejected'])->default('pending');
            $table->boolean('is_demo')->default(false);
            $table->timestamp('placed_at');
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Additional Data
            $table->text('cancellation_reason')->nullable();
            $table->json('fills')->nullable(); // Array of partial fills
            $table->string('client_order_id')->nullable(); // For API orders
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['trading_pair_id', 'side', 'price']);
            $table->index(['order_id']);
            $table->index(['status', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_orders');
    }
};