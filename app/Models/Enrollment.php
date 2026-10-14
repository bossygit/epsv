<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription annuelle : lie un élève à une classe pour une année scolaire.
 * C'est la table pivot de l'historique scolaire.
 */
class Enrollment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LEFT = 'left';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'enrolled_on',
        'status',
        'left_on',
        'left_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
