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

Au 10 août 2026 :

- APEC — PHP / Symfony ;
- LeHibou — Développeur Symfony.

### `ASSISTED_ONLY`

Les conditions publiques revues interdisent le scraping automatisé ou imposent des restrictions incompatibles avec une activation générique prudente.

JobPilot ne propose donc aucun bouton d’ajout automatique. Utiliser à la place une alerte Gmail, une extension/import utilisateur, une API partenaire ou une autorisation explicite spécifique.

Au 10 août 2026 :

- Free-Work — Symfony ;
- Welcome to the Jungle ;
- Hellowork — PHP ;
- LesJeudis.

## Références revues le 10 août 2026

- APEC : `https://corporate.apec.fr/home/informations-legales-et-conditio.html`
- LeHibou : `https://www.lehibou.com/conditions-generales-utilisation`
- Free-Work : `https://www.free-work.com/fr/terms`
- Welcome to the Jungle : `https://www.welcometothejungle.com/fr/pages/terms`
- Hellowork : `https://recruteur.hellowork.com/cgv-abo-lib`
- LesJeudis : `https://lesjeudis.com/fr/cgu`

Ces références doivent aussi être relues immédiatement avant toute évolution vers une collecte automatisée active, même si le délai de 90 jours n’est pas encore atteint. Un `robots.txt` permissif est un signal technique opérationnel, pas une autorisation contractuelle à lui seul.
