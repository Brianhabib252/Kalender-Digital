<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'recurrence_type')) {
                    $table->string('recurrence_type')->nullable()->after('all_day');
                }
                if (!Schema::hasColumn('events', 'recurrence_rule')) {
                    $table->json('recurrence_rule')->nullable()->after('recurrence_type');
                }
                if (!Schema::hasColumn('events', 'recurrence_until')) {
                    $table->date('recurrence_until')->nullable()->after('recurrence_rule');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'recurrence_until')) {
                    $table->dropColumn('recurrence_until');
                }
                if (Schema::hasColumn('events', 'recurrence_rule')) {
                    $table->dropColumn('recurrence_rule');
                }
                if (Schema::hasColumn('events', 'recurrence_type')) {
                    $table->dropColumn('recurrence_type');
                }
            });
        }
    }
};

