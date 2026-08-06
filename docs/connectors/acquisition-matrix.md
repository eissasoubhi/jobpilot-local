# Matrice d’acquisition des plateformes

La page **Connecteurs** contient une matrice informative couvrant les plateformes suivies par le produit. Son objectif est de distinguer un connecteur réellement exécutable d’une simple source demandée ou en cours de revue.

Une entrée de cette matrice ne crée jamais automatiquement :

- un scraper ;
- une authentification ;
- une synchronisation planifiée ;
- un droit de réutiliser les données d’une plateforme ;
- un bouton d’activation ou de test.

## Statuts

### `OPERATIONAL`

Un connecteur existe dans le registre backend et dispose de sa propre politique de collecte, de sa configuration, de ses diagnostics et de ses tests.

Plateformes actuellement représentées dans la matrice :

- Adzuna, via son API ;
- France Travail, via l’API officielle Offres d’emploi v2 ;
- SmartRecruiters, via la Posting API officielle et une liste bornée d’entreprises configurées par l’utilisateur.

Un connecteur opérationnel peut rester en **configuration requise** tant que ses identifiants ou paramètres obligatoires sont absents. Dans ce cas, aucune requête externe n’est exécutée.

### `PLANNED`

Un canal officiel réutilisable a été identifié, mais le connecteur n’est pas encore développé ou configuré.

Une source passe dans cet état uniquement après confirmation de son API ou de son flux officiel. Elle ne devient `OPERATIONAL` qu’après ajout au registre backend, politique de collecte, limites, tests et documentation.

### `EMAIL_OR_EXTENSION_ONLY`

Aucune collecte automatique en arrière-plan n’est autorisée dans JobPilot. Les seules voies admises sont :

- une alerte e-mail reconnue reçue par l’utilisateur ;
- l’import volontaire d’une page ouverte par l’utilisateur dans son navigateur.

LinkedIn, Indeed et Free-Work sont placés dans cet état.

#### Cas Free-Work

La visibilité publique d’une page ne constitue pas une autorisation de scraper. Les conditions de Free-Work limitent l’usage à la navigation personnelle, interdisent la reproduction ou l’adaptation des éléments du site et interdisent l’extraction substantielle de la base de données.

Référence officielle : <https://www.free-work.com/fr/terms>

JobPilot ne doit donc pas ajouter de scraper HTTP ou navigateur planifié pour Free-Work sans autorisation écrite ou canal officiel distinct. Le connecteur Gmail et l’import assisté restent les voies sûres déjà prévues.

### `UNDER_REVIEW`

Aucun canal réutilisable n’est encore confirmé. La source reste visible pour éviter qu’elle soit oubliée, mais aucune collecte planifiée ne doit être déclenchée.

La revue doit vérifier au minimum :

1. l’existence d’une API, d’un flux ou d’un export officiel ;
2. les conditions d’utilisation et de réutilisation ;
3. l’authentification et les quotas ;
4. les règles `robots.txt` lorsqu’un accès HTTP public est envisagé ;
5. la stabilité du format et la disponibilité des champs obligatoires ;
6. la nécessité éventuelle d’un accord partenaire.

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
- des limites de requêtes explicites ;
- un timeout ;
- une normalisation vers l’offre canonique ;
- une déduplication idempotente ;
- des tests sans appel au service externe ;
- des diagnostics de santé et de qualité des champs ;
- une documentation de configuration et de désactivation.
