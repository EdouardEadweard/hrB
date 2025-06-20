<?php

namespace App\Entity;

use App\Repository\LeaveRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeaveRequestRepository::class)]
#[ORM\Table(name: 'leave_request')]
class LeaveRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_APPROVED => 'Approuvée',
        self::STATUS_REJECTED => 'Rejetée',
        self::STATUS_CANCELLED => 'Annulée'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de début doit être une date valide')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de fin doit être une date valide')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure ou égale à la date de début'
    )]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre de jours est obligatoire')]
    #[Assert\Positive(message: 'Le nombre de jours doit être positif')]
    #[Assert\Range(
        min: 1,
        max: 365,
        notInRangeMessage: 'Le nombre de jours doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $numberOfDays = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $reason = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED
        ],
        message: 'Le statut sélectionné n\'est pas valide'
    )]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le commentaire du manager ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $managerComment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Assert\NotBlank(message: 'L\'employé est obligatoire')]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'leaveRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $employee = null;

    #[Assert\NotBlank(message: 'Le type de congé est obligatoire')]
    #[ORM\ManyToOne(targetEntity: LeaveType::class, inversedBy: 'leaveRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LeaveType $leaveType = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'leaveRequest')]
    private Collection $notifications;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        $this->calculateNumberOfDays();

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        $this->calculateNumberOfDays();

        return $this;
    }

    public function getNumberOfDays(): ?int
    {
        return $this->numberOfDays;
    }

    public function setNumberOfDays(int $numberOfDays): static
    {
        $this->numberOfDays = $numberOfDays;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getManagerComment(): ?string
    {
        return $this->managerComment;
    }

    public function setManagerComment(?string $managerComment): static
    {
        $this->managerComment = $managerComment;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEmployee(): ?User
    {
        return $this->employee;
    }

    public function setEmployee(?User $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getLeaveType(): ?LeaveType
    {
        return $this->leaveType;
    }

    public function setLeaveType(?LeaveType $leaveType): static
    {
        $this->leaveType = $leaveType;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setLeaveRequest($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getLeaveRequest() === $this) {
                $notification->setLeaveRequest(null);
            }
        }

        return $this;
    }

    /**
     * Méthode pour l'affichage dans les formulaires et listes
     */
    public function __toString(): string
    {
        return sprintf(
            'Demande %s du %s au %s',
            $this->leaveType?->getName() ?? 'N/A',
            $this->startDate?->format('d/m/Y') ?? 'N/A',
            $this->endDate?->format('d/m/Y') ?? 'N/A'
        );
    }

    /**
     * Calcule automatiquement le nombre de jours ouvrables
     */
    private function calculateNumberOfDays(): void
    {
        if ($this->startDate && $this->endDate) {
            $interval = $this->startDate->diff($this->endDate);
            $this->numberOfDays = $interval->days + 1; // +1 pour inclure le jour de début
        }
    }

    /**
     * Retourne le libellé du statut
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Inconnu';
    }

    /**
     * Vérifie si la demande est en attente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifie si la demande est approuvée
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Vérifie si la demande est rejetée
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Vérifie si la demande est annulée
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Vérifie si la demande peut être modifiée
     */
    public function canBeEdited(): bool
    {
        return $this->isPending();
    }

    /**
     * Vérifie si la demande peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    /**
     * Approuve la demande
     */
    public function approve(User $approver, ?string $comment = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedBy = $approver;
        $this->managerComment = $comment;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Rejette la demande
     */
    public function reject(User $rejector, ?string $comment = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->approvedBy = $rejector;
        $this->managerComment = $comment;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Annule la demande
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Soumet la demande
     */
    public function submit(): void
    {
        $this->submittedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Retourne la durée de la demande en format lisible
     */
    public function getDurationText(): string
    {
        if ($this->numberOfDays === 1) {
            return '1 jour';
        }
        
        return $this->numberOfDays . ' jours';
    }

    /**
     * Retourne la période de la demande en format lisible
     */
    public function getPeriodText(): string
    {
        if (!$this->startDate || !$this->endDate) {
            return 'Période non définie';
        }

        $start = $this->startDate->format('d/m/Y');
        $end = $this->endDate->format('d/m/Y');

        if ($start === $end) {
            return 'Le ' . $start;
        }

        return 'Du ' . $start . ' au ' . $end;
    }

    /**
     * Vérifie si la demande est dans le futur
     */
    public function isFuture(): bool
    {
        if (!$this->startDate) {
            return false;
        }

        return $this->startDate > new \DateTime();
    }

    /**
     * Vérifie si la demande est en cours
     */
    public function isCurrent(): bool
    {
        if (!$this->startDate || !$this->endDate) {
            return false;
        }

        $now = new \DateTime();
        return $this->startDate <= $now && $this->endDate >= $now;
    }

    /**
     * Vérifie si la demande est passée
     */
    public function isPast(): bool
    {
        if (!$this->endDate) {
            return false;
        }

        return $this->endDate < new \DateTime();
    }
}