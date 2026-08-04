# Format CSV des recettes (IO-08)

Le CSV de recettes est un format d’échange indépendant du document JSON SUPMEAL.
Il est encodé en UTF-8, utilise la virgule comme séparateur, le guillemet double
comme caractère d’échappement (RFC 4180), et possède exactement l’en-tête ci-dessous.

```csv
format_version,record_type,recipe_key,title,description,servings,prep_time_minutes,cook_time_minutes,rest_time_minutes,notes,source,ingredient_position,ingredient_name,ingredient_quantity,ingredient_unit,ingredient_preparation,ingredient_optional,ingredient_group,step_position,step_instruction,step_duration_minutes,tag
```

La version actuelle est `1`. Chaque recette possède une ligne `recipe`, identifiée
par `recipe_key`. Les champs structurés ne sont jamais encodés en JSON : chaque
ingrédient, étape et tag est une ligne séparée avec respectivement les types
`ingredient`, `step` et `tag`. Les colonnes sans rapport avec le type de ligne sont
vides. Les positions commencent à 1 et sont uniques par recette et type.

Les nombres utilisent `.` comme séparateur décimal, les booléens sont `true` ou
`false`, et une cellule vide représente une valeur nulle. Le CSV n’exporte pas les
identifiants internes, les images, les cookbooks ou les favoris. À l’import, les
recettes sont créées dans l’espace personnel de l’utilisateur ; `recipe_key` sert
uniquement de référence interne au document et n’est jamais persisté comme ID.

Export : `GET /api/export/csv`.

Import : `POST /api/import/csv` avec un champ multipart `file` portant l’extension
`.csv` (10 Mo maximum). Les erreurs de format sont retournées en HTTP 422 et aucune
donnée partiellement importée n’est conservée.
