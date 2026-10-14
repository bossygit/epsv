# Règles de calcul scolaires

Toutes les règles décrites ici sont **configurables** dans *Paramètres → Règles de calcul* et implémentées dans `App\Services\Grading\GradeCalculator` (testées dans `tests/Unit/GradeCalculatorTest.php`).

> ⚠️ Les barèmes et pondérations par défaut constituent une base technique. Ils doivent être validés par la direction de l'établissement avant la mise en production.

## 1. Paramètres configurables

| Paramètre | Clé (`settings.grading`) | Défaut | Description |
| --- | --- | --- | --- |
| Barème cible | `target_scale` | 20 | Barème d'affichage des moyennes |
| Décimales | `decimals` | 2 | Précision d'affichage |
| Arrondi | `rounding` | `half_up` | `half_up`, `half_even` ou `floor` |
| Seuil de réussite | `pass_threshold` | 10 | Utilisé pour le taux de réussite et la décision |
| Classement | `ranking_enabled` | activé | Désactive complètement les rangs |
| Notes manquantes | `missing_policy` | `strict` | `strict` : une matière incomplète est exclue de la moyenne générale ; `ignore` : elle est intégrée avec sa moyenne partielle |
| Absence à une évaluation | `absent_policy` | `exclude` | `exclude` : exclue du calcul ; `zero` : comptée 0 |
| Dispenses | `exempt_policy` | `exclude` | Toujours exclues (fixe) |
| Pondération annuelle | `annual_weighting` | `period_weight` | Poids de chaque période (`school_periods.weight`) |

## 2. Normalisation des notes

Les évaluations peuvent avoir des barèmes différents. Chaque note est d'abord normalisée sur le barème cible de la matière (`class_subjects.max_score`, par défaut 20) :

$$N_i^* = \frac{N_i}{B_i} \times B_c$$

- $N_i$ : note obtenue
- $B_i$ : barème de l'évaluation (`evaluations.max_score`)
- $B_c$ : barème cible (`class_subjects.max_score`)

Exemple : 8/10 → $8/10 \times 20 = 16/20$.

## 3. Moyenne d'une matière

$$M = \frac{\sum_{i=1}^{n} N_i^* \times w_i}{\sum_{i=1}^{n} w_i}$$

$w_i$ = coefficient de l'évaluation (`evaluations.coefficient`).

Exemple (cahier des charges) : 12/20 (coef 1) et 16/20 (coef 2) → $(12 \times 1 + 16 \times 2) / 3 = 14{,}67$.

### Évaluations prises en compte

- Seules les évaluations **publiées** comptent. Une évaluation en brouillon ou **annulée** est exclue.
- Une évaluation sans note saisie pour l'élève marque la matière **incomplète** (voir §5).

## 4. Moyenne générale

$$MG = \frac{\sum_{j=1}^{m} M_j \times C_j}{\sum_{j=1}^{m} C_j}$$

- $M_j$ : moyenne de la matière (calculée au §3)
- $C_j$ : coefficient de la matière (`class_subjects.coefficient`)

### Matières admissibles

- Les matières **non notées** (`is_graded = false`) sont toujours exclues.
- Une matière **sans aucune évaluation exploitable** est exclue et signale le résultat comme incomplet.
- Une matière **incomplète** (au moins une note manquante) :
  - `missing_policy = strict` → exclue de la moyenne générale, résultat marqué incomplet ;
  - `missing_policy = ignore` → intégrée avec sa moyenne partielle, résultat marqué incomplet.

Le système ne convertit **jamais** une note manquante en zéro et ne gonfle jamais une moyenne en ignorant silencieusement des données incomplètes : l'incomplétude est affichée (bulletin, tableau de bord, alertes).

## 5. Statuts des notes

| Statut | Signification | Traitement |
| --- | --- | --- |
| `missing` | Non saisie | Exclue du calcul ; rend la matière incomplète |
| `entered` | Valeur saisie | Comptée (normalisée puis pondérée) |
| `absent` | Élève absent à l'évaluation | `exclude` : exclue (définitive, ne rend pas le résultat incomplet) ; `zero` : comptée 0 |
| `exempt` | Dispensé | Toujours exclue |

## 6. Moyenne annuelle

$$MA = \frac{\sum_{p=1}^{k} M_p \times W_p}{\sum_{p=1}^{k} W_p}$$

- $M_p$ : moyenne générale de la période $p$
- $W_p$ : poids de la période (`school_periods.weight`)

Seules les périodes **validées ou verrouillées** sont prises en compte (les périodes en cours sont provisoires). S'il n'existe aucune période validée, la moyenne annuelle n'est pas calculée.

## 7. Arrondis

Le calcul interne conserve la précision complète. L'arrondi n'intervient qu'à l'affichage et à l'enregistrement final du bulletin, selon `decimals` et `rounding` :

- `half_up` : arrondi commercial classique (14,666… → 14,67) ;
- `half_even` : arrondi bancaire (0,125 → 0,12) ;
- `floor` : troncature (14,666… → 14,66).

## 8. Classement

- Calculé **par classe et par période**.
- Les élèves ex æquo partagent le même rang (1, 1, 3…).
- Les élèves sans moyenne exploitable sont **non classés** (affichés séparément).
- Le classement est désactivable (`ranking_enabled`) si le règlement de l'école ne le prévoit pas.
- Le rang par matière est également disponible sur les bulletins.

## 9. Taux de réussite et statistiques de classe

- Taux de réussite = nombre d'élèves dont la moyenne ≥ `pass_threshold` / nombre d'élèves classés.
- Les statistiques de classe (moyenne, plus forte, plus faible) n'incluent que les élèves ayant une moyenne calculable.

## 10. Taux de présence

- Taux de présence = (présences + retards) / total des pointages de la période, sur un dénominateur explicitement défini : les séances d'appel enregistrées (`attendance_sessions`).
- Les absences justifiées (`excused`) comptent comme absences dans le taux mais sont distinguées dans les rapports.
- Alertes : élève avec ≥ `alert_absences_count` absences sur `alert_window_days` jours glissants.

## 11. Workflow des notes

`Brouillon → Soumis → Validé → Verrouillé`

1. Le professeur affecté saisit et vérifie ses notes (évaluations publiées).
2. Il **soumet** la période (`grades.submit`).
3. La direction **valide** (`grades.validate`) puis **verrouille** (`grades.lock`).
4. Toute correction ultérieure exige la permission `grades.correct`, un **motif** et crée une **révision horodatée** (`grade_revisions` : ancienne valeur, nouvelle valeur, auteur, date, motif) + une entrée dans le journal d'audit.

## 12. Cas limites couverts par les tests

- Barèmes différents (20, 10, 5) normalisés avant pondération ;
- Coefficients d'évaluation et de matière ;
- Note manquante ≠ zéro ;
- Absence à une évaluation (exclue ou zéro selon configuration) ;
- Dispense toujours exclue ;
- Évaluation annulée ou en brouillon exclue ;
- Matière non notée exclue ;
- Arrondis `half_up`, `half_even`, `floor` ;
- Ex æquo dans le classement ;
- Périodes incomplètes (moyenne annuelle sur périodes validées uniquement).
