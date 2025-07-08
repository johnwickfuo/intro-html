<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->string('group')->default('general'); // general, trading, security, etc.
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be accessed by frontend
            $table->boolean('is_editable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['key']);
            $table->index(['group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};