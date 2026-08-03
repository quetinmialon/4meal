# SUPMEAL - Product Backlog

## Authentification

### MUST
- [X] AUTH-01 — Création d'un compte avec email et mot de passe
- [X] AUTH-02 — Connexion avec email et mot de passe
- [x] AUTH-03 — Connexion via OAuth2 (Google & Microsoft)
- [X] AUTH-04 — Déconnexion sécurisée
- [X] AUTH-05 — Gestion des sessions avec JWT
- [X] AUTH-06 — Modification du mot de passe

### SHOULD
- [x] AUTH-07 — Association de plusieurs comptes OAuth
- [x] AUTH-08 — Réinitialisation du mot de passe par email

### COULD
- [ ] AUTH-09 — Vérification de l'adresse email
- [ ] AUTH-10 — Authentification à deux facteurs (validation par email)

---

## CookBooks

### MUST
- [x] COOK-01 — Créer un CookBook
- [x] COOK-02 — Modifier un CookBook
- [x] COOK-03 — Supprimer un CookBook
- [x] COOK-04 — Inviter un utilisateur
- [x] COOK-05 — Quitter un CookBook
- [x] COOK-06 — Gérer les permissions des membres
- [x] COOK-07 — Afficher la liste des membres
- [x] COOK-08 — Retirer un membre

### SHOULD
- [x] COOK-09 — Transférer la propriété

### COULD
- [ ] COOK-10 — Personnaliser l'image du CookBook
- [ ] COOK-11 — Ajouter une description

---

## Recettes

### MUST
- [x] REC-01 — Créer une recette
- [x] REC-02 — Modifier une recette
- [x] REC-03 — Supprimer une recette
- [x] REC-04 — Ajouter un ou plusieurs ingrédients
- [x] REC-05 — Ajouter des étapes
- [x] REC-06 — Définir le temps de préparation
- [x] REC-07 — Définir le temps de cuisson
- [x] REC-08 — Définir le nombre de portions
- [x] REC-09 — Ajouter des catégories / tags
- [x] REC-10 — Ajouter une image
- [x] REC-11 — Ajouter une source
- [x] REC-12 — Ajouter aux favoris
- [x] REC-13 — Ajouter au planning

### SHOULD
- [x] REC-14 — Dupliquer une recette
- [x] REC-15 — Historique des modifications

### COULD
- [ ] REC-16 — Notation personnelle des recettes
- [ ] REC-17 — Estimation automatique des calories

---

## Recherche

### MUST
- [x] SEARCH-01 — Recherche plein texte
- [x] SEARCH-02 — Filtrer par CookBook
- [x] SEARCH-03 — Filtrer par catégorie
- [x] SEARCH-04 — Filtrer par ingrédients
- [x] SEARCH-05 — Filtrer par temps de préparation
- [x] SEARCH-06 — Filtrer par temps de cuisson
- [x] SEARCH-07 — Filtrer par favoris

### SHOULD
- [x] SEARCH-08 — Combiner plusieurs filtres

### COULD
- [ ] SEARCH-09 — Sauvegarder les recherches
- [ ] SEARCH-10 — Suggestions automatiques

---

## Planification des repas

### MUST
- [x] PLAN-01 — Ajouter une recette au planning
- [x] PLAN-02 — Modifier un repas planifié
- [x] PLAN-03 — Supprimer un repas planifié
- [x] PLAN-04 — Afficher le calendrier

### SHOULD
- [ ] PLAN-05 — Générer automatiquement la liste de courses
- [x] PLAN-06 — Gestion des portions dans le planning

### COULD
- [ ] PLAN-07 — Répéter automatiquement un planning
- [ ] PLAN-08 — Suggestion automatique de menu

---

## Messagerie

### MUST
- [x] CHAT-01 — Envoyer un message dans un CookBook
- [x] CHAT-02 — Consulter l'historique des messages
- [x] CHAT-03 — Commenter une recette
- [x] CHAT-04 — Modifier son commentaire
- [x] CHAT-05 — Supprimer son commentaire

### SHOULD
- [ ] CHAT-06 — Notification de nouveaux messages
- [ ] CHAT-07 — Répondre à un commentaire

### COULD
- [ ] CHAT-08 — Réactions emoji

---

## Imports / Exports

### MUST
- [x] IO-01 — Exporter ses recettes
- [x] IO-02 — Exporter ses CookBooks
- [x] IO-03 — Export JSON
- [x] IO-04 — Import JSON
- [x] IO-05 — Validation des fichiers importés
- [x] IO-06 — Gestion des erreurs d'import

### SHOULD
- [ ] IO-07 — Compatibilité Mealie
- [ ] IO-08 — Compatibilité CSV

### COULD
- [ ] IO-09 — Prévisualisation avant import

---

## Profil utilisateur

### MUST
- [x] USER-01 — Modifier son profil
- [x] USER-02 — Gérer les comptes OAuth associés
- [x] USER-03 — Définir son régime alimentaire
- [x] USER-04 — Définir ses allergies
- [x] USER-05 — Définir le nombre de portions par défaut

### SHOULD
- [ ] USER-06 — Gérer ses notifications

### COULD
- [ ] USER-07 — Choisir un thème (clair / sombre)
