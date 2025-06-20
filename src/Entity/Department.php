<?php

namespace App\Entity;

use App\Repository\DepartmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code de département est déjà utilisé.')]
#[UniqueEntity(fields: ['name'], message: 'Ce nom de département est déjà utilisé.')]
class Department
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du département est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom du département doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du département ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Assert\NotBlank(message: 'Le code du département est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 10,
        minMessage: 'Le code du département doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le code du département ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]+$/',
        message: 'Le code du département ne peut contenir que des lettres majuscules et des chiffres.'
    )]
    private ?string $code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'managedDepartments')]
    #[ORM\JoinColumn(name: 'manager_id', referencedColumnName: 'id')]
    private ?User $manager = null;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: User::class)]
    private Collection $employees;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: Team::class)]
    private Collection $teams;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: LeavePolicy::class, orphanRemoval: true)]
    private Collection $leavePolicies;

    public function __construct()
    {
        $this->employees = new ArrayCollection();
        $this->teams = new ArrayCollection();
        $this->leavePolicies = new ArrayCollection();
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

    public function isActive(): ?bool
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getEmployees(): Collection
    {
        return $this->employees;
    }

    public function addEmployee(User $employee): static
    {
        if (!$this->employees->contains($employee)) {
            $this->employees->add($employee);
            $employee->setDepartment($this);
        }

        return $this;
    }

    public function removeEmployee(User $employee): static
    {
        if ($this->employees->removeElement($employee)) {
            // set the owning side to null (unless already changed)
            if ($employee->getDepartment() === $this) {
                $employee->setDepartment(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): static
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
            $team->setDepartment($this);
        }

        return $this;
    }

    public function removeTeam(Team $team): static
    {
        if ($this->teams->removeElement($team)) {
            // set the owning side to null (unless already changed)
            if ($team->getDepartment() === $this) {
                $team->setDepartment(null);
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
            $leavePolicy->setDepartment($this);
        }

        return $this;
    }

    public function removeLeavePolicy(LeavePolicy $leavePolicy): static
    {
        if ($this->leavePolicies->removeElement($leavePolicy)) {
            // set the owning side to null (unless already changed)
            if ($leavePolicy->getDepartment() === $this) {
                $leavePolicy->setDepartment(null);
            }
        }

        return $this;
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Retourne le nombre total d'employés dans le département
     */
    public function getTotalEmployees(): int
    {
        return $this->employees->count();
    }

    /**
     * Retourne le nombre d'employés actifs dans le département
     */
    public function getActiveEmployees(): int
    {
        return $this->employees->filter(function(User $employee) {
            return $employee->isActive();
        })->count();
    }

    /**
     * Retourne le nombre total d'équipes dans le département
     */
    public function getTotalTeams(): int
    {
        return $this->teams->count();
    }

    /**
     * Retourne le nombre d'équipes actives dans le département
     */
    public function getActiveTeams(): int
    {
        return $this->teams->filter(function(Team $team) {
            return $team->isActive();
        })->count();
    }

    /**
     * Vérifie si l'utilisateur donné est le manager du département
     */
    public function isManagerBy(User $user): bool
    {
        return $this->manager && $this->manager->getId() === $user->getId();
    }

    /**
     * Vérifie si l'utilisateur donné fait partie du département
     */
    public function hasEmployee(User $user): bool
    {
        return $this->employees->contains($user);
    }

    /**
     * Retourne tous les employés actifs du département
     */
    public function getActiveEmployeesList(): Collection
    {
        return $this->employees->filter(function(User $employee) {
            return $employee->isActive();
        });
    }

    /**
     * Retourne toutes les équipes actives du département
     */
    public function getActiveTeamsList(): Collection
    {
        return $this->teams->filter(function(Team $team) {
            return $team->isActive();
        });
    }

    /**
     * Retourne le nom complet du département avec son code
     */
    public function getFullName(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Vérifie si le département a des politiques de congés définies
     */
    public function hasLeavePolicies(): bool
    {
        return $this->leavePolicies->count() > 0;
    }

    /**
     * Retourne les politiques de congés actives du département
     */
    public function getActiveLeavePolicies(): Collection
    {
        return $this->leavePolicies->filter(function(LeavePolicy $policy) {
            return $policy->getIsActive();
        });
    }

    /**
     * Callbacks Doctrine pour les dates automatiques
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}