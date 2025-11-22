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
        $messages = array_reverse($messages);

        $data = array_map(function (Message $m) {
            $user = $m->getUser();

            return [
                'id'        => $m->getId(),
                'content'   => $m->getContent(),
                'createdAt' => $m->getCreatedAt()->format('Y-m-d H:i'),
                'isSystem'  => $m->isSystem(),
                'user'      => $user ? [
                    'id'     => $user->getId(),
                    'pseudo' => $user->getPseudo() ?? $user->getEmail(),
                    'avatar' => $user->getAvatarUrl(),
                ] : null,
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

        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        $msg = new Message();
        $msg->setUser($user);
        $msg->setContent($content);
        $msg->setSystem(false);

        $em->persist($msg);
        $em->flush();

        $this->chatNotifier->notify($msg);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em->createQuery('DELETE FROM App\Entity\Message m')->execute();

        $this->chatNotifier->notifyClear();

        return new JsonResponse(['success' => true]);
    }
}
