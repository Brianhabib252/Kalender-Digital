<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_change_logs', function (Blueprint $table): void {
            $table->id();
            // The event being changed; nullable so logs remain after event deletion
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            // The actor (user) who made the change
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 100); // create_event | update_event | delete_event
            $table->json('changes')->nullable(); // { field: [old, new], ... }
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_change_logs');
    }
};

