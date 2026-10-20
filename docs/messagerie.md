# Messagerie interne

Messagerie de collaboration réservée au **personnel actif** : elle ne concerne ni
les élèves ni les parents (ces derniers n'ont pas de compte dans la V1).

## Périmètre

| Type | Création | Qui voit |
| --- | --- | --- |
| Canal général « Toute l'école » | Migration d'installation | Tout le personnel actif |
| Groupe | N'importe quel membre du personnel | Les participants |
| Conversation directe | N'importe quel membre du personnel | Les deux participants |

Un utilisateur ouvre la messagerie depuis **Communication → Messagerie**. Il est
rattaché automatiquement au canal général à sa première ouverture.

## Gestion des groupes

Le **créateur** d'un groupe dispose du bouton **Gérer** dans l'en-tête de la
conversation. Il peut :

- renommer le groupe ;
- voir tous les membres avec leur fonction (et la mention « Créateur » ou
  « Désactivé ») ;
- ajouter un membre en cochant son nom ;
- retirer un membre en le décochant.

Règles appliquées :

- seul le créateur peut modifier le groupe (les autres membres voient la liste
  des participants dans le sous-titre, en lecture seule) ;
- le créateur ne peut pas se retirer lui-même : il reste toujours membre ;
- un membre retiré perd immédiatement l'accès (404 sur la page, 403 sur les
  routes de messages et de pièces jointes), mais ses messages restent dans le
  fil ;
- un membre ajouté ne voit pas comme non lus les messages antérieurs à son
  arrivée.

## Notifications

Deux mécanismes complémentaires :

- **Badge de non-lus** (toujours actif) : badge numérique sur **Messagerie** dans
  la barre latérale, compteur par conversation et pastille du fil concerné. Le
  total est aussi affiché dans le titre de l'onglet, sur le favicon et, si le
  navigateur le permet, sur l'icône de l'application (Badging API).
- **Notifications Web Push** (optionnelles, sur consentement) : le membre peut
  activer les notifications depuis le bouton en haut de la Messagerie. Il reçoit
  alors une notification du navigateur pour les nouveaux messages des
  conversations auxquelles il appartient, avec le nom de l'expéditeur et un
  extrait. Un clic ouvre la conversation.

Les notifications Push nécessitent HTTPS (ou localhost en développement), les
clés VAPID configurées et l'autorisation du navigateur. Sur iPhone/iPad, il faut
iOS 16.4 ou plus et ajouter EPSV à l'écran d'accueil. Voir
[notifications-push.md](notifications-push.md) pour l'installation et les
limites.

