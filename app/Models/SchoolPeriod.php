<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Période scolaire (trimestre ou semestre).
 * Cycle de vie : open → submitted → validated → locked.
 */
class SchoolPeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SUBMITTED,
        self::STATUS_VALIDATED,
        self::STATUS_LOCKED,
    ];

    protected $fillable = [
        'academic_year_id',
        'name',
        'sequence',
        'type',
        'start_date',
        'end_date',
        'weight',
        'status',
        'validated_by',
        'validated_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'weight' => 'decimal:2',
            'validated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** Les notes peuvent-elles encore être saisies/modifiées librement ? */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN], true);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isValidated(): bool
    {
        return in_array($this->status, [self::STATUS_VALIDATED, self::STATUS_LOCKED], true);
    }

    public function label(): string
    {
        return $this->name.' — '.$this->academicYear->name;
    }
}
