<?php

namespace App\Entity;

use App\Repository\TeamMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\Table(name: 'team_member')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['team', 'user'],
    message: 'Cet utilisateur est déjà membre de cette équipe',
    ignoreNull: false
)]
class TeamMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date d\'adhésion est obligatoire')]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $leftAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'teamMembers')]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'L\'équipe est obligatoire')]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    private ?User $user = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }

    public function getLeftAt(): ?\DateTimeInterface
    {
        return $this->leftAt;
    }

    public function setLeftAt(?\DateTimeInterface $leftAt): static
    {
        $this->leftAt = $leftAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        
        // Si on désactive le membre, on définit automatiquement la date de sortie
        if (!$isActive && $this->leftAt === null) {
            $this->leftAt = new \DateTime();
        }
        
        // Si on réactive le membre, on supprime la date de sortie
        if ($isActive && $this->leftAt !== null) {
            $this->leftAt = null;
        }
        
        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Calcule la durée d'appartenance à l'équipe en jours
     */
    public function getMembershipDuration(): int
    {
        $endDate = $this->leftAt ?? new \DateTime();
        $startDate = $this->joinedAt ?? new \DateTime();
        
        $interval = $startDate->diff($endDate);
        return $interval->days;
    }

    /**
     * Calcule la durée d'appartenance à l'équipe en format lisible
     */
    public function getMembershipDurationFormatted(): string
    {
        $endDate = $this->leftAt ?? new \DateTime();
        $startDate = $this->joinedAt ?? new \DateTime();
        
        $interval = $startDate->diff($endDate);
        
        if ($interval->y > 0) {
            return $interval->format('%y an(s) et %m mois');
        } elseif ($interval->m > 0) {
            return $interval->format('%m mois et %d jour(s)');
        } else {
            return $interval->format('%d jour(s)');
        }
    }

    /**
     * Vérifie si le membre était actif à une date donnée
     */
    public function wasActiveAt(\DateTimeInterface $date): bool
    {
        $joinedAt = $this->joinedAt ?? new \DateTime();
        $leftAt = $this->leftAt;
        
        if ($date < $joinedAt) {
            return false;
        }
        
        if ($leftAt !== null && $date > $leftAt) {
            return false;
        }
        
        return true;
    }

    /**
     * Marque le membre comme ayant quitté l'équipe
     */
    public function leave(\DateTimeInterface $leftAt = null): static
    {
        $this->leftAt = $leftAt ?? new \DateTime();
        $this->isActive = false;
        return $this;
    }

    /**
     * Réactive un membre dans l'équipe
     */
    public function rejoin(\DateTimeInterface $joinedAt = null): static
    {
        if ($joinedAt !== null) {
            $this->joinedAt = $joinedAt;
        }
        $this->leftAt = null;
        $this->isActive = true;
        return $this;
    }

    /**
     * Vérifie si ce membre peut être supprimé
     * (par exemple, s'il n'a pas d'historique important)
     */
    public function canBeDeleted(): bool
    {
        // On peut supprimer si le membre vient juste de rejoindre (moins de 24h)
        // et n'a jamais quitté l'équipe
        if ($this->leftAt !== null) {
            return false;
        }
        
        $joinedAt = $this->joinedAt ?? new \DateTime();
        $now = new \DateTime();
        $interval = $joinedAt->diff($now);
        
        return $interval->days === 0 && $interval->h < 24;
    }

    /**
     * Retourne le statut du membre sous forme de texte
     */
    public function getStatusText(): string
    {
        if ($this->isActive) {
            return 'Actif';
        } elseif ($this->leftAt !== null) {
            return 'Parti le ' . $this->leftAt->format('d/m/Y');
        } else {
            return 'Inactif';
        }
    }

    /**
     * Validation personnalisée : la date de sortie ne peut pas être antérieure à la date d'adhésion
     */
    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->leftAt !== null && $this->joinedAt !== null) {
            if ($this->leftAt < $this->joinedAt) {
                $context->buildViolation('La date de sortie ne peut pas être antérieure à la date d\'adhésion')
                    ->atPath('leftAt')
                    ->addViolation();
            }
        }
    }

    /**
     * Méthode appelée automatiquement avant la persistance
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->joinedAt === null) {
            $this->joinedAt = new \DateTime();
        }
    }

    /**
     * Méthode appelée automatiquement avant la mise à jour
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        // Synchronisation automatique entre isActive et leftAt
        if (!$this->isActive && $this->leftAt === null) {
            $this->leftAt = new \DateTime();
        }
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        $userName = $this->user ? $this->user->getFirstName() . ' ' . $this->user->getLastName() : 'Utilisateur inconnu';
        $teamName = $this->team ? $this->team->getName() : 'Équipe inconnue';
        
        return $userName . ' - ' . $teamName;
    }
}