# SEARCH-01 — Recherche PostgreSQL

La recherche est exposée par `GET /api/recipes?q=...` et réutilise la requête des recettes accessibles. Le paramètre `q` ne constitue pas un filtre métier supplémentaire : il restreint les résultats au texte recherché.

En PostgreSQL, `recipes.search_vector` est un `tsvector` dénormalisé et pondéré : titre en `A`, ingrédients et tags en `B`, contenu des étapes en `C`. Des triggers le recalculent lors des modifications des recettes, ingrédients, étapes, associations de tags et noms/slugs de tags. Un index GIN accélère le prédicat `search_vector @@ websearch_to_tsquery('french', q)`.

Le classement utilise `ts_rank(...)` décroissant — les pondérations du vecteur donnent la priorité au titre — puis `created_at` et `id` décroissants pour un ordre stable. L’isolation d’accès est appliquée par `AccessibleRecipesQuery` avant le prédicat de recherche et la pagination Laravel.
