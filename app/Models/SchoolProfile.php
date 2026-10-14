<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Profil de l'établissement (ligne unique en pratique).
 * Les paramètres métier configurables (barèmes, arrondis, seuils…)
 * sont stockés dans la colonne JSON `settings`.
 */
class SchoolProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'motto',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'logo_path',
        'director_name',
        'director_title',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * Paramètres par défaut de l'application.
     * Ils peuvent être surchargés par la colonne `settings`.
     */
    public static function defaultSettings(): array
    {
        return [
            'grading' => [
                'target_scale' => 20,          // barème cible d'affichage
                'decimals' => 2,               // précision d'affichage
                'rounding' => 'half_up',       // half_up | half_even | floor
                'pass_threshold' => 10,        // seuil de réussite
                'ranking_enabled' => true,     // classement activé
                'missing_policy' => 'strict',  // strict | ignore : notes manquantes exclues du calcul ; strict = résultat marqué incomplet
                'absent_policy' => 'exclude',  // exclude | zero : élève absent à une évaluation
                'exempt_policy' => 'exclude',  // une dispense est toujours exclue du calcul
                'annual_weighting' => 'period_weight', // pondération annuelle par période
            ],
            'attendance' => [
                'alert_absences_count' => 3,   // alerte à partir de N absences
                'alert_window_days' => 30,     // sur une fenêtre glissante de N jours
            ],
            'grading_alerts' => [
                'grade_entry_delay_days' => 7, // alerte si notes non saisies N jours après l'évaluation
            ],
        ];
    }

    /** Ligne de profil courante (créée à la volée si absente). */
    public static function current(): self
    {
        return static::query()->firstOrCreate(
            [],
            ['name' => 'École', 'country' => 'Congo', 'settings' => static::defaultSettings()],
        );
    }

    /** Paramètre de notation fusionné avec les valeurs par défaut. */
    public function gradingSetting(string $key): mixed
    {
        $defaults = static::defaultSettings()['grading'];
        $custom = $this->settings['grading'] ?? [];

        return $custom[$key] ?? $defaults[$key];
    }

    public function attendanceSetting(string $key): mixed
    {
        $defaults = static::defaultSettings()['attendance'];
        $custom = $this->settings['attendance'] ?? [];

        return $custom[$key] ?? $defaults[$key];
    }

    public function gradingAlertSetting(string $key): mixed
    {
        $defaults = static::defaultSettings()['grading_alerts'];
        $custom = $this->settings['grading_alerts'] ?? [];

        return $custom[$key] ?? $defaults[$key];
    }
}
