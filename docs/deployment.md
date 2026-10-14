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

Render n'offre pas de MySQL managé : le fichier `render.yaml` (Blueprint) définit

- **service web Docker** : l'application Laravel (Dockerfile multi-stage, PHP 8.4-FPM + Nginx, migrations au démarrage) ;
- **service privé MySQL 8** : conteneur officiel `mysql:8` accessible uniquement sur le réseau privé, avec **disque persistant** monté sur `/var/lib/mysql` ;
- les variables d'environnement (mot de passe MySQL généré par Render, `APP_KEY`, domaine).

Déploiement :

```bash
render login                 # ou export RENDER_API_KEY=...
render blueprints validate   # valider render.yaml
# Créer le Blueprint depuis le tableau de bord Render (New > Blueprint) en
# connectant le dépôt GitHub — ou :
render services list
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
