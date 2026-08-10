# Catalogue de sources suggérées

Le catalogue `/api/custom-scrapers/presets` aide à orienter l’utilisateur vers le bon mode de collecte. Il ne constitue jamais une autorisation juridique automatique.

Chaque preset contient :

- une URL cible pertinente techniquement ;
- le mode conseillé (`HTTP`, `BROWSER` ou `AUTO`) ;
- un statut de conformité ;
- la référence publique consultée ;
- la date de dernière revue ;
- des limites réseau conservatrices.

## Statuts

### `AUTHORIZATION_REQUIRED`

La source est techniquement compatible avec le framework générique, mais JobPilot ne doit pas l’activer sans autorisation applicable à la collecte automatisée.

L’UI peut préremplir la source uniquement après :

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

Ces références doivent être relues périodiquement et immédiatement avant de faire évoluer un preset vers une collecte automatisée active. Un `robots.txt` permissif est un signal technique opérationnel, pas une autorisation contractuelle à lui seul.
