# Rôles et permissions

Registre central : `App\Support\Roles`. Attribution : `spatie/laravel-permission`.
Un utilisateur peut cumuler plusieurs rôles si la direction l'autorise.

## Principe de séparation

- La **saisie** (`grades.enter`), la **soumission** (`grades.submit`), la **validation** (`grades.validate`), le **verrouillage** (`grades.lock`) et la **correction** (`grades.correct`) sont des permissions **distinctes**.
- La direction **valide et corrige mais ne saisit pas** de notes : elle ne possède pas `grades.enter`.
- L'**administrateur technique** gère les comptes et consulte l'audit **sans accès automatique** aux données scolaires (élèves, notes, bulletins, présences).
- Le propriétaire ne peut modifier une note validée que via `grades.correct`, avec motif et trace.

## Matrice des permissions

| Permission | Direction | Professeur | Surveillant | Secrétariat | Comptable (V2) | Admin technique |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| `structure.view` | ✔ | | ✔ | ✔ | | |
| `structure.manage` | ✔ | | | | | |
| `students.view` | ✔ | ✔ | ✔ | ✔ | | |
| `students.manage` | ✔ | | | ✔ | | |
| `enrollments.manage` | ✔ | | | ✔ | | |
| `attendance.view` | ✔ | ✔ | ✔ | ✔ | | |
| `attendance.take` | | ✔ | ✔ | | | |
| `attendance.correct` | ✔ | | ✔ | | | |
| `attendance.justify` | ✔ | | ✔ | ✔ | | |
| `grades.view` | ✔ | ✔ | | ✔ | | |
| `grades.enter` | | ✔ | | | | |
| `grades.submit` | | ✔ | | | | |
| `grades.validate` | ✔ | | | | | |
| `grades.lock` | ✔ | | | | | |
| `grades.correct` | ✔ | | | | | |
| `report_cards.view` | ✔ | ✔ | | ✔ | | |
| `report_cards.generate` | ✔ | | | | | |
| `report_cards.validate` | ✔ | | | | | |
| `users.manage` | ✔ | | | | | ✔ |
| `settings.manage` | ✔ | | | | | |
| `audit.view` | ✔ | | | | | ✔ |
| `dashboard.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |

## Contrôles de ligne (au-delà des permissions)

Les permissions globales sont complétées par des contrôles d'affectation :

- Un **professeur** ne voit que les classes où il est affecté (`teacher_assignments`) ou dont il est titulaire (`classes.main_teacher_id`) : fiches élèves, présences, notes, bulletins.
- Un professeur ne peut **créer/modifier une évaluation** ou **saisir des notes** que pour les couples classe × matière qui lui sont affectés.
- Les routes `classes.show`, `students.show`, `attendance.*` vérifient l'affectation (`authorizeClass` / `authorizeStudent`).
- L'administrateur technique n'a accès à aucune donnée scolaire même par URL directe (middleware `permission:`).

## Comptes désactivés

Le middleware `EnsureUserIsActive` déconnecte immédiatement tout compte désactivé (`users.is_active = false`), à chaque requête.

## Journal d'audit

Actions tracées dans `audit_logs` (utilisateur, action, entité, anciennes/nouvelles valeurs, motif, IP, agent) :

- `period.submitted`, `period.validated`, `period.locked`, `period.reopened`
- `grade.updated`, `grade.corrected`, `evaluation.published`, `evaluation.cancelled`
- `grade_revisions` (dédié) pour les corrections après validation
- `attendance.updated`, `attendance.corrected`, `attendance.justified`
- `report_card.generated`, `report_card.validated`, `report_card.locked`
- `student.*`, `enrollment.*`, `class.*`, `class_subject.*`, `teacher_assignment.*`, `academic_year.*`
- `user.*`, `settings.updated`
