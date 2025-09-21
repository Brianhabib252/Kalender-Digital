<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_change_logs', function (Blueprint $table): void {
            $table->id();
            // The user whose record was changed
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The actor who made the change (admin)
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 100); // e.g., update_user
            $table->json('changes')->nullable(); // { field: [old, new], ... }
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_change_logs');
    }
};

