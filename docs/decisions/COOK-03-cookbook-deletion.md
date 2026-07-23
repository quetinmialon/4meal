# COOK-03 — Suppression d’un cookbook

## Décision

La suppression d’un cookbook est réservée à son propriétaire et nécessite une confirmation explicite du nom exact du cookbook dans le corps de la requête :

```json
{
  "confirmation": "Nom exact du cookbook"
}
```

L’endpoint est `DELETE /api/cookbooks/{cookbook}`. L’identifiant public UUID du cookbook est utilisé dans l’URL.

## Sort des données associées

Les recettes actuelles appartiennent exclusivement à leur cookbook : elles n’ont ni propriétaire distinct, ni relation de partage ou de détachement. La suppression du cookbook supprime donc définitivement :

- ses recettes ;
- ses membres et leurs rôles.

Cette suppression est assurée par les contraintes `ON DELETE CASCADE` des tables `recipes` et `cookbook_members`.

## Garanties

- la Policy refuse l’action à tout utilisateur qui n’est pas le propriétaire ;
- le Form Request refuse une confirmation absente ou différente du nom exact ;
- la suppression et ses effets associés sont exécutés dans une transaction ;
- la réponse `204 No Content` ne divulgue aucune donnée supprimée.
