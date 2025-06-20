<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: false)]
    private ?User $recipient = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: true)]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: LeaveRequest::class)]
    #[ORM\JoinColumn(name: 'leaveRequest_id', referencedColumnName: 'id', nullable: true)]
    private ?LeaveRequest $leaveRequest = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        
        // Automatiquement définir readAt quand la notification est marquée comme lue
        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTime();
        }
        
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

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getLeaveRequest(): ?LeaveRequest
    {
        return $this->leaveRequest;
    }

    public function setLeaveRequest(?LeaveRequest $leaveRequest): static
    {
        $this->leaveRequest = $leaveRequest;
        return $this;
    }

    /**
     * Méthode utilitaire pour marquer la notification comme lue
     */
    public function markAsRead(): static
    {
        $this->setIsRead(true);
        return $this;
    }

    /**
     * Méthode utilitaire pour vérifier si la notification est récente (moins de 24h)
     */
    public function isRecent(): bool
    {
        $now = new \DateTime();
        $interval = $now->diff($this->createdAt);
        return $interval->days < 1;
    }

    /**
     * Méthode utilitaire pour obtenir le temps écoulé depuis la création
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $interval = $now->diff($this->createdAt);

        if ($interval->days > 0) {
            return $interval->days . ' jour' . ($interval->days > 1 ? 's' : '');
        } elseif ($interval->h > 0) {
            return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
        } elseif ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        } else {
            return 'À l\'instant';
        }
    }

    /**
     * Constantes pour les types de notifications
     */
    public const TYPE_LEAVE_REQUEST = 'leave_request';
    public const TYPE_LEAVE_APPROVED = 'leave_approved';
    public const TYPE_LEAVE_REJECTED = 'leave_rejected';
    public const TYPE_LEAVE_CANCELLED = 'leave_cancelled';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_REMINDER = 'reminder';

    /**
     * Méthode pour obtenir tous les types disponibles
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_LEAVE_REQUEST => 'Demande de congé',
            self::TYPE_LEAVE_APPROVED => 'Congé approuvé',
            self::TYPE_LEAVE_REJECTED => 'Congé refusé',
            self::TYPE_LEAVE_CANCELLED => 'Congé annulé',
            self::TYPE_SYSTEM => 'Système',
            self::TYPE_REMINDER => 'Rappel',
        ];
    }

    /**
     * Méthode pour obtenir le libellé du type
     */
    public function getTypeLabel(): string
    {
        $types = self::getAvailableTypes();
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Méthode pour obtenir la classe CSS basée sur le type
     */
    public function getTypeCssClass(): string
    {
        return match ($this->type) {
            self::TYPE_LEAVE_REQUEST => 'text-primary',
            self::TYPE_LEAVE_APPROVED => 'text-success',
            self::TYPE_LEAVE_REJECTED => 'text-danger',
            self::TYPE_LEAVE_CANCELLED => 'text-warning',
            self::TYPE_SYSTEM => 'text-info',
            self::TYPE_REMINDER => 'text-secondary',
            default => 'text-dark',
        };
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        return $this->title ?? 'Notification #' . $this->id;
    }
}