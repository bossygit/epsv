# Plan d'implémentation — V1

## Phase 0 — Audit et cadrage ✅

| Vérification | Résultat |
| --- | --- |
| PHP | 8.5.0 (Herd Lite) |
| Composer | 2.8.12 |
| Node.js / npm | 26.0.0 / 11.12.1 |
| MySQL | installé (brew), base `epsv` |
| Extensions PHP | pdo_mysql, mbstring, xml, curl, zip, gd, intl, bcmath ✔ |
| Git / gh CLI | 2.50.1 / 2.93.0 (compte `bossygit`) |

Livrable : ce plan + la documentation `docs/`.

## Phase 1 — Socle applicatif ✅

- Projet Laravel 13 + Breeze (Blade/Tailwind/Alpine/Vite).
- Inscription publique désactivée, vérification e-mail et réinitialisation par e-mail retirées (V1 : la direction crée les comptes et réinitialise les mots de passe).
- spatie/laravel-permission : 23 permissions, 6 rôles (`App\Support\Roles`).
- Journal d'audit (`audit_logs` + `AuditLogger`).
- Middleware `EnsureUserIsActive`.
- **Tests** : `PermissionsTest` (matrice des rôles).

## Phase 2 — Structure scolaire ✅

- Années, périodes (workflow), cycles, niveaux, classes, matières, coefficients/barèmes par classe, affectations des enseignants.
- Élèves (matricule auto `EPSV-2026-0001`), responsables légaux, inscriptions annuelles.
- **Seeders** : rôles, profil EPSV, données de démonstration (97 élèves, 7 classes, 1 498 notes, présences sur 10 jours).

## Phase 3 — Présences ✅

- Écran d'appel rapide par classe/date/créneau, statuts (présent/absent/retard/justifié), minutes de retard, justification.
- Correction avec motif obligatoire + traçabilité ; rapport par classe sur période.
- **Tests** : `AttendanceTest` (unicité des séances, appel par défaut, corrections, justifications).

## Phase 4 — Notes et calculs ✅

- Évaluations (barème, coefficient, statut brouillon/publiée/annulée), grille de saisie.
- `GradeCalculator` : normalisation des barèmes, moyennes pondérées, notes manquantes, absences, dispenses, annulations, arrondis, classement (ex æquo), statistiques, moyenne annuelle.
- Paramètres configurables dans `school_profiles.settings`.
- Workflow période + révisions de notes.
- **Tests** : `GradeCalculatorTest` (15 cas), `GradeCorrectionTest`, `PeriodWorkflowTest`.

## Phase 5 — Bulletins et direction ✅

- Bulletins PDF (dompdf) : modèle par cycle possible, appréciations, rangs par matière, moyenne générale, rang, décision.
- Archivage privé + versions immuables (v2 après verrouillage).
- Tableau de bord : effectifs, présence, résultats, alertes configurables (périodes à valider, notes en retard, absences répétées).
- **Tests** : `ReportCardTest`, `PagesSmokeTest`.

## Phase 6 — Recette et déploiement

- ✅ Tests automatisés : 36+ tests (unitaires + fonctionnels), rendu de toutes les pages.
- ✅ Procédure de sauvegarde/restauration documentée (`docs/deployment.md`).
- ✅ Déploiement Render (Docker + MySQL privé + disque persistant) documenté et scripté (`render.yaml`).
- 🔲 Recette utilisateurs avec la direction (parcours professeur + direction sur téléphone).
- 🔲 Validation des barèmes/coefficients réels avec l'école (séance de cadrage).
- 🔲 Test réel de restauration de sauvegarde avant ouverture.

## Critères d'acceptation (checklist de recette)

| # | Critère | État |
| --- | --- | --- |
| 1 | Sécurité : un professeur ne peut pas consulter les classes/matières non affectées | ✅ testé (`PagesSmokeTest`) |
| 2 | Présences : appel saisi, corrigé avec traçabilité, consulté selon permissions | ✅ testé (`AttendanceTest`) |
| 3 | Calculs conformes aux formules validées | ✅ testé (`GradeCalculatorTest`) — à valider avec l'école |
| 4 | Bulletins PDF conformes et accès restreint | ✅ testé (`ReportCardTest`) |
| 5 | Historique : ancienne valeur, nouvelle valeur et motif conservés | ✅ testé (`GradeCorrectionTest`) |
| 6 | Sauvegardes : base et documents restaurables | ✅ procédure documentée — test de restauration à faire sur le serveur final |
| 7 | Ergonomie : présences et notes saisissables sur téléphone | ✅ interface mobile-first (recette terrain à faire) |

## Prochaines étapes recommandées

1. Séance de cadrage avec la direction : bulletin réel anonymisé, barèmes, coefficients, règles de classement et décision.
2. Ajuster les paramètres dans *Paramètres → Règles de calcul* et vérifier les moyennes contre des résultats connus.
3. Recette terrain : 1 professeur + 1 surveillant sur téléphone pendant une semaine d'appel réel.
4. V2 : cahier de textes, import/export Excel, PWA hors ligne, notifications WhatsApp, frais de scolarité.
