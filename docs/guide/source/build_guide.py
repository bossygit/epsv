#!/usr/bin/env python3
"""Génère le guide d'utilisation EPSV (HTML imprimable + images optimisées).

Usage :
    python build_guide.py            # construit img/ + guide.html (sans numéros de page)
    python build_guide.py toc.json   # réinjecte les numéros de page du sommaire
"""
from __future__ import annotations

import base64
import json
import os
import sys


HERE = os.path.dirname(os.path.abspath(__file__))
SRC = HERE
IMG_OUT = os.path.join(HERE, "img")
PDF_URL = "https://epsv-app.onrender.com"
DOC_DATE = "14 octobre 2026"

os.makedirs(IMG_OUT, exist_ok=True)


DATA_URIS: dict[str, str] = {}


def optimize(name: str, width: int = 1180, quality: int = 84) -> str:
    """Prépare une capture : JPEG optimisé + version base64 pour l'intégration.

    Les images sont intégrées au HTML en data URI : le navigateur n'a ainsi
    aucune ressource externe à charger au moment de l'impression. Si le PNG
    d'origine n'est plus là mais que le JPEG optimisé existe (source archivée),
    on réutilise ce dernier : la mise en page peut être retouchée sans
    recapturer les écrans.
    """
    src = os.path.join(SRC, name + ".png")
    dst = os.path.join(IMG_OUT, name + ".jpg")
    if os.path.exists(src):
        from PIL import Image

        with Image.open(src) as im:
            im = im.convert("RGB")
            if im.width > width:
                h = round(im.height * width / im.width)
                im = im.resize((width, h), Image.LANCZOS)
            im.save(dst, "JPEG", quality=quality, optimize=True, progressive=True)
    if not os.path.exists(dst):
        raise SystemExit(f"capture introuvable (ni {src} ni {dst})")
    with open(dst, "rb") as fh:
        DATA_URIS[name] = base64.b64encode(fh.read()).decode("ascii")
    return f"img/{name}.jpg"


SHOTS = [
    "01_connexion", "02_tableau_direction", "03_annees_scolaires", "04_eleves",
    "05_fiche_eleve", "06_classes", "07_fiche_classe", "08_matieres",
    "09_presences", "10_evaluations", "11_saisie_notes", "12_bulletins",
    "13_utilisateurs", "14_parametres", "15_audit", "16_tableau_professeur",
    "17_classes_professeur", "18_appel_professeur", "19_evaluations_professeur",
]

for s in SHOTS:
    optimize(s)


def figure(shot: str, caption: str, cls: str = "") -> str:
    uri = DATA_URIS.get(shot)
    if uri is None:
        raise SystemExit(f"capture manquante : {shot}")
    return (
        f'<figure class="{cls}"><img src="data:image/jpeg;base64,{uri}" alt="">'
        f"<figcaption>{caption}</figcaption></figure>"
    )


def steps(items: list[str]) -> str:
    lis = "".join(f"<li>{i}</li>" for i in items)
    return f'<ol class="steps">{lis}</ol>'


def table(headers: list[str], rows: list[list[str]], cls: str = "") -> str:
    th = "".join(f"<th>{h}</th>" for h in headers)
    trs = "".join("<tr>" + "".join(f"<td>{c}</td>" for c in r) + "</tr>" for r in rows)
    return f'<table class="{cls}"><thead><tr>{th}</tr></thead><tbody>{trs}</tbody></table>'


def box(kind: str, title: str, body: str) -> str:
    return f'<div class="box {kind}"><p class="box-t">{title}</p><p>{body}</p></div>'


# ---------------------------------------------------------------- contenu
# Sommaire : (ancre, titre, niveau)
TOC = [
    ("p1", "Partie 1 — Prise en main", 1),
    ("connexion", "1. Se connecter", 2),
    ("ecran", "2. Comprendre l'écran", 2),
    ("roles", "3. Qui peut faire quoi", 2),
    ("p2", "Partie 2 — Guide du directeur", 1),
    ("d1", "1. Lire le tableau de bord", 2),
    ("d2", "2. Préparer l'année scolaire et les périodes", 2),
    ("d3", "3. Configurer la structure", 2),
    ("d4", "4. Élèves, inscriptions et responsables légaux", 2),
    ("d5", "5. Suivre les présences", 2),
    ("d6", "6. Valider et verrouiller les résultats", 2),
    ("d7", "7. Générer et valider les bulletins", 2),
    ("d8", "8. Créer et gérer les comptes", 2),
    ("d9", "9. Paramètres de l'école et règles de calcul", 2),
    ("d10", "10. Vérifier les actions : journal d'audit", 2),
    ("d11", "11. Sauvegardes et restauration", 2),
    ("p3", "Partie 3 — Guide du professeur", 1),
    ("e1", "1. Se connecter et retrouver ses classes", 2),
    ("e2", "2. Faire l'appel", 2),
    ("e3", "3. Créer une évaluation", 2),
    ("e4", "4. Saisir les notes", 2),
    ("e5", "5. Soumettre les résultats de la période", 2),
    ("e6", "6. Consulter les bulletins", 2),
    ("e7", "7. Comment les moyennes sont calculées", 2),
    ("pa", "Annexes", 1),
    ("a1", "A. Rôles et permissions", 2),
    ("a2", "B. Comptes et mots de passe", 2),
    ("a3", "C. Questions fréquentes", 2),
    ("a4", "D. Checklist de fin de trimestre", 2),
    ("a5", "E. Fiche à valider par la direction", 2),
    ("a6", "F. Documents du projet", 2),
]

TOC_FR: dict[str, int] = {}
if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
    with open(sys.argv[1], encoding="utf-8") as fh:
        TOC_FR = json.load(fh)


def toc_html() -> str:
    rows = []
    for anchor, title, level in TOC:
        num = TOC_FR.get(title)
        page = f'<span class="toc-p">{num}</span>' if num else '<span class="toc-p">&nbsp;</span>'
        rows.append(
            f'<li class="lvl{level}"><span class="toc-t">{title}</span>'
            f'<span class="toc-d"></span>{page}</li>'
        )
    return "<ul class=\"toc\">" + "".join(rows) + "</ul>"


