<?php

namespace App\Controller;

use App\Entity\Task;
use App\Service\TaskService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/tasks', name: 'api_tasks_')]
#[OA\Tag(name: 'Tâches', description: 'Gestion de la liste de tâches')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly TaskService         $taskService,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Lister toutes les tâches',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tâches',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Task')
                )
            ),
        ]
    )]
    public function list(): JsonResponse
    {
        $tasks = $this->taskService->getAll();

        return $this->json($this->normalizeTasks($tasks));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tasks',
        summary: 'Créer une tâche',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/TaskInput',
                example: [
                    'titre'       => 'Corriger le bug de login',
                    'description' => 'Vérifier les middlewares JWT',
                    'statut'      => 'todo',
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tâche créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(
                response: 400,
                description: 'Corps JSON invalide ou absent',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'error', type: 'string')],
                    example: ['error' => 'Corps de la requête JSON invalide.']
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreurs de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        if ($data === null) {
            return $this->json(['error' => 'Corps de la requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->taskService->create($data);

        if (is_array($result)) {
            return $this->json(['errors' => $result], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->normalizeTask($result), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/tasks/{id}',
        summary: 'Modifier une tâche (mise à jour partielle)',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la tâche',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/TaskPatch',
                example: ['statut' => 'in_progress']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tâche mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(
                response: 400,
                description: 'Corps JSON invalide ou absent',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'error', type: 'string')],
                    example: ['error' => 'Corps de la requête JSON invalide.']
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Tâche introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound')
            ),
            new OA\Response(
                response: 422,
                description: 'Erreurs de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $task = $this->taskService->getById($id);

        if ($task === null) {
            return $this->json(['error' => "Tâche #{$id} introuvable."], Response::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJson($request);

        if ($data === null) {
            return $this->json(['error' => 'Corps de la requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->taskService->update($task, $data);

        if (is_array($result)) {
            return $this->json(['errors' => $result], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->normalizeTask($result));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/tasks/{id}',
        summary: 'Supprimer une tâche',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de la tâche',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Tâche supprimée (corps vide)'
            ),
            new OA\Response(
                response: 404,
                description: 'Tâche introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound')
            ),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $task = $this->taskService->getById($id);

        if ($task === null) {
            return $this->json(['error' => "Tâche #{$id} introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->taskService->delete($task);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // retourne null si le body est vide ou invalide
    private function decodeJson(Request $request): ?array
    {
        $content = $request->getContent();

        if (empty($content)) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

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

    private function normalizeTasks(array $tasks): array
    {
        return array_map($this->normalizeTask(...), $tasks);
    }
}
