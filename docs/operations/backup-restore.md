# Sauvegarde et restauration

## Objectif

Garantir qu’une perte de données, une migration défectueuse ou une erreur d’exploitation puisse être récupérée avec une procédure testée plutôt qu’une sauvegarde supposée fonctionner.

Ce runbook complète le [socle de production](production-baseline.md). Il décrit la cible d’exploitation ; les commandes exactes dépendront du fournisseur PostgreSQL et du stockage objet retenus.

## Données à protéger

### PostgreSQL

La base contient les données métier : profil candidat, offres, occurrences multi-sources, candidatures, messages, CRM, paramètres, historique de synchronisation et métadonnées de documents.

Objectifs initiaux :

- sauvegarde quotidienne automatique ;
- chiffrement au repos et pendant le transfert ;
- rétention glissante d’au moins 14 jours en production ;
- une sauvegarde supplémentaire avant migration risquée ;
- restauration testée régulièrement sur un environnement isolé.

### CV et documents

En production, les fichiers doivent être stockés dans un stockage objet privé S3-compatible avec versioning ou politique de sauvegarde équivalente.

La sauvegarde doit préserver :

- le contenu du fichier ;
- la clé objet utilisée par JobPilot ;
- le type MIME ;
- la taille ;
- le checksum lorsque disponible ;
- la relation avec l’enregistrement PostgreSQL correspondant.

Une restauration de base sans les documents associés n’est pas considérée comme complète.

## RPO et RTO initiaux

Tant que JobPilot reste un produit mono-utilisateur ou à faible volume, la cible initiale est :

- **RPO** : 24 heures maximum pour une panne totale du stockage primaire ;
- **RTO** : 4 heures pour remettre le service principal en fonctionnement après décision de restauration.

Ces objectifs devront être resserrés avant un usage multi-utilisateurs critique.

## Procédure de restauration PostgreSQL

1. Déclarer l’incident et arrêter les écritures applicatives si elles risquent d’aggraver la situation.
2. Identifier le dernier point de restauration sain avant l’incident.
3. Restaurer dans une **nouvelle base isolée** ; ne jamais écraser immédiatement la base courante.
4. Vérifier que le schéma correspond à une version applicative connue.
5. Exécuter les contrôles d’intégrité et quelques requêtes métier représentatives.
6. Vérifier les relations vers les documents et les occurrences de sources.
7. Démarrer une instance applicative de validation sur la base restaurée.
8. Tester au minimum : connexion API, lecture des offres, lecture des candidatures, accès aux documents et pages de reporting.
9. Basculer seulement après validation explicite.
10. Conserver l’ancienne base en lecture seule pendant la fenêtre d’investigation définie.

## Migrations et rollback

Avant une migration destructrice ou difficilement réversible :

- créer un point de sauvegarde identifié ;
- noter le commit applicatif et la version de migration ;
- vérifier que le rollback applicatif est compatible avec le schéma ;
- préférer les migrations expand/contract aux changements cassants en une seule étape.

Une migration n’est pas considérée comme sûre uniquement parce que la commande Doctrine a réussi.

## Test de restauration

Au minimum une fois par mois en production :

1. sélectionner une sauvegarde récente ;
2. la restaurer dans un environnement isolé ;
3. démarrer une version applicative compatible ;
4. exécuter les smoke tests métier ;
5. ouvrir plusieurs documents restaurés ;
6. mesurer le temps réel de restauration ;
7. consigner le résultat, les écarts et les actions correctives.

Le test ne doit envoyer aucun e-mail réel, ne doit déclencher aucune candidature et ne doit appeler aucun connecteur externe avec des credentials de production.

## Vérifications après restauration

Contrôler au minimum :

- nombre d’offres et d’occurrences cohérent ;
- candidatures et statuts disponibles ;
- absence de migrations manquantes ;
- documents accessibles et checksum cohérent ;
- files asynchrones redémarrées sans retraiter des messages déjà consommés ;
- connecteurs désactivés jusqu’à la fin de la validation si une resynchronisation pourrait créer des doublons ;
- logs exempts de secrets ou données personnelles inutiles.

## Journal de restauration

Chaque exercice ou restauration réelle doit enregistrer :

- date et responsable ;
- motif ;
- sauvegarde choisie ;
- commit applicatif ;
- durée ;
- contrôles effectués ;
- écarts observés ;
- décision finale ;
- actions correctives.

Ne jamais stocker de secret, token OAuth, contenu d’e-mail ou contenu de CV dans ce journal technique.
