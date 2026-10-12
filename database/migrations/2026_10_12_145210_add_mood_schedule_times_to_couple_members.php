<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Пустое значение означает расписание по умолчанию. */
    public function up(): void
    {
        Schema::table('couple_members', function (Blueprint $table): void {
            $table->json('mood_schedule_times')->nullable()->after('mood_schedule_version');
        });
    }

    /** Откат возвращает всем расписание по умолчанию: выбранное время теряется. */
    public function down(): void
    {
        Schema::table('couple_members', function (Blueprint $table): void {
            $table->dropColumn('mood_schedule_times');
        });
    }
};
