# Réponse aux incidents

## Objectif

Donner une procédure courte et reproductible quand JobPilot présente un risque de perte de données, d’envoi incorrect, de fuite de secret ou de collecte externe non conforme.

La priorité est de limiter l’impact avant de rechercher une explication complète.

## Niveaux initiaux

### SEV-1 — critique

Exemples :

- fuite ou exposition probable d’un secret de production ;
- accès non autorisé à des CV, e-mails ou données candidat ;
- envois de candidatures incontrôlés ou multiples ;
- corruption ou perte importante de données ;
- collecte externe qui continue malgré un refus d’accès ou un kill switch.

Action immédiate : arrêter le composant concerné, révoquer les accès exposés et conserver les preuves techniques utiles.

### SEV-2 — majeur

Exemples :

- API ou frontend indisponible durablement ;
- PostgreSQL ou RabbitMQ indisponible ;
- synchronisations critiques bloquées ;
- file de messages qui s’accumule sans traitement ;
- connecteur qui produit massivement des données incorrectes.

### SEV-3 — dégradé

Exemples :

- une source non critique indisponible ;
- extraction partiellement cassée ;
- métrique ou reporting incorrect sans perte de données ;
- fonctionnalité secondaire dégradée avec contournement utilisateur simple.

## Première réponse

1. Noter l’heure de détection et le symptôme observable.
2. Classer provisoirement la sévérité.
3. Identifier le périmètre : frontend, API, DB, queue, Gmail, connecteur, documents ou candidature.
4. Stopper les écritures ou traitements automatiques susceptibles d’aggraver l’incident.
5. Préserver les logs, `correlation_id`, `sync_run_id`, IDs métier et version applicative utiles.
6. Ne jamais copier de secret ou de contenu personnel dans le ticket d’incident.
7. Choisir une mitigation réversible avant une correction complexe.

## Kill switches recommandés

Selon l’incident, pouvoir :

- désactiver un connecteur individuel ;
- suspendre les synchronisations planifiées ;
- suspendre les workers d’envoi ;
- désactiver l’auto-préparation ou l’auto-envoi ;
- révoquer un token OAuth ou une clé API ;
- passer l’application en lecture seule si la base est à risque ;
- désactiver temporairement l’extension ou une capacité Autofill problématique.

Un kill switch ne doit pas supprimer les données nécessaires à l’analyse.

## Incidents de connecteur

Après 401, 403 ou 429 répétés :

- ne pas contourner la limitation ;
- ouvrir/laisser ouvert le circuit breaker ;
- suspendre la source ;
- vérifier credentials, quotas et règles de collecte ;
- ne reprendre qu’après résolution explicite.

Si un parseur renvoie soudainement beaucoup d’offres mal formées ou zéro résultat inhabituel, désactiver l’import de cette source avant d’ajuster le parseur.

## Incidents d’envoi de candidature

Si des envois incorrects ou multiples sont suspectés :

1. arrêter immédiatement l’auto-envoi et les workers concernés ;
2. identifier les candidatures avec `submittedAt`, journal d’audit et IDs de message ;
3. vérifier l’idempotence avant tout retry ;
4. ne pas renvoyer automatiquement une candidature dont l’état externe est inconnu ;
5. réconcilier manuellement les cas ambigus ;
6. corriger la cause et ajouter un test de non-régression avant réactivation.

## Incident de sécurité ou secret exposé

- révoquer/faire tourner le secret avant d’éditer les logs ou le code ;
- invalider les sessions/tokens concernés lorsque possible ;
- rechercher l’usage du secret dans les logs et historiques ;
- vérifier qu’il n’a pas été publié dans Git, artefacts CI ou captures ;
- conserver uniquement les éléments nécessaires à l’investigation ;
- documenter l’étendue et les actions de rotation.

## Incident de données

En cas de corruption ou suppression :

- arrêter les écritures susceptibles de propager le problème ;
- ne pas lancer une restauration directement sur la base active ;
- suivre [`backup-restore.md`](backup-restore.md) ;
- restaurer d’abord dans un environnement isolé ;
- comparer les données avant la bascule.

## Rollback applicatif

Un rollback est préféré à un hotfix risqué lorsque :

- la régression est clairement liée au dernier déploiement ;
- le schéma de base reste compatible ;
- revenir à l’image précédente réduit immédiatement l’impact.

Ne pas rollbacker une application vers une version incompatible avec une migration déjà exécutée. Dans ce cas, appliquer la stratégie de migration/compatibilité documentée avant toute bascule.

## Critères de résolution

Un incident peut être clôturé lorsque :

- le symptôme utilisateur est résolu ;
- les traitements automatiques peuvent reprendre sans risque connu ;
- les données ont été réconciliées ou la perte est comprise ;
- les secrets compromis ont été révoqués ;
- le monitoring confirme le retour à un état stable ;
- une action de prévention ou un test de non-régression est enregistré si nécessaire.

## Post-mortem

Pour SEV-1 et SEV-2, documenter :

- chronologie ;
- impact réel ;
- signal de détection ;
- cause technique et facteurs contributifs ;
- mitigation ;
- ce qui a ralenti le diagnostic ;
- actions correctives avec priorité ;
- amélioration du monitoring ou des garde-fous.

Le post-mortem doit rester factuel et orienté système. Il ne doit contenir ni secrets ni contenu personnel inutile.
