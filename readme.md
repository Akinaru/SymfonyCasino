# Casino en ligne en symfony sur le thème de Minecraft

![Schéma Mercure](public/banner.png)

## Objectifs d’apprentissage
- Maîtriser les différents environnements (local, staging, prod)
- Créer des entités avec leurs propriétées et éxecuter des migrations
- Créer des formulaires
- Implémenter un système de sécurité
- Concevoir une architecture twig propre (base, layout, composants, blocs)
- Concevoir une architecture extensible facilitant l’ajout de nouveaux jeux.
- Établir un aspect communautaire via Mercure permettant aux joueurs de jouer et de communiquer entre eux.
- Deployer le projet

## Roadmap (grandes étapes)
- [x] Setup projet Symfony propre : arborescence, env (`.env`/`.env.local`), base de données.
- [x] Authentification/inscription : création de compte, login, vérif e-mail, reset password, protections CSRF/rate-limit.
- [x] Panneau Admin minimal : gestions des utilisateurs (monnaie, statistiques).
- [x] Module Profil : vue/édition du profil (pseudo, avatar, préférences), page profil public minimal.
- [x] Noyau “moteur de jeu” : entités génériques (Session, Player, Round, Event, Score) + interface `GameTemplate` pour plugger des jeux.
- [x] Implémentation du Jeu #1.
- [x] Implémentation du Jeu #2.
- [x] Leaderboard
- [x] Système d'objet de marché noir (ajout objet sur le marché, achat, inventaire)
- [x] Temps réel avec Mercure : chat en direct, action en direct (envoie de monnaie).
- [x] Statistiques & profil enrichi : historique de parties, meilleurs scores

## Architecture de la base de données

![Schéma Mercure](public/Symfsino.png)

## Déploiement

Le site a été déployé sur https://casino.gallotta.fr avec docker.
Compte admin:
    - user: `admin@symfony.fr`
    - pass: `admin123`

## Informations

### Génération des images

Ce json a été utilisé avec l'outil de génération d'image pour permettre de créer toutes les images du site. Le format json permet de garder une cohérence entre les images et de pouvoir en créer des nouvelles en gardant la direction artistique.

```
{
   "game_key":"slots",
   "title":"Slots",
   "slug":"slots",
   "route":"/games/slots",
   "short_description":"Machine à sous 3×3 : aligne les mêmes symboles pour déclencher les gains.",
   "cover":{
      "image_prompt":"Illustration cartoon inspirée de l’univers pixelisé type Minecraft : au centre de l’image, une machine à sous compacte vue de face, avec 3 rouleaux verticaux et 3 lignes visibles (grille 3×3). Chaque case est un cadre cubique clair, façon bloc pixel art, contenant des symboles inspirés de minerais et objets de l’univers (émeraude, diamant, lingot d’or, symbole rouge perdant). La rangée centrale affiche un alignement gagnant de trois mêmes symboles verts (émeraudes ou diamants) pour bien faire comprendre le principe du jeu. La machine a un encadrement doré stylisé, légèrement cubique, avec quelques reflets, mais reste simple et lisible. En dessous ou sur le côté droit, une petite manette/levier pixel art rappelle la machine à sous classique. Le fond reprend le même univers de casino numérique sombre que les autres vignettes (violets profonds, bleus nuit, reflets cyan, bokeh lumineux discret) en arrière-plan. Style global : cartoon propre, pixel art doux, bords nets, sans flou, couleurs saturées mais harmonieuses, toujours inspiré d’un univers Minecraft-like non-officiel. Aucune interface texte, aucun logo, uniquement la scène du jeu.",
      "main_element":"Une machine à sous 3×3 centrée, avec trois rouleaux verticaux montrant un alignement de symboles identiques sur la ligne centrale.",
      "elements":{
         "slot_machine":{
            "type":"slot_machine",
            "structure":"3 rouleaux, 3 lignes (grille 3×3)",
            "frame_style":"encadrement doré cubique, style pixel art",
            "details":"bords crantés, quelques reflets dorés mais design épuré"
         },
         "reels":{
            "type":"reels",
            "rows":3,
            "columns":3,
            "appearance":"cases rectangulaires claires avec légère bordure pixel art",
            "state":"ligne centrale affichant trois symboles identiques pour illustrer un gain"
         },
         "symbols":{
            "emerald":{
               "icon":"emerald",
               "color":"vert lumineux",
               "rarity":"symbole premium gagnant",
               "visual_style":"cristal facetté brillant, style Minecraft-like cartoon"
            },
            "diamond":{
               "icon":"diamond",
               "color":"cyan clair lumineux",
               "rarity":"symbole premium",
               "visual_style":"gros diamant facetté, reflets froids"
            },
            "gold_ingot":{
               "icon":"gold_ingot",
               "color":"jaune doré",
               "rarity":"symbole intermédiaire",
               "visual_style":"lingot cubique doré en pixel art"
            },
            "lose_symbol":{
               "icon":"loss_symbol",
               "color":"rouge vif",
               "rarity":"symbole perdant",
               "visual_style":"X ou petite tête de mort cubique rouge/orange, simple et lisible"
            }
         },
         "lever":{
            "type":"lever",
            "position":"côté droit de la machine",
            "appearance":"petit levier métallique avec boule rouge en pixel art",
            "symbolism":"accentue l’idée de machine à sous classique"
         },
         "background":{
            "theme":"casino numérique sombre",
            "colors":[
               "violet profond",
               "bleu nuit",
               "reflets cyan"
            ],
            "details":"lueurs diffuses, motifs géométriques doux, bokeh lumineux discret, cohérent avec les autres jeux"
         }
      },
      "composition":{
         "focus":"la machine à sous 3×3 occupe environ 70–80% de la largeur de la vignette et est parfaitement centrée",
         "camera":"vue quasi frontale, très légère contre-plongée pour donner du volume",
         "lighting":"éclairage venant du dessus et légèrement de face, mettant en valeur les symboles alignés et l’encadrement doré"
      },
      "style":{
         "inspiration":"Minecraft mais en version dessin cartoon plus lisse",
         "line_quality":"bords nets, pixel art non flou",
         "render":"2D, pas de réalisme photo, uniquement illustration cartoon"
      }
   }
}
```

