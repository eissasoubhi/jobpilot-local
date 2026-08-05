# ADR 0009 — Préférer un flux officiel au scraping HTML

- Statut : accepté
- Date : 2026-08-05

## Contexte

L’infrastructure HTTP contrôlée permet désormais d’implémenter des connecteurs HTML robustes. Elle ne crée toutefois aucune autorisation d’extraire une plateforme.

La recherche d’une première source pilote a identifié le job board Symfony, pertinent pour le profil ciblé par JobPilot. Sa page publique propose directement un lien **Jobs RSS**. Utiliser les pages HTML aurait dupliqué un canal de syndication déjà prévu pour les lecteurs automatisés et aurait créé une dépendance plus fragile aux sélecteurs de présentation.

## Décision

JobPilot intègre Symfony Jobs avec un connecteur `RSS` et non avec un connecteur `SCRAPING_HTTP`.

Le connecteur :

- appelle uniquement `https://symfony.com/jobs.rss` ;
- réutilise le transport HTTP contrôlé pour les quotas, retries, timeouts, redirections, cache et circuit breaker ;
- parse RSS 2.0 et Atom avec un composant sans dépendance au site réel dans la CI ;
- est déclaré `ALLOWED` car le job board expose explicitement ce flux de syndication ;
- ne considère pas cette décision comme une autorisation générale de parcourir le reste du site.

Le premier scraper HTML réel reste différé jusqu’à l’identification d’une source qui autorise explicitement ce mode de collecte.

## Conséquences

### Positives

- canal plus stable et moins coûteux qu’un parseur HTML ;
- intention d’usage automatisé explicite ;
- une seule requête normale par synchronisation ;
- identité stable par `guid` ou identifiant Atom ;
- parseur réutilisable pour d’autres flux autorisés.

### Négatives

- le flux peut contenir moins de détails que la page HTML ;
- certaines rémunérations mensuelles ou horaires restent seulement dans la description ;
- le parseur doit rester tolérant aux variantes RSS et Atom.

## Alternatives rejetées

### Scraper les cartes HTML Symfony Jobs

Rejeté car le flux RSS officiel fournit déjà le canal adapté.

### Attendre une source HTML

Rejeté pour cette livraison : l’ajout d’un canal RSS immédiatement utile valide le transport contrôlé et le pipeline canonique sans diminuer les exigences applicables au futur scraper HTML.

### Convertir les rémunérations horaires ou mensuelles

Rejeté afin de ne pas inventer une hypothèse de durée journalière ou annuelle.
