<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const THEME_ID_LENGTH = 64;

    /** Пустое значение означает общую тему приложения. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme', self::THEME_ID_LENGTH)->nullable()->after('locale');
        });
    }

    /** Откат возвращает всем общую тему: личный выбор теряется. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }
};
