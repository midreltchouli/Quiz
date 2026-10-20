<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('quiz_questions_answered')->default(0);
            $table->timestamp('quiz_upgraded_at')->nullable();
            $table->string('quiz_checkout_session_id')->nullable();
            $table->unsignedInteger('quiz_checkout_amount')->nullable();
            $table->string('quiz_checkout_currency', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quiz_questions_answered', 'quiz_upgraded_at', 'quiz_checkout_session_id', 'quiz_checkout_amount', 'quiz_checkout_currency']);
        });
    }
};
