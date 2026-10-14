<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Séance d'appel : une classe, une date, un créneau.
 */
class AttendanceSession extends Model
{
    use HasFactory;

    public const SLOT_MORNING = 'matin';
    public const SLOT_AFTERNOON = 'apres_midi';
    public const SLOT_FULLDAY = 'journee';

    protected $fillable = [
        'class_id',
        'session_date',
        'slot',
        'taken_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public static function slotLabel(string $slot): string
    {
        return match ($slot) {
            self::SLOT_MORNING => 'Matin',
            self::SLOT_AFTERNOON => 'Après-midi',
            default => 'Journée',
        };
    }
}
