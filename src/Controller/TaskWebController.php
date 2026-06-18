<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TaskWebController extends AbstractController
{
    #[Route('/tasks', name: 'tasks_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tasks/index.html.twig');
    }
}
