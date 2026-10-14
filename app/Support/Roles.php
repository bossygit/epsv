<?php

namespace App\Support;

/**
 * Registre central des rôles et permissions de l'application.
 *
 * Principes :
 *  - Le propriétaire/direction ne peut PAS saisir de notes : la saisie
 *    (grades.enter) et la correction (grades.correct) sont des permissions
 *    distinctes, tout comme la validation (grades.validate).
 *  - L'administrateur technique gère les comptes et l'audit mais n'accède
 *    pas automatiquement aux données scolaires confidentielles.
 */
final class Roles
{
    public const DIRECTION = 'direction';
    public const PROFESSEUR = 'professeur';
    public const SURVEILLANT = 'surveillant';
    public const SECRETARIAT = 'secretariat';
    public const COMPTABLE = 'comptable';
    public const ADMIN_TECHNIQUE = 'admin_technique';

    public const LABELS = [
        self::DIRECTION => 'Direction / Propriétaire',
        self::PROFESSEUR => 'Professeur',
        self::SURVEILLANT => 'Surveillant',
        self::SECRETARIAT => 'Secrétariat',
        self::COMPTABLE => 'Comptable',
        self::ADMIN_TECHNIQUE => 'Administrateur technique',
    ];

    public const PERMISSIONS = [
        // Structure scolaire
        'structure.view' => 'Consulter la structure (années, classes, matières…)',
        'structure.manage' => 'Gérer la structure scolaire',

        // Élèves et inscriptions
        'students.view' => 'Consulter les élèves',
        'students.manage' => 'Créer et modifier les élèves',
        'enrollments.manage' => 'Gérer les inscriptions',

        // Présences
        'attendance.view' => 'Consulter les présences',
        'attendance.take' => 'Faire l\'appel',
        'attendance.correct' => 'Corriger un appel (avec motif)',
        'attendance.justify' => 'Justifier une absence ou un retard',

        // Notes et évaluations
        'grades.view' => 'Consulter les notes',
        'grades.enter' => 'Saisir les notes',
        'grades.submit' => 'Soumettre les résultats d\'une période',
        'grades.validate' => 'Valider les résultats d\'une période',
        'grades.lock' => 'Verrouiller une période',
        'grades.correct' => 'Corriger une note validée (avec motif)',

        // Bulletins
        'report_cards.view' => 'Consulter les bulletins',
        'report_cards.generate' => 'Générer les bulletins',
        'report_cards.validate' => 'Valider et verrouiller les bulletins',

        // Administration
        'users.manage' => 'Gérer les comptes utilisateurs',
        'settings.manage' => 'Gérer les paramètres de l\'école',
        'audit.view' => 'Consulter le journal d\'audit',
        'dashboard.view' => 'Accéder au tableau de bord',
    ];

    /** @return array<string, array<int, string>> */
    public static function rolePermissions(): array
    {
        return [
            self::DIRECTION => [
                'structure.view', 'structure.manage',
                'students.view', 'students.manage', 'enrollments.manage',
                'attendance.view', 'attendance.correct', 'attendance.justify',
                'grades.view', 'grades.validate', 'grades.lock', 'grades.correct',
                'report_cards.view', 'report_cards.generate', 'report_cards.validate',
                'users.manage', 'settings.manage', 'audit.view', 'dashboard.view',
            ],

            self::PROFESSEUR => [
                'students.view',
                'attendance.view', 'attendance.take',
                'grades.view', 'grades.enter', 'grades.submit',
                'report_cards.view',
                'dashboard.view',
            ],

            self::SURVEILLANT => [
                'structure.view',
                'students.view',
                'attendance.view', 'attendance.take', 'attendance.correct', 'attendance.justify',
                'dashboard.view',
            ],

            self::SECRETARIAT => [
                'structure.view',
                'students.view', 'students.manage', 'enrollments.manage',
                'attendance.view', 'attendance.justify',
                'grades.view',
                'report_cards.view',
                'dashboard.view',
            ],

            self::COMPTABLE => [
                'dashboard.view',
            ],

            self::ADMIN_TECHNIQUE => [
                'users.manage',
                'audit.view',
                'dashboard.view',
            ],
        ];
    }
}
