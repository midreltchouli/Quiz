<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('font_family', 20)->default('system');
            $table->string('text_size', 10)->default('normal');
            $table->boolean('quiz_notifications')->default(true);
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['font_family', 'text_size', 'quiz_notifications', 'avatar_path']);
        });
    }
};
