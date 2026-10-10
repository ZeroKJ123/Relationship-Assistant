<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->uuid('operation_key');
            $table->uuid('last_operation_key');
            $table->unsignedInteger('version');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['user_id', 'archived_at', 'id']);
        });
        Schema::create('guest_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->unique()->constrained()->restrictOnDelete();
            $table->json('guests_snapshot');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('date_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title', 120);
            $table->timestamp('starts_at');
            $table->text('description')->nullable();
            $table->string('dress_code', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_invitations');
        Schema::dropIfExists('guest_visits');
        Schema::dropIfExists('friends');
    }
};
