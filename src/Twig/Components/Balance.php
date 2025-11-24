<?php

namespace App\Twig\Components;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Balance
{
    use DefaultActionTrait;

    public ?float $amount = null;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function mount(): void
    {
        $this->refresh();
    }

    #[LiveAction]
    public function refresh(): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof Utilisateur) {
            $this->amount = 0;
            return;
        }

        $this->amount = $user->getBalance();
    }
}
