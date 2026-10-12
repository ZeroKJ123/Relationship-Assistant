<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streak_restores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('restored_on');
            $table->timestamps();
            $table->unique(['couple_id', 'restored_on']);
        });
    }

    /** Откат удаляет восстановленные дни: серии, державшиеся на них, прервутся. */
    public function down(): void
    {
        Schema::dropIfExists('streak_restores');
    }
};
