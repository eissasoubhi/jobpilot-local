# Le Studio Tech — scraping HTTP public

## Décision de collecte

Le connecteur `le-studio-tech` lit les missions publiquement visibles sur :

- <https://app.lestudiotech.com/freelances/missions>
- les fiches `/freelances/missions/{id}/{slug}` associées.

La revue du 9 août 2026 a constaté que la liste et les fiches mission sont accessibles sans compte ni session privée. Les conditions consultées ne contiennent pas de clause nommant explicitement les robots, le scraping ou l’extraction automatisée. Elles encadrent toutefois l’exploitation commerciale du contenu et le contournement de la mise en relation.

JobPilot classe donc ce canal `ALLOWED` uniquement pour son usage de recherche personnelle : découverte d’une mission, analyse locale et conservation du lien vers la plateforme. Le connecteur ne republie pas la base, ne constitue pas un fichier commercial de prospection, ne contacte pas le client final en contournant la plateforme et ne soumet aucune candidature automatiquement.

Références de revue :

- <https://app.lestudiotech.com/freelances/missions>
- <https://www.lestudiotech.com/mentions-legales>

Cette décision doit être réexaminée si les conditions, le comportement HTTP ou `robots.txt` changent.

## Mode

```text
SCRAPING_HTTP
```

Le connecteur réutilise `ControlledHttpScrapingClient`. Il bénéficie donc des garde-fous communs :

- URL HTTPS publique uniquement ;
- User-Agent explicite ;
- contrôle `robots.txt` obligatoire avant collecte ;
- pagination bornée ;
- quota par synchronisation et quota journalier ;
- délai minimal entre requêtes ;
- retries bornés et `Retry-After` ;
- cache conditionnel `ETag` / `Last-Modified` ;
- circuit breaker ;
- arrêt temporaire après `401` ou `403` ;
- aucun contournement de `429` ;
- redirections et taille des réponses bornées.

## Configuration

```env
LE_STUDIO_TECH_ENABLED=1
LE_STUDIO_TECH_PAGES=8
LE_STUDIO_TECH_MAX_DETAILS=20
```

Le code impose aussi des hard caps : au maximum 10 pages de liste et 30 fiches détaillées, même si les variables d’environnement demandent davantage.

La politique du connecteur limite le transport contrôlé à 60 requêtes par synchronisation, 240 requêtes par jour et 1,2 seconde minimum entre deux requêtes de collecte.

## Extraction

Le parseur `le-studio-tech-html-v1` recherche les liens de mission par leur chemin `/freelances/missions/...` plutôt que par des classes CSS de présentation.

La liste fournit lorsque disponibles :

- référence source stable ;
- titre ;
- URL directe ;
- date de publication ;
- localisation ;
- TJM ;
- mode de travail ;
- expérience minimale ;
- date de début.

Le contrat est normalisé en `Freelance`. L’entreprise canonique reste `Le Studio Tech` lorsqu’aucun client fiable n’est publiquement identifié ; JobPilot ne doit pas inventer un client final.

Toutes les missions trouvées sur les pages parcourues sont retournées au pipeline commun. Pour économiser les requêtes, les fiches détaillées sont enrichies en priorité lorsque leur titre partage des termes avec les `targetJobs` ou `skills` configurés. Aucune identité candidat n’est codée dans le connecteur.

Une fiche explicitement marquée `Mission terminée` est écartée.

## Tests et stabilité

La CI n’appelle jamais Le Studio Tech. Les tests utilisent des fixtures HTML synthétiques qui reproduisent uniquement les marqueurs structurels nécessaires au parseur.

Les diagnostics communs enregistrent la version du parseur, le volume reçu, le taux de normalisation et la qualité des champs. Une rupture du HTML doit apparaître dans la santé du connecteur au lieu d’être masquée silencieusement.

## Limites

Le scraper ne doit jamais :

- se connecter à un compte Le Studio Tech ;
- utiliser un cookie ou jeton privé ;
- contourner un CAPTCHA ou une protection d’accès ;
- ignorer un `robots.txt` défavorable ;
- contourner un `401`, `403` ou `429` ;
- masquer son User-Agent ;
- automatiser la candidature ou contourner la mise en relation de la plateforme.
