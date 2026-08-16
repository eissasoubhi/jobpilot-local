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
- `RSS` ou autre flux officiel ;
- `XML` partenaire lorsqu’un export officiel sous convention existe ;
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

Un canal officiel réutilisable a été identifié, mais le connecteur n’est pas encore développé, configuré ou autorisé pour JobPilot.

Une source passe dans cet état uniquement après confirmation d’une API, d’un flux ou d’un export officiel. Elle ne devient `OPERATIONAL` qu’après obtention des accès ou accords nécessaires, ajout au registre backend, politique de collecte, limites, tests et documentation.

Apec est dans cet état via son export XML sous convention. Talent.com l’est via son programme Publisher. Jobijoba rejoint cet état via son programme officiel d’affiliation, qui annonce des intégrations par flux, API ou widget ; JobPilot retient l’API ou un flux officiel comme voie cible et n’active aucun scraping.

### `EMAIL_OR_EXTENSION_ONLY`

Aucune collecte automatique en arrière-plan n’est autorisée dans JobPilot. Les seules voies admises sont :

- une alerte e-mail reconnue reçue par l’utilisateur ;
- l’import volontaire d’une page ouverte par l’utilisateur dans son navigateur.

LinkedIn, Indeed, Free-Work, Collective.work, WeLoveDevs, HelloWork, Welcome to the Jungle, LesJeudis, LeHibou, Meteojob et Cadremploi sont placés dans cet état.

#### Cas Free-Work

La visibilité publique d’une page ne constitue pas une autorisation de scraper. Les conditions de Free-Work limitent l’usage à la navigation personnelle, interdisent la reproduction ou l’adaptation des éléments du site et interdisent l’extraction substantielle de la base de données.

Référence officielle : <https://www.free-work.com/fr/terms>

JobPilot ne doit donc pas ajouter de scraper HTTP ou navigateur planifié pour Free-Work sans autorisation écrite ou canal officiel distinct. Le connecteur Gmail et l’import assisté restent les voies sûres déjà prévues.

#### Cas Collective.work

Décision revue le **11 août 2026** sur les CGU officielles Collective.work et les pages publiques d’opportunités.

Les CGU indiquent explicitement que le re-postage, le scraping, l’utilisation automatisée et l’utilisation à grand volume de la plateforme sont interdits. L’utilisateur ne dispose que d’un droit d’utilisation pour son propre compte. La visibilité publique des opportunités ne constitue donc pas une autorisation de collecte planifiée.

Références officielles :

- <https://www.collective.work/cgu>
- <https://www.collective.work/jobs/fr>

JobPilot classe Collective.work en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP/Browser planifié, aucune automatisation de session et aucun endpoint non documenté ne sont ajoutés. Les alertes Gmail lorsqu’elles existent et sont reconnues, ainsi que l’import volontaire d’une page ouverte par l’utilisateur, restent les canaux admis.

Cette décision ne pourra être réouverte qu’après publication d’un canal officiel réutilisable ou autorisation écrite applicable de Collective.work.

#### Cas WeLoveDevs

Décision revue le **9 août 2026**. Les listes d’offres et des fiches de poste WeLoveDevs peuvent être consultées publiquement sans session privée, ce qui rend la source techniquement accessible. Cette visibilité ne suffit toutefois pas à autoriser une collecte planifiée.

Les CGU développeur actuellement publiées par WeLoveDevs encadrent strictement l’usage de la plateforme. Elles restreignent les actes de copie ou reproduction de la plateforme, y compris dans un contexte de stockage, interdisent plus généralement l’usage des services à des fins autres que celles prévues et demandent de ne pas imposer de charge disproportionnée à l’infrastructure. Elles prévoient qu’un usage pour une autre finalité doit faire l’objet d’une demande auprès de l’éditeur.

Références officielles :

- <https://welovedevs.com/fr/legal-notes/>
- <https://welovedevs.com/wp-content/uploads/2025/05/CGU.Developpeur.V.3.4.docx.pdf>

JobPilot classe donc WeLoveDevs en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP ou navigateur planifié n’est ajouté. Une collecte automatique ne pourra être envisagée qu’après autorisation écrite ou identification d’un canal officiel réutilisable. Les alertes reconnues et l’import explicitement déclenché par l’utilisateur restent les voies admises.

#### Cas HelloWork

Décision revue le **11 août 2026**. Les listes et fiches d’offres HelloWork sont consultables publiquement, mais les CGU actuelles distinguent explicitement cette visibilité d’un droit d’extraction automatisée.

Les CGU HelloWork indiquent que les systèmes automatisés ou logiciels accédant au Site sont des utilisateurs non légitimes de la base de données et interdisent strictement l’utilisation de systèmes automatisés pour extraire les données du Site, y compris le Contenu, par `screen scraping` ou `web scraping`, à des fins commerciales **ou non**. L’exception prévue exige une convention de licence écrite conclue avec HELLOWORK et autorisant expressément l’extraction.

