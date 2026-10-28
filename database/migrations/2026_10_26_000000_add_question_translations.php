<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('translations')->nullable();
            $table->string('bank_key')->nullable()->unique();
            $table->string('category')->nullable();
            $table->string('source_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['bank_key']);
            $table->dropColumn(['translations', 'bank_key', 'category', 'source_url']);
        });
    }
};
