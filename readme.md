# README — Plateforme de jeux (Symfony)

## Tutoriel
_Un tutoriel pas-à-pas arrive bientôt._  
Il couvrira : installation locale, configuration `.env`, création BDD, lancement du serveur et premiers écrans.

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
- [ ] Système d'objet de marché noir (ajout objet sur le marché, achat, inventaire)
- [ ] Temps réel avec Mercure : chat en direct, action en direct (envoie de monnaie).
- [ ] Statistiques & profil enrichi : historique de parties, meilleurs scores, badges/récompenses simples.

## Liens utiles

- https://github.com/nebulimity/MoreLikeMinecraft/blob/main/default/default_glass.png
