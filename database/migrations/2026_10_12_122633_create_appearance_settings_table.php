<?php

declare(strict_types=1);

use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appearance_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('revision');
            $table->json('settings');
            $table->timestamps();
        });
        DB::table('appearance_settings')->insert(['id' => AppearanceCatalog::SINGLETON_ID, 'revision' => 0, 'settings' => json_encode((new AppearanceCatalog)->defaults(), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appearance_settings');
    }
};
