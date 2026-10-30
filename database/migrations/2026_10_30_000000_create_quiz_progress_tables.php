<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quizzes', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->json('question_ids');
            $table->timestamps();
        });
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->date('daily_date')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->unsignedSmallInteger('length')->default(10);
            $table->json('question_ids');
            $table->json('players');
            $table->json('answers');
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'daily_date']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('daily_quizzes');
    }
};
