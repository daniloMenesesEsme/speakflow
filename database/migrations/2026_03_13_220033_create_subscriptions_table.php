<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            // active | canceled | expired | trialing
            $table->string('status', 20)->default('active');

            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable(); // null = lifetime/gratuito

            // Referência externa de pagamento (Stripe, PagSeguro, etc.)
            $table->string('payment_reference', 120)->nullable();

            // Notas internas (cancelamento, motivo, etc.)
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
