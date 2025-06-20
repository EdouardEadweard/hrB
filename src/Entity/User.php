<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir un email valide.')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[0-9+\-\s()]+$/',
        message: 'Le numéro de téléphone n\'est pas valide.'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $hireDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $position = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id')]
    private ?Department $department = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subordinates')]
    #[ORM\JoinColumn(name: 'manager_id', referencedColumnName: 'id')]
    private ?self $manager = null;

    #[ORM\OneToMany(mappedBy: 'manager', targetEntity: self::class)]
    private Collection $subordinates;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: LeaveRequest::class, orphanRemoval: true)]
    private Collection $leaveRequests;

    #[ORM\OneToMany(mappedBy: 'approvedBy', targetEntity: LeaveRequest::class)]
    private Collection $approvedLeaveRequests;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: LeaveBalance::class, orphanRemoval: true)]
    private Collection $leaveBalances;

    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $receivedNotifications;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Notification::class)]
    private Collection $sentNotifications;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: Attendance::class, orphanRemoval: true)]
    private Collection $attendances;

    #[ORM\OneToMany(mappedBy: 'leader', targetEntity: Team::class)]
    private Collection $ledTeams;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TeamMember::class, orphanRemoval: true)]
    private Collection $teamMemberships;

    #[ORM\OneToMany(mappedBy: 'manager', targetEntity: Department::class)]
    private Collection $managedDepartments;

    public function __construct()
    {
        $this->subordinates = new ArrayCollection();
        $this->leaveRequests = new ArrayCollection();
        $this->approvedLeaveRequests = new ArrayCollection();
        $this->leaveBalances = new ArrayCollection();
        $this->receivedNotifications = new ArrayCollection();
        $this->sentNotifications = new ArrayCollection();
        $this->attendances = new ArrayCollection();
        $this->ledTeams = new ArrayCollection();
        $this->teamMemberships = new ArrayCollection();
        $this->managedDepartments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Un identifiant visuel qui représente cet utilisateur.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // garantir que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_EMPLOYEE';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez des données temporaires sensibles sur l'utilisateur, effacez-les ici
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getHireDate(): ?\DateTimeInterface
    {
        return $this->hireDate;
    }

    public function setHireDate(?\DateTimeInterface $hireDate): static
    {
        $this->hireDate = $hireDate;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position ?? '';
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

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

    public function getManager(): ?self
    {
        return $this->manager;
    }

    public function setManager(?self $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getSubordinates(): Collection
    {
        return $this->subordinates;
    }

    public function addSubordinate(self $subordinate): static
    {
        if (!$this->subordinates->contains($subordinate)) {
            $this->subordinates->add($subordinate);
            $subordinate->setManager($this);
        }

        return $this;
    }

    public function removeSubordinate(self $subordinate): static
    {
        if ($this->subordinates->removeElement($subordinate)) {
            // set the owning side to null (unless already changed)
            if ($subordinate->getManager() === $this) {
                $subordinate->setManager(null);
            }
        }

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
            $leaveRequest->setEmployee($this);
        }

        return $this;
    }

    public function removeLeaveRequest(LeaveRequest $leaveRequest): static
    {
        if ($this->leaveRequests->removeElement($leaveRequest)) {
            // set the owning side to null (unless already changed)
            if ($leaveRequest->getEmployee() === $this) {
                $leaveRequest->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function getApprovedLeaveRequests(): Collection
    {
        return $this->approvedLeaveRequests;
    }

    public function addApprovedLeaveRequest(LeaveRequest $approvedLeaveRequest): static
    {
        if (!$this->approvedLeaveRequests->contains($approvedLeaveRequest)) {
            $this->approvedLeaveRequests->add($approvedLeaveRequest);
            $approvedLeaveRequest->setApprovedBy($this);
        }

        return $this;
    }

    public function removeApprovedLeaveRequest(LeaveRequest $approvedLeaveRequest): static
    {
        if ($this->approvedLeaveRequests->removeElement($approvedLeaveRequest)) {
            // set the owning side to null (unless already changed)
            if ($approvedLeaveRequest->getApprovedBy() === $this) {
                $approvedLeaveRequest->setApprovedBy(null);
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
            $leaveBalance->setEmployee($this);
        }

        return $this;
    }

    public function removeLeaveBalance(LeaveBalance $leaveBalance): static
    {
        if ($this->leaveBalances->removeElement($leaveBalance)) {
            // set the owning side to null (unless already changed)
            if ($leaveBalance->getEmployee() === $this) {
                $leaveBalance->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getReceivedNotifications(): Collection
    {
        return $this->receivedNotifications;
    }

    public function addReceivedNotification(Notification $receivedNotification): static
    {
        if (!$this->receivedNotifications->contains($receivedNotification)) {
            $this->receivedNotifications->add($receivedNotification);
            $receivedNotification->setRecipient($this);
        }

        return $this;
    }

    public function removeReceivedNotification(Notification $receivedNotification): static
    {
        if ($this->receivedNotifications->removeElement($receivedNotification)) {
            // set the owning side to null (unless already changed)
            if ($receivedNotification->getRecipient() === $this) {
                $receivedNotification->setRecipient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getSentNotifications(): Collection
    {
        return $this->sentNotifications;
    }

    public function addSentNotification(Notification $sentNotification): static
    {
        if (!$this->sentNotifications->contains($sentNotification)) {
            $this->sentNotifications->add($sentNotification);
            $sentNotification->setSender($this);
        }

        return $this;
    }

    public function removeSentNotification(Notification $sentNotification): static
    {
        if ($this->sentNotifications->removeElement($sentNotification)) {
            // set the owning side to null (unless already changed)
            if ($sentNotification->getSender() === $this) {
                $sentNotification->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function addAttendance(Attendance $attendance): static
    {
        if (!$this->attendances->contains($attendance)) {
            $this->attendances->add($attendance);
            $attendance->setEmployee($this);
        }

        return $this;
    }

    public function removeAttendance(Attendance $attendance): static
    {
        if ($this->attendances->removeElement($attendance)) {
            // set the owning side to null (unless already changed)
            if ($attendance->getEmployee() === $this) {
                $attendance->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getLedTeams(): Collection
    {
        return $this->ledTeams;
    }

    public function addLedTeam(Team $ledTeam): static
    {
        if (!$this->ledTeams->contains($ledTeam)) {
            $this->ledTeams->add($ledTeam);
            $ledTeam->setLeader($this);
        }

        return $this;
    }

    public function removeLedTeam(Team $ledTeam): static
    {
        if ($this->ledTeams->removeElement($ledTeam)) {
            // set the owning side to null (unless already changed)
            if ($ledTeam->getLeader() === $this) {
                $ledTeam->setLeader(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getTeamMemberships(): Collection
    {
        return $this->teamMemberships;
    }

    public function addTeamMembership(TeamMember $teamMembership): static
    {
        if (!$this->teamMemberships->contains($teamMembership)) {
            $this->teamMemberships->add($teamMembership);
            $teamMembership->setUser($this);
        }

        return $this;
    }

    public function removeTeamMembership(TeamMember $teamMembership): static
    {
        if ($this->teamMemberships->removeElement($teamMembership)) {
            // set the owning side to null (unless already changed)
            if ($teamMembership->getUser() === $this) {
                $teamMembership->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Department>
     */
    public function getManagedDepartments(): Collection
    {
        return $this->managedDepartments;
    }

    public function addManagedDepartment(Department $managedDepartment): static
    {
        if (!$this->managedDepartments->contains($managedDepartment)) {
            $this->managedDepartments->add($managedDepartment);
            $managedDepartment->setManager($this);
        }

        return $this;
    }

    public function removeManagedDepartment(Department $managedDepartment): static
    {
        if ($this->managedDepartments->removeElement($managedDepartment)) {
            // set the owning side to null (unless already changed)
            if ($managedDepartment->getManager() === $this) {
                $managedDepartment->setManager(null);
            }
        }

        return $this;
    }

    /**
     * Méthodes utilitaires pour les rôles
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function addRole(string $role): static
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(string $role): static
    {
        if (($key = array_search($role, $this->roles)) !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('ROLE_ADMIN');
    }

    public function isManager(): bool
    {
        return $this->hasRole('ROLE_MANAGER') || $this->isAdmin();
    }

    public function isEmployee(): bool
    {
        return $this->hasRole('ROLE_EMPLOYEE') || $this->isManager();
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