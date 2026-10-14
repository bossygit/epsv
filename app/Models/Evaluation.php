<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Évaluation d'une matière dans une classe pour une période.
 * Le barème (max_score) peut différer d'une évaluation à l'autre.
 */
class Evaluation extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'class_subject_id',
        'school_period_id',
        'name',
        'type',
        'evaluation_date',
        'max_score',
        'coefficient',
        'status',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'max_score' => 'decimal:2',
            'coefficient' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SchoolPeriod::class, 'school_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
