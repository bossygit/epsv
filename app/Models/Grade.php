<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Note d'un élève à une évaluation.
 * Statuts : missing (non saisie), entered (valeur saisie), absent (élève absent
 * à l'évaluation — distinct de zéro), exempt (dispensé).
 */
class Grade extends Model
{
    use HasFactory;

    public const STATUS_MISSING = 'missing';
    public const STATUS_ENTERED = 'entered';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXEMPT = 'exempt';

    protected $fillable = [
        'evaluation_id',
        'student_id',
        'value',
        'status',
        'entered_by',
        'entered_at',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'entered_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GradeRevision::class)->latest('created_at');
    }

    /** La note compte-t-elle dans le calcul ? */
    public function countsInAverage(): bool
    {
        return $this->status === self::STATUS_ENTERED
            && $this->value !== null
            && ! $this->evaluation->isCancelled();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_MISSING => 'Non saisie',
            self::STATUS_ENTERED => 'Saisie',
            self::STATUS_ABSENT => 'Absent(e)',
            self::STATUS_EXEMPT => 'Dispensé(e)',
            default => $status,
        };
    }
}
