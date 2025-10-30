<?php
// src/Entity/Partie.php
namespace App\Entity;

use App\Enum\IssueType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "partie")]
class Partie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "utilisateur_id", referencedColumnName: "id", nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $game_key;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $mise = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $gain = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $resultat_net = 0;

    #[ORM\Column(type: "string", length: 10, enumType: IssueType::class, options: ["default" => "perdu"])]
    private IssueType $issue = IssueType::PERDU;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $debut_le;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $fin_le = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $meta_json = null;

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getGameKey(): string { return $this->game_key; }
    public function setGameKey(string $game_key): self { $this->game_key = $game_key; return $this; }

    public function getMise(): int { return $this->mise; }
    public function setMise(int $mise): self { $this->mise = $mise; return $this; }

    public function getGain(): int { return $this->gain; }
    public function setGain(int $gain): self { $this->gain = $gain; return $this; }

    public function getResultatNet(): int { return $this->resultat_net; }
    public function setResultatNet(int $resultat_net): self { $this->resultat_net = $resultat_net; return $this; }

    public function getIssue(): IssueType { return $this->issue; }
    public function setIssue(IssueType $issue): self { $this->issue = $issue; return $this; }

    public function getDebutLe(): \DateTimeInterface { return $this->debut_le; }
    public function setDebutLe(\DateTimeInterface $debut_le): self { $this->debut_le = $debut_le; return $this; }

    public function getFinLe(): ?\DateTimeInterface { return $this->fin_le; }
    public function setFinLe(?\DateTimeInterface $fin_le): self { $this->fin_le = $fin_le; return $this; }

    public function getMetaJson(): ?string { return $this->meta_json; }
    public function setMetaJson(?string $meta_json): self { $this->meta_json = $meta_json; return $this; }
}
