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

    public function getAll(): array
    {
        return $this->taskRepository->findAllOrderedByDate();
    }

    public function getById(int $id): ?Task
    {
        return $this->taskRepository->find($id);
    }

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

    // on ne touche que les champs envoyés dans le body
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
            // ex: "titre", "statut"
            $field = $violation->getPropertyPath();
            $errors[$field] = $violation->getMessage();
        }

        return $errors;
    }
}
