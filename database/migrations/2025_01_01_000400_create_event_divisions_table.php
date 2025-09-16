<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('event_divisions')) {
            Schema::create('event_divisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['event_id', 'division_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_divisions');
    }
};