## Mercure

Le casino utilise Symfony Mercure pour pousser en direct certaines infos vers le front (dernières parties, etc.), sans WebSocket custom ni polling.

### Principe général

- Le serveur publie un message JSON sur un topic Mercure (ex. https://casino.gallotta.fr/mercure/last-games) via un HubInterface.
- Le hub Mercure diffuse ce message à tous les navigateurs abonnés à ce topic.
- Le front ouvre une connexion EventSource via un petit helper MercureBus et :
  - s’abonne à un ou plusieurs topics,
  - écoute les messages par type (ex. partie.created),
  - met à jour le DOM (tableau des dernières parties, etc.).

### Liste des fonctionnalités qui utilisent mercure

- Le chat de discussion en direct
- L'affichage des dernières parties
- Les tips (le receveur d'un tips reçoit une alerte sur sont écran avec le nom de l'envoyeur et le montant du tips) (un message est également envoyé dans le chat pour notifier tous les utilisateurs)

## Système de registre des jeux

Le cœur du casino repose sur deux briques simples : GameInterface et GameRegistry.
Ensemble, elles permettent d’ajouter de nouveaux jeux sans toucher au reste du code applicatif.

- GameInterface — contrat commun à tous les jeux

GameInterface définit le contrat que tout jeu doit respecter pour être reconnu par l’application.
Chaque jeu (Dice, Slots, Mines, Tower, etc.) est une classe qui l’implémente.

Concrètement, un jeu doit fournir :

- Une identité technique
    - une clé unique (ex. dice, slots, mines) pour lier les parties en base (Partie.game_key) au jeu concerné ;
    - un nom lisible (ex. “🎲 Dice”) utilisé dans l’interface.

  - Sa présence dans l’UI
    - une URL (route du jeu) pour générer les liens et boutons “Jouer” ;
    - une image (cover) pour les vignettes sur la page d’accueil.

  - Sa description
    - une courte description “catalogue” pour présenter le jeu dans les listes ;
    - une description “in game” plus détaillée, affichée dans le panneau d’informations du jeu.

  - Ses limites de mise
    - une mise minimale et maximale, utilisées à la fois :
      - pour afficher les bornes dans l’UI,
      - et pour les validations côté serveur.
      
Grâce à ce contrat, tous les jeux exposent la même interface : le front, les contrôleurs et les vues peuvent manipuler un “jeu” sans savoir s’il s’agit de Dice, Slots ou d’un futur jeu ajouté plus tard.