Le badge se met à jour toutes les 4 s sur la page Messagerie et toutes les 15 s
sur les autres pages (titre, favicon et badge d'application compris). Aucun son
n'est joué.

## Fonctionnement

- **Point d'entrée** : après connexion, l'utilisateur arrive directement sur le
  canal général (**Toute l'école**). Le tableau de bord reste accessible depuis
  la barre latérale.
- **Temps réel léger** : le fil actif interroge le serveur toutes les 4 secondes
  (`GET /chat/conversations/{id}/messages?after_id=…`). Aucun serveur WebSocket
  à maintenir ; le coût est négligeable pour une équipe de taille scolaire.
- **Non-lus** : chaque participant a un curseur `last_read_message_id` dans
  `conversation_user`. Le compteur compare des identifiants de messages, pas des
  horodatages — insensible aux fuseaux et à la précision de la base.
- **Pièces jointes** : PDF, images (JPG/PNG/WebP), Word et Excel, 10 Mo maximum.
  Elles sont stockées sur le disque **privé** (`storage/app/private/chat/…`) et
  servies uniquement aux participants via une route authentifiée.
- **Édition Word et Excel** : un fichier `.docx` ou `.xlsx` affiche un bouton
  **Modifier** qui ouvre l'éditeur intégré, sans quitter EPSV. L'enregistrement
  ajoute une nouvelle version dans la conversation ; l'original n'est jamais
  écrasé.
- **Suppression** : chaque auteur peut supprimer ses propres messages
  (suppression logique ; l'historique de la base conserve la ligne).
- **Pas d'audit des messages** : la messagerie est un espace d'échange interne,
  pas une action scolaire sensible. Elle n'alimente pas `audit_logs`.

## Modèle de données

```
conversations          id, type (channel|group|direct), name, direct_key,
                       created_by, last_message_at
conversation_user      conversation_id, user_id, last_read_message_id
messages               id, conversation_id, user_id, body,
                       attachment_path/name/mime/size, deleted_at
push_subscriptions     id, user_id, endpoint_hash, endpoint,
                       public_key, auth_token, content_encoding,
                       user_agent, last_used_at
```

`direct_key` (`petit_id:grand_id`) garantit qu'une seule conversation directe
peut exister entre deux personnes, quel que soit l'initiateur.

## Autorisations

- Toute route exige `auth` + compte actif (`EnsureUserIsActive`).
- Un non-participant reçoit **404** sur la page d'une conversation (pour ne pas
  révéler son existence) et **403** sur les API de messages et les pièces jointes.
- Seul l'auteur peut supprimer son message (**403** sinon).

## Édition des documents Word et Excel

- Seuls les fichiers **`.docx`** et **`.xlsx`** sont éditables. Les anciens
  `.doc`/`.xls`, les PDF, les images et les fichiers CSV restent en
  téléchargement.
- L'éditeur s'ouvre sur une page dédiée d'EPSV
  (`GET /chat/messages/{id}/document`) et charge le fichier par la route
  authentifiée de la pièce jointe. Aucun fichier n'est envoyé à un service
  externe.
- **Enregistrer dans le chat** dépose le document modifié comme **nouveau
  message** (« Document modifié : … »). L'original et les versions précédentes
  restent consultables ; deux personnes qui éditent en même temps produisent
  donc deux versions, sans écrasement silencieux.
- Taille maximale du document enregistré : **20 Mo**. Un fichier chiffré ou un
  faux `.docx`/`.xlsx` est refusé.
- Ce n'est pas de l'édition simultanée : une seule personne édite sa copie à la
  fois. La mise en page Word et les classeurs Excel courants sont repris par
  l'éditeur ; les fonctionnalités très avancées (macros, compléments, champs
  dynamiques, graphiques exotiques) peuvent être limitées.
- Les éditeurs sont des composants open source exécutés entièrement dans le
  navigateur : `@docx-editor.dev/react` (Apache 2.0) pour Word, et
  `@fortune-sheet/react` + `@corbe30/fortune-excel` (MIT) pour Excel. Aucun
  document n'est envoyé à un tiers.

## Limites connues de la V1

- Les 60 derniers messages sont affichés ; pas encore de pagination « charger
  plus anciens ».
- Pas de modification de message, de recherche, de mentions `@`, d'indicateur
  de saisie ni d'accusés de lecture par message.
- La composition d'un groupe ne peut être modifiée que par son créateur ; les
  autres membres ne peuvent pas quitter le groupe eux-mêmes.
- Le badge de non-lus se met à jour sur la page Messagerie (4 s) et sur les
  autres pages (15 s), y compris dans le titre de l'onglet et le favicon.
- Pas de WebSocket : latence perçue de 0 à 4 secondes sur le fil actif.
- Les notifications Push ne remplacent pas le badge : si l'utilisateur refuse
  l'autorisation ou ferme complètement le navigateur, le badge reste la source
  de vérité à la reconnexion.

## Exploitation

- Les migrations créent les tables et le canal général ; aucun seeder n'est
  nécessaire en production.
- Sauvegarder `storage/app/private/chat/` avec la base : les pièces jointes ne
  sont pas régénérables, contrairement aux bulletins.
- Vérifier `upload_max_filesize` et `post_max_size` (≥ 20 Mo et 24 Mo) dans PHP.
- Sur Render, le disque du service web est éphémère : monter un disque
  persistant sur `storage/app/private` (ou passer le disque `local` sur S3)
  avant de s'appuyer sur les pièces jointes en production.
