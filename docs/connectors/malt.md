# Malt

## Décision de collecte

- Statut JobPilot : `EMAIL_OR_EXTENSION_ONLY`
- Revue : 29 août 2026
- Collecte planifiée : non
- Scraping HTTP : non
- Scraping navigateur : non
- Login/session automatisée : non
- Canaux admis : alertes e-mail reconnues et import volontaire déclenché par l’utilisateur

## Motif

Les conditions générales Malt Community applicables depuis le 16 avril 2026 indiquent que la Marketplace et ses éléments sont protégés par les droits de propriété intellectuelle et les droits du producteur de base de données. Elles interdisent également explicitement l’extraction, la collecte, le transfert ou la réutilisation d’informations provenant des pages hébergées par Malt, automatiquement ou autrement, au moyen de logiciels dédiés, bots ou autres dispositifs de scraping.

Malt documente par ailleurs des API destinées à l’accès et à l’interopérabilité des données de l’utilisateur au moyen de jetons personnels. Cette disposition ne constitue pas un canal public de lecture ou de redistribution du catalogue de missions pour JobPilot.

La présence de pages publiques ou de profils accessibles sans compte ne vaut donc pas autorisation de collecte automatisée du catalogue.

## Références officielles

- Conditions générales Malt Community, applicables depuis le 16 avril 2026 : <https://www.malt.com/legal>
- Site Malt : <https://www.malt.com/>

Articles pertinents lors de la revue :

- 10.1 — protection de la Marketplace et droits sur les bases de données ;
- 10.2 — interdiction explicite du scraping ;
- 10.4 — API destinées à l’accès/interoperabilité des données de l’utilisateur.

## Conséquence pour JobPilot

JobPilot ne doit pas ajouter de scraper HTTP ou Playwright planifié pour Malt, ni automatiser une authentification, une session ou des cookies privés. Les alertes e-mail reconnues et l’import assisté d’une page ouverte volontairement par l’utilisateur restent les voies compatibles avec la politique de conformité du produit.

Cette décision pourra être réouverte uniquement si Malt publie un canal officiel réutilisable pour la lecture/redistribution des missions ou accorde une autorisation écrite applicable à JobPilot.
