# Contexte

```mermaid
flowchart LR
    user[Utilisateur] --> browser[Navigateur]
    browser -->|HTTP / JSON, multipart| app[SUPMEAL]
    browser -->|OAuth redirect| google[Google OAuth]
    browser -->|OAuth redirect| microsoft[Microsoft OAuth]
    google -->|callback| app
    microsoft -->|callback| app
    app -->|courriels| mail[SMTP / Mailpit]
    app -->|réponses, cookies JWT, fichiers publics| browser
```
