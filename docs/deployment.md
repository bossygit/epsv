# Déploiement

## 1. Prérequis

- Linux (distribution LTS maintenue), Nginx ou Apache, PHP compatible, MySQL maintenu, HTTPS, Git, Composer.
- L'application est servie depuis `public/`, jamais depuis la racine du projet.
- Les secrets de production ne sont jamais dans Git.

## 2. Variables d'environnement de production

```env
APP_NAME="EPSV - Gestion scolaire"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate
APP_URL=https://votre-domaine

APP_LOCALE=fr
APP_TIMEZONE=Africa/Brazzaville

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=epsv
DB_USERNAME=epsv
DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

EPSV_SEED_DEMO=false          # pas de données fictives en production
EPSV_ADMIN_EMAIL=direction@epsv.cg
EPSV_DEMO_PASSWORD=...        # mot de passe initial du compte direction
```

## 3. Procédure minimale (serveur de l'école)

```bash
git clone <dépôt> /var/www/epsv && cd /var/www/epsv
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env      # renseigner les valeurs ci-dessus
php artisan key:generate
npm ci && npm run build                # ou compiler en local et copier public/build
php artisan migrate --force --seed     # seed = rôles + profil école uniquement
php artisan storage:link               # si des fichiers publics sont nécessaires
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

Nginx : `root /var/www/epsv/public;` + PHP-FPM. HTTPS via Let's Encrypt (`certbot`).

Tâches planifiées (rapports/alertes automatiques V2) :

```
* * * * * cd /var/www/epsv && php artisan schedule:run >> /dev/null 2>&1
```

### Test de recette après déploiement

1. Connexion direction, professeur, surveillant, secrétariat.
2. Faire un appel, saisir une note, soumettre/valider/verrouiller une période.
3. Générer et télécharger un bulletin PDF.
4. Vérifier qu'un professeur n'accède pas à une classe non affectée (403).
5. Vérifier que les bulletins sont bien sur le disque privé (`storage/app/private`).

## 4. Déploiement Render (cloud)

Deux configurations sont fournies dans le dépôt :

| Fichier | Architecture | Prérequis |
| --- | --- | --- |
| `render.yaml` | **Démo gratuite** : service web gratuit + PostgreSQL managé Render (plan gratuit) | Aucun frais |
| `render-mysql-production.yaml` | **Production** : application Docker + MySQL 8 privé avec disque persistant 10 Go | Moyen de paiement Render (**≈ 16 $/mois** : 2 × service Starter + disque) |

⚠️ Render n'offre **pas** de MySQL managé : en production, la base tourne dans un **service privé** accessibles uniquement sur le réseau privé, avec un **disque persistant** monté sur `/var/lib/mysql`. Ces deux éléments sont payants — c'est pourquoi la variante gratuite utilise PostgreSQL (l'application est compatible avec les deux SGBD ; le serveur de l'école utilisera MySQL comme prévu au cahier des charges).

Limites de la variante gratuite :

- Le service web gratuit s'endort après 15 minutes d'inactivité (premier chargement plus lent).
- La base PostgreSQL gratuite **expire après 30 jours** (à convertir en payant pour conserver les données).
- Sans disque persistant, les PDF de bulletins archivés sont régénérés après chaque redéploiement (les notes, elles, restent en base).

### Étapes

1. **Créer le Blueprint** : Render Dashboard → *New* → *Blueprint* → sélectionner le dépôt `bossygit/epsv` → Render lit `render.yaml` et crée les ressources.
2. **Suivre le déploiement** avec la CLI :

```bash
render login                 # authentification (une fois)
render blueprints validate ./render.yaml
render services              # lister app + base
render deploys list <service-id>
render logs --service <service-id> --tail
```

⚠️ Points d'attention :

- Le service MySQL privé doit être démarré **avant** que le web service ne lance ses migrations (le script d'entrée attend la disponibilité de la base).
- Les disques Render ne sont pas sauvegardés automatiquement : programmer des exports (`mysqldump`) vers un stockage externe (voir §5).
- Le disque du service MySQL est facturé et lié au service : ne pas le recréer sans sauvegarde.

## 5. Sauvegardes et restauration

### Sauvegarde (base + documents)

```bash
#!/usr/bin/env bash
set -euo pipefail
DATE=$(date +%F_%H%M)
DEST=/backups/epsv
mkdir -p "$DEST"

# Base de données
mysqldump --single-transaction --routines --triggers \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "$DEST/epsv-db-$DATE.sql.gz"

# Documents privés (bulletins, pièces jointes)
tar czf "$DEST/epsv-storage-$DATE.tar.gz" storage/app/private

# Rétention : 30 jours
find "$DEST" -type f -mtime +30 -delete
```

Sur Render : exécuter cet export depuis un job planifié ou en local avec les identifiants du service MySQL, puis pousser le résultat vers un stockage externe (S3-compatible, Drive, etc.).

### Restauration (procédure testée)

```bash
# 1. Base
gunzip -c epsv-db-2026-10-14.sql.gz | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_DATABASE"

# 2. Documents
tar xzf epsv-storage-2026-10-14.tar.gz -C /var/www/epsv

# 3. Vérifications
php artisan migrate:status
php artisan tinker --execute="echo App\Models\Student::count().' élèves';"
# → se connecter et vérifier un bulletin téléchargeable
```

**Avant l'ouverture aux utilisateurs : effectuer un test réel de restauration sur une base jetable.**

## 6. Mise à jour de l'application

```bash
cd /var/www/epsv
php artisan down                     # mode maintenance
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 7. Sécurité

- `APP_DEBUG=false` en production ; HTTPS obligatoire ; mots de passe de démo changés.
- Inscription publique désactivée ; comptes créés par la direction/l'administrateur.
- Comptes désactivés déconnectés à la requête suivante.
- Journal d'audit consultable (direction, administrateur technique).
- Bulletins et documents hors de `public/`, servis uniquement via des routes autorisées.
