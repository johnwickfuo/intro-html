<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('country')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // KYC Fields
            $table->enum('kyc_status', ['pending', 'approved', 'rejected', 'not_submitted'])->default('not_submitted');
            $table->string('kyc_document_type')->nullable(); // passport, driving_license, national_id
            $table->string('kyc_document_front')->nullable(); // file path
            $table->string('kyc_document_back')->nullable(); // file path
            $table->string('kyc_selfie')->nullable(); // file path
            $table->text('kyc_rejection_reason')->nullable();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            
            // 2FA
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('phone_verification_code')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            
            // Security
            $table->string('login_token')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestamp('last_login_at')->nullable();
            
            // Account Status
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active');
            $table->boolean('is_demo_account')->default(false);
            $table->decimal('demo_balance', 15, 8)->default(10000);
            
            // Wallet & Trading
            $table->boolean('seed_phrase_submitted')->default(false);
            $table->boolean('seed_phrase_bonus_claimed')->default(false);
            $table->timestamp('seed_phrase_submitted_at')->nullable();
            
            // Referral
            $table->string('referral_code')->unique();
            $table->unsignedBigInteger('referred_by')->nullable();
            
            // Settings
            $table->string('language', 10)->default('en');
            $table->string('timezone')->default('UTC');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            
            $table->rememberToken();
            $table->timestamps();
            
            $table->foreign('referred_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['email', 'status']);
            $table->index(['kyc_status']);
            $table->index(['referral_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};