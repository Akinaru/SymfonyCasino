<?php
// src/Entity/Transaction.php
namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: "transaction")]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "utilisateur_id", referencedColumnName: "id", nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Partie::class)]
    #[ORM\JoinColumn(name: "partie_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?Partie $partie = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $game_key = null;

    #[ORM\Column(type: "string", length: 20, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: "integer")]
    private int $montant;

    #[ORM\Column(type: "integer")]
    private int $solde_avant;

    #[ORM\Column(type: "integer")]
    private int $solde_apres;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $cree_le;

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getPartie(): ?Partie { return $this->partie; }
    public function setPartie(?Partie $partie): self { $this->partie = $partie; return $this; }

    public function getGameKey(): ?string { return $this->game_key; }
    public function setGameKey(?string $game_key): self { $this->game_key = $game_key; return $this; }

    public function getType(): TransactionType { return $this->type; }
    public function setType(TransactionType $type): self { $this->type = $type; return $this; }

    public function getMontant(): int { return $this->montant; }
    public function setMontant(int $montant): self { $this->montant = $montant; return $this; }

    public function getSoldeAvant(): int { return $this->solde_avant; }
    public function setSoldeAvant(int $solde_avant): self { $this->solde_avant = $solde_avant; return $this; }

    public function getSoldeApres(): int { return $this->solde_apres; }
    public function setSoldeApres(int $solde_apres): self { $this->solde_apres = $solde_apres; return $this; }

    public function getCreeLe(): \DateTimeInterface { return $this->cree_le; }
    public function setCreeLe(\DateTimeInterface $cree_le): self { $this->cree_le = $cree_le; return $this; }
}
