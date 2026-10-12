<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('compliments')->nullable();
        });
        Schema::table('movies', function (Blueprint $table): void {
            $table->text('link')->nullable();
            $table->string('photo_path')->nullable();
        });
        Schema::create('movie_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('first_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('second_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->string('mode', 20);
            $table->string('status', 20);
            $table->boolean('first_ready')->default(false);
            $table->boolean('second_ready')->default(false);
            $table->json('pool');
            $table->json('votes');
            $table->foreignId('selected_movie_id')->nullable()->constrained('movies')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['first_user_id', 'operation_key']);
            $table->index(['couple_id', 'status', 'id']);
        });
        Schema::create('duty_reminder_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamps();
            $table->unique(['couple_id', 'user_id', 'scheduled_at']);
        });
        Schema::create('chore_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('first_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('second_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->text('description');
            $table->timestamp('due_at');
            $table->string('status', 20);
            $table->boolean('first_ready')->default(false);
            $table->boolean('second_ready')->default(false);
            $table->foreignId('executor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('partner_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['first_user_id', 'operation_key']);
            $table->index(['couple_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chore_draws');
        Schema::dropIfExists('duty_reminder_batches');
        Schema::dropIfExists('movie_choices');
        Schema::table('movies', function (Blueprint $table): void {
            $table->dropColumn(['link', 'photo_path']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('compliments');
        });
    }
};
