# REC-14 — Duplication d’une recette

L’endpoint `POST /api/recipes/{recipe}/duplicate` duplique une recette accessible
vers l’utilisateur authentifié lorsque `cookbook_id` est absent, ou vers le
cookbook indiqué lorsque ce champ est fourni.

La copie reprend les champs métier de la recette, ses ingrédients, ses étapes et
ses tags. Les tags sont recréés/réutilisés dans l’espace de tags de l’utilisateur
qui effectue la copie, car les tags sont des données personnelles. L’auteur de la
copie est l’utilisateur authentifié.

Décision image : les chemins d’image de la recette et des étapes ne sont pas
recopiés. Un chemin partagé pourrait rendre la copie dépendante du cycle de vie
du fichier original ; la copie démarre donc sans image (`image_path: null`).

La création de la recette et de toutes ses relations est exécutée dans une seule
transaction. Une erreur annule l’ensemble de la copie.
