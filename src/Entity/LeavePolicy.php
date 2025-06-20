<?php

namespace App\Entity;

use App\Repository\LeavePolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeavePolicyRepository::class)]
#[ORM\Table(name: 'leave_policy')]
#[ORM\UniqueConstraint(name: 'unique_department_leave_type_policy', columns: ['department_id', 'leave_type_id', 'name'])]
class LeavePolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la politique est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les règles sont obligatoires')]
    private array $rules = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Le département est obligatoire')]
    private ?Department $department = null;

    #[ORM\ManyToOne(targetEntity: LeaveType::class)]
    #[ORM\JoinColumn(name: 'leave_type_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Le type de congé est obligatoire')]
    private ?LeaveType $leaveType = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
        $this->rules = $this->getDefaultRules();
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function setRules(array $rules): static
    {
        $this->rules = array_merge($this->getDefaultRules(), $rules);

        return $this;
    }

    public function getIsActive(): bool
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

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;

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
     * Retourne les règles par défaut d'une politique de congés
     */
    private function getDefaultRules(): array
    {
        return [
            'max_days_per_year' => 25,
            'max_consecutive_days' => 15,
            'min_advance_days' => 7,
            'can_carry_over' => false,
            'max_carry_over_days' => 5,
            'blackout_periods' => [],
            'minimum_notice_days' => 1,
            'requires_manager_approval' => true,
            'requires_hr_approval' => false,
            'auto_approve_under_days' => 0,
            'can_split_requests' => true,
            'weekend_included' => false,
            'holiday_included' => false,
            'probation_period_months' => 3,
            'accrual_rate' => 2.08, // jours par mois
            'reset_date' => '01-01', // 1er janvier
        ];
    }

    /**
     * Obtient une règle spécifique
     */
    public function getRule(string $key, $default = null)
    {
        return $this->rules[$key] ?? $default;
    }

    /**
     * Définit une règle spécifique
     */
    public function setRule(string $key, $value): static
    {
        $this->rules[$key] = $value;

        return $this;
    }

    /**
     * Vérifie si une demande de congés respecte cette politique
     */
    public function validateLeaveRequest(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $requestedDays, User $employee): array
    {
        $errors = [];

        // Vérifier le préavis minimum
        $minAdvanceDays = $this->getRule('min_advance_days', 7);
        $daysInAdvance = (new \DateTime())->diff($startDate)->days;
        if ($daysInAdvance < $minAdvanceDays) {
            $errors[] = sprintf('Vous devez faire votre demande au moins %d jours à l\'avance', $minAdvanceDays);
        }

        // Vérifier le nombre maximum de jours consécutifs
        $maxConsecutiveDays = $this->getRule('max_consecutive_days', 15);
        if ($requestedDays > $maxConsecutiveDays) {
            $errors[] = sprintf('Vous ne pouvez pas prendre plus de %d jours consécutifs', $maxConsecutiveDays);
        }

        // Vérifier les périodes d'interdiction
        $blackoutPeriods = $this->getRule('blackout_periods', []);
        foreach ($blackoutPeriods as $period) {
            $blackoutStart = new \DateTime($period['start']);
            $blackoutEnd = new \DateTime($period['end']);
            
            if (($startDate >= $blackoutStart && $startDate <= $blackoutEnd) ||
                ($endDate >= $blackoutStart && $endDate <= $blackoutEnd)) {
                $errors[] = sprintf('Les congés ne sont pas autorisés du %s au %s', 
                    $blackoutStart->format('d/m/Y'), 
                    $blackoutEnd->format('d/m/Y')
                );
            }
        }

        // Vérifier la période d'essai
        $probationMonths = $this->getRule('probation_period_months', 3);
        if ($employee->getHireDate()) {
            $probationEnd = clone $employee->getHireDate();
            $probationEnd->add(new \DateInterval('P' . $probationMonths . 'M'));
            
            if (new \DateTime() < $probationEnd) {
                $errors[] = sprintf('Vous ne pouvez pas prendre de congés pendant votre période d\'essai (jusqu\'au %s)', 
                    $probationEnd->format('d/m/Y')
                );
            }
        }

        return $errors;
    }

    /**
     * Calcule le nombre de jours de congés acquis pour un employé
     */
    public function calculateAccruedDays(User $employee, ?\DateTimeInterface $asOfDate = null): float
    {
        if (!$employee->getHireDate()) {
            return 0.0;
        }

        $asOfDate = $asOfDate ?? new \DateTime();
        $hireDate = $employee->getHireDate();
        $accrualRate = (float) $this->getRule('accrual_rate', 2.08);

        // Calculer le nombre de mois travaillés
        $monthsWorked = $hireDate->diff($asOfDate)->m + ($hireDate->diff($asOfDate)->y * 12);
        
        return round($monthsWorked * $accrualRate, 2);
    }

    /**
     * Détermine si l'approbation automatique est possible
     */
    public function canAutoApprove(int $requestedDays): bool
    {
        $autoApproveUnderDays = $this->getRule('auto_approve_under_days', 0);
        
        return $autoApproveUnderDays > 0 && $requestedDays <= $autoApproveUnderDays;
    }

    /**
     * Détermine qui doit approuver la demande
     */
    public function getRequiredApprovers(): array
    {
        $approvers = [];

        if ($this->getRule('requires_manager_approval', true)) {
            $approvers[] = 'manager';
        }

        if ($this->getRule('requires_hr_approval', false)) {
            $approvers[] = 'hr';
        }

        return $approvers;
    }

    /**
     * Vérifie si les reports de congés sont autorisés
     */
    public function allowsCarryOver(): bool
    {
        return (bool) $this->getRule('can_carry_over', false);
    }

    /**
     * Obtient le nombre maximum de jours reportables
     */
    public function getMaxCarryOverDays(): int
    {
        return (int) $this->getRule('max_carry_over_days', 5);
    }

    /**
     * Obtient la date de remise à zéro des congés
     */
    public function getResetDate(): string
    {
        return $this->getRule('reset_date', '01-01');
    }

    /**
     * Vérifie si les week-ends sont inclus dans le calcul
     */
    public function includesWeekends(): bool
    {
        return (bool) $this->getRule('weekend_included', false);
    }

    /**
     * Vérifie si les jours fériés sont inclus dans le calcul
     */
    public function includesHolidays(): bool
    {
        return (bool) $this->getRule('holiday_included', false);
    }

    /**
     * Obtient un résumé des règles principales
     */
    public function getRulesSummary(): array
    {
        return [
            'Jours maximum par an' => $this->getRule('max_days_per_year'),
            'Jours consécutifs maximum' => $this->getRule('max_consecutive_days'),
            'Préavis minimum (jours)' => $this->getRule('min_advance_days'),
            'Report autorisé' => $this->allowsCarryOver() ? 'Oui' : 'Non',
            'Jours reportables maximum' => $this->getMaxCarryOverDays(),
            'Approbation manager requise' => $this->getRule('requires_manager_approval') ? 'Oui' : 'Non',
            'Approbation RH requise' => $this->getRule('requires_hr_approval') ? 'Oui' : 'Non',
            'Période d\'essai (mois)' => $this->getRule('probation_period_months'),
        ];
    }

    /**
     * Représentation textuelle de l'entité
     */
    public function __toString(): string
    {
        $departmentName = $this->department ? $this->department->getName() : 'N/A';
        $leaveTypeName = $this->leaveType ? $this->leaveType->getName() : 'N/A';
        
        return sprintf(
            '%s - %s (%s) %s',
            $this->name,
            $departmentName,
            $leaveTypeName,
            $this->isActive ? '[Actif]' : '[Inactif]'
        );
    }
}