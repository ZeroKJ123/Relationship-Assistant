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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('login', 40)->nullable()->unique();
            $table->string('gender', 20)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('avatar_path')->nullable();
            $table->text('payment_details')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->orWhereNull('password')->exists()) {
            throw new LogicException('Restore a backup or supply real emails/passwords before reverting nullable account fields.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['login']);
            $table->dropColumn(['login', 'gender', 'locale', 'currency', 'avatar_path', 'payment_details', 'recovery_codes', 'is_admin']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
