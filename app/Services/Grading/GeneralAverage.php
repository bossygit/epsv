<?php

namespace App\Services\Grading;

/**
 * Résultat du calcul de moyenne générale d'un élève pour une période.
 */
final class GeneralAverage
{
    /**
     * @param  array<int, SubjectAverage>  $subjectAverages  Indexé par class_subject_id.
     * @param  array<int, float>  $coefficients  Coefficient par class_subject_id.
     */
    public function __construct(
        public readonly ?float $value,
        public readonly bool $complete,
        public readonly float $totalCoefficient,
        public readonly array $subjectAverages = [],
        public readonly array $coefficients = [],
        public readonly int $missingSubjects = 0,
    ) {}

    public function hasValue(): bool
    {
        return $this->value !== null && $this->totalCoefficient > 0;
    }

    public function isComplete(): bool
    {
        return $this->complete && $this->missingSubjects === 0;
    }
}
