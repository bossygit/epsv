<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SchoolPeriod::class)->orderBy('sequence');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public static function currentYear(): ?self
    {
        return static::query()->where('is_current', true)->first()
            ?? static::query()->orderByDesc('start_date')->first();
    }

    /** Période en cours (dates couvrant aujourd'hui), sinon la première ouverte. */
    public function currentPeriod(): ?SchoolPeriod
    {
        $today = now()->toDateString();

        return $this->periods()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first()
            ?? $this->periods()->where('status', SchoolPeriod::STATUS_OPEN)->first();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
