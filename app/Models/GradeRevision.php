<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'une correction de note (valeur avant/après, auteur, motif).
 * Une note validée ne peut être modifiée que via ce mécanisme.
 */
class GradeRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'grade_id',
        'old_value',
        'new_value',
        'old_status',
        'new_status',
        'reason',
        'revised_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function reviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
