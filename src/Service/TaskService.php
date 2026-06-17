<?php

namespace App\Service;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository         $taskRepository,
        private readonly ValidatorInterface     $validator,
    ) {}

    /**
     * Retourne toutes les tâches, ordonnées par date de création.
     *
     * @return Task[]
     */
    public function getAll(): array
    {
        return $this->taskRepository->findAllOrderedByDate();
    }

    /**
     * Retourne une tâche par son identifiant, ou null si introuvable.
     *
     * @param int $id
     * @return Task|null
     */
    public function getById(int $id): ?Task
    {
        return $this->taskRepository->find($id);
    }

    /**
     * Crée et persiste une nouvelle tâche à partir des données brutes.
     *
     * @param array<string, mixed> $data Données issues du corps de la requête.
     * @return Task|array<string, string> La tâche créée, ou un tableau d'erreurs de validation.
     */
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
            return $errors;
        }

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    /**
     * Met à jour les champs fournis d'une tâche existante.
     * Seuls les champs présents dans $data sont modifiés (PATCH sémantique).
     *
     * @param Task                 $task La tâche à mettre à jour.
     * @param array<string, mixed> $data Champs à modifier.
     * @return Task|array<string, string> La tâche mise à jour, ou un tableau d'erreurs de validation.
     */
    public function update(Task $task, array $data): Task|array
    {
        if (array_key_exists('titre', $data)) {
            $task->setTitre($data['titre']);
        }

        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description']);
        }

        if (array_key_exists('statut', $data)) {
            $task->setStatut($data['statut']);
        }

        $errors = $this->validate($task);
        if (!empty($errors)) {
            return $errors;
        }

        $this->em->flush();

        return $task;
    }

    /**
     * Supprime une tâche de la base de données.
     *
     * @param Task $task La tâche à supprimer.
     */
    public function delete(Task $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }

    /**
     * Valide une entité Task et retourne les violations sous forme de tableau associatif.
     *
     * @param Task $task
     * @return array<string, string> Tableau vide si valide, sinon [champ => message].
     */
    private function validate(Task $task): array
    {
        $violations = $this->validator->validate($task);

        if (count($violations) === 0) {
            return [];
        }

        $errors = [];
        foreach ($violations as $violation) {
            // Extrait le nom du champ depuis le chemin de propriété (ex: "titre")
            $field = $violation->getPropertyPath();
            $errors[$field] = $violation->getMessage();
        }

        return $errors;
    }
}
