# Matrice d’acquisition des plateformes

La page **Connecteurs** contient une matrice informative couvrant les plateformes suivies par le produit. Son objectif est de distinguer un connecteur réellement exécutable d’une simple source demandée ou en cours de revue.

Une entrée de cette matrice ne crée jamais automatiquement :

- un scraper ;
- une authentification ;
- une synchronisation planifiée ;
- un droit de réutiliser les données d’une plateforme ;
- un bouton d’activation ou de test.

## Décision produit : audit exhaustif du scraping public

La V1 doit désormais évaluer **toutes les plateformes de cette matrice** comme candidates potentielles à la collecte automatisée des offres publiques.

Pour chaque plateforme, la revue doit répondre explicitement aux questions suivantes :

1. la liste des offres est-elle visible sans compte, sans session et sans cookie privé ?
2. les pages de détail sont-elles également publiques ?
3. existe-t-il une API, un flux RSS/Atom ou un export officiel couvrant les mêmes offres ?
4. si aucun canal officiel équivalent n’existe, un scraper HTTP statique est-il techniquement suffisant ?
5. sinon, un rendu Playwright est-il nécessaire ?
6. les conditions d’utilisation, règles de réutilisation et `robots.txt` permettent-ils une collecte planifiée ?
7. quels quotas, délais et limites de pagination doivent être imposés ?
8. quelle est la date de la dernière revue de cette décision ?

L’absence de connexion requise rend une plateforme **candidate techniquement** au scraping, mais ne suffit pas à rendre ce scraping autorisé. La décision finale de JobPilot doit être l’un des modes suivants :

- `API` ;
- `RSS` ;
- `SCRAPING_HTTP` ;
- `SCRAPING_BROWSER` ;
- `GMAIL/EXTENSION` ;
- `BLOCKED` avec une raison documentée.

L’objectif est qu’aucune plateforme ne reste durablement dans un état vague. Toute source `UNDER_REVIEW` doit progressivement obtenir une décision de collecte explicite. Lorsqu’un scraper est autorisé, il doit être implémenté **une plateforme par PR** avec fixtures locales et sans dépendre du site réel dans la CI.

## Statuts

### `OPERATIONAL`

Un connecteur existe dans le registre backend et dispose de sa propre politique de collecte, de sa configuration, de ses diagnostics et de ses tests.

Plateformes actuellement représentées dans la matrice :

- Adzuna, via son API ;
- France Travail, via l’API officielle Offres d’emploi v2 ;
- SmartRecruiters, via la Posting API officielle et une liste bornée d’entreprises configurées par l’utilisateur ;
- Le Studio Tech, via les pages publiques de missions en `SCRAPING_HTTP` contrôlé.

Un connecteur opérationnel peut rester en **configuration requise** tant que ses identifiants ou paramètres obligatoires sont absents. Dans ce cas, aucune requête externe n’est exécutée.

### `PLANNED`

Un canal officiel réutilisable a été identifié, mais le connecteur n’est pas encore développé ou configuré.

Une source passe dans cet état uniquement après confirmation de son API ou de son flux officiel. Elle ne devient `OPERATIONAL` qu’après ajout au registre backend, politique de collecte, limites, tests et documentation.

### `EMAIL_OR_EXTENSION_ONLY`

Aucune collecte automatique en arrière-plan n’est autorisée dans JobPilot. Les seules voies admises sont :

- une alerte e-mail reconnue reçue par l’utilisateur ;
- l’import volontaire d’une page ouverte par l’utilisateur dans son navigateur.

LinkedIn, Indeed, Free-Work et WeLoveDevs sont placés dans cet état.

#### Cas Free-Work

La visibilité publique d’une page ne constitue pas une autorisation de scraper. Les conditions de Free-Work limitent l’usage à la navigation personnelle, interdisent la reproduction ou l’adaptation des éléments du site et interdisent l’extraction substantielle de la base de données.

Référence officielle : <https://www.free-work.com/fr/terms>

JobPilot ne doit donc pas ajouter de scraper HTTP ou navigateur planifié pour Free-Work sans autorisation écrite ou canal officiel distinct. Le connecteur Gmail et l’import assisté restent les voies sûres déjà prévues.

#### Cas WeLoveDevs

Décision revue le **9 août 2026**. Les listes d’offres et des fiches de poste WeLoveDevs peuvent être consultées publiquement sans session privée, ce qui rend la source techniquement accessible. Cette visibilité ne suffit toutefois pas à autoriser une collecte planifiée.

