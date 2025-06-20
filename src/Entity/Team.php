<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'team')]
#[ORM\HasLifecycleCallbacks]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'équipe est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'leader_id', referencedColumnName: 'id', nullable: true)]
    //#[Assert\NotNull(message: 'Un chef d\'équipe doit être assigné')]
    private ?User $leader = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'teams')]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Le département est obligatoire')]
    private ?Department $department = null;

    #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMember::class, cascade: ['persist', 'remove'])]
    private Collection $teamMembers;

    public function __construct()
    {
        $this->teamMembers = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLeader(): ?User
    {
        return $this->leader;
    }

    public function setLeader(?User $leader): static
    {
        $this->leader = $leader;
        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;
        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getTeamMembers(): Collection
    {
        return $this->teamMembers;
    }

    public function addTeamMember(TeamMember $teamMember): static
    {
        if (!$this->teamMembers->contains($teamMember)) {
            $this->teamMembers->add($teamMember);
            $teamMember->setTeam($this);
        }
        return $this;
    }

    public function removeTeamMember(TeamMember $teamMember): static
    {
        if ($this->teamMembers->removeElement($teamMember)) {
            // Set the owning side to null (unless already changed)
            if ($teamMember->getTeam() === $this) {
                $teamMember->setTeam(null);
            }
        }
        return $this;
    }

    /**
     * Retourne les membres actifs de l'équipe
     * 
     * @return Collection<int, TeamMember>
     */
    public function getActiveTeamMembers(): Collection
    {
        return $this->teamMembers->filter(function(TeamMember $teamMember) {
            return $teamMember->isActive();
        });
    }

    /**
     * Retourne le nombre de membres actifs dans l'équipe
     */
    public function getActiveMembersCount(): int
    {
        return $this->getActiveTeamMembers()->count();
    }

    /**
     * Vérifie si un utilisateur est membre de cette équipe
     */
    public function hasUser(User $user): bool
    {
        foreach ($this->getActiveTeamMembers() as $teamMember) {
            if ($teamMember->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si un utilisateur est le leader de cette équipe
     */
    public function isLeader(User $user): bool
    {
        return $this->leader === $user;
    }

    /**
     * Méthode appelée automatiquement avant la persistance
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        return $this->name ?? 'Équipe sans nom';
    }
}