# Identité visuelle EPSV

Direction retenue après comparaison de trois maquettes (`sketches/`) :
**structure « Console » (002) + typographie « Registre » (001)**.

## Jetons

Définis dans `tailwind.config.js`, utilisables partout dans les vues.

| Jeton | Valeur | Usage |
|---|---|---|
| `navy` / `navy-800` / `navy-700` | `#0F2438` / `#16304A` / `#1B3A5C` | sidebar, boutons primaires, aplats |
| `ivory` | `#FAF7F1` | fond de page |
| `paper` | `#FFFFFF` | surfaces (cartes, barre supérieure) |
| `ink` | `#14232E` | texte principal |
| `muted` | `#62717C` | texte secondaire (contraste 4,7:1 sur ivoire) |
| `line` / `line-soft` | `#E4DED1` / `#EFEAE0` | filets et séparateurs |
| `gold` | `#B08A3E` | accent unique : état actif, soulignés, crest |

Les échelles `gray`, `slate` et `indigo` **ont été remappées** vers ce neutre
chaud et ce bleu nuit : les vues héritées de Breeze se sont alignées sans
réécriture. Les couleurs sémantiques (`emerald`, `amber`, `rose`, `blue`)
restent intactes.

## Typographie

- **Spectral** (`font-serif`) : titres `h1`–`h3`, chiffres clés, noms de classe.
- **Inter** (`font-sans`) : interface, tableaux, libellés.
- Chiffres en `tabular-nums` (classe `.num` ou `table` par défaut).
- Libellés de sections : 8,5–9,5 px, uppercase, `tracking-[0.12em]`.

## Règles de composition

1. **Filet plutôt qu'ombre.** Les surfaces sont séparées par des bordures
   `border-line` ; le rayon est de 2 px. Pas de carte flottante.
2. **Un seul accent.** L'or signale l'action ou l'état actif ; jamais de
   couleur décorative.
3. **Bandeau d'indicateurs unique.** Les KPI se lisent comme une ligne de
   registre (`x-stat-card variant="strip"`), pas comme quatre cartes.
4. **Colonne d'action à droite.** `lg:grid-cols-[1fr_300px]` : contenu à
   gauche, « à traiter » + raccourcis à droite.
5. **La coque est fixe** : barre latérale 212 px, barre supérieure 52 px
   collante (fil d'Ariane, recherche élèves, année/période, avatar).

## Composants touchés

`layouts/app`, `layouts/navigation`, `layouts/guest`, `auth/login`,
`card`, `stat-card` (variantes `card` et `strip`), `page-header`,
`primary-button`, `secondary-button`, `danger-button`, `text-input`, `select`,
`empty-state`, `status-badge`, `sidebar-link`, `dashboard`.

## Suite

Les pages métier (élèves, présences, notes, messagerie) héritent déjà des
jetons et des composants. Reste à les reprendre une par une pour appliquer la
densité du tableau de bord (tableaux, barres de progression, colonnes
d'action).
