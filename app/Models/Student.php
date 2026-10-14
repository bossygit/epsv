<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_GRADUATED = 'graduated';

    public const GENDER_M = 'M';
    public const GENDER_F = 'F';

    protected $fillable = [
        'matricule',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'birth_place',
        'nationality',
        'address',
        'photo_path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->last_name.' '.$this->first_name);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot(['relationship', 'is_primary', 'is_emergency_contact'])
            ->withTimestamps();
    }

    public function primaryGuardian(): ?Guardian
    {
        return $this->guardians()->wherePivot('is_primary', true)->first()
            ?? $this->guardians()->first();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    /** Inscription pour une année donnée (par défaut l'année courante). */
    public function enrollmentFor(?AcademicYear $year = null): ?Enrollment
    {
        $year ??= AcademicYear::currentYear();

        if (! $year) {
            return null;
        }

        return $this->enrollments()
            ->where('academic_year_id', $year->id)
            ->with('schoolClass')
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }
}
