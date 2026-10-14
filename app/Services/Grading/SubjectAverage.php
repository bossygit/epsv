<?php

namespace App\Services\Grading;

/**
 * Résultat du calcul de moyenne d'une matière pour un élève sur une période.
 */
final class SubjectAverage
{
    /**
     * @param  array<int, array<string, mixed>>  $entries  Détail par évaluation.
     */
    public function __construct(
        public readonly ?float $value,        // moyenne brute (non arrondie), null si non calculable
        public readonly bool $complete,       // toutes les évaluations publiées ont une note exploitable
        public readonly float $totalWeight,   // somme des coefficients pris en compte
        public readonly int $missingCount,    // évaluations sans note saisie
        public readonly int $absentCount,     // absences à l'évaluation (exclues du calcul)
        public readonly int $exemptCount,     // dispenses (toujours exclues)
        public readonly int $gradedCount,     // évaluations prises en compte dans le calcul
        public readonly array $entries = [],
    ) {}

    public function hasValue(): bool
    {
        return $this->value !== null && $this->gradedCount > 0;
    }

    /** La moyenne est-elle exploitable pour la moyenne générale ? */
    public function isUsable(string $missingPolicy): bool
    {
        if (! $this->hasValue()) {
            return false;
        }

        return $missingPolicy === 'ignore' || $this->complete;
    }
}
