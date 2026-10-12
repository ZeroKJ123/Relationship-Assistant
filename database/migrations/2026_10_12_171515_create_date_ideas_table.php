<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_ideas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('title', 120);
            $table->timestamps();
            $table->unique(['couple_id', 'operation_key']);
        });
    }

    /** Откат удаляет идеи свиданий; уже отправленные приглашения остаются. */
    public function down(): void
    {
        Schema::dropIfExists('date_ideas');
    }
};
