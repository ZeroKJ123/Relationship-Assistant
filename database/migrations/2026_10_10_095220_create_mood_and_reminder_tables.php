<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couple_members', function (Blueprint $table): void {
            $table->boolean('mood_schedule_enabled')->default(true);
            $table->unsignedInteger('mood_schedule_version')->default(0);
        });
        Schema::create('mood_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('expires_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['couple_id', 'user_id', 'scheduled_at']);
            $table->index(['couple_id', 'user_id', 'expires_at']);
        });
        Schema::create('mood_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('mood_prompt_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->string('source', 20);
            $table->uuid('operation_key');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['couple_id', 'user_id', 'recorded_at', 'id']);
        });
        Schema::create('partner_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->text('text');
            $table->timestamp('due_at');
            $table->string('status', 20);
            $table->unsignedInteger('version');
            $table->uuid('operation_key');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();
            $table->unique(['author_id', 'operation_key']);
            $table->index(['status', 'due_at', 'id']);
            $table->index(['couple_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_reminders');
        Schema::dropIfExists('mood_entries');
        Schema::dropIfExists('mood_prompts');
        Schema::table('couple_members', function (Blueprint $table): void {
            $table->dropColumn(['mood_schedule_enabled', 'mood_schedule_version']);
        });
    }
};
