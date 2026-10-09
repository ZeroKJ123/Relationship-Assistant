<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economy_settings_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('settings');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('economy_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('current_version_id')->nullable()->constrained('economy_settings_versions')->restrictOnDelete();
        });
        DB::table('economy_settings')->insert(['id' => 1, 'current_version_id' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('economy_settings');
        Schema::dropIfExists('economy_settings_versions');
    }
};
