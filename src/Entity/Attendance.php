<?php

namespace App\Entity;

use App\Repository\AttendanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendanceRepository::class)]
#[ORM\Table(name: 'attendance')]
class Attendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'work_date', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de travail est obligatoire')]
    #[Assert\Type(type: '\DateTimeInterface', message: 'La date de travail doit être une date valide')]
    private ?\DateTimeInterface $workDate = null;

    #[ORM\Column(name: 'check_in', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\Type(type: '\DateTimeInterface', message: 'L\'heure d\'arrivée doit être une date/heure valide')]
    private ?\DateTimeInterface $checkIn = null;

    #[ORM\Column(name: 'check_out', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\Type(type: '\DateTimeInterface', message: 'L\'heure de départ doit être une date/heure valide')]
    private ?\DateTimeInterface $checkOut = null;

    #[ORM\Column(name: 'worked_hours', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le nombre d\'heures travaillées doit être positif ou zéro')]
    private ?int $workedHours = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: ['présent', 'absent', 'retard', 'départ_anticipé', 'télétravail', 'mission'],
        message: 'Le statut doit être : présent, absent, retard, départ_anticipé, télétravail ou mission'
    )]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'L\'employé est obligatoire')]
    private ?User $employee = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->workDate = new \DateTime();
        $this->status = 'présent';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkDate(): ?\DateTimeInterface
    {
        return $this->workDate;
    }

    public function setWorkDate(\DateTimeInterface $workDate): static
    {
        $this->workDate = $workDate;

        return $this;
    }

    public function getCheckIn(): ?\DateTimeInterface
    {
        return $this->checkIn;
    }

    public function setCheckIn(?\DateTimeInterface $checkIn): static
    {
        $this->checkIn = $checkIn;
        
        // Calculer automatiquement les heures travaillées si check_out existe
        if ($this->checkOut && $checkIn) {
            $this->calculateWorkedHours();
        }

        return $this;
    }

    public function getCheckOut(): ?\DateTimeInterface
    {
        return $this->checkOut;
    }

    public function setCheckOut(?\DateTimeInterface $checkOut): static
    {
        $this->checkOut = $checkOut;
        
        // Calculer automatiquement les heures travaillées si check_in existe
        if ($this->checkIn && $checkOut) {
            $this->calculateWorkedHours();
        }

        return $this;
    }

    public function getWorkedHours(): ?int
    {
        return $this->workedHours;
    }

    public function setWorkedHours(?int $workedHours): static
    {
        $this->workedHours = $workedHours;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function getEmployee(): ?User
    {
        return $this->employee;
    }

    public function setEmployee(?User $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    /**
     * Calcule automatiquement les heures travaillées en minutes
     */
    private function calculateWorkedHours(): void
    {
        if ($this->checkIn && $this->checkOut) {
            $diff = $this->checkOut->diff($this->checkIn);
            $this->workedHours = ($diff->h * 60) + $diff->i; // Stockage en minutes
        }
    }

    /**
     * Retourne les heures travaillées formatées (HH:MM)
     */
    public function getFormattedWorkedHours(): string
    {
        if ($this->workedHours === null) {
            return '00:00';
        }
        
        $hours = intval($this->workedHours / 60);
        $minutes = $this->workedHours % 60;
        
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Vérifie si l'employé est en retard (arrivée après 9h)
     */
    public function isLate(): bool
    {
        if (!$this->checkIn) {
            return false;
        }
        
        $workStart = clone $this->workDate;
        $workStart->setTime(9, 0, 0); // 9h00
        
        return $this->checkIn > $workStart;
    }

    /**
     * Vérifie si l'employé est parti en avance (départ avant 17h)
     */
    public function isEarlyLeave(): bool
    {
        if (!$this->checkOut) {
            return false;
        }
        
        $workEnd = clone $this->workDate;
        $workEnd->setTime(17, 0, 0); // 17h00
        
        return $this->checkOut < $workEnd;
    }

    /**
     * Retourne les statuts disponibles
     */
    public static function getStatusChoices(): array
    {
        return [
            'Présent' => 'présent',
            'Absent' => 'absent',
            'Retard' => 'retard',
            'Départ anticipé' => 'départ_anticipé',
            'Télétravail' => 'télétravail',
            'Mission' => 'mission'
        ];
    }

    /**
     * Retourne le libellé du statut
     */
    public function getStatusLabel(): string
    {
        $labels = array_flip(self::getStatusChoices());
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        return sprintf(
            'Présence du %s - %s (%s)',
            $this->workDate?->format('d/m/Y') ?? '',
            $this->employee?->getFirstName() . ' ' . $this->employee?->getLastName() ?? 'Employé inconnu',
            $this->getStatusLabel()
        );
    }
}