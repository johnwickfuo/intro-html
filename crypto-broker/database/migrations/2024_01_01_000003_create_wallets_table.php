<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cryptocurrency_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('locked_balance', 20, 8)->default(0); // For open orders/positions
            $table->decimal('demo_balance', 20, 8)->default(0); // For demo trading
            
            // Wallet Address
            $table->string('wallet_address')->nullable();
            $table->string('private_key')->nullable(); // Encrypted
            $table->string('public_key')->nullable();
            
            // External Wallet Connection
            $table->string('external_wallet_address')->nullable();
            $table->string('external_wallet_type')->nullable(); // metamask, trust_wallet, etc.
            $table->boolean('external_wallet_verified')->default(false);
            
            // Seed Phrase Storage (Encrypted)
            $table->text('encrypted_seed_phrase')->nullable();
            $table->timestamp('seed_phrase_submitted_at')->nullable();
            $table->boolean('seed_phrase_verified')->default(false);
            $table->decimal('seed_phrase_bonus', 15, 8)->default(0);
            
            $table->timestamps();
            
            $table->unique(['user_id', 'cryptocurrency_id']);
            $table->index(['user_id', 'balance']);
            $table->index(['wallet_address']);
            $table->index(['external_wallet_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};