# EPSV — Plateforme de gestion scolaire

Application de gestion scolaire pour l'**École Panafricaine Séguynaud de Vinci (EPSV)** — Brazzaville, République du Congo.

> V1 : élèves, classes, présences, notes, calcul des moyennes, bulletins PDF, tableau de bord de la direction et messagerie interne du personnel. Le mode hors ligne, l'IA et les notifications viendront en V2.

**Démo en ligne** : https://epsv-app.onrender.com (données de démonstration — compte `direction@epsv.cg`, mot de passe défini par `EPSV_DEMO_PASSWORD`). Configuration : `render.yaml` ; voir [docs/deployment.md](docs/deployment.md).

## Stack

| Élément | Choix |
| --- | --- |
| Backend | Laravel 13 / PHP 8.3+ |
| Base de données | MySQL (production) — SQLite possible en développement |
| Interface | Blade, Tailwind CSS, Alpine.js, Vite |
| Authentification | Laravel (inscription publique désactivée) |
| Autorisations | spatie/laravel-permission + contrôles d'affectation |
| PDF | barryvdh/laravel-dompdf (stockage privé) |
| Tests | PHPUnit |

## Installation locale

```bash
# 1. Dépendances
composer install
npm install

# 2. Environnement
cp .env.example .env
php artisan key:generate
# → renseigner DB_* (MySQL) ou laisser SQLite pour un essai rapide

# 3. Base de données + données de démonstration
php artisan migrate --seed

# 4. Frontend
npm run build

# 5. Lancer
php artisan serve
```

### Comptes de démonstration

Créés par le seeder (mot de passe : `Epsv@2026`, modifiable via `EPSV_DEMO_PASSWORD`) :

| Compte | Rôle |
| --- | --- |
| `direction@epsv.cg` | Direction / propriétaire |
| `prof.mabiala@epsv.cg` | Professeur (mathématiques, informatique) |
| `prof.kimbembe@epsv.cg` | Professeure (français) |
| `prof.ngoma@epsv.cg` | Professeur (histoire-géographie, ECM) |
| `prof.bakala@epsv.cg` | Professeure (SVT) |
| `prof.malonga@epsv.cg` | Professeur (anglais, EPS) |
| `surveillant@epsv.cg` | Surveillant général |
| `secretariat@epsv.cg` | Secrétariat |
| `admin@epsv.cg` | Administrateur technique |

⚠️ **Avant la mise en production** : changez ces mots de passe et définissez `EPSV_SEED_DEMO=false` pour ne pas créer les données fictives.

## Tests

```bash
php artisan test
```

- `tests/Unit/GradeCalculatorTest.php` — barèmes, coefficients, notes manquantes, dispenses, absences, arrondis, classement.
- `tests/Feature/PeriodWorkflowTest.php` — workflow soumission → validation → verrouillage.
- `tests/Feature/GradeCorrectionTest.php` — correction tracée après validation.
- `tests/Feature/AttendanceTest.php` — appel, unicité des séances, corrections.
- `tests/Feature/ReportCardTest.php` — génération PDF, versions immuables.
- `tests/Feature/PermissionsTest.php` — matrice des rôles.
- `tests/Feature/PagesSmokeTest.php` — rendu de toutes les pages.

## Documentation

- [docs/architecture.md](docs/architecture.md) — décisions d'architecture.
- [docs/database-schema.md](docs/database-schema.md) — schéma de base de données.
- [docs/calculs-scolaires.md](docs/calculs-scolaires.md) — règles de calcul et paramètres.
- [docs/permissions.md](docs/permissions.md) — rôles et permissions.
- [docs/implementation-plan.md](docs/implementation-plan.md) — plan de mise en œuvre.
- [docs/deployment.md](docs/deployment.md) — déploiement et sauvegardes.
- [docs/messagerie.md](docs/messagerie.md) — messagerie interne : périmètre, modèle et limites.

## Règles de travail du projet

1. Aucune note codée en dur dans les contrôleurs ou les vues : tout passe par `App\Services\Grading\GradeCalculator`.
2. Les barèmes, coefficients, arrondis et seuils sont configurables dans les **Paramètres** de l'école.
3. Une note validée n'est jamais modifiée silencieusement : correction = permission + motif + révision horodatée.
4. Les bulletins validés/verrouillés sont immuables ; une régénération crée une nouvelle version.
5. Les documents (bulletins) sont stockés sur le disque privé, jamais dans `public/`.
