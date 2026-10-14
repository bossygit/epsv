<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_EXCUSED,
    ];

    protected $fillable = [
        'attendance_session_id',
        'student_id',
        'status',
        'minutes_late',
        'justification',
        'justified_by',
        'justified_at',
    ];

    protected function casts(): array
    {
        return [
            'justified_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function justifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justified_by');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => 'Présent',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_LATE => 'Retard',
            self::STATUS_EXCUSED => 'Absence justifiée',
            default => $status,
        };
    }
}
