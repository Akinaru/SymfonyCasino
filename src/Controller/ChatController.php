<?php

namespace App\Controller;

use App\Entity\Message;
use App\Notifier\ChatMessageNotifier;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/chat', name: 'chat_')]
class ChatController extends AbstractController
{
    public function __construct(
        private ChatMessageNotifier $chatNotifier
    ) {}

    #[Route('/messages', name: 'messages', methods: ['GET'])]
    public function messages(MessageRepository $repo): JsonResponse
    {
        $messages = $repo->findLastMessages(100);
        $messages = array_reverse($messages); // ordre chronologique

        $data = array_map(function (Message $m) {
            return [
                'id'        => $m->getId(),
                'content'   => $m->getContent(),
                'createdAt' => $m->getCreatedAt()->format('Y-m-d H:i'),
                'user'      => [
                    'id'     => $m->getUser()->getId(),
                    'pseudo' => $m->getUser()->getPseudo() ?? $m->getUser()->getEmail(),
                    'avatar' => $m->getUser()->getAvatarUrl(),
                ],
            ];
        }, $messages);

        return new JsonResponse($data);
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $content = trim($request->request->get('content', ''));
        if ($content === '') {
            return new JsonResponse(['error' => 'empty'], 400);
        }

        $msg = new Message();
        $msg->setUser($this->getUser());
        $msg->setContent($content);

        $em->persist($msg);
        $em->flush();

        // 🔔 Mercure — notify tous les clients
        $this->chatNotifier->notify($msg);

        return new JsonResponse(['success' => true]);
    }
}