CSS = """
@page { size: A4; margin: 16mm 14mm 18mm 14mm; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 10.4pt;
       line-height: 1.5; color: #1f2937; margin: 0; }
h1, h2, h3 { color: #111827; line-height: 1.25; }
h1 { font-size: 19pt; margin: 0 0 6pt; }
h2 { font-size: 14.5pt; margin: 18pt 0 6pt; padding-bottom: 3pt; border-bottom: 2px solid #e5e7eb; }
h3 { font-size: 11.6pt; margin: 12pt 0 4pt; color: #374151; }
p { margin: 0 0 7pt; }
ul, ol { margin: 0 0 8pt; padding-left: 16pt; }
li { margin-bottom: 3pt; }
code, .menu { font-family: "SF Mono", Menlo, Consolas, monospace; font-size: 9.2pt;
      background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 3px; padding: 0 3px; }
b, strong { color: #111827; }
a { color: #4338ca; text-decoration: none; }

.cover { height: 246mm; display: flex; flex-direction: column; justify-content: space-between;
         page-break-after: always; }
.cover-top { display: flex; align-items: center; gap: 12pt; }
.logo { width: 46pt; height: 46pt; border-radius: 10pt; background: #4338ca; color: #fff;
        font-weight: bold; font-size: 15pt; display: flex; align-items: center;
        justify-content: center; }
.cover-top div p { margin: 0; }
.cover-top .school { font-size: 12.5pt; font-weight: bold; color: #111827; }
.cover-top .sub { font-size: 9.6pt; color: #6b7280; }
.cover-mid { margin-top: 60mm; }
.cover-mid .kicker { text-transform: uppercase; letter-spacing: 2pt; font-size: 9.5pt;
                     color: #4338ca; font-weight: bold; margin-bottom: 6pt; }
.cover-mid h1 { font-size: 30pt; margin: 0 0 10pt; }
.cover-mid .lead { font-size: 12pt; color: #4b5563; max-width: 420pt; }
.cover-meta { border-top: 3px solid #4338ca; padding-top: 8pt; display: flex;
              justify-content: space-between; font-size: 9.4pt; color: #6b7280; }

.part { page-break-before: always; }
.part-head { background: #eef2ff; border-left: 5px solid #4338ca; padding: 10pt 12pt;
             margin-bottom: 10pt; }
.part-head .n { font-size: 9.5pt; text-transform: uppercase; letter-spacing: 1.6pt;
                color: #4338ca; font-weight: bold; }
.part-head h1 { margin: 2pt 0 4pt; font-size: 21pt; }
.part-head p { margin: 0; color: #4b5563; font-size: 10pt; }

table { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; font-size: 9.4pt; }
th, td { border: 1px solid #e5e7eb; padding: 4pt 6pt; text-align: left; vertical-align: top; }
th { background: #f3f4f6; font-weight: bold; color: #374151; }
tr { page-break-inside: avoid; }

figure { margin: 6pt 0 12pt; page-break-inside: avoid; }
figure img { width: 100%; border: 1px solid #d1d5db; border-radius: 4pt; display: block; }
figcaption { font-size: 8.8pt; color: #6b7280; margin-top: 3pt; font-style: italic; }
figure.half { width: 88%; }

.box { border-radius: 4pt; padding: 7pt 9pt; margin: 6pt 0 10pt; page-break-inside: avoid;
       border-left: 4px solid #9ca3af; background: #f9fafb; font-size: 9.8pt; }
.box p { margin: 0; }
.box .box-t { font-weight: bold; margin-bottom: 2pt; color: #111827; }
.box.tip { border-color: #059669; background: #ecfdf5; }
.box.warn { border-color: #d97706; background: #fffbeb; }
.box.danger { border-color: #dc2626; background: #fef2f2; }
.box.info { border-color: #4338ca; background: #eef2ff; }

.steps { padding-left: 0; list-style: none; counter-reset: st; }
.steps li { counter-increment: st; position: relative; padding-left: 20pt; margin-bottom: 5pt; }
.steps li:before { content: counter(st); position: absolute; left: 0; top: 1pt;
    width: 13pt; height: 13pt; border-radius: 50%; background: #4338ca; color: #fff;
    font-size: 8pt; font-weight: bold; text-align: center; line-height: 13pt; }

.toc { list-style: none; padding: 0; font-size: 10.2pt; }
.toc li { display: flex; align-items: baseline; gap: 4pt; margin: 0 0 4pt; }
.toc .toc-t { white-space: nowrap; }
.toc .toc-d { flex: 1; border-bottom: 1px dotted #d1d5db; margin: 0 2pt; }
.toc-p { font-variant-numeric: tabular-nums; color: #6b7280; }
.toc .lvl1 { font-weight: bold; margin-top: 9pt; color: #4338ca; }
.toc .lvl2 { padding-left: 12pt; }

.kpi-row { display: flex; gap: 6pt; margin: 6pt 0 10pt; }
.kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 4pt; padding: 6pt 7pt; background: #f9fafb; }
.kpi .v { font-size: 15pt; font-weight: bold; color: #111827; }
.kpi .l { font-size: 8.6pt; color: #6b7280; text-transform: uppercase; letter-spacing: .4pt; }
.small { font-size: 9.2pt; color: #6b7280; }
.nowrap { white-space: nowrap; }
"""

