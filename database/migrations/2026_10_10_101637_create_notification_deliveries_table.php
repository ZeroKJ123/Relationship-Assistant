<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 32);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('source_version')->nullable();
            $table->string('operation_key', 100);
            $table->string('status', 24);
            $table->unsignedInteger('attempts');
            $table->timestamp('available_at');
            $table->timestamp('expires_at');
            $table->timestamp('queued_until')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->unsignedBigInteger('telegram_account_id')->nullable();
            $table->string('chat_fingerprint', 64)->nullable();
            $table->string('message_id', 32)->nullable();
            $table->string('failure_reason', 48)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_id', 'operation_key']);
            $table->index(['status', 'available_at']);
            $table->index(['status', 'leased_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
