<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
    ];

    protected $casts = [
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens'      => 'integer',
        'estimated_cost'    => 'float',
        'created_at'        => 'datetime',
    ];

    // ─── Pricing por modelo (USD por token) ──────────────────────────────────

    public const PRICING = [
        'gpt-4o-mini' => [
            'input'  => 0.0000006,   // $0.60  / 1M tokens
            'output' => 0.0000024,   // $2.40  / 1M tokens
        ],
        'gpt-4o' => [
            'input'  => 0.000005,    // $5.00  / 1M tokens
            'output' => 0.000015,    // $15.00 / 1M tokens
        ],
        'gpt-3.5-turbo' => [
            'input'  => 0.0000005,   // $0.50  / 1M tokens
            'output' => 0.0000015,   // $1.50  / 1M tokens
        ],
    ];

    // ─── Helpers estáticos ───────────────────────────────────────────────────

    /**
     * Calcula o custo estimado com base no modelo e tokens utilizados.
     */
    public static function calculateCost(
        string $model,
        int    $promptTokens,
        int    $completionTokens,
    ): float {
        $pricing = self::PRICING[$model] ?? self::PRICING['gpt-4o-mini'];

        return round(
            ($promptTokens    * $pricing['input']) +
            ($completionTokens * $pricing['output']),
            8
        );
    }

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForModel($query, string $model)
    {
        return $query->where('model', $model);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at',  now()->year);
    }
}