Références officielles revues :

- <https://www.hellowork-group.com/fr/legal/cgu-hellowork/>
- <https://www.hellowork.com/fr-fr/page/infos-legales.html>
- <https://recruteur.hellowork.com/fr/page/nos-partenaires-ats.html>

Les intégrations ATS présentées publiquement servent aux recruteurs à diffuser leurs offres ou synchroniser des candidatures avec HelloWork ; elles ne constituent pas, dans cette revue, une API publique de lecture du catalogue candidat.

JobPilot classe donc HelloWork en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP ou Browser planifié, aucun endpoint interne non documenté et aucune automatisation de session ne doivent être ajoutés. Gmail et l’import assisté restent disponibles. Cette décision ne pourra être réouverte qu’après licence écrite applicable ou identification d’un canal officiel de lecture/réutilisation.

#### Cas Welcome to the Jungle

Décision revue le **11 août 2026** sur les CGU officielles version du **27 avril 2026**.

Les CGU Welcome to the Jungle interdisent explicitement de développer, soutenir ou utiliser un logiciel, dispositif, script, robot, robot d’indexation, extension/module de navigateur ou autre technologie afin de procéder à une extraction automatisée de données (`scraping`) ou de copier/télécharger le Site, ses Contenus ou ses Services.

L’exception prévue pour certains opérateurs de moteurs de recherche publics vise la création d’index de recherche accessibles au public et ne s’applique pas à l’usage JobPilot. Aucun canal officiel de lecture/réutilisation du catalogue candidat n’a été identifié dans cette revue.

Référence officielle :

- <https://www.welcometothejungle.com/fr/pages/terms>

JobPilot classe donc Welcome to the Jungle en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP ou Browser planifié, aucun endpoint interne non documenté et aucune automatisation de session privée ne doivent être ajoutés. Gmail et l’import assisté restent les canaux admis tant qu’un accord écrit applicable ou un canal officiel de lecture/réutilisation n’est pas publié.

#### Cas LesJeudis

Décision revue le **11 août 2026** sur les CGU officielles mises à jour le **20 janvier 2026**.

Les CGU LesJeudis imposent aux utilisateurs, candidats comme recruteurs, de ne pas utiliser un logiciel robot ou tout autre procédé automatisé de scraping et de ne pas détourner ou tenter de détourner les systèmes de protection internes à la plateforme.

Références officielles :

- <https://lesjeudis.com/fr/cgu>
- <https://lesjeudis.com/fr/mentions-legales>

Aucun canal officiel de lecture/réutilisation du catalogue JobPilot n’a été confirmé dans cette revue. JobPilot classe donc LesJeudis en `EMAIL_OR_EXTENSION_ONLY`. Aucun scraper HTTP ou Browser planifié et aucune automatisation de session ne doivent être ajoutés. Les alertes Gmail déjà reconnues et l’import assisté restent les canaux admis tant qu’un accord écrit applicable ou un canal officiel n’est pas publié.

#### Cas LeHibou

Décision revue le **11 août 2026**.

LeHibou expose des pages publiques de présentation et une interface de recherche, mais aucun API, flux RSS/XML ou canal officiel de lecture/réutilisation des missions n’a été identifié lors de cette revue. Les CGU indiquent que l’utilisateur doit créer un compte pour accéder aux Services et que l’accès au Site et aux Services est réservé aux utilisateurs inscrits.

Références officielles :

- <https://www.lehibou.com/conditions-generales-utilisation>
- <https://www.lehibou.com/freelance>
- <https://www.lehibou.com/recherche/annonces>

Cette décision ne prétend pas que `robots.txt` interdit la collecte. Le point déterminant est l’absence de canal officiel réutilisable et le périmètre d’accès aux Services décrit par les CGU. Comme JobPilot interdit l’automatisation de connexion, de cookies privés ou de session utilisateur, il classe LeHibou en `EMAIL_OR_EXTENSION_ONLY` plutôt que de déduire une autorisation de scraping de simples pages SEO accessibles publiquement.

Les alertes Gmail reconnues et l’import volontaire d’une page ouverte par l’utilisateur restent les canaux admis. Une collecte planifiée ne pourra être envisagée qu’après publication d’un canal officiel réutilisable ou autorisation écrite applicable.

#### Cas Meteojob

Décision revue le **11 août 2026** sur les CGU Meteojob/CleverConnect mises à jour en octobre 2025.

Les CGU limitent la visualisation du contenu à un usage personnel et privé. Toute autre utilisation non expressément autorisée nécessite un accord exprès écrit et préalable de CleverConnect, et l’extraction de tout ou partie des bases de données du site est interdite. Les conditions professionnelles renforcent cette restriction pour le scraping et certaines extractions ou réutilisations répétées des offres.

