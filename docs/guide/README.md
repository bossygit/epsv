# Guide d'utilisation EPSV

`Guide-utilisation-EPSV.pdf` — guide de la plateforme pour la **direction** et les
**professeurs** : connexion, structure, élèves, présences, notes, moyennes, bulletins,
comptes, audit et sauvegardes (25 pages, A4, captures d'écran de l'application).

## Contenu

| Partie | Destinataires |
| --- | --- |
| 1 — Prise en main | tout le monde (connexion, écran, rôles) |
| 2 — Guide du directeur | direction, secrétariat |
| 3 — Guide du professeur | enseignants |
| Annexes | droits par rôle, comptes, dépannage, checklist de fin de trimestre, fiche à valider par la direction |

## Régénérer le PDF

La mise en page et tous les textes sont dans `source/build_guide.py` (HTML + CSS
d'impression, images intégrées en base64) ; `source/img/*.jpg` contient les captures
déjà optimisées et `source/toc.json` les numéros de page du sommaire.

```bash
cd docs/guide/source
python3 build_guide.py toc.json     # → guide.html
```

Puis imprimer `guide.html` en PDF (A4, marges 14 mm, « imprimer les arrière-plans »
activé, pied de page « Page X / Y »). Dans Chrome/Chromium :

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --print-to-pdf=Guide-utilisation-EPSV.pdf --no-pdf-header-footer \
  file://$PWD/guide.html
```

> Sans en-tête/pied gérés par Chrome, ajoutez au besoin les numéros de page via la
> boîte d'impression. Le rendu utilisé pour ce guide a été produit via l'API
> `Page.printToPDF` avec un pied de page numéroté.

## Modifier ou compléter le guide

1. Éditez le texte directement dans `build_guide.py` (fonctions `box()`, `table()`,
   `steps()`, `figure()` pour les encadrés, tableaux, listes numérotées et captures).
2. Si le contenu change de longueur, régénérez d'abord sans `toc.json`, relevez les
   pages réelles dans le PDF, mettez à jour `toc.json`, puis régénérez :
   `python3 build_guide.py toc.json`.

## Remplacer les captures d'écran

Déposez dans `source/` des PNG nommés comme les fichiers de `source/img/`
(`02_tableau_direction.png`, `11_saisie_notes.png`, …) puis régénérez : les PNG sont
redimensionnés (1180 px de large) et remplacés dans `source/img/` (nécessite Pillow :
`python3 -m pip install pillow`). Les captures ont été prises en 1440 × 900, connecté
en direction puis en professeur.
