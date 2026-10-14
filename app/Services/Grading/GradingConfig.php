<?php

namespace App\Services\Grading;

use App\Models\SchoolProfile;

/**
 * Paramètres de notation, lus depuis le profil de l'établissement
 * et fusionnés avec les valeurs par défaut. Objet immuable.
 */
final class GradingConfig
{
    public function __construct(
        public readonly float $targetScale = 20.0,
        public readonly int $decimals = 2,
        public readonly string $rounding = 'half_up',       // half_up | half_even | floor
        public readonly float $passThreshold = 10.0,
        public readonly bool $rankingEnabled = true,
        public readonly string $missingPolicy = 'strict',   // strict | ignore
        public readonly string $absentPolicy = 'exclude',   // exclude | zero
        public readonly string $annualWeighting = 'period_weight',
    ) {}

    public static function fromProfile(?SchoolProfile $profile = null): self
    {
        $profile ??= SchoolProfile::current();

        return new self(
            targetScale: (float) $profile->gradingSetting('target_scale'),
            decimals: (int) $profile->gradingSetting('decimals'),
            rounding: (string) $profile->gradingSetting('rounding'),
            passThreshold: (float) $profile->gradingSetting('pass_threshold'),
            rankingEnabled: (bool) $profile->gradingSetting('ranking_enabled'),
            missingPolicy: (string) $profile->gradingSetting('missing_policy'),
            absentPolicy: (string) $profile->gradingSetting('absent_policy'),
            annualWeighting: (string) $profile->gradingSetting('annual_weighting'),
        );
    }

    /** Arrondi selon la règle configurée. */
    public function round(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $mode = match ($this->rounding) {
            'half_even' => PHP_ROUND_HALF_EVEN,
            'floor' => PHP_ROUND_HALF_DOWN,
            default => PHP_ROUND_HALF_UP,
        };

        if ($this->rounding === 'floor') {
            $factor = 10 ** $this->decimals;

            return floor($value * $factor) / $factor;
        }

        return round($value, $this->decimals, $mode);
    }

    /** Formatage d'affichage (virgule décimale à la française). */
    public function format(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, $this->decimals, ',', ' ');
    }
}
