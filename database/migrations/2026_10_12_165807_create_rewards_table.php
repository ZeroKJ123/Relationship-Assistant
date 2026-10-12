<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->uuid('last_operation_key');
            $table->string('title', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('heart_price');
            $table->unsignedInteger('version');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['user_id', 'archived_at']);
        });
    }

    /** Откат удаляет личные списки наград; уже созданные по ним просьбы и сердечки не затрагиваются. */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
