#!/usr/bin/env bash
#
# Point d'entrée du conteneur EPSV :
#   1. configure nginx sur le port fourni par Render ($PORT)
#   2. garantit une APP_KEY
#   3. attend la disponibilité de MySQL
#   4. exécute les migrations, les seeders nécessaires
#   5. met en cache la configuration et démarre nginx + php-fpm
#
set -euo pipefail

cd /var/www/html

export PORT="${PORT:-10000}"
echo "[entrypoint] Démarrage EPSV sur le port ${PORT}"

# URL publique : Render fournit RENDER_EXTERNAL_URL
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
    echo "[entrypoint] APP_URL=${APP_URL}"
fi

# 1. Configuration nginx (substitution du port uniquement).
#    La template EST la configuration complète : elle remplace /etc/nginx/nginx.conf.
#    (La placer dans conf.d/ la rendrait imbriquée dans le bloc http → erreur.)
envsubst '${PORT}' < /etc/nginx/templates/laravel.conf.template > /etc/nginx/nginx.conf

# Contrôle de syntaxe avant démarrage (échec immédiat et lisible si la config est cassée)
nginx -t -q || { echo "[entrypoint] Configuration nginx invalide — arrêt."; exit 1; }

# 2. Clé d'application
if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] APP_KEY absente — génération d'une clé (définissez-la durablement dans les variables Render)."
    php artisan key:generate --force --no-interaction
fi

# 3. Attente de la base de données
if [ "${DB_CONNECTION:-mysql}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Attente de MySQL sur ${DB_HOST}:${DB_PORT:-3306}…"
    for i in $(seq 1 90); do
        if php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; then
            echo "[entrypoint] MySQL est disponible."
            break
        fi
        if [ "$i" = "90" ]; then
            echo "[entrypoint] MySQL injoignable après 180 s — arrêt."
            exit 1
        fi
        sleep 2
    done
fi

# Migrations — EPSV_RESET_DB=true recrée tout le schéma (usage ponctuel :
# incident de premier déploiement). À retirer ensuite pour ne pas effacer
# les données à chaque redéploiement.
if [ "${EPSV_RESET_DB:-false}" = "true" ]; then
    echo "[entrypoint] EPSV_RESET_DB=true : recréation complète du schéma."
    php artisan migrate:fresh --force --no-interaction
else
    php artisan migrate --force --no-interaction
fi

# Rôles, permissions et profil de l'école (idempotents)
php artisan db:seed --class=RolePermissionSeeder --force --no-interaction
php artisan db:seed --class=SchoolProfileSeeder --force --no-interaction

# Données de démonstration : le seeder est idempotent (il s'ignore si la base
# contient déjà des données) et ne dépend d'aucun paquet de développement.
if [ "${EPSV_SEED_DEMO:-false}" = "true" ]; then
    echo "[entrypoint] Données de démonstration (ignorées si déjà présentes)."
    php artisan db:seed --class=DemoDataSeeder --force --no-interaction
fi

# 5. Caches de production + lien de stockage
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

# Permissions du stockage (disque éventuellement monté)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Prêt — démarrage de nginx et php-fpm."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
