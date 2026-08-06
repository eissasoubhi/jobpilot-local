# Connecteur SmartRecruiters

JobPilot utilise la **Posting API** officielle de SmartRecruiters. Le connecteur ne parcourt pas les pages HTML et n’automatise aucune session utilisateur.

Références officielles :

- <https://developers.smartrecruiters.com/docs/posting-api>
- <https://developers.smartrecruiters.com/reference/v1listpostings>
- <https://developers.smartrecruiters.com/reference/v1getposting>
- <https://developers.smartrecruiters.com/docs/objects>

## Prérequis

La Posting API utilise une clé API transmise dans l’en-tête `X-SmartToken`. OAuth n’est pas pris en charge pour cette ressource.

Il faut également fournir les identifiants des entreprises à suivre. Un identifiant correspond généralement au segment situé après `careers.smartrecruiters.com/` dans l’URL de la page carrière de l’entreprise.

```dotenv
SMARTRECRUITERS_API_TOKEN=your-api-key
SMARTRECRUITERS_COMPANY_IDENTIFIERS=company-one,company-two
```

Le connecteur reste en **configuration requise** et n’effectue aucune requête tant que le jeton ou la liste d’entreprises manque.

Après modification du fichier `.env`, recréer les services qui exécutent les synchronisations :

```bash
docker compose up -d --force-recreate api scheduler
```

## Limites configurables

```dotenv
SMARTRECRUITERS_PAGES=1
SMARTRECRUITERS_RESULTS_PER_PAGE=100
SMARTRECRUITERS_MAX_DETAILS=20
```

Les bornes internes restent prioritaires sur les valeurs du fichier `.env` :

- cinq entreprises maximum ;
- deux pages maximum par entreprise ;
- cent publications maximum par page ;
- vingt fiches détaillées maximum par synchronisation ;
- timeout de dix secondes par requête.

Avec les valeurs maximales, une synchronisation effectue au plus trente requêtes : dix pages de liste et vingt fiches détaillées.

## Fonctionnement

Pour chaque entreprise configurée, JobPilot :

1. demande uniquement les publications `PUBLIC` ;
2. récupère une ou deux pages bornées ;
3. applique localement les critères globaux `targetJobs` et `skills` sur le titre, le département, la fonction, l’industrie, le type de contrat et le lieu ;
4. télécharge la fiche détaillée uniquement pour une publication correspondante ;
5. normalise le titre, l’entreprise, le lieu, le contrat, le mode de travail, les sections de l’annonce, la date et l’URL publique ;
6. déduplique les résultats par UUID ou identifiant SmartRecruiters ;
7. envoie les offres au catalogue canonique multi-sources.

Le filtrage avant téléchargement des détails réduit le trafic et évite de récupérer le contenu complet d’offres manifestement étrangères au profil.

## Données normalisées

La description combine, lorsqu’elles existent :

- la description du poste ;
- les qualifications ;
- les informations complémentaires.

Le mode de travail utilise en priorité l’indicateur `location.remote`, puis les termes explicites de l’annonce. Le type de contrat est déduit du champ `typeOfEmployment` avec un repli limité sur le texte.

Les informations de rémunération SmartRecruiters sont facultatives et leur structure dépend des publications. Cette première version ne les convertit pas artificiellement : `salaryMin` et `salaryMax` restent vides.

## Politique et sécurité

- canal : Posting API officielle uniquement ;
- statut : `AUTHORIZED_ONLY` ;
- authentification : clé API fournie par l’utilisateur ;
- identifiants d’entreprises validés et limités à cinq ;
- aucune URL arbitraire configurable ;
- aucune page HTML, session privée, connexion utilisateur, CAPTCHA ou proxy ;
- aucune requête lorsque la configuration est incomplète ;
- les tests utilisent `MockHttpClient` et ne contactent jamais SmartRecruiters.

Le connecteur apparaît dans la page **Connecteurs** dès le démarrage. Il peut être activé et testé uniquement après configuration complète.
