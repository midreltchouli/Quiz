<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_suggestions', function (Blueprint $table) {
            $table->string('provider')->default('mistral');
        });
    }

    public function down(): void
    {
        Schema::table('question_suggestions', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
