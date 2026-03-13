<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            // Contador diário de lições geradas por IA
            $table->unsignedSmallInteger('lesson_generation')
                ->default(0)
                ->after('voice_messages');
        });
    }

    public function down(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            $table->dropColumn('lesson_generation');
        });
    }
};
