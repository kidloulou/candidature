# Task Manager — API REST + Interface Web (Symfony 7)

Projet réalisé dans le cadre d'un exercice technique de candidature.  
Il s'agit d'une application complète de gestion de tâches construite avec **Symfony 7**, **Doctrine ORM** et une base **SQLite** pour la portabilité. Elle expose une **API REST JSON** consommée par une **interface web Twig + JS vanilla**, et génère une **documentation Swagger interactive** via NelmioApiDocBundle.

---

## Sommaire

- [Stack technique](#stack-technique)
- [Architecture du projet](#architecture-du-projet)
- [Installation](#installation)
- [Lancer l'application](#lancer-lapplication)
- [L'entité Task](#lentité-task)
- [La couche Repository](#la-couche-repository)
- [La couche Service](#la-couche-service)
- [API REST — les 4 endpoints](#api-rest--les-4-endpoints)
- [Interface web Twig](#interface-web-twig)
- [Documentation Swagger](#documentation-swagger)
- [Codes de réponse HTTP](#codes-de-réponse-http)
- [Exemples curl complets](#exemples-curl-complets)
- [Base de données et migration](#base-de-données-et-migration)

---

## Stack technique

| Élément | Choix |
|---|---|
| Framework | Symfony 7.4 |
| ORM | Doctrine ORM 3 |
| Base de données | SQLite (fichier `var/app.db`) |
| Templating | Twig 3 |
| JS frontend | Vanilla JS ES6+ (fetch API) |
| CSS | Bootstrap 5.3 CDN |
| Documentation API | NelmioApiDocBundle v5 + OpenAPI 3.0 |
| PHP | 8.2+ |

Aucun framework JS (React, Vue…), aucune API Platform. Les controllers sont classiques, la logique métier est isolée dans un service dédié.

---

## Architecture du projet

```
src/
├── Controller/
│   ├── TaskApiController.php   # Les 4 endpoints REST (/api/tasks)
│   └── TaskWebController.php   # La page web (/tasks)
├── Entity/
│   └── Task.php                # Entité Doctrine + contraintes de validation
├── Repository/
│   └── TaskRepository.php      # Requêtes DQL personnalisées
└── Service/
    └── TaskService.php         # Logique métier (créer, modifier, supprimer, valider)

templates/
├── base.html.twig              # Layout Bootstrap 5
└── tasks/
    └── index.html.twig         # Page SPA (liste, formulaire, modals)

migrations/
└── Version20260617083124.php   # Création de la table task

config/
├── packages/
│   ├── doctrine.yaml           # Config SQLite
│   └── nelmio_api_doc.yaml     # Config Swagger / OpenAPI
└── routes/
    └── nelmio_api_doc.yaml     # Routes /api/doc et /api/doc.json
```

**Principe de séparation des responsabilités :**

- `TaskApiController` — reçoit la requête HTTP, décode le JSON, délègue au service, formate la réponse. Il ne contient aucune logique métier.
- `TaskService` — crée, modifie, supprime, valide. C'est ici que vivent les règles.
- `TaskRepository` — encapsule les requêtes Doctrine.
- `TaskWebController` — rend simplement le template Twig. Aucune donnée côté serveur : tout passe par l'API via `fetch()`.

---

## Installation

### Prérequis

- PHP 8.2+
- Composer
- Extension PHP `pdo_sqlite` (active par défaut sur la plupart des installations)

### Étapes

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> task-manager
cd task-manager

# 2. Copier et ajuster l'environnement
cp .env.example .env

# 3. Installer les dépendances PHP
composer install

# 4. Créer la base SQLite et appliquer la migration
php bin/console doctrine:migrations:migrate --no-interaction
```

Le fichier `var/app.db` est créé automatiquement à l'étape 4.

---

## Lancer l'application

```bash
# Avec le CLI Symfony (recommandé)
symfony server:start

# Sans le CLI Symfony
php -S localhost:8000 -t public/
```

| URL | Description |
|---|---|
| `http://localhost:8000/tasks` | Interface web |
| `http://localhost:8000/api/tasks` | API REST (JSON) |
| `http://localhost:8000/api/doc` | Documentation Swagger UI |
| `http://localhost:8000/api/doc.json` | Spec OpenAPI 3.0 brute |

---

## L'entité Task

`src/Entity/Task.php`

L'entité définit la structure d'une tâche. Les contraintes de validation Symfony (`#[Assert\*]`) sont portées directement sur les propriétés, ce qui centralise les règles métier au plus proche des données.

```php
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'task')]
class Task
{
    public const STATUS_TODO        = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    public const VALID_STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le titre ne peut pas être vide.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private string $titre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: self::VALID_STATUSES, message: 'Le statut doit être "todo", "in_progress" ou "done".')]
    private string $statut = self::STATUS_TODO;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    // Les dates sont gérées automatiquement par les lifecycle callbacks
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

**Points notables :**
- `createdAt` et `updatedAt` sont des `DateTimeImmutable` — jamais modifiés directement, uniquement via les callbacks `#[ORM\PrePersist]` et `#[ORM\PreUpdate]`.
- Les valeurs acceptées pour `statut` sont centralisées dans des constantes de classe (`VALID_STATUSES`) réutilisées à la fois par `#[Assert\Choice]` et dans la documentation OpenAPI.
- Le `statut` vaut `'todo'` par défaut — déclaré à la fois en PHP et dans le SQL de la migration.

---

## La couche Repository

`src/Repository/TaskRepository.php`

```php
/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Retourne toutes les tâches, ordonnées par date de création décroissante.
     *
     * @return Task[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

Le repository étend `ServiceEntityRepository`, ce qui lui donne gratuitement `find()`, `findAll()`, `findBy()`, etc. La méthode custom `findAllOrderedByDate()` est la seule requête spécifique nécessaire.

---

## La couche Service

`src/Service/TaskService.php`

Le service est le seul endroit qui touche à la logique métier. Le contrôleur ne fait qu'appeler ses méthodes et formater la réponse.

```php
class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository         $taskRepository,
        private readonly ValidatorInterface     $validator,
    ) {}

    public function create(array $data): Task|array
    {
        $task = new Task();
        $task->setTitre($data['titre'] ?? '');
        $task->setDescription($data['description'] ?? null);

        if (isset($data['statut'])) {
            $task->setStatut($data['statut']);
        }

        $errors = $this->validate($task);
        if (!empty($errors)) {
            return $errors; // tableau [champ => message]
        }

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    public function update(Task $task, array $data): Task|array
    {
        // PATCH sémantique : on ne touche qu'aux clés présentes dans $data
        if (array_key_exists('titre', $data))       $task->setTitre($data['titre']);
        if (array_key_exists('description', $data)) $task->setDescription($data['description']);
        if (array_key_exists('statut', $data))      $task->setStatut($data['statut']);

        $errors = $this->validate($task);
        if (!empty($errors)) {
            return $errors;
        }

        $this->em->flush(); // pas besoin de persist() : l'entité est déjà managée
        return $task;
    }

    public function delete(Task $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }

    private function validate(Task $task): array
    {
        $violations = $this->validator->validate($task);

        if (count($violations) === 0) {
            return [];
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return $errors;
    }
}
```

**Point important sur `update()`** : on utilise `array_key_exists()` et non `isset()`. La différence est subtile mais cruciale : si quelqu'un envoie `{"description": null}` pour effacer la description, `isset()` retournerait `false` et ignorerait le champ, alors qu'`array_key_exists()` le détecte correctement.

---

## API REST — les 4 endpoints

`src/Controller/TaskApiController.php`

Le contrôleur est volontairement fin : il décode le JSON entrant, appelle le service, et normalise la réponse. La méthode privée `normalizeTask()` construit un tableau associatif propre sans passer par le Serializer (ce qui évite d'avoir à configurer des groupes de sérialisation pour un cas aussi simple).

```php
#[Route('/api/tasks', name: 'api_tasks_')]
#[OA\Tag(name: 'Tâches')]
class TaskApiController extends AbstractController
{
    private function normalizeTask(Task $task): array
    {
        return [
            'id'          => $task->getId(),
            'titre'       => $task->getTitre(),
            'description' => $task->getDescription(),
            'statut'      => $task->getStatut(),
            'createdAt'   => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'   => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function decodeJson(Request $request): ?array
    {
        $content = $request->getContent();
        if (empty($content)) return null;

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
```

### GET /api/tasks

Retourne toutes les tâches, triées par `createdAt` décroissant.

```php
#[Route('', name: 'list', methods: ['GET'])]
public function list(): JsonResponse
{
    $tasks = $this->taskService->getAll();
    return $this->json($this->normalizeTasks($tasks));
}
```

**Réponse 200 :**
```json
[
  {
    "id": 2,
    "titre": "Écrire les tests unitaires",
    "description": "Couvrir le TaskService",
    "statut": "in_progress",
    "createdAt": "2026-06-17T10:05:00+00:00",
    "updatedAt": "2026-06-17T10:05:00+00:00"
  },
  {
    "id": 1,
    "titre": "Préparer la démo",
    "description": null,
    "statut": "todo",
    "createdAt": "2026-06-17T10:00:00+00:00",
    "updatedAt": "2026-06-17T10:00:00+00:00"
  }
]
```

---

### POST /api/tasks

Crée une nouvelle tâche. `titre` est obligatoire, `statut` vaut `"todo"` par défaut.

```php
#[Route('', name: 'create', methods: ['POST'])]
public function create(Request $request): JsonResponse
{
    $data = $this->decodeJson($request);

    if ($data === null) {
        return $this->json(['error' => 'Corps de la requête JSON invalide.'], 400);
    }

    $result = $this->taskService->create($data);

    if (is_array($result)) {
        return $this->json(['errors' => $result], 422);
    }

    return $this->json($this->normalizeTask($result), 201);
}
```

**Body attendu :**
```json
{
  "titre": "Corriger le bug de login",
  "description": "Vérifier les middlewares JWT",
  "statut": "todo"
}
```

**Réponse 201 :**
```json
{
  "id": 3,
  "titre": "Corriger le bug de login",
  "description": "Vérifier les middlewares JWT",
  "statut": "todo",
  "createdAt": "2026-06-17T11:00:00+00:00",
  "updatedAt": "2026-06-17T11:00:00+00:00"
}
```

**Réponse 422 (titre vide + statut invalide) :**
```json
{
  "errors": {
    "titre": "Le titre ne peut pas être vide.",
    "statut": "Le statut doit être \"todo\", \"in_progress\" ou \"done\"."
  }
}
```

---

### PATCH /api/tasks/{id}

Mise à jour **partielle** : seuls les champs présents dans le body sont modifiés.

```php
#[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
public function update(int $id, Request $request): JsonResponse
{
    $task = $this->taskService->getById($id);

    if ($task === null) {
        return $this->json(['error' => "Tâche #{$id} introuvable."], 404);
    }

    $data = $this->decodeJson($request);
    if ($data === null) {
        return $this->json(['error' => 'Corps de la requête JSON invalide.'], 400);
    }

    $result = $this->taskService->update($task, $data);

    if (is_array($result)) {
        return $this->json(['errors' => $result], 422);
    }

    return $this->json($this->normalizeTask($result));
}
```

**Body (changer uniquement le statut) :**
```json
{ "statut": "done" }
```

**Réponse 200 :** la tâche complète mise à jour, avec `updatedAt` rafraîchi.

**Réponse 404 :**
```json
{ "error": "Tâche #99 introuvable." }
```

---

### DELETE /api/tasks/{id}

Supprime la tâche. Retourne **204 No Content** (corps vide) en cas de succès.

```php
#[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
public function delete(int $id): JsonResponse
{
    $task = $this->taskService->getById($id);

    if ($task === null) {
        return $this->json(['error' => "Tâche #{$id} introuvable."], 404);
    }

    $this->taskService->delete($task);

    return $this->json(null, 204);
}
```

---

## Interface web Twig

`src/Controller/TaskWebController.php` + `templates/tasks/index.html.twig`

Le contrôleur web ne fait que rendre le template — aucune donnée n'est chargée côté serveur :

```php
#[Route('/tasks', name: 'tasks_index', methods: ['GET'])]
public function index(): Response
{
    return $this->render('tasks/index.html.twig');
}
```

Toutes les données arrivent via `fetch()` en JavaScript. La page se comporte comme une mini-SPA :

```js
const TaskApi = {
    BASE: '/api/tasks',

    async fetchAll() {
        const r = await fetch(this.BASE);
        if (!r.ok) throw new Error('Impossible de charger les tâches.');
        return r.json();
    },

    async create(payload) {
        const r = await fetch(this.BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        // On vérifie le content-type avant de parser pour éviter
        // une SyntaxError si le serveur retourne du HTML (erreur 500)
        const data = r.headers.get('content-type')?.includes('application/json')
            ? await r.json()
            : null;
        return { ok: r.ok, status: r.status, data };
    },

    async update(id, payload) {
        const r = await fetch(`${this.BASE}/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = r.headers.get('content-type')?.includes('application/json')
            ? await r.json()
            : null;
        return { ok: r.ok, data };
    },

    async delete(id) {
        const r = await fetch(`${this.BASE}/${id}`, { method: 'DELETE' });
        return r.ok;
    },
};
```

**Fonctionnalités de l'interface :**

- Liste de tâches avec badge coloré selon le statut
  - `todo` → gris
  - `in_progress` → orange
  - `done` → vert
- Filtres par statut (Toutes / À faire / En cours / Terminées), appliqués localement sans appel API supplémentaire
- Formulaire de création dans un modal Bootstrap (titre requis, description optionnelle, statut avec `todo` par défaut)
- Changement de statut via un `<select>` inline dans chaque carte — PATCH immédiat, badge mis à jour sans rechargement de la liste
- Suppression avec modal de confirmation avant l'appel DELETE
- Toasts de notification (succès / erreur) en bas à droite, qui disparaissent automatiquement
- Gestion des erreurs réseau : si l'API ne répond pas, le spinner du formulaire se libère toujours grâce au bloc `finally`

```js
document.getElementById('form-create').addEventListener('submit', async (e) => {
    e.preventDefault();
    setCreateLoading(true);

    try {
        const { ok, status, data } = await TaskApi.create({ titre, description, statut });

        if (ok) {
            allTasks.unshift(data);
            renderList();
            modalCreate().hide();
            showToast('Tâche créée avec succès.', 'success');
            return;
        }

        if (status === 422 && data?.errors) {
            showFormErrors(data.errors);
        } else {
            showToast('Une erreur est survenue lors de la création.', 'danger');
        }
    } catch (e) {
        showToast('Erreur réseau — impossible de contacter l\'API.', 'danger');
    } finally {
        setCreateLoading(false); // s'exécute toujours, même en cas d'exception
    }
});
```

---

## Documentation Swagger

`config/packages/nelmio_api_doc.yaml` + annotations `#[OA\*]` dans `TaskApiController`

Le bundle NelmioApiDocBundle v5 génère une documentation OpenAPI 3.0 à partir des attributs PHP du contrôleur.

**Configuration (`config/packages/nelmio_api_doc.yaml`) :**
```yaml
nelmio_api_doc:
    documentation:
        info:
            title: Task API
            description: API REST de gestion de tâches — Symfony 7
            version: '1.0.0'
        components:
            schemas:
                Task:       { ... }   # schéma de réponse
                TaskInput:  { ... }   # body du POST
                TaskPatch:  { ... }   # body du PATCH (tous champs optionnels)
                ValidationError: { ... }
                NotFound:   { ... }
    areas:
        path_patterns:
            - ^/api(?!/doc$)   # scanne /api/* mais exclut /api/doc lui-même
```

**Annotations sur le contrôleur (exemple pour POST) :**
```php
#[OA\Post(
    path: '/api/tasks',
    summary: 'Créer une tâche',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/TaskInput')
    ),
    responses: [
        new OA\Response(response: 201, description: 'Tâche créée',
            content: new OA\JsonContent(ref: '#/components/schemas/Task')),
        new OA\Response(response: 400, description: 'Corps JSON invalide'),
        new OA\Response(response: 422, description: 'Erreurs de validation',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
    ]
)]
```

**Routes Swagger (`config/routes/nelmio_api_doc.yaml`) :**
```yaml
app.swagger_ui:
    path: /api/doc
    methods: GET
    defaults:
        _controller: nelmio_api_doc.controller.swagger_ui

app.swagger_json:
    path: /api/doc.json
    methods: GET
    defaults:
        _controller: nelmio_api_doc.controller.swagger
```

> **Note** : NelmioApiDocBundle v5 requiert `symfony/asset` pour activer le contrôleur `swagger_ui`. Sans ce package, le service est silencieusement retiré du container et la page renvoie une 500. C'est installé dans ce projet.

---

## Codes de réponse HTTP

| Code | Situation |
|---|---|
| `200 OK` | GET liste ou PATCH réussi |
| `201 Created` | POST réussi — la tâche créée est dans le body |
| `204 No Content` | DELETE réussi — body vide |
| `400 Bad Request` | Body JSON absent ou syntaxiquement invalide |
| `404 Not Found` | Tâche inexistante (PATCH ou DELETE) |
| `422 Unprocessable Entity` | Validation échouée — détail des erreurs par champ |

---

## Exemples curl complets

### Créer une tâche

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"titre": "Préparer la démo", "description": "Slides + démo live", "statut": "todo"}'
```

### Créer une tâche sans description (statut par défaut : todo)

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"titre": "Écrire les tests"}'
```

### Lister toutes les tâches

```bash
curl http://localhost:8000/api/tasks
```

### Passer une tâche en "in_progress"

```bash
curl -X PATCH http://localhost:8000/api/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"statut": "in_progress"}'
```

### Modifier le titre et la description en une seule requête

```bash
curl -X PATCH http://localhost:8000/api/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"titre": "Nouveau titre", "description": "Nouvelle description"}'
```

### Supprimer une tâche

```bash
curl -X DELETE http://localhost:8000/api/tasks/1
# → 204 No Content
```

### Tester la validation (422)

```bash
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"titre": "", "statut": "invalide"}'

# Réponse :
# {
#   "errors": {
#     "titre": "Le titre ne peut pas être vide.",
#     "statut": "Le statut doit être \"todo\", \"in_progress\" ou \"done\"."
#   }
# }
```

### Tester le 404

```bash
curl -X PATCH http://localhost:8000/api/tasks/999 \
  -H "Content-Type: application/json" \
  -d '{"statut": "done"}'

# Réponse :
# { "error": "Tâche #999 introuvable." }
```

---

## Base de données et migration

La base de données est un fichier SQLite stocké dans `var/app.db`. Ce choix garantit une portabilité maximale : aucun serveur de base de données à configurer.

**Migration initiale (`migrations/Version20260617083124.php`) :**

```php
public function up(Schema $schema): void
{
    $this->addSql(
        'CREATE TABLE task (
            id          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            titre       VARCHAR(255) NOT NULL,
            description CLOB         DEFAULT NULL,
            statut      VARCHAR(20)  NOT NULL DEFAULT \'todo\',
            created_at  DATETIME     NOT NULL,
            updated_at  DATETIME     NOT NULL
        )'
    );
}
```

**Commandes utiles :**

```bash
# Appliquer les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Voir l'état des migrations
php bin/console doctrine:migrations:status

# Créer une nouvelle migration après modification de l'entité
php bin/console doctrine:migrations:diff

# Vider le cache (utile si les routes ou la config changent)
php bin/console cache:clear

# Lister toutes les routes enregistrées
php bin/console debug:router
```

**Configuration Doctrine (`config/packages/doctrine.yaml`) :**

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        naming_strategy: doctrine.orm.naming_strategy.underscore
        auto_mapping: true
        mappings:
            App:
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
```

**Variable d'environnement (`.env`) :**

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/app.db"
```

Pour utiliser MySQL ou PostgreSQL à la place, il suffit de changer cette ligne :

```dotenv
# MySQL
DATABASE_URL="mysql://user:password@127.0.0.1:3306/task_api?serverVersion=8.0.32&charset=utf8mb4"

# PostgreSQL
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/task_api?serverVersion=16&charset=utf8"
```
