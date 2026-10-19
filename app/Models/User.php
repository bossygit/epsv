<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Valeurs par défaut : un compte fraîchement créé est actif même si
     * la valeur n'est pas encore rechargée depuis la base de données.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Affectations pédagogiques (classe + matière). */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /** Classes dont l'utilisateur est titulaire (responsable). */
    public function mainTeacherClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'main_teacher_id');
    }

    /** Séances d'appel prises par cet utilisateur. */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'taken_by');
    }

    /** Conversations de la messagerie interne. */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_message_id')
            ->withTimestamps();
    }

    /** Messages envoyés par cet utilisateur. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Identifiants des classes accessibles selon les affectations pour une année.
     *
     * @return array<int>
     */
    public function assignedClassIds(?int $academicYearId = null): array
    {
        $query = $this->teacherAssignments();

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        $classIds = $query->pluck('class_id')->all();

        $mainTeacherIds = $this->mainTeacherClasses()
            ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($classIds, $mainTeacherIds)));
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
