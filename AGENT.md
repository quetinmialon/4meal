Tu interviens sur SUPMEAL, une application de gestion de recettes, de cookbooks partagés et de planification de repas.

Architecture cible :
- backend/ : API REST Laravel
- frontend/ : SPA Vue 3 avec TypeScript
- PostgreSQL
- Docker Compose
- aucune logique métier dans le frontend
- le frontend communique exclusivement avec l’API
- authentification JWT
- tests backend avec Pest
- tests frontend avec Vitest lorsque pertinent

Principes obligatoires :
- respecter SOLID, DRY, KISS et les conventions Laravel/Vue ;
- utiliser des Form Requests pour la validation Laravel ;
- utiliser des API Resources pour les réponses JSON ;
- utiliser des Policies pour les autorisations ;
- utiliser des services ou actions lorsque la logique dépasse le rôle d’un contrôleur ;
- garder les contrôleurs minces ;
- utiliser des transactions pour les opérations atomiques ;
- éviter les requêtes N+1 ;
- ajouter les index de base de données pertinents ;
- ne jamais exposer de secret ;
- ne jamais placer de logique métier dans Vue ;
- utiliser TypeScript strictement côté frontend ;
- ne modifier aucun fichier sans rapport avec la tâche ;
- ne pas ajouter de fonctionnalité non demandée ;
- préserver les fonctionnalités existantes.
- ne décrit pas les implémentations dans le front, l'utilisateur n'est pas un developpeur

Avant chaque implémentation :
1. inspecte le dépôt, et en particulier ProductBacklog.md;
2. indique les fichiers que tu prévois de modifier ;
3. propose un plan court ;
4. signale les hypothèses ou ambiguïtés ;
5. attends mon accord avant de coder.

Après implémentation :
1. exécute les linters et tests concernés ;
2. résume les fichiers modifiés ;
3. indique les commandes exécutées ;
4. indique les limites connues ;
5. propose un message de commit Conventional Commits ;
6. ne crée pas le commit sans demande explicite.