Références officielles :

- <https://www.meteojob.com/conditions>
- <https://www.meteojob.com/conditions-vente>

Aucun canal public officiel de lecture ou de redistribution des offres pour JobPilot n’a été identifié dans cette revue. JobPilot classe donc Meteojob en `EMAIL_OR_EXTENSION_ONLY` : aucun scraper HTTP/Browser planifié, aucune session privée automatisée et aucun endpoint non documenté. Les alertes Gmail reconnues et l’import volontaire d’une page ouverte par l’utilisateur restent les canaux admis.

Cette décision ne pourra être réouverte qu’après accord écrit CleverConnect ou identification d’un canal officiel de redistribution applicable.

#### Cas Cadremploi

Décision revue le **11 août 2026** sur les CGU officielles Cadremploi/Figaro Classifieds et les pages publiques d’offres.

Les CGU reconnaissent plusieurs bases de données protégées, accordent un droit d’usage privé, non collectif et non exclusif du contenu, et interdisent toute extraction, utilisation, stockage ou reproduction substantielle des bases. Elles interdisent également, sans autorisation écrite préalable, certaines collectes ou extractions automatiques au moyen de robots, logiciels ou autres dispositifs automatiques.

Références officielles :

- <https://www.cadremploi.fr/emploi/legal/conditions-generales-utilisation>
- <https://www.cadremploi.fr/emploi>

Les offres peuvent être consultées publiquement, mais cette visibilité n’autorise pas à elle seule une collecte planifiée. JobPilot classe donc Cadremploi en `EMAIL_OR_EXTENSION_ONLY` : aucun scraper HTTP/Browser planifié, aucune session privée automatisée et aucun endpoint non documenté. Les alertes Gmail reconnues et l’import volontaire d’une page ouverte par l’utilisateur restent les canaux admis.

Cette décision ne pourra être réouverte qu’après autorisation écrite Figaro Classifieds ou obtention d’un flux/canal officiel de redistribution applicable.

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
8. la décision finale entre API/RSS, flux partenaire, scraping HTTP, scraping navigateur, Gmail/extension ou blocage documenté.

## Apec

Décision revue le **11 août 2026**.

Apec propose officiellement aux sites tiers deux moyens de relayer ses offres :

- un widget de moteur de recherche ;
- un **export des offres via flux XML standardisé**, organisé par **convention de partenariat**.

Ce flux XML est le canal de redistribution à privilégier pour JobPilot si Apec accepte un partenariat. Il ne doit pas être confondu avec l’API **ADEP** mise en avant en 2026 pour permettre aux recruteurs et ATS de **diffuser leurs propres offres vers Apec.fr** : ADEP n’est pas présentée comme une API publique de lecture du catalogue candidat.

Références officielles :

- <https://corporate.apec.fr/devenir-partenaire>
- <https://www.apec.fr/faq.html?question=comment-puis-je-publier-des-offres-d-emploi-en-utilisant-un-flux-xml>
- <https://www.apec.fr/recruteur/recruter/diffuser-une-offre/fiches-transactions/diffusez-vos-offres-d-emploi-automatiquement-sur-apec-fr-gr-ce-a-votre-ats-.html>

JobPilot classe donc Apec en `PLANNED` : un canal officiel réutilisable est identifié, mais son accès nécessite une convention que JobPilot ne possède pas encore. Aucun scraper HTTP ou Browser Apec n’est activé par défaut. En attendant, les alertes Gmail reconnues et l’import assisté restent les canaux utilisables.

Étape suivante : demander une convention de partenariat Apec pour l’export XML, documenter les spécifications et quotas obtenus, puis seulement implémenter un connecteur exécutable.

## Jobijoba

Décision revue le **11 août 2026** sur le programme d’affiliation officiel.

Jobijoba propose explicitement aux sites et applications d’intégrer ses offres d’emploi dans le cadre de son programme d’affiliation. La page officielle annonce trois modalités : **flux**, **API** et **widget**. Ce canal de redistribution est la voie appropriée pour JobPilot ; la visibilité du site public ne doit pas être utilisée pour justifier un scraper.

Référence officielle :

- <https://www.jobijoba.com/fr/affiliation-offres-emploi>

JobPilot classe donc Jobijoba en `PLANNED`. Le mode structuré reste `API` tant que le format exact du flux d’affiliation n’a pas été communiqué ; la mise en œuvre future pourra retenir l’API ou le flux officiel fourni dans le cadre du partenariat. Aucun scraper HTTP ou Browser Jobijoba n’est planifié.

Étape suivante : obtenir les spécifications, accès, conditions de redistribution, credentials éventuels et quotas applicables au programme d’affiliation. Jobijoba ne pourra devenir `OPERATIONAL` qu’après réception de ces éléments et implémentation d’un connecteur dédié avec tests locaux/mocks, diagnostics et kill switch.

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
