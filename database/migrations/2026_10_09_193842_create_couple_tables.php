<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20);
            $table->date('started_on');
            $table->string('timezone', 64);
            $table->string('currency', 3);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
        Schema::create('couple_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->enum('slot', ['first', 'second']);
            $table->timestamp('joined_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['couple_id', 'slot']);
            $table->unique(['couple_id', 'user_id']);
            $table->index(['user_id', 'couple_id']);
        });
        Schema::create('couple_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['couple_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_invitations');
        Schema::dropIfExists('couple_members');
        Schema::dropIfExists('couples');
    }
};
