<?php

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Encapsula a lógica de níveis do CEFR (Common European Framework of Reference).
 * Imutável — use os métodos estáticos para criar instâncias.
 */
final class CefrLevel
{
    // Mínimo de XP para entrar em cada nível
    private const XP_THRESHOLDS = [
        'A1' => 0,
        'A2' => 500,
        'B1' => 1_500,
        'B2' => 3_500,
        'C1' => 7_000,
        'C2' => 13_000,
    ];

    // Descrição do perfil de competência por nível
    private const DESCRIPTIONS = [
        'A1' => 'Iniciante — reconhece palavras e frases muito básicas.',
        'A2' => 'Elementar — comunica-se em situações simples e rotineiras.',
        'B1' => 'Intermediário — lida com situações em áreas familiares.',
        'B2' => 'Intermediário avançado — interage com fluência relativa.',
        'C1' => 'Avançado — expressa-se de forma fluente e espontânea.',
        'C2' => 'Proficiente — compreende e expressa com precisão e nuance.',
    ];

    // Dificuldade de progressão relativa entre níveis (multiplica o XP necessário)
    private const PROGRESSION_WEIGHT = [
        'A1' => 1.0,
        'A2' => 1.2,
        'B1' => 1.5,
        'B2' => 1.8,
        'C1' => 2.2,
        'C2' => null, // topo
    ];

    private function __construct(private readonly string $code) {}

    // ─── Factory ────────────────────────────────────────────────────────────

    public static function fromCode(string $code): self
    {
        $code = strtoupper(trim($code));

        if (!array_key_exists($code, self::XP_THRESHOLDS)) {
            throw new InvalidArgumentException("Nível CEFR inválido: '{$code}'.");
        }

        return new self($code);
    }

    public static function fromXp(int $xp): self
    {
        $resolved = 'A1';

        foreach (self::XP_THRESHOLDS as $level => $threshold) {
            if ($xp >= $threshold) {
                $resolved = $level;
            }
        }

        return new self($resolved);
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function code(): string
    {
        return $this->code;
    }

    public function description(): string
    {
        return self::DESCRIPTIONS[$this->code];
    }

    public function minXp(): int
    {
        return self::XP_THRESHOLDS[$this->code];
    }

    public function maxXp(): ?int
    {
        $codes = array_keys(self::XP_THRESHOLDS);
        $idx   = array_search($this->code, $codes);

        if ($idx === false || $idx >= count($codes) - 1) {
            return null; // C2 não tem teto
        }

        return self::XP_THRESHOLDS[$codes[$idx + 1]];
    }

    public function xpRange(): int
    {
        return ($this->maxXp() ?? ($this->minXp() + 10_000)) - $this->minXp();
    }

    public function progressionWeight(): float
    {
        return self::PROGRESSION_WEIGHT[$this->code] ?? 1.0;
    }

    public function isTop(): bool
    {
        return $this->code === 'C2';
    }

    public function next(): ?self
    {
        $codes = array_keys(self::XP_THRESHOLDS);
        $idx   = array_search($this->code, $codes);

        if ($idx === false || $idx >= count($codes) - 1) {
            return null;
        }

        return new self($codes[$idx + 1]);
    }

    public function previous(): ?self
    {
        $codes = array_keys(self::XP_THRESHOLDS);
        $idx   = array_search($this->code, $codes);

        if ($idx === false || $idx <= 0) {
            return null;
        }

        return new self($codes[$idx - 1]);
    }

    public function numericRank(): int
    {
        return array_search($this->code, array_keys(self::XP_THRESHOLDS)) + 1;
    }

    /**
     * Percentual de progresso dentro do nível atual, baseado em XP atual.
     */
    public function progressPercentage(int $currentXp): float
    {
        $xpInLevel = max(0, $currentXp - $this->minXp());
        $range     = $this->xpRange();

        return min(100.0, round(($xpInLevel / $range) * 100, 2));
    }

    /**
     * Quantos XP faltam para passar ao próximo nível.
     */
    public function xpToNextLevel(int $currentXp): ?int
    {
        if ($this->isTop()) {
            return null;
        }

        return max(0, $this->maxXp() - $currentXp);
    }

    // ─── Helpers estáticos ──────────────────────────────────────────────────

    public static function allCodes(): array
    {
        return array_keys(self::XP_THRESHOLDS);
    }

    public static function allThresholds(): array
    {
        return self::XP_THRESHOLDS;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function isHigherThan(self $other): bool
    {
        return $this->numericRank() > $other->numericRank();
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
