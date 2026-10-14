<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bulletin d'une période. Les versions validées/verrouillées sont
 * conservées telles quelles : une régénération crée une nouvelle version.
 */
class ReportCard extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'student_id',
        'school_period_id',
        'version',
        'status',
        'file_path',
        'average',
        'rank',
        'class_size',
        'decision',
        'metadata',
        'generated_by',
        'generated_at',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'metadata' => 'array',
            'generated_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SchoolPeriod::class, 'school_period_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, [self::STATUS_VALIDATED, self::STATUS_LOCKED], true);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_GENERATED => 'Généré',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_LOCKED => 'Verrouillé',
            default => $status,
        };
    }
}