Les CGU développeur actuellement publiées par WeLoveDevs encadrent strictement l’usage de la plateforme. Elles restreignent les actes de copie ou reproduction de la plateforme, y compris dans un contexte de stockage, interdisent plus généralement l’usage des services à des fins autres que celles prévues et demandent de ne pas imposer de charge disproportionnée à l’infrastructure. Elles prévoient qu’un usage pour une autre finalité doit faire l’objet d’une demande auprès de l’éditeur.

Références officielles :

- <https://welovedevs.com/fr/legal-notes/>
- <https://welovedevs.com/wp-content/uploads/2025/05/CGU.Developpeur.V.3.4.docx.pdf>

JobPilot classe donc WeLoveDevs en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP ou navigateur planifié n’est ajouté. Une collecte automatique ne pourra être envisagée qu’après autorisation écrite ou identification d’un canal officiel réutilisable. Les alertes reconnues et l’import explicitement déclenché par l’utilisateur restent les voies admises.

### `UNDER_REVIEW`

Aucun canal réutilisable n’est encore confirmé. La source reste visible pour éviter qu’elle soit oubliée, mais aucune collecte planifiée ne doit être déclenchée.

La revue doit vérifier au minimum :

1. l’existence d’une API, d’un flux ou d’un export officiel ;
2. l’existence de listes et détails d’offres publics sans authentification ;
3. les conditions d’utilisation et de réutilisation ;
4. l’authentification et les quotas ;
5. les règles `robots.txt` lorsqu’un accès HTTP public est envisagé ;
6. la stabilité du format et la disponibilité des champs obligatoires ;
7. la nécessité éventuelle d’un accord partenaire ;
8. la décision finale entre API/RSS, scraping HTTP, scraping navigateur, Gmail/extension ou blocage documenté.

## Le Studio Tech

Le Studio Tech est le premier scraper HTML public opérationnel de cette matrice.

Décision revue le **9 août 2026** :

- liste de missions accessible sans compte ;
- fiches mission accessibles sans session privée ;
- mode `SCRAPING_HTTP` suffisant ;
- `robots.txt` vérifié obligatoirement au runtime ;
- pagination et enrichissement des fiches bornés ;
- aucune candidature ou authentification automatisée ;
- usage limité à la recherche personnelle et conservation du lien source.

La documentation détaillée est disponible dans [`le-studio-tech.md`](le-studio-tech.md).

## EURES

Le portail officiel EURES agrège des offres provenant des services publics de l’emploi, membres et partenaires de 31 pays. Il constitue une source très importante, mais la revue actuelle de JobPilot n’a pas encore identifié une API ou un flux officiel de vacances réutilisable avec un contrat technique suffisamment clair.

Références officielles :

- <https://eures.europa.eu/>
- <https://eures.europa.eu/employers/advertise-job_fr>

EURES reste donc `UNDER_REVIEW` plutôt que d’être présenté prématurément comme un connecteur API.

## Plateformes couvertes

La matrice couvre la liste demandée : LinkedIn, Malt, Free-Work, Apec, Collective.work, Crème de la Crème, FreelanceRepublik, Comet, Cherry Pick, LeHibou, Mindquest, WeLoveDevs, Sept Lieues, Jean-Michel.io, Welcome to the Jungle, Cadremploi, HelloWork, Jobijoba, EURES, Freelance-Informatique, Indeed, Adzuna, Kicklox, Talent.com, SmartRecruiters, GetYourJob, Le Studio Tech, Meteojob, Michael Page et France Travail.

LesJeudis est conservé en complément, car les alertes de cette source font déjà partie des canaux reconnus par JobPilot.

## Règle d’implémentation

Chaque nouvelle source opérationnelle doit être livrée dans une PR séparée comprenant :

- sa politique et son statut de conformité ;
- son mode de collecte explicite ;
- la date de revue des règles de collecte ;
- des limites de requêtes explicites ;
- un timeout ;
- une normalisation vers l’offre canonique ;
- une déduplication idempotente ;
- des tests sans appel au service externe ;
- des diagnostics de santé et de qualité des champs ;
- une documentation de configuration et de désactivation.

Pour un scraper, ajouter en plus :

- fixtures HTML locales anonymisées ;
- version du parseur ;
- tests de sélecteurs/champs manquants ;
- pagination maximale ;
- délai minimal entre requêtes ;
- arrêt sur refus d’accès répétés ;
- aucun login automatisé, CAPTCHA bypass, proxy rotatif ou mode stealth.
