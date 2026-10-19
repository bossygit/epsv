# Notifications Web Push

Les notifications Web Push préviennent un membre de la messagerie lorsqu'un
nouveau message arrive dans une conversation à laquelle il appartient. Elles
sont **optionnelles** : chaque utilisateur les active sur chaque navigateur, et
le badge de non-lus reste le mécanisme de secours.

## Fonctionnement

1. Le membre ouvre **Messagerie** et clique sur **Activer les notifications**.
2. Le navigateur demande l'autorisation, puis crée un abonnement Push
   (`PushManager.subscribe`) avec la clé publique VAPID.
3. L'abonnement est envoyé à `POST /push/subscriptions` et stocké dans
   `push_subscriptions` (un enregistrement par navigateur).
4. Quand un message est créé, `ChatPushNotifier` envoie une notification aux
   autres participants actifs qui ont au moins un abonnement.
5. Le service worker (`public/sw.js`) affiche la notification. Un clic ouvre la
   conversation dans un onglet existant ou nouveau.

L'envoi se fait après la réponse HTTP (`app()->terminating`) : l'expéditeur
n'attend pas les services de push. Les abonnements expirés (404/410) sont
supprimés automatiquement. À la déconnexion, l'abonnement est **désactivé**
côté serveur mais conservé dans le navigateur ; à la connexion suivante, il est
réattribué automatiquement au compte courant. Un poste partagé ne reçoit donc
pas les messages du compte précédent, et l'utilisateur n'a pas à réaccorder
l'autorisation à chaque connexion.

## Prérequis

- HTTPS en production (localhost est accepté pour le développement).
- PHP avec `openssl`, `curl` et `gmp` ou `bcmath`.
- Le package `minishlink/web-push` (déjà dans `composer.json`).
- Les clés VAPID dans l'environnement.

## Configuration

Générer une paire de clés :

```bash
php artisan webpush:vapid
```

La commande affiche les lignes à ajouter dans `.env` (ou dans les variables
d'environnement de l'hébergeur) :

```dotenv
WEBPUSH_ENABLED=true
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT="mailto:contact@epsv.cg"
```

Puis vider le cache de configuration :

```bash
php artisan config:clear
```

Sur Render, ajouter ces variables dans **Environment** et redéployer. La clé
privée est un secret : ne jamais la committer ni la régénérer sans raison — une
nouvelle paire invalide tous les abonnements existants.

Sans `WEBPUSH_ENABLED=true` ou sans clés, le bouton n'apparaît pas et le badge
continue de fonctionner normalement.

## Modèle de données

```
push_subscriptions  id, user_id, endpoint_hash (unique), endpoint,
                    public_key, auth_token, content_encoding,
                    user_agent, last_used_at, disabled_at
```

`disabled_at` est renseigné à la déconnexion : l'abonnement reste dans le
navigateur mais le serveur cesse les envois. La connexion suivante le réactive
et le réattribue au compte courant.

`endpoint_hash` (SHA-256 de l'endpoint) sert d'identifiant unique : si un autre
utilisateur active les notifications sur le même navigateur, l'abonnement est
réattribué au nouveau compte au lieu d'être dupliqué.

## Limites

- Le navigateur doit pouvoir recevoir les notifications. Sur ordinateur, il faut
  généralement que le navigateur soit ouvert (même en arrière-plan) ; sur
  Android et sur iPhone/iPad en PWA installée, le système peut délivrer les
  notifications lorsque l'application n'est pas au premier plan.
- Sur iPhone/iPad : iOS 16.4 minimum et EPSV doit être ajouté à l'écran
  d'accueil. Safari en onglet classique ne reçoit pas ces notifications.
- L'autorisation est propre à chaque navigateur et à chaque appareil. Un membre
  qui change d'appareil doit réactiver les notifications.
- Si l'utilisateur refuse ou bloque les notifications, seul le badge reste
  disponible ; aucun e-mail, SMS ou WhatsApp n'est envoyé en V1.
- Le contenu (expéditeur + extrait) peut apparaître sur l'écran verrouillé. Ne
  pas utiliser la messagerie pour des informations confidentielles si cela pose
  problème.
- La livraison n'est pas garantie (mode Ne pas déranger, navigateur fermé,
  service de push indisponible). Le badge à la reconnexion reste la référence.

## Dépannage

- **Bouton absent** : vérifier `WEBPUSH_ENABLED=true` et les deux clés, puis
  `php artisan config:clear`.
- **Permission refusée** : rouvrir les réglages du site dans le navigateur
  (icône cadenas → Notifications → Autoriser), puis recharger la page.
- **Erreur 419** : session expirée ; recharger la page et réessayer.
- **Aucune notification** : vérifier que le service worker est actif
  (`chrome://serviceworker-internals`), que le système n'est pas en mode Ne pas
  déranger, et que l'utilisateur a bien un abonnement dans `push_subscriptions`.
- **Firefox sur macOS** : vérifier **Réglages Système → Notifications →
  Firefox** (autoriser les bannières) et regarder dans le Centre de
  notifications. Firefox y conserve les notifications même quand la bannière
  est discrète.
- **Après une déconnexion** : l'abonnement est désactivé puis réattribué
  automatiquement à la connexion suivante. Si le navigateur avait été
  désabonné par une ancienne version, cliquer une fois sur **Activer les
  notifications**.
- **Sur iPhone** : installer EPSV sur l'écran d'accueil avant d'activer.
- **Tests** : `php artisan test --filter=PushSubscriptionTest`.
