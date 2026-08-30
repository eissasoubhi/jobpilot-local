# Préflight de configuration production

Avant de démarrer un déploiement, valider le fichier d'environnement de production avec :

```bash
bash scripts/production-preflight.sh /chemin/vers/.env.production
```

Le script lit le fichier sans l'exécuter et n'affiche jamais les valeurs de secrets. Il bloque notamment :

- les mots de passe/secret applicatif laissés sur les valeurs de développement ;
- une clé `APP_ENCRYPTION_KEY` absente, placeholder ou qui ne décode pas exactement 32 octets en base64 ;
- le token interne du browser worker laissé sur sa valeur locale/de démonstration ;
- un `WEB_URL` qui n'utilise pas HTTPS ;
- une configuration OAuth Gmail partielle ;
- un callback Gmail non HTTPS lorsque OAuth est activé.

Ce préflight valide uniquement la cohérence statique de la configuration. Il ne prouve pas qu'un secret est fort, qu'un endpoint distant est joignable, qu'une sauvegarde est restaurable ou que le déploiement est sain. Les healthchecks, smoke tests, sauvegardes/restaurations et contrôles d'observabilité du runbook de déploiement restent obligatoires.

Pour les tests CI, `scripts/test-production-preflight.sh` utilise uniquement des fichiers temporaires et des valeurs factices. Il ne lit ni n'écrit aucune donnée de développement ou de production.
