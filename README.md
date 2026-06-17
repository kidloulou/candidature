# Task Manager — API REST Symfony 7

Application de gestion de tâches construite avec Symfony 7, Doctrine ORM et SQLite.
Elle expose une API REST JSON consommée par une interface web Twig + JS vanilla, avec une doc Swagger générée automatiquement.

**Démo en ligne :** https://candidature-production.up.railway.app/tasks  
**Swagger UI :** https://candidature-production.up.railway.app/api/doc

---

## Stack

- PHP 8.2 / Symfony 7.4
- Doctrine ORM + SQLite
- Twig + Vanilla JS (fetch API) + Bootstrap 5
- NelmioApiDocBundle (OpenAPI 3.0)

---

## Installation

```bash
git clone https://github.com/kidloulou/candidature.git && cd candidature
cp .env.example .env
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S localhost:8000 -t public/
```

---

## Les 4 endpoints

### GET /api/tasks
```bash
curl http://localhost:8000/api/tasks
```

### POST /api/tasks
```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"titre": "Préparer la démo", "description": "Slides + démo live", "statut": "todo"}'
```
`titre` est obligatoire, `statut` vaut `todo` par défaut. Retourne 422 avec le détail des erreurs si la validation échoue.

### PATCH /api/tasks/{id}
```bash
curl -X PATCH http://localhost:8000/api/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"statut": "in_progress"}'
```
Mise à jour partielle : seuls les champs envoyés sont modifiés.

### DELETE /api/tasks/{id}
```bash
curl -X DELETE http://localhost:8000/api/tasks/1
# → 204 No Content
```

---

## Permissions

L'API n'a pas d'authentification — c'est volontaire pour un exercice technique de cette portée. Toutes les routes sont publiques. Si le projet devait évoluer vers une vraie prod, l'étape suivante serait d'ajouter Lexik JWT ou Symfony Security avec des rôles par endpoint.
