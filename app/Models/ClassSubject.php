<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matière proposée dans une classe, avec son coefficient et son barème cible.
 */
class ClassSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'subject_id',
        'coefficient',
        'max_score',
        'is_graded',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'max_score' => 'decimal:2',
            'is_graded' => 'boolean',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Affectations des enseignants de la classe.
     *
     * Note : `teacher_assignments` identifie une affectation par le couple
     * (class_id, subject_id) — une clé composite non supportée directement
     * par les relations Eloquent. Cette relation retourne donc toutes les
     * affectations de la classe ; filtrer par matière côté application :
     * `$classSubject->classAssignments->where('subject_id', $classSubject->subject_id)`.
     */
    public function classAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id', 'class_id');
    }

    public function label(): string
    {
        return $this->subject->name.' (coef. '.rtrim(rtrim($this->coefficient, '0'), '.').')';
    }
}
