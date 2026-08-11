# Catalogue de sources suggérées

Le catalogue `/api/custom-scrapers/presets` aide à orienter l’utilisateur vers le bon mode de collecte. Il ne constitue jamais une autorisation juridique automatique.

Chaque preset contient :

- une URL cible pertinente techniquement ;
- le mode conseillé (`HTTP`, `BROWSER` ou `AUTO`) ;
- un statut de conformité ;
- la référence publique consultée ;
- la date de dernière revue ;
- la date d’échéance de cette revue ;
- un indicateur `reviewFresh` ;
- des limites réseau conservatrices.

## Autorisations écrites signalées le 11 août 2026

L’utilisateur de JobPilot a signalé disposer d’autorisations écrites de scraping pour les plateformes qu’il souhaite connecter, à l’exception explicite de **LinkedIn**.

JobPilot ne stocke pas le contenu privé de ces e-mails dans le dépôt et ne transforme pas cette déclaration en activation automatique. Pour chaque source concernée, le workflow reste :

1. renseigner une référence locale de l’accord écrit ;
2. confirmer explicitement que cet accord couvre la collecte automatisée visée ;
3. créer la source en état **désactivé** ;
4. exécuter diagnostic et prévisualisation ;
5. activer manuellement seulement si le test est concluant.

Les presets Free-Work, Welcome to the Jungle, Hellowork et LesJeudis passent donc de `ASSISTED_ONLY` à `AUTHORIZATION_REQUIRED`. Cette évolution autorise le préremplissage contrôlé, pas le scraping automatique par défaut.

**LinkedIn reste exclu de ce workflow** : aucune collecte automatisée de LinkedIn n’est ajoutée. Les mécanismes Gmail/import assisté restent les seuls canaux JobPilot prévus pour cette plateforme.

Lorsqu’une API, un flux RSS/XML ou un autre canal officiel adapté existe, ce canal reste prioritaire sur le scraping même lorsqu’un accord écrit de scraping est disponible.

## Durée de validité d’une revue

Une revue de conformité JobPilot est considérée fraîche pendant **90 jours**.

L’API expose :

```text
reviewedAt
reviewDueAt
reviewFresh
reviewTtlDays
```

Après l’échéance :

- le preset reste visible pour transparence ;
- `reviewFresh=false` ;
- `canPrefill=false`, y compris pour une source auparavant classée `AUTHORIZATION_REQUIRED` ;
- l’UI affiche **Revue expirée** et ne propose plus le formulaire d’ajout automatique ;
- la référence publique doit être relue et le catalogue mis à jour avant de réactiver le préremplissage.

Le canal Gmail reste distinct : une revue de scraper expirée n’empêche pas JobPilot d’analyser les alertes déjà reçues dans le compte Gmail connecté de l’utilisateur.

Les références revues le **10 août 2026** ont actuellement une échéance au **8 novembre 2026**.

## Statuts

### `AUTHORIZATION_REQUIRED`

La source est techniquement compatible avec le framework générique, mais JobPilot ne doit pas l’activer sans autorisation applicable à la collecte automatisée.

Lorsque la revue JobPilot est encore fraîche, l’UI peut préremplir la source uniquement après :

1. saisie d’une référence d’autorisation par l’utilisateur ;
2. confirmation explicite ;
3. création de la source en état **désactivé** ;
4. diagnostic et prévisualisation avant activation manuelle.

Au 11 août 2026 :

- APEC — PHP / Symfony ;
- LeHibou — Développeur Symfony ;
- Free-Work — Symfony ;
- Welcome to the Jungle ;
- Hellowork — PHP ;
- LesJeudis.

### `ASSISTED_ONLY`

Ce statut reste disponible pour les plateformes pour lesquelles aucun droit applicable à une collecte automatisée n’est confirmé. JobPilot n’y propose aucun bouton d’ajout automatique et conserve uniquement les canaux tels que Gmail, import utilisateur ou canal officiel adapté.

LinkedIn doit rester hors des presets de scraping automatisé et utiliser ce principe d’import assisté.

## Références revues le 10 août 2026

- APEC : `https://corporate.apec.fr/home/informations-legales-et-conditio.html`
- LeHibou : `https://www.lehibou.com/conditions-generales-utilisation`
- Free-Work : `https://www.free-work.com/fr/terms`
- Welcome to the Jungle : `https://www.welcometothejungle.com/fr/pages/terms`
- Hellowork : `https://recruteur.hellowork.com/cgv-abo-lib`
- LesJeudis : `https://lesjeudis.com/fr/cgu`

Ces références doivent aussi être relues immédiatement avant toute évolution vers une collecte automatisée active, même si le délai de 90 jours n’est pas encore atteint. Un `robots.txt` permissif est un signal technique opérationnel, pas une autorisation contractuelle à lui seul.
