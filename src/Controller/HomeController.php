<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Repository\ChatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private ChatRepository $chatRepository;

    public function __construct(ChatRepository $chatRepository)
    {
        $this->chatRepository = $chatRepository;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $chats = $this->chatRepository->findAll();

        return $this->render('home/index.html.twig', [
            'chats' => $chats,
        ]);
    }

    #[Route('/chat/{chat}', name: 'app_chat')]
    public function chat(Chat $chat): Response
    {
        return $this->render('home/chat.html.twig', [
            'chat' => $chat,
        ]);
    }
}
