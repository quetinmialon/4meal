# Import SUPMEAL — règles et sécurité

L’endpoint `POST /api/import` accepte un champ multipart `file` en JSON, limité à 10 Mo et aux MIME `application/json`, `application/ld+json` ou `text/json`, avec extension `.json`.

Le fichier est d’abord décodé, puis validé contre [`supmeal-1.0.schema.json`](supmeal-1.0.schema.json). Une validation métier vérifie l’unicité des références externes, l’existence des références croisées et leur symétrie. Les erreurs retournées contiennent uniquement des chemins, codes et messages génériques ; le contenu du fichier et les détails internes ne sont jamais renvoyés.

L’import est transactionnel : toute erreur d’écriture annule l’ensemble de l’opération. Les identifiants `supmeal:*` sont uniquement des clés de correspondance en mémoire et ne sont jamais utilisés comme identifiants de base de données. Toute donnée importée est attribuée à l’utilisateur authentifié comme créateur (`author_id`) ; les cookbooks importés lui appartiennent et il en devient membre `owner`.

La stratégie anti-doublon est déterministe et documentée : un cookbook déjà possédé avec le même nom ou slug est réutilisé ; une recette déjà présente dans le même périmètre avec le même titre et la même source est ignorée et signalée dans `duplicates`. Les doublons sont donc idempotents sans faire confiance à un identifiant externe.
