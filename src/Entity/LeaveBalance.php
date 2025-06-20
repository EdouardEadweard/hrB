<?php

namespace App\Entity;

use App\Repository\LeaveBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeaveBalanceRepository::class)]
#[ORM\Table(name: 'leave_balance')]
#[ORM\UniqueConstraint(name: 'unique_employee_leave_type_year', columns: ['employee_id', 'leave_type_id', 'year'])]
class LeaveBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'L\'année est obligatoire')]
    #[Assert\Range(
        min: 2020,
        max: 2050,
        notInRangeMessage: 'L\'année doit être comprise entre {{ min }} et {{ max }}'
    )]
    private ?int $year = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le nombre total de jours est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre total de jours doit être positif ou nul')]
    private ?int $totalDays = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le nombre de jours utilisés est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre de jours utilisés doit être positif ou nul')]
    private ?int $usedDays = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero(message: 'Le nombre de jours restants doit être positif ou nul')]
    private ?int $remainingDays = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastUpdated = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'L\'employé est obligatoire')]
    private ?User $employee = null;

    #[ORM\ManyToOne(targetEntity: LeaveType::class)]
    #[ORM\JoinColumn(name: 'leave_type_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Le type de congé est obligatoire')]
    private ?LeaveType $leaveType = null;

    public function __construct()
    {
        $this->year = (int) date('Y');
        $this->usedDays = 0;
        $this->lastUpdated = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        $this->updateRemainingDays();
        $this->updateLastUpdated();

        return $this;
    }

    public function getTotalDays(): ?int
    {
        return $this->totalDays;
    }

    public function setTotalDays(int $totalDays): static
    {
        $this->totalDays = $totalDays;
        $this->updateRemainingDays();
        $this->updateLastUpdated();

        return $this;
    }

    public function getUsedDays(): ?int
    {
        return $this->usedDays;
    }

    public function setUsedDays(int $usedDays): static
    {
        $this->usedDays = $usedDays;
        $this->updateRemainingDays();
        $this->updateLastUpdated();

        return $this;
    }

    public function getRemainingDays(): ?int
    {
        return $this->remainingDays;
    }

    public function setRemainingDays(?int $remainingDays): static
    {
        $this->remainingDays = $remainingDays;

        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;

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

    /**
     * Met à jour automatiquement les jours restants
     */
    private function updateRemainingDays(): void
    {
        if ($this->totalDays !== null && $this->usedDays !== null) {
            $this->remainingDays = $this->totalDays - $this->usedDays;
        }
    }

    /**
     * Met à jour automatiquement la date de dernière modification
     */
    private function updateLastUpdated(): void
    {
        $this->lastUpdated = new \DateTime();
    }

    /**
     * Ajoute des jours utilisés et met à jour le solde
     */
    public function addUsedDays(int $days): static
    {
        $this->usedDays += $days;
        $this->updateRemainingDays();
        $this->updateLastUpdated();

        return $this;
    }

    /**
     * Retire des jours utilisés et met à jour le solde
     */
    public function removeUsedDays(int $days): static
    {
        $this->usedDays = max(0, $this->usedDays - $days);
        $this->updateRemainingDays();
        $this->updateLastUpdated();

        return $this;
    }

    /**
     * Vérifie si le solde permet la prise de congés
     */
    public function canTakeDays(int $days): bool
    {
        return $this->remainingDays >= $days;
    }

    /**
     * Retourne le pourcentage de jours utilisés
     */
    public function getUsagePercentage(): float
    {
        if ($this->totalDays === 0) {
            return 0.0;
        }
        
        return round(($this->usedDays / $this->totalDays) * 100, 2);
    }

    /**
     * Vérifie si le solde est épuisé
     */
    public function isExhausted(): bool
    {
        return $this->remainingDays <= 0;
    }

    /**
     * Vérifie si le solde est presque épuisé (moins de 10% restant)
     */
    public function isAlmostExhausted(): bool
    {
        if ($this->totalDays === 0) {
            return false;
        }
        
        return ($this->remainingDays / $this->totalDays) <= 0.1;
    }

    /**
     * Représentation textuelle de l'entité
     */
    public function __toString(): string
    {
        $employeeName = $this->employee ? $this->employee->getFirstName() . ' ' . $this->employee->getLastName() : 'N/A';
        $leaveTypeName = $this->leaveType ? $this->leaveType->getName() : 'N/A';
        
        return sprintf(
            '%s - %s (%d) : %d/%d jours',
            $employeeName,
            $leaveTypeName,
            $this->year,
            $this->usedDays,
            $this->totalDays
        );
    }
}