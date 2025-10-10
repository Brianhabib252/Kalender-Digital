<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('calendar_type', 16);
            $table->unsignedTinyInteger('gregorian_month')->nullable();
            $table->unsignedTinyInteger('gregorian_day')->nullable();
            $table->unsignedSmallInteger('gregorian_year')->nullable();
            $table->unsignedTinyInteger('hijri_month')->nullable();
            $table->unsignedTinyInteger('hijri_day')->nullable();
            $table->unsignedSmallInteger('hijri_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_holidays');
    }
};
