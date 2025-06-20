<?php

namespace App\Entity;

use App\Repository\LeaveTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeaveTypeRepository::class)]
#[ORM\Table(name: 'leave_type')]
class LeaveType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du type de congé est obligatoire')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Le code du type de congé est obligatoire')]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9_]+$/',
        message: 'Le code doit contenir uniquement des lettres majuscules, des chiffres et des underscores'
    )]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre maximum de jours par année est obligatoire')]
    #[Assert\Positive(message: 'Le nombre de jours doit être positif')]
    #[Assert\Range(
        min: 1,
        max: 365,
        notInRangeMessage: 'Le nombre de jours doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $maxDaysPerYear = null;

    #[ORM\Column]
    private ?bool $requiresApproval = true;

    #[ORM\Column]
    private ?bool $isPaid = true;

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format hexadécimal (#RRGGBB)'
    )]
    private ?string $color = '#007bff';

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, LeaveRequest>
     */
    #[ORM\OneToMany(targetEntity: LeaveRequest::class, mappedBy: 'leaveType')]
    private Collection $leaveRequests;

    /**
     * @var Collection<int, LeaveBalance>
     */
    #[ORM\OneToMany(targetEntity: LeaveBalance::class, mappedBy: 'leaveType')]
    private Collection $leaveBalances;

    /**
     * @var Collection<int, LeavePolicy>
     */
    #[ORM\OneToMany(targetEntity: LeavePolicy::class, mappedBy: 'leaveType')]
    private Collection $leavePolicies;

    public function __construct()
    {
        $this->leaveRequests = new ArrayCollection();
        $this->leaveBalances = new ArrayCollection();
        $this->leavePolicies = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);

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

    public function getMaxDaysPerYear(): ?int
    {
        return $this->maxDaysPerYear;
    }

    public function setMaxDaysPerYear(int $maxDaysPerYear): static
    {
        $this->maxDaysPerYear = $maxDaysPerYear;

        return $this;
    }

    public function isRequiresApproval(): ?bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): static
    {
        $this->requiresApproval = $requiresApproval;

        return $this;
    }

    public function isIsPaid(): ?bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): static
    {
        $this->isPaid = $isPaid;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function getLeaveRequests(): Collection
    {
        return $this->leaveRequests;
    }

    public function addLeaveRequest(LeaveRequest $leaveRequest): static
    {
        if (!$this->leaveRequests->contains($leaveRequest)) {
            $this->leaveRequests->add($leaveRequest);
            $leaveRequest->setLeaveType($this);
        }

        return $this;
    }

    public function removeLeaveRequest(LeaveRequest $leaveRequest): static
    {
        if ($this->leaveRequests->removeElement($leaveRequest)) {
            // set the owning side to null (unless already changed)
            if ($leaveRequest->getLeaveType() === $this) {
                $leaveRequest->setLeaveType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LeaveBalance>
     */
    public function getLeaveBalances(): Collection
    {
        return $this->leaveBalances;
    }

    public function addLeaveBalance(LeaveBalance $leaveBalance): static
    {
        if (!$this->leaveBalances->contains($leaveBalance)) {
            $this->leaveBalances->add($leaveBalance);
            $leaveBalance->setLeaveType($this);
        }

        return $this;
    }

    public function removeLeaveBalance(LeaveBalance $leaveBalance): static
    {
        if ($this->leaveBalances->removeElement($leaveBalance)) {
            // set the owning side to null (unless already changed)
            if ($leaveBalance->getLeaveType() === $this) {
                $leaveBalance->setLeaveType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LeavePolicy>
     */
    public function getLeavePolicies(): Collection
    {
        return $this->leavePolicies;
    }

    public function addLeavePolicy(LeavePolicy $leavePolicy): static
    {
        if (!$this->leavePolicies->contains($leavePolicy)) {
            $this->leavePolicies->add($leavePolicy);
            $leavePolicy->setLeaveType($this);
        }

        return $this;
    }

    public function removeLeavePolicy(LeavePolicy $leavePolicy): static
    {
        if ($this->leavePolicies->removeElement($leavePolicy)) {
            // set the owning side to null (unless already changed)
            if ($leavePolicy->getLeaveType() === $this) {
                $leavePolicy->setLeaveType(null);
            }
        }

        return $this;
    }

    /**
     * Méthode pour l'affichage dans les formulaires et listes
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Retourne le nombre total de demandes de congés pour ce type
     */
    public function getTotalLeaveRequests(): int
    {
        return $this->leaveRequests->count();
    }

    /**
     * Retourne le nombre de demandes approuvées pour ce type
     */
    public function getApprovedLeaveRequests(): int
    {
        return $this->leaveRequests->filter(function(LeaveRequest $request) {
            return $request->getStatus() === 'approved';
        })->count();
    }

    /**
     * Retourne le libellé complet (nom + code)
     */
    public function getFullLabel(): string
    {
        return sprintf('%s (%s)', $this->name, $this->code);
    }

    /**
     * Vérifie si le type de congé est utilisé
     */
    public function isUsed(): bool
    {
        return $this->leaveRequests->count() > 0 || $this->leaveBalances->count() > 0;
    }
}