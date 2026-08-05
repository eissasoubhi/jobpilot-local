# ADR 0006 — Prioriser l’import assisté avant un scraping non autorisé

- Statut : accepté
- Date : 2026-08-05

## Contexte

La roadmap prévoit un framework de scraping contrôlé pour les plateformes dont les pages sont publiques et dont l’automatisation est autorisée. Free-Work faisait partie des premières sources à étudier.

La revue de conformité du 5 août 2026 a cependant relevé que les conditions générales Free-Work limitent l’usage à la navigation personnelle et interdisent la reproduction, la diffusion, la combinaison ou l’adaptation des éléments du site. Les mentions légales réservent également les droits de reproduction.

Le fait qu’une offre soit lisible sans authentification ne suffit donc pas à justifier une collecte planifiée par le backend.

## Décision

Pour Free-Work :

- ne pas implémenter de crawler HTTP ou navigateur planifié sans autorisation explicite ;
- exploiter les alertes Gmail déjà reçues par l’utilisateur ;
- proposer un import unitaire déclenché par l’utilisateur depuis l’extension Chrome ;
- limiter l’extraction aux données visibles nécessaires à son usage local ;
- passer l’import de l’extension dans le catalogue canonique pour garantir idempotence, fusion multi-sources et traçabilité ;
- réexaminer la décision si une API, un partenariat ou une autorisation écrite devient disponible.

Le framework de scraping générique sera introduit avec une source pilote dont l’autorisation et les limites sont vérifiables.

## Conséquences positives

- absence d’exploration automatisée de Free-Work ;
- conservation de la valeur produit grâce aux alertes Gmail et à l’import assisté ;
- aucune duplication lors d’imports répétés ;
- meilleure extraction des données structurées `JobPosting` ;
- règle de conformité réutilisable pour les futures plateformes.

## Compromis

- l’utilisateur doit ouvrir la page et déclencher l’import ;
- JobPilot ne découvre pas directement toutes les offres Free-Work en arrière-plan ;
- la qualité du fallback visible dépend du HTML de la page ;
- une nouvelle revue sera nécessaire avant tout changement de mode.

## Références

- <https://www.free-work.com/fr/terms>
- <https://www.free-work.com/fr/terms/legal-mentions>
- [`../../connectors/free-work.md`](../../connectors/free-work.md)
