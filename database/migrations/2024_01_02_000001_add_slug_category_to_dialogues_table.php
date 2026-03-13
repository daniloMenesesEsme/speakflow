<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos para organização de diálogos por tema padronizado (slug)
     * e categoria, permitindo busca offline eficiente por tópico.
     */
    public function up(): void
    {
        Schema::table('dialogues', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('topic');
            $table->string('topic_category', 50)->nullable()->after('slug');
            $table->integer('estimated_minutes')->default(5)->after('topic_category');
            $table->integer('total_turns')->default(0)->after('estimated_minutes');

            $table->index('slug');
            $table->index('topic_category');
        });
    }

    public function down(): void
    {
        Schema::table('dialogues', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['topic_category']);
            $table->dropColumn(['slug', 'topic_category', 'estimated_minutes', 'total_turns']);
        });
    }
};
