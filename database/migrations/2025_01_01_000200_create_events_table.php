<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->boolean('all_day')->default(false);
                // Recurrence (optional)
                $table->string('recurrence_type')->nullable(); // weekly|monthly
                $table->json('recurrence_rule')->nullable();   // {"days":[1,2],"interval":1} or {"month_days":[1],"interval":1}
                $table->date('recurrence_until')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['start_at', 'end_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
