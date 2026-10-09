<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['expense_categories', 'request_categories'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 100);
                $table->unsignedSmallInteger('sort_order');
                $table->timestamps();
            });
        }
        Schema::create('request_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->foreignId('request_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });
        Schema::create('settlement_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->text('name');
            $table->text('details');
            $table->json('options');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->index(['user_id', 'sort_order']);
        });
        Schema::table('request_conditions', function (Blueprint $table): void {
            $table->string('method_kind', 20)->nullable();
            $table->string('method_name', 100)->nullable();
            $table->string('sushi_size', 20)->nullable();
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->foreignId('request_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('template_name', 150)->nullable();
            $table->string('category_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('request_conditions', function (Blueprint $table): void {
            $table->dropColumn(['method_kind', 'method_name', 'sushi_size']);
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('request_template_id');
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropColumn(['template_name', 'category_name']);
        });
        Schema::dropIfExists('settlement_methods');
        Schema::dropIfExists('request_templates');
        Schema::dropIfExists('request_categories');
        Schema::dropIfExists('expense_categories');
    }
};
