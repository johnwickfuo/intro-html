<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('room_id')->nullable(); // For group chats or specific chat rooms
            
            // Message Details
            $table->text('message');
            $table->enum('type', ['text', 'image', 'file', 'system', 'notification'])->default('text');
            $table->string('file_url')->nullable(); // For file attachments
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable();
            
            // Message Status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_support_message')->default(false);
            $table->boolean('is_system_message')->default(false);
            
            // Message Metadata
            $table->json('metadata')->nullable(); // Additional message data
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['room_id', 'created_at']);
            $table->index(['is_read', 'receiver_id']);
            $table->index(['is_support_message', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};