<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heart_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('balance');
            $table->timestamps();
        });
        Schema::create('heart_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('heart_wallet_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 120)->unique();
            $table->string('type', 30);
            $table->integer('requested_amount');
            $table->integer('amount');
            $table->bigInteger('balance_after');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('couple_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('duty_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['heart_wallet_id', 'type', 'created_at']);
            $table->index(['heart_wallet_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heart_transactions');
        Schema::dropIfExists('heart_wallets');
    }
};
