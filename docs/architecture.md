# Architecture

## 1. Vue d'ensemble

Architecture **monolithique modulaire** Laravel — pas d'API séparée, pas de microservices.

```
Interface (Blade · Tailwind · Alpine · Vite)
        │
Application Laravel (Contrôleurs · Form Requests · Policies · Services métier)
        │
MySQL (données et résultats scolaires)
        │
Stockage privé (bulletins et documents)
        │
Sécurité et traçabilité (Permissions · Audit · Sauvegardes)
```

## 2. Organisation du code

| Dossier | Rôle |
| --- | --- |
| `app/Models` | 24 modèles Eloquent (élèves, classes, notes, présences, messagerie…) |
| `app/Http/Controllers` | Contrôleurs fins : validation, autorisation, orchestration |
| `app/Http/Middleware` | `EnsureUserIsActive` (comptes désactivés) + middlewares Spatie |
| `app/Services` | Logique métier : `AttendanceService`, `PeriodWorkflowService`, `AuditLogger`, `MatriculeGenerator` |
| `app/Services/Grading` | Cœur métier des calculs : `GradeCalculator`, `GradingConfig`, `GradeEntryService`, `ReportCardService`, DTO `SubjectAverage`/`GeneralAverage` |
| `app/Support/Roles.php` | Registre central rôles + permissions |
| `database/migrations` | 28 migrations versionnées |
| `database/seeders` | Rôles, profil école, données de démonstration réalistes |
| `resources/views` | Vues Blade (français, mobile-first) |
| `docs/` | Documentation du projet |

## 3. Décisions clés

1. **Inscriptions annuelles** (`enrollments`) : chaque donnée scolaire est reliée à l'année et à la période. Un élève change de classe → nouvelle inscription, l'historique est préservé.
2. **Aucune moyenne codée en dur** : tous les calculs passent par `GradeCalculator` (services testables, paramètres configurables dans `school_profiles.settings`).
3. **Workflow des notes** : `Brouillon → Soumis → Validé → Verrouillé` (`PeriodWorkflowService`), avec révisions horodatées après validation.
4. **Bulletins immuables** : un bulletin validé/verrouillé n'est jamais écrasé — la régénération crée une version `v2`, `v3`… ; les PDF sont stockés sur le disque privé (`storage/app/private/report-cards/…`).
5. **Archivage plutôt que suppression** : élèves en soft-delete, matières désactivées, années clôturées.
6. **Audit systématique** : `AuditLogger` trace les actions sensibles (qui, quoi, avant/après, motif, IP).
7. **Pas de multi-tenant** : `school_profile` unique et configurable ; le multi-écoles viendra plus tard avec des tests d'isolation.
8. **Autorisations à deux niveaux** : permissions globales (Spatie) + contrôles d'affectation par ligne (un professeur ne voit que ses classes).
9. **Messagerie sans infrastructure temps réel** : conversations et messages en base, rafraîchissement par interrogation périodique (4 s), pièces jointes sur le disque privé et accès réservé aux participants. Voir `docs/messagerie.md`.

## 4. Flux principaux

### Saisie et validation des notes

```
Professeur → crée une évaluation (brouillon)
          → saisit les notes (grille mobile)
          → publie l'évaluation (entre dans les calculs)
          → soumet la période (grades.submit)
Direction → valide (grades.validate) → verrouille (grades.lock)
          → toute correction ensuite : permission grades.correct + motif
            → grade_revisions + audit_logs
```

### Bulletins

```
Direction → génère le bulletin (ReportCardService)
          → PDF rendu par dompdf (resources/views/reports/report-card-pdf.blade.php)
          → stocké en privé + ligne report_cards (moyenne, rang, métadonnées)
          → valide → verrouille (immuable)
          → régénération ultérieure = nouvelle version
```

## 5. Interface

- **Barre latérale** adaptée aux permissions : chaque entrée n'apparaît que si l'utilisateur a la permission correspondante.
- **Mobile-first** : grille d'appel et saisie de notes utilisables sur téléphone (Alpine.js pour les interactions rapides, sans rechargement).
- **Tableau de bord** : effectifs, taux de présence (30 jours), absences non justifiées, notes manquantes, alertes configurables (périodes à valider, saisies en retard, absences répétées).
- **Messagerie** : canal général, groupes et conversations directes ; composition mobile-first, pièces jointes privées et badge de non-lus.

## 6. Performance

- `GradeCalculator::classPeriodResults()` calcule toute une classe en un nombre minimal de requêtes (chargement groupé des évaluations et notes).
- Index sur les colonnes filtrées (`students.status`, `grades(student_id,status)`, `attendance_sessions.session_date`, `audit_logs(user_id,created_at)`…).
- Contraintes d'unicité protégeant l'intégrité : une note active par élève et par évaluation, une séance par classe/date/créneau, une inscription par élève et par année.

## 7. Limites connues de la V1

- Pas de mode hors ligne (V2 : PWA + synchronisation contrôlée).
- Pas de notifications e-mail/SMS/WhatsApp (V2).
- Pas de gestion financière (V2, rôle comptable préparé).
- Réinitialisation de mot de passe : par la direction (pas de flux e-mail en V1).
- Les comparaisons entre enseignants ne sont pas fournies : les indicateurs restent pédagogiques et contextuels.
- Messagerie : 60 derniers messages affichés, pas de pagination, de recherche ni de WebSocket (rafraîchissement toutes les 4 s) ; voir `docs/messagerie.md`.
