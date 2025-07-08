<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('futures_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            
            // Position Details
            $table->string('position_id')->unique();
            $table->enum('side', ['long', 'short']);
            $table->decimal('size', 20, 8); // Position size
            $table->decimal('entry_price', 20, 8);
            $table->decimal('mark_price', 20, 8)->default(0); // Current mark price
            $table->decimal('liquidation_price', 20, 8)->nullable();
            $table->integer('leverage'); // Leverage multiplier
            
            // Margin Management
            $table->enum('margin_type', ['isolated', 'cross'])->default('isolated');
            $table->decimal('initial_margin', 20, 8); // Required margin to open
            $table->decimal('maintenance_margin', 20, 8); // Required margin to maintain
            $table->decimal('margin_balance', 20, 8); // Current margin balance
            $table->decimal('unrealized_pnl', 20, 8)->default(0); // Unrealized profit/loss
            $table->decimal('realized_pnl', 20, 8)->default(0); // Realized profit/loss
            
            // Risk Management
            $table->decimal('stop_loss_price', 20, 8)->nullable();
            $table->decimal('take_profit_price', 20, 8)->nullable();
            $table->decimal('margin_ratio', 10, 6)->default(0); // Current margin ratio
            $table->boolean('auto_add_margin')->default(false);
            
            // Status and Timing
            $table->enum('status', ['open', 'closed', 'liquidated', 'cancelled'])->default('open');
            $table->boolean('is_demo')->default(false);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_updated_at');
            
            // Closing Details
            $table->decimal('close_price', 20, 8)->nullable();
            $table->decimal('close_fee', 20, 8)->default(0);
            $table->text('close_reason')->nullable(); // manual, stop_loss, take_profit, liquidation
            
            // Additional Data
            $table->json('price_history')->nullable(); // Store key price points
            $table->decimal('funding_fee_paid', 20, 8)->default(0); // Cumulative funding fees
            $table->integer('days_held')->default(0);
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['trading_pair_id', 'side']);
            $table->index(['position_id']);
            $table->index(['status', 'opened_at']);
            $table->index(['liquidation_price', 'status']); // For liquidation monitoring
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futures_positions');
    }
};