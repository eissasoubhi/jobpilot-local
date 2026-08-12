# Navigation contextuelle vers le CRM

Le CRM accepte un filtre initial dans l’URL via `?q=`. Par exemple :

```text
/crm?q=Acme%20France
```

À l’ouverture, la page initialise sa recherche avec cette valeur puis applique le filtrage CRM existant. Aucun identifiant d’organisation n’est inventé et aucune donnée source n’est modifiée.

Le helper frontend `crmOrganizationHref()` construit une URL encodée uniquement lorsqu’un nom d’organisation non vide est disponible. Cette fondation permet aux écrans Offres et Review Queue d’ajouter ensuite des accès directs vers le contexte société/recruteur sans dupliquer la logique d’URL.

Ce lot ne modifie ni les règles de déduplication CRM, ni les annotations, ni les corrections de contacts.
