<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            // Flag de conclusão formal da lição (completion_percentage >= 100 não basta,
            // pois o usuário pode ter um progresso parcial alto mas nunca ter "finalizado").
            $table->boolean('completed')->default(false)->after('best_score');

            // XP total acumulado pelo usuário nesta lição (soma das tentativas corretas).
            $table->unsignedSmallInteger('xp_earned')->default(0)->after('completed');

            // Momento exato em que a lição foi marcada como concluída pela primeira vez.
            $table->timestamp('completed_at')->nullable()->after('xp_earned');

            $table->index(['user_id', 'completed']);
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'completed']);
            $table->dropColumn(['completed', 'xp_earned', 'completed_at']);
        });
    }
};