BODY = f"""
<!-- ============================ COUVERTURE ============================ -->
<section class="cover">
  <div class="cover-top">
    <div class="logo">EPSV</div>
    <div>
      <p class="school">École Panafricaine Séguynaud de Vinci</p>
      <p class="sub">Gestion scolaire — Brazzaville, République du Congo</p>
    </div>
  </div>

  <div class="cover-mid">
    <p class="kicker">Guide d'utilisation</p>
    <h1>Plateforme de gestion scolaire</h1>
    <p class="lead">Procédures pas à pas pour la <b>direction</b> et pour les
    <b>professeurs</b> : élèves, classes, présences, notes, moyennes, bulletins
    et tableau de bord.</p>
  </div>

  <div class="cover-meta">
    <span>Version 1.0 — {DOC_DATE}</span>
    <span>Établie par la direction · À diffuser aux enseignants</span>
  </div>
</section>

<!-- ============================== SOMMAIRE ============================= -->
<h1>Sommaire</h1>
{toc_html()}
<div class="box info" style="margin-top:14pt">
  <p class="t-box"><b>Comment lire ce guide ?</b></p>
  <p>La <b>partie 1</b> est commune à tout le monde. La <b>partie 2</b> s'adresse à la
  direction (et au secrétariat). La <b>partie 3</b> s'adresse aux professeurs. Les annexes
  contiennent les tableaux de référence et une fiche à compléter avec la direction.</p>
</div>

<!-- ============================== PARTIE 1 ============================= -->
<section class="part" id="p1">
  <div class="part-head">
    <p class="n">Partie 1</p>
    <h1>Prise en main</h1>
    <p>Ce que fait la plateforme, comment y accéder et qui peut faire quoi.</p>
  </div>

  <h3>Ce que la plateforme gère</h3>
  {table(["Module", "Ce qu'il permet"], [
      ["Administration scolaire", "Années scolaires, périodes (trimestres), cycles, niveaux, classes, matières, coefficients et affectations des professeurs."],
      ["Élèves et inscriptions", "Fiche élève, matricule, responsables légaux, inscription dans une classe, historique par année."],
      ["Présences", "Appel par classe et par créneau (matin, après-midi, journée), absences, retards, justifications et corrections."],
      ["Notes et évaluations", "Devoirs et compositions, saisie en grille, calcul automatique des moyennes, contrôle des barèmes."],
      ["Bulletins", "Bulletin par élève et par période, appréciations, moyenne générale, rang, génération PDF et archivage par version."],
      ["Tableau de bord", "Effectifs, taux de présence, résultats par classe, alertes (notes en retard, absences à traiter)."],
      ["Sécurité et traçabilité", "Rôles et permissions, journal d'audit, workflow de validation et de verrouillage des périodes."],
  ])}

  <h2 id="connexion">1. Se connecter</h2>
  <ol class="steps">
    <li>Ouvrez un navigateur (Chrome, Safari, Edge ou Firefox) et allez à l'adresse de
        l'école : <code>{PDF_URL}</code></li>
    <li>Saisissez votre <b>adresse e-mail</b> et votre <b>mot de passe</b>, puis cliquez sur
        <span class="menu">SE CONNECTER</span>.</li>
    <li>Vous arrivez sur le <b>tableau de bord</b>. Le menu latéral gauche donne accès à toutes
        les rubriques auxquelles votre fonction donne droit.</li>
  </ol>
  {figure("01_connexion", "Page de connexion. Les comptes sont créés par la direction : il n'y a pas d'inscription publique.")}

  {box("info", "Bon à savoir",
   "Il n'existe pas d'inscription en ligne : la direction crée les comptes et réinitialise les mots de passe. " +
   "Un compte désactivé ne peut pas ouvrir de session : un message vous invite à contacter la direction.")}
  {box("tip", "Sur téléphone",
   "L'interface s'adapte aux petits écrans : le menu s'ouvre avec le bouton en haut à gauche. L'appel et la saisie des notes " +
   "se font très bien sur téléphone, y compris depuis la salle de classe.")}

  <h2 id="ecran">2. Comprendre l'écran</h2>
  <p>Trois zones reviennent sur toutes les pages :</p>
  <ul>
    <li><b>Le menu latéral</b> — organisé en rubriques : <span class="menu">SCOLARITÉ</span>
        (Élèves, Inscriptions, Présences, Notes &amp; évaluations, Bulletins),
        <span class="menu">STRUCTURE</span> (Années scolaires, Classes, Matières, Cycles &amp; niveaux)
        et <span class="menu">ADMINISTRATION</span> (Utilisateurs, Paramètres, Journal d'audit).
        Chacun ne voit que les rubriques de sa fonction.</li>
    <li><b>L'en-tête</b> — le titre de la page, l'année scolaire et la période en cours,
        et les boutons d'action principaux.</li>
    <li><b>La zone de travail</b> — tableaux, cartes d'indicateurs, alertes et formulaires.
        Les boutons d'action sont à droite de chaque ligne des tableaux.</li>
  </ul>
  <p class="small">En bas du menu : votre nom, votre fonction, l'accès à votre profil et la
  déconnexion. Déconnectez-vous toujours sur un appareil partagé.</p>

  <h2 id="roles">3. Qui peut faire quoi</h2>
  {table(["Fonction", "Ce qu'elle permet de faire"], [
      ["Direction / propriétaire", "Tout consulter ; gérer la structure, les élèves, les comptes et les paramètres ; valider et verrouiller les résultats ; corriger une note validée avec motif. <b>La direction ne saisit pas les notes.</b>"],
      ["Professeur", "Voir ses classes affectées, ses élèves et les bulletins ; faire l'appel ; créer ses évaluations ; saisir ses notes ; soumettre les résultats de la période."],
      ["Surveillant", "Appels, absences, retards, justifications et corrections, suivi des élèves."],
      ["Secrétariat", "Inscriptions, dossiers élèves, responsables légaux, documents administratifs."],
      ["Admin. technique", "Comptes utilisateurs et journal d'audit, sans accès automatique aux données scolaires."],
      ["Comptable", "Tableau de bord (les frais de scolarité sont prévus dans une version ultérieure)."],
  ])}
  {box("warn", "Séparation volontaire des droits",
   "Saisir une note, la valider et la corriger sont trois droits différents. Un professeur saisit ; " +
   "la direction valide et verrouille ; toute correction après validation exige un motif et reste inscrite " +
   "dans le journal d'audit. C'est ce qui garantit la fiabilité des bulletins.")}
</section>

<!-- ============================== PARTIE 2 ============================= -->
<section class="part" id="p2">
  <div class="part-head">
    <p class="n">Partie 2</p>
    <h1>Guide du directeur</h1>
    <p>De la préparation de l'année scolaire à la remise des bulletins.</p>
  </div>

  <h2 id="d1">1. Lire le tableau de bord</h2>
  <p>Le tableau de bord est la première information à consulter chaque matin. Il affiche
  l'année et la période en cours, quatre indicateurs, les résultats par classe et, à droite,
  les points à traiter.</p>
  <div class="kpi-row">
    <div class="kpi"><p class="v">97</p><p class="l">Élèves inscrits</p><p class="small">54 filles — 43 garçons</p></div>
    <div class="kpi"><p class="v">94,1 %</p><p class="l">Taux de présence</p><p class="small">30 derniers jours</p></div>
    <div class="kpi"><p class="v">42</p><p class="l">Absences non justifiées</p><p class="small">À traiter</p></div>
    <div class="kpi"><p class="v">12</p><p class="l">Notes manquantes</p><p class="small">Saisies incomplètes</p></div>
  </div>
  {figure("02_tableau_direction", "Tableau de bord de la direction : indicateurs, résultats par classe (moyenne, élèves évalués, taux de réussite) et alertes « Points à traiter en priorité ».")}
  <ul>
    <li><b>Élèves inscrits</b> — effectif de l'année courante, avec la répartition filles / garçons.</li>
    <li><b>Taux de présence</b> — sur les 30 derniers jours, calculé sur les pointages enregistrés.</li>
    <li><b>Absences non justifiées</b> — à faire traiter par la surveillance.</li>
    <li><b>Notes manquantes</b> — évaluations publiées non complétées : elles empêchent de valider une période.</li>
  </ul>
  {box("tip", "Deux actions accessibles depuis l'en-tête",
   "Quand une période est soumise, un encadré <b>« Période à valider »</b> apparaît avec le bouton " +
   "<b>Valider maintenant →</b>. Les professeurs, eux, y trouvent le bouton <b>Soumettre</b> de la période.")}

  <h2 id="d2">2. Préparer l'année scolaire et les périodes</h2>
  <p>Rubrique <span class="menu">STRUCTURE → Années scolaires</span>. C'est le point de départ :
  tant qu'une année et ses périodes ne sont pas définies, rien d'autre ne peut être saisi.</p>
  <ol class="steps">
    <li>Cliquez sur <span class="menu">+ NOUVELLE ANNÉE</span>, indiquez le libellé
        (ex. <code>2027-2028</code>), la date de début et la date de fin, puis
        <span class="menu">Créer</span>.</li>
    <li>Dans la carte de l'année, cliquez sur <span class="menu">+ Ajouter une période</span> et
        créez les trimestres : nom, dates, <b>poids</b> (utilisé pour la moyenne annuelle) et
        type (trimestre ou semestre).</li>
    <li>Cliquez sur <span class="menu">Définir comme courante</span> : c'est l'année qui sera
        proposée partout ailleurs (classes, inscriptions, notes).</li>
    <li>En fin d'année, utilisez <span class="menu">Clôturer</span> : l'année passe en
        « Clôturée » et reste consultable.</li>
  </ol>
  {figure("03_annees_scolaires", "Année courante, périodes, poids et statut de chaque période. C'est aussi ici que se trouvent les boutons Valider, Verrouiller et Réouvrir une période.")}
  {box("warn", "Ordre à respecter",
   "Créez d'abord l'année, puis ses périodes, puis les classes de cette année. Une classe appartient " +
   "toujours à une année : c'est ce qui permet, dans cinq ans, de retrouver la classe exacte d'un élève.")}

  <h2 id="d3">3. Configurer la structure</h2>
  <p>Une seule fois par cycle d'enseignement, puis à chaque rentrée pour les classes :</p>
  <h3>Cycles et niveaux</h3>
  <p><span class="menu">STRUCTURE → Cycles &amp; niveaux</span>. Créez les cycles
  (Primaire, Secondaire — Collège, Secondaire — Lycée) puis les niveaux de chaque cycle
  (CP1, CP2, CE1… Sixième, Cinquième…). Les niveaux servent à classer les élèves et à
  regrouper les statistiques.</p>
  <h3>Matières</h3>
  <p><span class="menu">STRUCTURE → Matières</span> : créez chaque matière avec son nom, son code
  (ex. <code>MATH</code>) et une description. Une matière devenue inutile se
  <b>désactive</b> plutôt que de se supprimer.</p>
  {figure("08_matieres", "Liste des matières. Le code court sert aux exports et à la lecture rapide des bulletins.")}
  <h3>Classes</h3>
  <ol class="steps">
    <li><span class="menu">STRUCTURE → Classes</span> puis <span class="menu">+ Nouvelle classe</span> :
        choisissez l'année scolaire, le niveau, le nom (<code>6ème A</code>), la capacité,
        le professeur titulaire et la salle.</li>
    <li>Ouvrez la fiche de la classe (clic sur son nom) : vous y inscrivez les élèves, vous
        ajoutez les <b>matières avec leur coefficient</b> et vous <b>affectez les professeurs</b>.</li>
    <li>Pour chaque matière de la classe, précisez le <b>coefficient</b>, le <b>barème maximum</b>
        et si la matière est <b>notée</b> (langue vivante non notée, dispense collective, etc.).</li>
    <li>Affectez le professeur en choisissant la matière : c'est cette affectation qui détermine
        ce que le professeur verra et pourra saisir.</li>
  </ol>
  {figure("07_fiche_classe", "Fiche d'une classe : élèves inscrits, matières et coefficients, professeurs affectés, et liens rapides vers les présences et les bulletins.")}
  {box("danger", "Point le plus important de la configuration",
   "Un professeur ne voit ni ne peut noter une classe à laquelle il n'est pas affecté. Si un professeur " +
   "signale qu'il « ne voit pas sa classe », c'est presque toujours une affectation manquante sur la fiche de la classe.")}

  <h2 id="d4">4. Élèves, inscriptions et responsables légaux</h2>
  <p>Rubrique <span class="menu">SCOLARITÉ → Élèves</span>.</p>
  <ol class="steps">
    <li><span class="menu">+ Nouvel élève</span> : identité, date et lieu de naissance, sexe,
        nationalité (Congo par défaut), adresse. Le <b>matricule</b> est généré automatiquement
        (forme <code>EPSV-2026-0001</code>) et ne change jamais.</li>
    <li>Sur la fiche de l'élève, cliquez sur <span class="menu">Ajouter</span> dans la section des
        responsables légaux : nom, lien de parenté, téléphone. Cochez
        <b>Responsable principal</b> ou <b>Contact d'urgence</b> (un seul responsable principal
        à la fois : désigner un nouveau principal retire le drapeau au précédent).</li>
    <li>Cliquez sur <span class="menu">Inscrire</span> pour placer l'élève dans une classe de
        l'année courante, avec la date d'inscription.</li>
    <li>Pour un départ, utilisez <span class="menu">Archiver</span> : l'élève est désactivé
        mais son historique (notes, présences, bulletins) reste intact.</li>
  </ol>
  {figure("05_fiche_eleve", "Fiche élève : identité, responsables légaux (principal et contact d'urgence), assiduité, dernières absences et historique des inscriptions par année.")}
  <p>Le menu <span class="menu">Inscriptions</span> donne la vue d'ensemble : qui est inscrit
  où, par année et par classe, avec les filtres par statut.</p>
  {box("info", "Jamais de suppression d'un élève",
   "L'application privilégie la désactivation et l'archivage : les notes et bulletin déjà délivrés " +
   "doivent rester vérifiables des années plus tard.")}

  <h2 id="d5">5. Suivre les présences</h2>
  <p>L'appel est fait par les professeurs et les surveillants ; la direction en contrôle les
  résultats et traite les absences.</p>
  <ol class="steps">
    <li><span class="menu">SCOLARITÉ → Présences</span> : les séances récentes sont listées
        (date, classe, créneau, auteur de l'appel, absents, retards).</li>
    <li>Le bouton <span class="menu">Rapport</span> d'une classe donne, élève par élève : séances,
        présences, absences, retards, absences justifiées et <b>taux de présence</b> — le document
        à produire pour la surveillance ou lors d'un entretien avec les parents.</li>
    <li>Pour justifier une absence ou un retard, ouvrez la séance concernée
        (<span class="menu">Ouvrir</span>) puis, sur la ligne de l'élève,
        <span class="menu">Justifier</span>. La correction d'un statut passe par
        <span class="menu">Corriger</span> et demande un motif.</li>
  </ol>
  {figure("09_presences", "Présences : ouverture d'un nouvel appel et séances récentes avec, pour chacune, le nombre d'absents et de retards.")}
  {box("tip", "Définir le dénominateur",
   "Le taux de présence se calcule sur les séances réellement enregistrées. Indiquez aux professeurs " +
   "de faire l'appel une fois par demi-journée, toujours avec le même créneau : les statistiques resteront comparables.")}

  <h2 id="d6">6. Valider et verrouiller les résultats</h2>
  <p>Les notes suivent un circuit strict, période par période :</p>
  {table(["Statut de la période", "Ce qui est possible", "Qui agit"], [
      ["Ouverte", "Les professeurs créent les évaluations et saisissent les notes.", "Professeur"],
      ["Soumise", "La saisie est terminée ; la direction contrôle et valide.", "Professeur (soumission)"],
      ["Validée", "Les résultats sont arrêtés ; la période peut être verrouillée.", "Direction"],
      ["Verrouillée", "Plus aucune modification sans réouverture explicite et tracée.", "Direction"],
  ])}
  <ol class="steps">
    <li>Vérifiez sur le tableau de bord qu'il ne reste pas d'alertes « Notes en retard » pour la
        période concernée.</li>
    <li>À réception d'une période <b>soumise</b>, cliquez sur
        <span class="menu">Valider maintenant →</span> (tableau de bord) ou
        <span class="menu">Valider</span> sur la ligne de la période
        (<span class="menu">Années scolaires</span>).</li>
    <li>Cliquez ensuite sur <span class="menu">Verrouiller</span> : les bulletins doivent être
        édités sur des résultats figés.</li>
    <li>En cas d'erreur constatée après verrouillage, <span class="menu">Réouvrir</span> la période,
        faites corriger par le professeur (ou par vous-même avec le motif de correction), puis
        validez et verrouillez de nouveau.</li>
  </ol>
  {box("danger", "Une note validée ne s'écrase jamais en silence",
   "Toute correction après validation conserve l'ancienne valeur, la nouvelle, l'auteur, l'heure et le " +
   "motif dans le journal d'audit. Toute correction sans motif doit être refusée.")}

  <h2 id="d7">7. Générer et valider les bulletins</h2>
  <p>Rubrique <span class="menu">SCOLARITÉ → Bulletins</span>, après validation de la période.</p>
  <ol class="steps">
    <li>Filtrez par classe et par période, puis cliquez sur <span class="menu">Générer</span> pour
        un élève, ou sur <span class="menu">Générer tous les bulletins</span> pour toute la classe
        (une seule opération, un PDF par élève).</li>
    <li>Chaque bulletin porte une <b>version</b> (v1, v2…). Une nouvelle génération crée une
        nouvelle version : le bulletin déjà remis aux parents n'est jamais modifié.</li>
    <li>Contrôlez le rendu avec <span class="menu">Voir</span> (aperçu à l'écran) puis
        <span class="menu">PDF</span> pour le fichier à imprimer ou à transmettre.</li>
    <li>Le résumé de classe (depuis la fiche de la classe) donne la liste des moyennes et des rangs,
        utile pour le conseil de classe.</li>
  </ol>
  {figure("12_bulletins", "Bulletins : moyenne, rang et version par élève ; génération individuelle ou pour toute la classe.")}
  {box("info", "Documents confidentiels",
   "Les bulletins sont stockés dans un espace privé du serveur : aucun bulletin n'est accessible " +
   "sans être connecté avec un compte autorisé. Le bulletin ne contient que les matières réellement " +
   "notées pour la période.")}

  <h2 id="d8">8. Créer et gérer les comptes</h2>
  <p>Rubrique <span class="menu">ADMINISTRATION → Utilisateurs</span>.</p>
  <ol class="steps">
    <li><span class="menu">+ Nouvel utilisateur</span> : nom, e-mail, fonction et rôle, mot de passe
        initial à communiquer à l'intéressé.</li>
    <li>Un même utilisateur peut cumuler plusieurs fonctions (par exemple professeur et surveillant) :
        ajoutez alors les deux rôles.</li>
    <li><span class="menu">Réinitialiser mot de passe</span> — c'est la seule façon de dépanner un
        professeur qui a perdu son mot de passe (il n'y a pas d'auto-réinitialisation).</li>
    <li>Un départ ou une absence prolongée : <b>désactivez</b> le compte. Un compte désactivé ne
        peut plus se connecter, sans perdre son historique.</li>
  </ol>
  {figure("13_utilisateurs", "Comptes : nom, e-mail, fonction, rôle, statut, dernière connexion, et les actions Modifier et Réinitialiser mot de passe.")}
  {box("warn", "Comptes nominatifs uniquement",
   "Un compte par personne, jamais un compte partagé « professeur » : l'auteur de chaque appel et de " +
   "chaque note doit être identifiable. Le mot de passe doit être changé par l'utilisateur dès la " +
   "première connexion (menu Profil).")}

  <h2 id="d9">9. Paramètres de l'école et règles de calcul</h2>
  <p>Rubrique <span class="menu">ADMINISTRATION → Paramètres</span>. Deux parties :</p>
  <h3>Identité de l'établissement</h3>
  <p>Nom, sigle, devise, adresse, téléphone, e-mail, site, nom et titre du signataire — ce sont ces
  informations qui figurent en tête des bulletins.</p>
  <h3>Règles pédagogiques</h3>
  {table(["Paramètre", "Signification"], [
      ["Barème cible", "Barème sur lequel les moyennes sont ramenées (20 par défaut)."],
      ["Décimales", "Nombre de décimales affichées sur les bulletins (2 par défaut)."],
      ["Arrondi", "Méthode d'arrondi appliquée en fin de calcul."],
      ["Seuil de réussite", "Moyenne à partir de laquelle un élève est déclaré admis (10 par défaut)."],
      ["Classement", "Activer ou désactiver l'affichage des rangs."],
      ["Note manquante", "Ce qu'on fait d'une note non saisie : jamais comptée comme zéro."],
      ["Absence", "Une absence est exclue du calcul par défaut (décision de l'école)."],
      ["Pondération annuelle", "Utilisation des poids des périodes pour la moyenne annuelle."],
      ["Seuils d'alerte", "Nombre d'absences déclenchant une alerte, fenêtre en jours, délai de saisie des notes."],
  ])}
  {figure("14_parametres", "Paramètres : identité de l'école (en-tête des bulletins) et règles de calcul modifiables sans intervention technique.")}
  {box("warn", "À faire valider avant la première période",
   "Faites confirmer ces valeurs par écrit par la direction (voir annexe E) : barème, coefficients par " +
   "matière, seuil de réussite, règles de classement et modèle de bulletin. Ce sont ces choix — et non " +
   "la technique — qui déterminent les bulletins remis aux familles.")}

  <h2 id="d10">10. Vérifier les actions : journal d'audit</h2>
  <p>Rubrique <span class="menu">ADMINISTRATION → Journal d'audit</span>. Chaque action sensible y est
  inscrite : date et heure, utilisateur, action, objet concerné, motif et adresse IP.</p>
  <p>À consulter en priorité : les corrections de notes après validation, les modifications
  d'inscription, les changements de statut d'une période et les actions sur les comptes.</p>
  {figure("15_audit", "Journal d'audit : qui a fait quoi, quand, sur quel objet, avec quel motif.")}

  <h2 id="d11">11. Sauvegardes et restauration</h2>
  <p>Une sauvegarde n'a de valeur que si sa restauration a été testée. À prévoir :</p>
  <ul>
    <li><b>Base de données</b> — export quotidien, conservé au moins 30 jours, dont une copie hors du
        serveur de l'école.</li>
    <li><b>Documents</b> — le dossier privé des bulletins
        (<code>storage/app/private</code>) est sauvegardé en même temps que la base, sinon les
        bulletins déjà émis deviennent introuvables.</li>
    <li><b>Test de restauration</b> — une fois par trimestre, sur un serveur de test, avant l'ouverture
        aux utilisateurs.</li>
  </ul>
  {box("tip", "Deux échéances dans l'année",
   "Après la validation du dernier trimestre et avant la clôture de l'année : sauvegarde complète " +
   "étiquetée et rangée à part. C'est votre point de retour en cas de problème.")}
  <p class="small">Les commandes exactes, la configuration du serveur, HTTPS, les variables
  d'environnement et la procédure de restauration détaillée figurent dans
  <code>docs/deployment.md</code> du projet.</p>
</section>

<!-- ============================== PARTIE 3 ============================= -->
<section class="part" id="p3">
  <div class="part-head">
    <p class="n">Partie 3</p>
    <h1>Guide du professeur</h1>
    <p>L'appel, les évaluations, la saisie des notes et la soumission des résultats.</p>
  </div>

  <h2 id="e1">1. Se connecter et retrouver ses classes</h2>
  <p>Votre compte vous est remis par la direction. À la première connexion, changez votre mot de
  passe depuis le menu <span class="menu">Profil</span> (en bas du menu latéral).</p>
  <p>Vous ne voyez que <b>vos classes et vos matières</b> : c'est l'affectation faite par la direction
  sur la fiche de chaque classe.</p>
  {figure("17_classes_professeur", "Vos classes : niveau, cycle, titulaire, effectif, salle et statut.", "17_classes_professeur")}
  {figure("16_tableau_professeur", "Votre tableau de bord : indicateurs, résultats de vos classes et alertes de notes à saisir.")}
  <p>Le bloc <b>« Points à traiter en priorité »</b> liste les notes en retard, matière par matière :
  c'est votre liste de travail avant la fin de la période.</p>

  <h2 id="e2">2. Faire l'appel</h2>
  <ol class="steps">
    <li><span class="menu">SCOLARITÉ → Présences</span>, carte
        <b>« Nouvel appel »</b>.</li>
    <li>Choisissez la <b>classe</b>, la <b>date</b> (aujourd'hui par défaut) et le
        <b>créneau</b> : Matin, Après-midi ou Journée.</li>
    <li>Cliquez sur <span class="menu">Appel</span> : la liste des élèves s'affiche.</li>
    <li>Marquez chacun : présent, absent ou en retard. Les élèves sont listés dans l'ordre
        alphabétique, prénom après nom, comme sur votre liste papier.</li>
    <li><span class="menu">Enregistrer</span>. La séance apparaît dans les séances récentes, avec
        vos nom et nombre d'absents.</li>
  </ol>
  {figure("18_appel_professeur", "Présences : ouverture d'un nouvel appel (classe, date, créneau) et séances récentes que vous avez prises.")}
  {box("info", "Se tromper est rattrapable",
   "Une séance déjà enregistrée peut être rouverte (<span class=\"menu\">Ouvrir</span>). La " +
   "correction d'un statut (<span class=\"menu\">Corriger</span>) et la justification d'une absence " +
   "(<span class=\"menu\">Justifier</span>) conservent la trace de l'ancienne valeur et demandent un motif.")}

  <h2 id="e3">3. Créer une évaluation</h2>
  <ol class="steps">
    <li><span class="menu">SCOLARITÉ → Notes &amp; évaluations</span> puis
        <span class="menu">+ NOUVELLE ÉVALUATION</span>.</li>
    <li>Choisissez la <b>classe</b>, la <b>matière</b>, la <b>période</b>, le <b>type</b>
        (devoir, composition…), la <b>date</b>, le <b>barème</b> (ex. 20) et le
        <b>coefficient</b> de l'évaluation.</li>
    <li>Validez avec <span class="menu">Créer l'évaluation</span> : vous êtes conduit directement à
        la grille de saisie.</li>
    <li>L'évaluation reste modifiable tant qu'elle n'est pas publiée ; le bouton
        <span class="menu">Publier</span> la rend visible comme travail officiel de la période.</li>
    <li>Une évaluation erronée s'<b>annule</b> (<span class="menu">Annuler</span>) plutôt que de se
        supprimer : elle est alors exclue du calcul des moyennes sans effacer son historique.</li>
  </ol>
  {figure("19_evaluations_professeur", "Évaluations de vos classes : type, date, barème, coefficient, progression de la saisie et statut.")}
  {box("tip", "Barème et coefficient",
   "Le <b>barème</b> est le maximum de l'évaluation (20, 10, 40…). Le <b>coefficient</b> est son poids " +
   "dans la moyenne de la matière. Exemple : composition coefficient 2 compte deux fois plus qu'un devoir " +
   "coefficient 1.")}

  <h2 id="e4">4. Saisir les notes</h2>
  <ol class="steps">
    <li>Depuis la liste des évaluations, cliquez sur <span class="menu">Saisir les notes</span>
        (ou <span class="menu">Modifier</span> en haut de la grille).</li>
    <li>Saisissez la note de chaque élève. Vous pouvez utiliser la touche <b>Tabulation</b> pour
        passer d'un élève au suivant.</li>
    <li>Pour un élève qui n'a pas composé, choisissez le statut
        <b>« Absent(e) »</b> ; pour un élève dispensé, <b>« Dispensé(e) »</b>. N'inscrivez
        <b>jamais 0</b> à la place d'une absence : la moyenne serait faussée.</li>
    <li>Le champ <b>Commentaire</b> est facultatif ; il sert à noter un cas particulier
        (copie non rendue, problème de santé…).</li>
    <li>Cliquez sur <span class="menu">Enregistrer les notes</span>. Vous pouvez revenir autant de
        fois que nécessaire tant que la période est ouverte.</li>
  </ol>
  {figure("11_saisie_notes", "Grille de saisie : barème et coefficient rappelés en haut, un champ de note et un statut par élève. Le bouton « Afficher uniquement les non saisies » permet de finir vite.")}
  {table(["Statut de la note", "Effet sur la moyenne"], [
      ["Saisie", "La note compte, normalisée sur le barème cible."],
      ["Absent(e)", "La note est exclue du calcul (elle ne vaut pas zéro)."],
      ["Dispensé(e)", "La note est exclue du calcul."],
      ["Non saisie", "La note est manquante : elle empêche la validation de la période."],
  ])}
  {box("warn", "Les trois règles à ne pas oublier",
   "1) ne jamais mettre 0 pour un absent ; 2) toute note saisie est définitive après validation de la " +
   "période, sauf correction motivée ; 3) vérifiez le nombre de notes manquantes affiché en haut de la " +
   "grille avant de soumettre.")}

  <h2 id="e5">5. Soumettre les résultats de la période</h2>
  <ol class="steps">
    <li>Assurez-vous que toutes vos évaluations sont terminées : plus aucune note manquante, aucune
        alerte « Notes en retard » à votre nom.</li>
    <li>Sur votre tableau de bord, en haut à droite, cliquez sur
        <span class="menu">SOUMETTRE [période]</span> et confirmez.</li>
    <li>La période passe en « Soumise » : la direction contrôle, valide, puis verrouille.</li>
    <li>Après verrouillage, la saisie est fermée. Si une erreur est découverte, signalez-la à la
        direction : elle peut réouvrir la période pour une correction motivée et tracée.</li>
  </ol>
  {box("info", "Pourquoi cette étape ?",
   "C'est le moment où vous déclarez « mes notes sont complètes et vérifiées ». La direction valide sur " +
   "cette base et les bulletins sont ensuite édités. C'est ce qui évite qu'une note soit modifiée après " +
   "la remise des bulletins aux familles.")}

  <h2 id="e6">6. Consulter les bulletins</h2>
  <p>Rubrique <span class="menu">SCOLARITÉ → Bulletins</span> : vous consultez les bulletins de vos
  élèves (moyenne, rang, version) et le PDF, dès que la direction les a générés. Le
  <span class="menu">Voir</span> affiche l'aperçu à l'écran ; <span class="menu">PDF</span>
  télécharge le fichier.</p>
  {box("tip", "Préparer le conseil de classe",
   "Le résumé de la classe (fiche de la classe → bulletins) permet de comparer les moyennes et les rangs " +
   "avant la réunion. Rappel utile : un écart de moyenne entre deux classes reflète aussi la difficulté " +
   "des sujets et le niveau réel des élèves — il ne constitue pas à lui seul un jugement sur un professeur.")}

  <h2 id="e7">7. Comment les moyennes sont calculées</h2>
  <p>Vous n'avez rien à calculer : la plateforme applique les règles validées par la direction.
  Voici ce qui se passe, en clair.</p>
  <h3>Moyenne d'une matière</h3>
  <p>Chaque note est d'abord ramenée au barème cible (20 par défaut) si l'évaluation avait un autre
  barème, puis pondérée par le coefficient de l'évaluation :</p>
  <p class="small">Moyenne de matière = somme (note × coefficient) ÷ somme des coefficients.</p>
  <p><i>Exemple :</i> 12/20 coefficient 1 et 16/20 coefficient 2 → (12×1 + 16×2) ÷ 3 = <b>14,67/20</b>.</p>
  <h3>Moyenne générale</h3>
  <p class="small">Moyenne générale = somme (moyenne de matière × coefficient de la matière)
  ÷ somme des coefficients des matières.</p>
  <p>Seules les matières notées et réellement évaluées entrent dans le calcul. Une matière sans
  aucune note ne fausse pas la moyenne : elle est simplement absente du bulletin.</p>
  <h3>Moyenne annuelle</h3>
  <p class="small">Moyenne annuelle = somme (moyenne de période × poids de la période)
  ÷ somme des poids.</p>
  <h3>Ce qui est exclu du calcul</h3>
  <ul>
    <li>Une évaluation <b>annulée</b>.</li>
    <li>Une note <b>Absent(e)</b> ou <b>Dispensé(e)</b>.</li>
    <li>Une note <b>non saisie</b> : elle n'est jamais comptée comme zéro, mais elle bloque la
        validation de la période jusqu'à régularisation.</li>
  </ul>
  {box("info", "Rangs et ex æquo",
   "Le classement se fait par classe et par période. Deux élèves ayant la même moyenne obtiennent le " +
   "même rang. Si l'école ne souhaite pas de classement, la direction le désactive dans les Paramètres.")}
</section>

<!-- ============================== ANNEXES ============================== -->
<section class="part" id="pa">
  <div class="part-head">
    <p class="n">Annexes</p>
    <h1>Références et outils</h1>
    <p>Tableaux à garder sous la main, dépannage et fiche à compléter avec la direction.</p>
  </div>

  <h2 id="a1">A. Rôles et permissions</h2>
  {table(["Droit", "Direction", "Professeur", "Surveillant", "Secrétariat", "Admin. tech."], [
      ["Consulter la structure (classes, matières)", "Oui", "—", "Oui", "Oui", "—"],
      ["Gérer la structure", "Oui", "—", "—", "—", "—"],
      ["Consulter / gérer les élèves", "Oui / Oui", "Oui / —", "Oui / —", "Oui / Oui", "—"],
      ["Gérer les inscriptions", "Oui", "—", "—", "Oui", "—"],
      ["Faire l'appel", "—", "Oui", "Oui", "—", "—"],
      ["Corriger un appel / justifier", "Oui / Oui", "—", "Oui / Oui", "— / Oui", "—"],
      ["Consulter les notes", "Oui", "Oui", "—", "Oui", "—"],
      ["Saisir les notes", "—", "Oui", "—", "—", "—"],
      ["Soumettre une période", "—", "Oui", "—", "—", "—"],
      ["Valider / verrouiller une période", "Oui / Oui", "—", "—", "—", "—"],
      ["Corriger une note validée (motif)", "Oui", "—", "—", "—", "—"],
      ["Consulter / générer les bulletins", "Oui / Oui", "Oui / —", "—", "Oui / —", "—"],
      ["Gérer les comptes utilisateurs", "Oui", "—", "—", "—", "Oui"],
      ["Paramètres de l'école", "Oui", "—", "—", "—", "—"],
      ["Journal d'audit", "Oui", "—", "—", "—", "Oui"],
  ])}
  {box("info", "Les droits se cumulent",
   "Si la direction l'autorise, une même personne peut recevoir plusieurs rôles (professeur et " +
   "surveillant, par exemple) : elle dispose alors de la réunion des droits.")}

  <h2 id="a2">B. Comptes et mots de passe</h2>
  <p>Les comptes sont créés par la direction ; il n'y a pas d'inscription publique ni
  d'auto-réinitialisation.</p>
  {table(["Fonction", "Adresse e-mail", "Usage"], [
      ["Direction / propriétaire", "direction@epsv.cg", "Pilotage, structure, validation, bulletins, comptes"],
      ["Professeur", "prof.mabiala@epsv.cg", "Appel, évaluations, notes, soumission"],
      ["Surveillant", "surveillant@epsv.cg", "Appels, absences, justifications"],
      ["Secrétariat", "secretariat@epsv.cg", "Inscriptions et dossiers élèves"],
      ["Admin. technique", "admin@epsv.cg", "Comptes et journal d'audit"],
  ])}
  {box("danger", "Avant la mise en service",
   "Ces comptes et le mot de passe de démonstration doivent être remplacés : créez un compte personnel " +
   "par membre du personnel, avec un mot de passe propre à chacun, et désactivez les comptes de test. " +
   "Aucun mot de passe ne doit circuler par affichage ou par note affichée au bureau.")}

  <h2 id="a3">C. Questions fréquentes</h2>
  <table>
    <thead><tr><th style="width:44%">Situation</th><th>Que faire</th></tr></thead>
    <tbody>
      <tr><td>J'ai oublié mon mot de passe.</td><td>Contactez la direction : elle utilise
          « Réinitialiser mot de passe » et vous communique un mot de passe provisoire, à changer
          dès la connexion.</td></tr>
      <tr><td>Mon compte est désactivé.</td><td>Le message de connexion vous le signale. Demandez la
          réactivation à la direction ; l'historique du compte est conservé.</td></tr>
      <tr><td>Je ne vois pas une de mes classes.</td><td>L'affectation manque sur la fiche de la classe
          (section « Affectations des enseignants »). La direction l'ajoute : la classe apparaît
          immédiatement.</td></tr>
      <tr><td>Je ne peux pas modifier une note.</td><td>La période est probablement validée ou
          verrouillée. Signalez la correction à la direction, qui réouvre la période et laisse une
          trace avec motif.</td></tr>
      <tr><td>La liste des élèves d'une évaluation est vide.</td><td>Vérifiez la classe choisie et que
          des élèves y sont réellement inscrits pour l'année courante
          (<span class="menu">Inscriptions</span>).</td></tr>
      <tr><td>Une moyenne me paraît fausse.</td><td>Vérifiez le barème et le coefficient de
          l'évaluation, puis les statuts : une note « Non saisie », « Absent(e) » ou « Dispensé(e) »
          est exclue du calcul. Les paramètres de calcul sont visibles dans
          <span class="menu">Paramètres</span>.</td></tr>
      <tr><td>Un bulletin ne montre pas toutes les matières.</td><td>Seules les matières notées et
          réellement évaluées sur la période apparaissent. Une matière sans note n'entre pas dans le
          bulletin.</td></tr>
      <tr><td>Le PDF ne s'ouvre pas.</td><td>Utilisez le bouton <span class="menu">PDF</span> depuis
          la liste des bulletins ; le fichier se télécharge dans vos téléchargements.</td></tr>
      <tr><td>La page met du temps à s'ouvrir le matin.</td><td>Sur l'environnement de démonstration,
          le service se met en veille après 15 minutes d'inactivité : le premier accès de la journée
          peut demander une minute.</td></tr>
      <tr><td>J'ai saisi une note pour le mauvais élève.</td><td>Tant que la période est ouverte,
          corrigez directement dans la grille. Après validation, la correction passe par le circuit
          motivé de la direction.</td></tr>
    </tbody>
  </table>

  <h2 id="a4">D. Checklist de fin de trimestre</h2>
  <h3>Pour chaque professeur</h3>
  <ul>
    <li>Toutes les évaluations de la période sont créées, publiées et complètes.</li>
    <li>Aucune note manquante dans mes matières (vérifier les alertes du tableau de bord).</li>
    <li>Les absences et retards sont justifiés lorsque les parents l'ont fait.</li>
    <li>Les résultats de la période sont <b>soumis</b> à la direction.</li>
  </ul>
  <h3>Pour la direction</h3>
  <ul>
    <li>Contrôle des moyennes sur quelques bulletins avant diffusion.</li>
    <li>Période <b>validée</b> puis <b>verrouillée</b>.</li>
    <li>Bulletins générés pour toutes les classes, versions vérifiées, PDF archivés.</li>
    <li>Sauvegarde complète (base + documents) étiquetée et rangée hors du serveur.</li>
    <li>Journal d'audit relu : corrections de notes justifiées.</li>
  </ul>

  <h2 id="a5">E. Fiche à valider par la direction</h2>
  <p>À compléter et à signer avant la première période. Ces valeurs sont ensuite saisies dans
  <span class="menu">Paramètres</span> et sur les fiches de classes.</p>
  {table(["Point à décider", "Décision de l'école"], [
      ["Barème des notes", ""],
      ["Nombre de décimales affichées", ""],
      ["Règle d'arrondi", ""],
      ["Seuil de réussite (moyenne d'admission)", ""],
      ["Périodes : trimestres ou semestres, et leurs poids", ""],
      ["Pondération des périodes pour la moyenne annuelle", ""],
      ["Classement : activé ? mode de calcul des rangs", ""],
      ["Traitement des notes manquantes / dispenses", ""],
      ["Traitement des absences dans les moyennes", ""],
      ["Coefficients par matière et par niveau", ""],
      ["Modèle de bulletin par cycle", ""],
      ["Appréciations : barème et libellés", ""],
      ["Seuils d'alerte (absences répétées, retard de saisie)", ""],
  ])}
  {box("tip", "Le meilleur test qui existe",
   "Prenez un <b>bulletin réel anonymisé</b> d'un trimestre passé, recalculez la moyenne de deux ou trois " +
   "matières avec les règles ci-dessus, puis comparez avec le bulletin papier. Si les résultats concordent, " +
   "les règles sont justes. Sinon, ajustez les paramètres — jamais le code.")}

  <h2 id="a6">F. Documents du projet</h2>
  <p>Pour l'équipe technique et la maintenance, le dépôt du projet contient :</p>
  {table(["Document", "Contenu"], [
      ["<code>README.md</code>", "Présentation, installation locale, commandes courantes."],
      ["<code>docs/architecture.md</code>", "Choix techniques, organisation du code."],
      ["<code>docs/database-schema.md</code>", "Structure des données et relations."],
      ["<code>docs/calculs-scolaires.md</code>", "Formules, arrondis et cas limites, avec exemples."],
      ["<code>docs/permissions.md</code>", "Rôles, droits et règles d'affectation."],
      ["<code>docs/implementation-plan.md</code>", "Phases de réalisation et critères d'acceptation."],
      ["<code>docs/deployment.md</code>", "Installation serveur, HTTPS, sauvegardes, restauration, mise à jour."],
  ])}
  <p class="small" style="margin-top:14pt">Guide d'utilisation — École Panafricaine Séguynaud de Vinci
  (EPSV) · Version 1.0 · {DOC_DATE} · Interface en ligne : <code>{PDF_URL}</code></p>
</section>
"""

HTML = f"""<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8">
<title>Guide d'utilisation — Plateforme de gestion scolaire EPSV</title>
<style>{CSS}</style></head>
<body>{BODY}</body></html>
"""

out = os.path.join(HERE, "guide.html")
with open(out, "w", encoding="utf-8") as fh:
    fh.write(HTML)

total = sum(os.path.getsize(os.path.join(IMG_OUT, f)) for f in os.listdir(IMG_OUT))
print(f"guide.html écrit ({len(HTML)//1024} Ko), {len(SHOTS)} images optimisées ({total//1024} Ko au total)")
print("numéros de page du sommaire :", "injectés" if TOC_FR else "absents (première passe)")
