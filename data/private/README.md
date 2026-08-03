# Stockage privé local

Ce dossier contient physiquement les documents privés utilisés par JobPilot sur cette machine.

Après le premier démarrage, il peut notamment contenir :

- `cvs/` : CV téléversés depuis l’interface ;
- `gmail-token.enc` : jeton OAuth Gmail chiffré ;
- les fichiers techniques privés générés par l’application.

Tous les fichiers de ce dossier, à l’exception de ce README, sont ignorés par Git. Ne les commitez jamais.

Les CV physiques portent un nom aléatoire. Leur nom original et leurs métadonnées restent enregistrés dans PostgreSQL.
