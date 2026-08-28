# 4meal — Guide utilisateur

## Bien démarrer

Ouvrez 4meal à l’adresse fournie par votre installation, puis connectez-vous.
Depuis le tableau de bord, vous pouvez accéder rapidement à vos recettes, vos
cookbooks, votre planning et vos notifications.

## Recettes

Dans **Mes recettes**, vous pouvez rechercher, filtrer et consulter vos
recettes. Une recette contient notamment une description, des portions, des
ingrédients, des étapes, des tags, une note et éventuellement une image.

Depuis la fiche d’une recette, vous pouvez :

- l’ajouter à vos favoris ou la noter ;
- l’ajouter au planning ;
- la modifier, la dupliquer ou la supprimer selon vos droits ;
- consulter son historique ;
- lire, écrire et réagir aux commentaires.

**Découvrir** permet de consulter les recettes rendues publiques par les
autres utilisateurs. La recherche propose des filtres et des recherches
sauvegardées, réutilisables depuis votre compte.

## Cookbooks partagés

Un cookbook est un espace partagé pour organiser des recettes et collaborer.
Depuis **Mes cookbooks**, vous pouvez créer un cookbook puis consulter ses
recettes, son planning, ses membres et sa messagerie.

Les droits dépendent du rôle :

| Rôle | Droits principaux |
| --- | --- |
| Propriétaire | gestion complète du cookbook et de ses membres |
| Éditeur | gestion des recettes et participation au cookbook |
| Commentateur | lecture, commentaires et réactions |
| Lecteur | consultation du contenu autorisé |

Le propriétaire ou un éditeur peut inviter une personne depuis la page des
membres et lui attribuer un rôle. Les messages du cookbook sont partagés avec
les membres autorisés et peuvent recevoir des réactions.

## Planning et liste de courses

Dans **Planning**, choisissez une recette, une date et, si besoin, un cookbook.
Vous pouvez afficher vos repas, les modifier ou les supprimer. Les repas
récurrents permettent de répéter automatiquement une organisation.

La **liste de courses** regroupe les ingrédients des repas planifiés. Vérifiez
les quantités avant vos achats et actualisez le planning si le menu change.

## Importer et exporter

La rubrique **Import & Export** permet de sauvegarder ou transférer vos
recettes :

- export JSON SUPMEAL pour un export complet, avec option d’inclure les
  cookbooks ;
- export CSV pour les recettes, ingrédients, étapes et tags ;
- import JSON SUPMEAL, CSV ou fichier Mealie avec prévisualisation et rapport
  d’erreurs.

Le CSV ne transporte pas les cookbooks, images, favoris, commentaires ni
planning. Corrigez les erreurs signalées dans la prévisualisation avant de
confirmer un import.

## Notifications et compte

Le panneau de notifications affiche votre activité récente. Une notification
peut être marquée comme lue individuellement ou toutes les notifications
peuvent être marquées comme lues.

Dans **Profil**, vous pouvez modifier vos informations, votre photo, votre
thème, vos préférences alimentaires et vos préférences de notifications. La
rubrique **Sécurité** permet de changer le mot de passe et de gérer la
validation en deux étapes. Les comptes Google ou Microsoft liés peuvent être
gérés depuis le même espace.

## Conseils et aide

- Utilisez un mot de passe personnel et ne partagez jamais vos identifiants.
- Vérifiez le rôle d’un membre avant de lui partager un cookbook.
- Exportez régulièrement vos données avant une opération importante.
- En environnement de démonstration, les emails de validation et de
  récupération sont visibles dans Mailpit à `http://localhost:8025/`.

Pour la procédure d’installation, de migration et le scénario de démonstration,
consultez le [README du projet](../README.md).
