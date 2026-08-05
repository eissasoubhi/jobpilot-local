# Free-Work

## Statut

```text
mode: EXTENSION + GMAIL
collecte HTTP planifiée: désactivée
statut de conformité: email_or_extension_only
révision: 2026-08-05
```

JobPilot ne lance aucune requête de scraping en arrière-plan vers Free-Work.

## Pourquoi il n’existe pas de scraper direct

La revue du 5 août 2026 a relevé que les conditions générales de Free-Work accordent un usage personnel de navigation et interdisent la reproduction, la diffusion, la combinaison ou l’adaptation de tout ou partie des éléments du site. Les mentions légales réservent également les droits de reproduction sur les contenus.

Références examinées :

- <https://www.free-work.com/fr/terms>
- <https://www.free-work.com/fr/terms/legal-mentions>

En l’absence d’une autorisation explicite ou d’une API partenaire, le mode `SCRAPING_HTTP` n’est donc pas activé pour cette plateforme. Un fichier `robots.txt` ne suffirait pas, à lui seul, à remplacer cette vérification contractuelle.

## Canaux pris en charge

### Alertes Gmail

Les alertes reçues par e-mail sont classées par le connecteur Gmail. Les liens Free-Work reconnus deviennent des occurrences de source dans le catalogue canonique lorsque les informations disponibles sont suffisantes.

### Import assisté avec l’extension Chrome

L’utilisateur ouvre lui-même une offre puis clique sur **Importer l’offre** dans l’extension JobPilot. Aucun navigateur automatisé n’explore le jobboard et aucune session privée n’est récupérée par le backend.

L’extracteur privilégie le balisage `JobPosting` JSON-LD présent sur la page. À défaut, il lit uniquement le contenu déjà visible dans l’onglet afin de récupérer :

- intitulé ;
- entreprise ;
- localisation ;
- contrat ;
- télétravail ;
- date de publication ;
- salaire ou TJM ;
- description ;
- URL canonique.

L’import passe ensuite par le même pipeline que les autres sources :

1. identité `sourceCode + externalId` ;
2. création ou mise à jour d’une occurrence ;
3. rapprochement avec une offre canonique ;
4. scoring et sélection du CV ;
5. préparation éventuelle de la candidature.

Importer plusieurs fois la même page ne crée donc plus plusieurs cartes ni plusieurs candidatures.

## Limites

- L’extension ne clique jamais sur le bouton final de candidature.
- Le contenu extrait reste limité à ce qui est nécessaire au traitement local de l’offre.
- Une page réservée aux membres ou masquant la description ne doit pas être contournée.
- Une modification du HTML peut réduire la qualité du fallback visible ; le JSON-LD reste prioritaire.
- Toute future collecte HTTP nécessite une nouvelle revue des conditions, de `robots.txt` et, si nécessaire, une autorisation écrite.

## Vérification manuelle

1. Ouvrir une page de détail Free-Work accessible normalement.
2. Cliquer sur l’icône JobPilot, puis **Importer l’offre**.
3. Vérifier dans **Offres** l’entreprise, le contrat, le lieu, le TJM ou salaire et la source `Free-Work`.
4. Réimporter la même page et vérifier qu’une seconde carte n’est pas créée.
5. Vérifier que l’URL source ouvre toujours la page d’origine.
