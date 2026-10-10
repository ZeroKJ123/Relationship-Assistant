<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_auth_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('telegram_auth_locks')->insert(['id' => 1]);
        Schema::create('telegram_login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('start_hash', 64)->unique();
            $table->char('browser_hash', 64);
            $table->char('confirmation_hash', 64)->nullable()->unique();
            $table->string('status', 24);
            $table->text('identity')->nullable();
            $table->string('verification_code', 6);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('telegram_update_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->char('payload_hash', 64);
            $table->text('reply')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
        Schema::create('telegram_callback_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('operation', 32);
            $table->string('source_kind', 24);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('source_version');
            $table->uuid('operation_key');
            $table->string('stage', 24);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_callback_actions');
        Schema::dropIfExists('telegram_update_receipts');
        Schema::dropIfExists('telegram_login_attempts');
        Schema::dropIfExists('telegram_auth_locks');
    }
};
