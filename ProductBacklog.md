# SUPMEAL - Product Backlog

## Authentification

### MUST
- [ ] AUTH-01 — Création d'un compte avec email et mot de passe
- [ ] AUTH-02 — Connexion avec email et mot de passe
- [ ] AUTH-03 — Connexion via OAuth2 (Google & Microsoft)
- [ ] AUTH-04 — Déconnexion sécurisée
- [ ] AUTH-05 — Gestion des sessions avec JWT
- [ ] AUTH-06 — Modification du mot de passe

### SHOULD
- [ ] AUTH-07 — Association de plusieurs comptes OAuth
- [ ] AUTH-08 — Réinitialisation du mot de passe par email

### COULD
- [ ] AUTH-09 — Vérification de l'adresse email
- [ ] AUTH-10 — Authentification à deux facteurs (validation par email)

---

## CookBooks

### MUST
- [ ] COOK-01 — Créer un CookBook
- [ ] COOK-02 — Modifier un CookBook
- [ ] COOK-03 — Supprimer un CookBook
- [ ] COOK-04 — Inviter un utilisateur
- [ ] COOK-05 — Quitter un CookBook
- [ ] COOK-06 — Gérer les permissions des membres
- [ ] COOK-07 — Afficher la liste des membres
- [ ] COOK-08 — Retirer un membre

### SHOULD
- [ ] COOK-09 — Transférer la propriété

### COULD
- [ ] COOK-10 — Personnaliser l'image du CookBook
- [ ] COOK-11 — Ajouter une description

---

## Recettes

### MUST
- [ ] REC-01 — Créer une recette
- [ ] REC-02 — Modifier une recette
- [ ] REC-03 — Supprimer une recette
- [ ] REC-04 — Ajouter un ou plusieurs ingrédients
- [ ] REC-05 — Ajouter des étapes
- [ ] REC-06 — Définir le temps de préparation
- [ ] REC-07 — Définir le temps de cuisson
- [ ] REC-08 — Définir le nombre de portions
- [ ] REC-09 — Ajouter des catégories / tags
- [ ] REC-10 — Ajouter une image
- [ ] REC-11 — Ajouter une source
- [ ] REC-12 — Ajouter aux favoris
- [ ] REC-13 — Ajouter au planning

### SHOULD
- [ ] REC-14 — Dupliquer une recette
- [ ] REC-15 — Historique des modifications

### COULD
- [ ] REC-16 — Notation personnelle des recettes
- [ ] REC-17 — Estimation automatique des calories

---

## Recherche

### MUST
- [ ] SEARCH-01 — Recherche plein texte
- [ ] SEARCH-02 — Filtrer par CookBook
- [ ] SEARCH-03 — Filtrer par catégorie
- [ ] SEARCH-04 — Filtrer par ingrédients
- [ ] SEARCH-05 — Filtrer par temps de préparation
- [ ] SEARCH-06 — Filtrer par temps de cuisson
- [ ] SEARCH-07 — Filtrer par favoris

### SHOULD
- [ ] SEARCH-08 — Combiner plusieurs filtres

### COULD
- [ ] SEARCH-09 — Sauvegarder les recherches
- [ ] SEARCH-10 — Suggestions automatiques

---

## Planification des repas

### MUST
- [ ] PLAN-01 — Ajouter une recette au planning
- [ ] PLAN-02 — Modifier un repas planifié
- [ ] PLAN-03 — Supprimer un repas planifié
- [ ] PLAN-04 — Afficher le calendrier

### SHOULD
- [ ] PLAN-05 — Générer automatiquement la liste de courses
- [ ] PLAN-06 — Gestion des portions dans le planning

### COULD
- [ ] PLAN-07 — Répéter automatiquement un planning
- [ ] PLAN-08 — Suggestion automatique de menu

---

## Messagerie

### MUST
- [ ] CHAT-01 — Envoyer un message dans un CookBook
- [ ] CHAT-02 — Consulter l'historique des messages
- [ ] CHAT-03 — Commenter une recette
- [ ] CHAT-04 — Modifier son commentaire
- [ ] CHAT-05 — Supprimer son commentaire

### SHOULD
- [ ] CHAT-06 — Notification de nouveaux messages
- [ ] CHAT-07 — Répondre à un commentaire

### COULD
- [ ] CHAT-08 — Réactions emoji

---

## Imports / Exports

### MUST
- [ ] IO-01 — Exporter ses recettes
- [ ] IO-02 — Exporter ses CookBooks
- [ ] IO-03 — Export JSON
- [ ] IO-04 — Import JSON
- [ ] IO-05 — Validation des fichiers importés
- [ ] IO-06 — Gestion des erreurs d'import

### SHOULD
- [ ] IO-07 — Compatibilité Mealie
- [ ] IO-08 — Compatibilité CSV

### COULD
- [ ] IO-09 — Prévisualisation avant import

---

## Profil utilisateur

### MUST
- [ ] USER-01 — Modifier son profil
- [ ] USER-02 — Gérer les comptes OAuth associés
- [ ] USER-03 — Définir son régime alimentaire
- [ ] USER-04 — Définir ses allergies
- [ ] USER-05 — Définir le nombre de portions par défaut

### SHOULD
- [ ] USER-06 — Gérer ses notifications

### COULD
- [ ] USER-07 — Choisir un thème (clair / sombre)
