<?php

namespace App\Services;

use App\Models\SchoolProfile;
use App\Models\Student;

/**
 * Génération des matricules élèves : PRÉFIXE-ANNÉE-NUMÉRO
 * (ex. EPSV-2026-0001). Le matricule est stable et ne change jamais.
 */
class MatriculeGenerator
{
    public function next(?string $yearLabel = null): string
    {
        $yearLabel ??= now()->format('Y');
        $prefix = $this->prefix();

        $base = sprintf('%s-%s-', $prefix, $yearLabel);

        $last = Student::withTrashed()
            ->where('matricule', 'like', $base.'%')
            ->orderByDesc('matricule')
            ->value('matricule');

        $sequence = 1;

        if ($last !== null) {
            $sequence = (int) substr($last, strlen($base)) + 1;
        }

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function prefix(): string
    {
        $profile = SchoolProfile::current();

        $candidate = $profile->short_name ?: $profile->name;

        // Garde uniquement les caractères alphanumériques, max 8.
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $candidate);

        return strtoupper(substr($clean ?: 'ECOLE', 0, 8));
    }
}
