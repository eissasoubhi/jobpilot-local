# Fixtures de scraping personnalisé

Ces fixtures sont des documents HTML **synthétiques et anonymisés** utilisés pour les tests de contrat du scraper générique.

Règles :

- ne jamais copier ici une page réelle complète ni des données personnelles ;
- conserver uniquement les structures minimales nécessaires pour reproduire un comportement de parser ;
- utiliser des domaines réservés de test (`example.test`) et des entreprises fictives ;
- ajouter une fixture lorsqu'une régression dépend d'une forme HTML précise plutôt que d'intégrer un gros heredoc dans le test ;
- tester le comportement métier attendu (offres extraites, mode HTTP/Browser, qualité) plutôt que des sélecteurs internes trop précis.

Fixtures actuelles :

- `listing-jobposting.html` : liste avec plusieurs `JobPosting` JSON-LD ;
- `detail-dom.html` : fiche sans données structurées, enrichie par le DOM ;
- `browser-js-shell.html` : coquille Next.js vide qui doit recommander Browser ;
- `degraded-jobposting.html` : `JobPosting` incomplet sans description, qui ne doit pas être considéré fiable pour l'import automatique.
