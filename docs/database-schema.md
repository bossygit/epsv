# Schéma de base de données

24 migrations versionnées (`database/migrations`). MySQL en production (utf8mb4), SQLite en développement/tests.

## Principes de conception

- Identifiants stables (`bigint` auto-incrémentés), clés étrangères et index adaptés.
- Toute donnée scolaire est reliée à l'**année** (`academic_years`) et à la **période** (`school_periods`).
- Le nom de l'élève n'est jamais un identifiant : `matricule` unique et stable.
- Désactivation/archivage plutôt que suppression destructrice (soft-delete élèves, statuts).
- Documents privés hors de l'espace public.
- Les moyennes ne sont pas dupliquées dans plusieurs tables : recalcul via `GradeCalculator` ; seules les versions de bulletins conservent une copie (archivage volontaire).
- Contraintes d'unicité empêchant les doublons incohérents (ex. deux notes actives pour le même élève sur la même évaluation).

## Tables

### Identité et paramètres

| Table | Rôle | Colonnes clés |
| --- | --- | --- |
| `school_profiles` | Identité de l'école + paramètres JSON (barèmes, arrondis, seuils, alertes) | `name`, `short_name`, `settings` |
| `users` | Comptes (direction, professeurs, surveillant, secrétariat, admin technique) | `email`, `is_active`, `job_title` |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Rôles et permissions (Spatie) | — |

### Structure scolaire

| Table | Rôle | Contraintes |
| --- | --- | --- |
| `academic_years` | Années scolaires (statut draft/active/closed, `is_current`) | `name` unique |
| `school_periods` | Trimestres/semestres, poids, statut (open/submitted/validated/locked) | unique(`academic_year_id`, `sequence`) |
| `cycles` | Primaire, collège… | `name`, `code` uniques |
| `grade_levels` | CP1…3ème | `code` unique |
| `classes` | Classes d'une année (ex. 6ème A), titulaire | unique(`academic_year_id`, `name`) |
| `subjects` | Matières | `code` unique |
| `class_subjects` | Matière dans une classe : coefficient, barème cible, notée ou non | unique(`class_id`, `subject_id`) |
| `teacher_assignments` | Enseignant × classe × matière × année | unique(4 colonnes) |

### Élèves

| Table | Rôle | Contraintes |
| --- | --- | --- |
| `students` | Fiche élève (soft-delete, statut) | `matricule` unique |
| `guardians` | Responsables légaux | — |
| `student_guardian` | Lien élève↔responsable (relation, principal, contact d'urgence) | unique(`student_id`, `guardian_id`) |
| `enrollments` | Inscription annuelle élève × année × classe (statut, sortie) | unique(`student_id`, `academic_year_id`) |

### Présences

| Table | Rôle | Contraintes |
| --- | --- | --- |
| `attendance_sessions` | Séance d'appel : classe × date × créneau (matin/après-midi/journée) | unique(3 colonnes) |
| `attendance_records` | Statut par élève (present/absent/late/excused), minutes de retard, justification | unique(`attendance_session_id`, `student_id`) |

### Notes et évaluations

| Table | Rôle | Contraintes |
| --- | --- | --- |
| `evaluations` | Évaluation : matière-classe × période, barème, coefficient, statut (draft/published/cancelled) | — |
| `grades` | Note : valeur, statut (missing/entered/absent/exempt) | unique(`evaluation_id`, `student_id`) — une seule note active par élève et par évaluation |
| `grade_revisions` | Ancienne/nouvelle valeur, auteur, date, motif | — |
| `report_cards` | Bulletin : période, version, statut, PDF, moyenne, rang, décision, métadonnées | unique(`student_id`, `school_period_id`, `version`) |

### Traçabilité et documents

| Table | Rôle |
| --- | --- |
| `audit_logs` | Journal d'audit (utilisateur, action, entité, avant/après, motif, IP) |
| `documents` | Métadonnées de documents privés (bulletins, justificatifs) |

## Relations importantes

```
AcademicYear 1—n SchoolPeriod        SchoolPeriod 1—n Evaluation
AcademicYear 1—n SchoolClass         ClassSubject 1—n Evaluation
AcademicYear 1—n Enrollment          Evaluation   1—n Grade
Cycle        1—n GradeLevel          Grade        1—n GradeRevision
GradeLevel   1—n SchoolClass         Student      1—n Enrollment (1 par année)
SchoolClass  1—n Enrollment          Student      n—n Guardian
SchoolClass  1—n ClassSubject        SchoolPeriod 1—n ReportCard
User         1—n TeacherAssignment   Student      1—n ReportCard
```

## Stratégie de recalcul

- Les moyennes (matière, générale, annuelle, rangs, statistiques) sont **calculées à la volée** par `GradeCalculator` à partir des évaluations publiées et des notes.
- Le bulletin généré conserve une copie de ses résultats (`average`, `rank`, `metadata`) : c'est un **archivage volontaire** d'une version validée, jamais une source de calcul.
- Après verrouillage d'une période, les modifications passent par des révisions tracées ; la cohérence est garantie par le workflow, pas par une duplication.

## V2 (prévu)

Tables supplémentaires : `curriculum_chapters`, `lesson_entries`, `curriculum_progress` (cahier de textes et suivi du programme), gestion des frais de scolarité, portail parents.
