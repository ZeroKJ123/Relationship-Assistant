<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mood_entries', function (Blueprint $table): void {
            $table->string('sticker_path')->nullable();
            $table->string('sticker_format', 10)->nullable();
            $table->string('sticker_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mood_entries', function (Blueprint $table): void {
            $table->dropColumn(['sticker_path', 'sticker_format', 'sticker_status']);
        });
    }
};
