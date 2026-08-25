# Entreprise ciblée dans les messages et lettres de motivation

Les textes de candidature ne doivent jamais présenter un job board comme l’employeur réel.

## Règle de résolution

JobPilot choisit le nom à utiliser dans cet ordre :

1. nom saisi manuellement au moment de la régénération ;
2. `clientName` lorsque ce champ est connu et ne correspond pas à la plateforme source ;
3. `company` lorsqu’il est connu et ne correspond pas à la plateforme source ;
4. aucun nom d’entreprise lorsque l’information reste inconnue.

Les valeurs correspondant à la source, au `sourceCode` ou au domaine de la plateforme sont traitées comme des noms de plateforme. Par exemple, une offre importée depuis Indeed avec `company = Indeed` ne doit pas produire « chez Indeed ».

## Review Queue

Le drawer Motivation affiche un champ **Entreprise ciblée** partagé entre les onglets **Lettre de motivation** et **Message court**.

- lorsqu’une entreprise fiable est déjà connue, le champ est prérempli ;
- lorsqu’aucune entreprise fiable n’est connue, le champ reste vide et un avertissement l’indique ;
- l’utilisateur peut saisir le nom réel puis régénérer ;
- si le champ reste vide, le texte est généré sans citer d’entreprise plutôt que d’inventer ou d’utiliser la plateforme.

La saisie de régénération est un override local au contenu généré : elle ne modifie pas silencieusement l’offre canonique ni les données importées.
