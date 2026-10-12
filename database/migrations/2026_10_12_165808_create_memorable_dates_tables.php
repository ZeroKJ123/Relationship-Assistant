<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorable_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('title', 120);
            $table->date('occurs_on');
            $table->boolean('is_yearly');
            $table->timestamps();
            $table->unique(['couple_id', 'operation_key']);
        });
        Schema::create('memorable_date_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 40);
            $table->string('title', 120);
            $table->date('occurs_on');
            $table->unsignedTinyInteger('days_before');
            $table->unsignedSmallInteger('years')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'source_key', 'occurs_on', 'days_before'], 'memorable_date_notices_once');
        });
    }

    /** Откат удаляет добавленные парами даты и журнал напоминаний о них; даты начала отношений и свадьбы хранятся в паре и остаются. */
    public function down(): void
    {
        Schema::dropIfExists('memorable_date_notices');
        Schema::dropIfExists('memorable_dates');
    }
};
