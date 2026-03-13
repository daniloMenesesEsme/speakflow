<?php

namespace App\Models;

use App\ValueObjects\CefrLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'native_language',
        'target_language',
        'level',
        'daily_goal_minutes',
        'total_xp',
        'streak_days',
        'last_study_date',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_study_date'   => 'date',
            'password'          => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'name'            => $this->name,
            'level'           => $this->level,
            'target_language' => $this->target_language,
        ];
    }

    public function pronunciationScores(): HasMany
    {
        return $this->hasMany(PronunciationScore::class);
    }

    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function phraseReviewStates(): HasMany
    {
        return $this->hasMany(PhraseReviewState::class);
    }

    public function exerciseAttempts(): HasMany
    {
        return $this->hasMany(UserExerciseAttempt::class);
    }

    public function dailyActivity(): HasMany
    {
        return $this->hasMany(UserDailyActivity::class);
    }

    public function weeklyXp(): HasMany
    {
        return $this->hasMany(UserWeeklyXp::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function aiCorrections(): HasMany
    {
        return $this->hasMany(AiCorrection::class);
    }

    public function aiVoiceMessages(): HasMany
    {
        return $this->hasMany(AiVoiceMessage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function generatedLessons(): HasMany
    {
        return $this->hasMany(GeneratedLesson::class);
    }

    public function usageLimits(): HasMany
    {
        return $this->hasMany(UsageLimit::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function getTotalStudyMinutesAttribute(): int
    {
        return $this->studySessions()->sum('duration_minutes');
    }

    /**
     * Retorna o objeto CefrLevel para o nível atual do usuário.
     */
    public function getCefrLevelAttribute(): CefrLevel
    {
        return CefrLevel::fromCode($this->level ?? 'A1');
    }

    /**
     * Rank numérico do nível (1 = A1 ... 6 = C2).
     */
    public function getLevelRankAttribute(): int
    {
        return $this->cefrLevel->numericRank();
    }

    /**
     * Percentual de progresso dentro do nível atual.
     */
    public function getLevelProgressAttribute(): float
    {
        return $this->cefrLevel->progressPercentage($this->total_xp);
    }

    /**
     * XP restante para o próximo nível.
     */
    public function getXpToNextLevelAttribute(): ?int
    {
        return $this->cefrLevel->xpToNextLevel($this->total_xp);
    }
}
