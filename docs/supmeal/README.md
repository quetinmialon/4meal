# Format d’export SUPMEAL

SUPMEAL est le format JSON versionné d’échange de 4meal. La version initiale est `1.0.0` et le document doit commencer par `format: "SUPMEAL"` et `version: "1.0.0"`.

## Compatibilité

Le modèle est volontairement neutre : `title`, `ingredients` et `steps` sont toujours disponibles et se mappent directement vers les champs usuels de Mealie, Tandoor Recipes et Paprika. Un adaptateur peut donc importer une recette sans connaître les champs internes de 4meal. Les champs non supportés par une plateforme sont ignorés ou conservés dans ses métadonnées si la plateforme le permet.

## Sérialisation

- UTF-8, JSON RFC 8259, un seul objet racine ; aucune enveloppe HTTP et aucun JSON Lines.
- Les clés sont en `snake_case`, les tableaux sont ordonnés et les doublons sont interdits lorsqu’indiqué par le schéma.
- Dates en ISO 8601 avec fuseau (`date-time`). `exported_at` est obligatoire.
- Les nombres sont JSON numbers/integers ; les durées sont des minutes entières et les quantités sont des nombres décimaux positifs ou `null`.
- Une donnée absente ou inconnue est `null` uniquement pour les champs explicitement nullable. Les chaînes vides sont interdites pour les valeurs textuelles obligatoires.
- Les URL d’images sont absolues ; une image locale non publiable est omise (`null`). Les images ne sont pas embarquées en base64 dans la v1.
- L’ordre des ingrédients est l’ordre d’affichage ; l’ordre des étapes est `position` croissante et commence à 1.

## Identifiants et références

Chaque identifiant est une chaîne stable dans l’export, namespacée et opaque : `supmeal:cookbook:<token>` ou `supmeal:recipe:<token>`. Il ne faut jamais réutiliser un entier de base de données comme identifiant global. Les `recipe_ids` d’un cookbook et les `cookbook_ids` d’une recette se référencent mutuellement ; une recette sans cookbook est autorisée avec `cookbook_ids: []`. Les références doivent exister et être cohérentes dans le document : si un cookbook contient une recette, la recette doit contenir ce cookbook.

## Limites v1

La v1 ne transporte ni utilisateurs/membres, permissions, favoris, commentaires, planification, historique, suppression, secrets, ni fichiers image. Les étapes ne représentent qu’une instruction texte, une durée et une URL. Les conversions d’unités, de langue et de taxonomie des tags relèvent de l’adaptateur d’import.

Une évolution incompatible augmente le major (`2.0.0`). Une évolution rétrocompatible augmente le minor ; un correctif de définition augmente le patch. Un importeur doit refuser une major inconnue et peut refuser toute version autre que `1.0.0` tant qu’il ne supporte pas la négociation de versions.

## Exemples

- Valide : [`valid-minimal.json`](examples/valid-minimal.json)
- Valide : [`valid-complete.json`](examples/valid-complete.json)
- Invalide : [`invalid-unknown-version.json`](examples/invalid-unknown-version.json)
- Invalide : [`invalid-reference.json`](examples/invalid-reference.json) (référence structurellement valide mais inexistante ; à rejeter par la validation d’intégrité)

La validation JSON Schema ne peut pas exprimer de façon portable l’existence et la symétrie de références entre deux tableaux. Après validation du schéma, l’importeur doit vérifier l’intégrité référentielle et l’unicité des identifiants.
