<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('operation', 160);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamps();
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_admin_id');
        });
        Schema::dropIfExists('admin_audits');
    }
};
