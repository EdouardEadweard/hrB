<?php

namespace App\Entity;

use App\Repository\HolidayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: HolidayRepository::class)]
#[ORM\Table(name: 'holiday')]
#[UniqueEntity(
    fields: ['name', 'date'],
    message: 'Un jour férié avec ce nom existe déjà pour cette date'
)]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom du jour férié est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire')]
    #[Assert\Type(type: '\DateTimeInterface', message: 'La date doit être une date valide')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'is_recurring', type: Types::BOOLEAN)]
    private bool $isRecurring = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
        $this->isRecurring = false;
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;

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

    /**
     * Vérifie si le jour férié tombe sur une date donnée
     */
    public function isOnDate(\DateTimeInterface $checkDate): bool
    {
        if ($this->isRecurring) {
            // Pour les jours récurrents, on compare seulement le mois et le jour
            return $this->date->format('m-d') === $checkDate->format('m-d');
        }
        
        // Pour les jours non récurrents, on compare la date complète
        return $this->date->format('Y-m-d') === $checkDate->format('Y-m-d');
    }

    /**
     * Vérifie si le jour férié est valide pour une année donnée
     */
    public function isValidForYear(int $year): bool
    {
        if (!$this->isRecurring) {
            return $this->date->format('Y') == $year;
        }
        
        return true; // Les jours récurrents sont valides pour toutes les années
    }

    /**
     * Retourne la date du jour férié pour une année donnée (utile pour les récurrents)
     */
    public function getDateForYear(int $year): ?\DateTimeInterface
    {
        if (!$this->isRecurring) {
            return $this->date;
        }
        
        try {
            $newDate = new \DateTime();
            $newDate->setDate($year, (int)$this->date->format('m'), (int)$this->date->format('d'));
            return $newDate;
        } catch (\Exception $e) {
            // En cas d'erreur (par exemple, 29 février sur une année non bissextile)
            return null;
        }
    }

    /**
     * Vérifie si le jour férié est passé pour l'année courante
     */
    public function isPastForCurrentYear(): bool
    {
        $currentYear = (int)date('Y');
        $holidayDate = $this->getDateForYear($currentYear);
        
        if (!$holidayDate) {
            return true;
        }
        
        return $holidayDate < new \DateTime();
    }

    /**
     * Vérifie si le jour férié est aujourd'hui
     */
    public function isToday(): bool
    {
        $today = new \DateTime();
        return $this->isOnDate($today);
    }

    /**
     * Vérifie si le jour férié tombe un week-end
     */
    public function isOnWeekend(): bool
    {
        $dayOfWeek = (int)$this->date->format('w'); // 0 = dimanche, 6 = samedi
        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }

    /**
     * Retourne le nom du jour de la semaine
     */
    public function getDayOfWeekName(): string
    {
        $days = [
            'dimanche', 'lundi', 'mardi', 'mercredi',
            'jeudi', 'vendredi', 'samedi'
        ];
        
        return $days[(int)$this->date->format('w')];
    }

    /**
     * Retourne les jours fériés français par défaut
     */
    public static function getDefaultFrenchHolidays(): array
    {
        return [
            [
                'name' => 'Nouvel An',
                'date' => '01-01',
                'isRecurring' => true,
                'description' => 'Premier jour de l\'année'
            ],
            [
                'name' => 'Fête du Travail',
                'date' => '05-01',
                'isRecurring' => true,
                'description' => 'Fête internationale des travailleurs'
            ],
            [
                'name' => 'Victoire 1945',
                'date' => '05-08',
                'isRecurring' => true,
                'description' => 'Commémoration de la victoire du 8 mai 1945'
            ],
            [
                'name' => 'Fête Nationale',
                'date' => '07-14',
                'isRecurring' => true,
                'description' => 'Fête nationale française'
            ],
            [
                'name' => 'Assomption',
                'date' => '08-15',
                'isRecurring' => true,
                'description' => 'Assomption de Marie'
            ],
            [
                'name' => 'Toussaint',
                'date' => '11-01',
                'isRecurring' => true,
                'description' => 'Fête de la Toussaint'
            ],
            [
                'name' => 'Armistice 1918',
                'date' => '11-11',
                'isRecurring' => true,
                'description' => 'Commémoration de l\'armistice du 11 novembre 1918'
            ],
            [
                'name' => 'Noël',
                'date' => '12-25',
                'isRecurring' => true,
                'description' => 'Fête de Noël'
            ]
        ];
    }

    /**
     * Retourne la couleur d'affichage selon le type de jour férié
     */
    public function getDisplayColor(): string
    {
        if ($this->isOnWeekend()) {
            return '#ff6b6b'; // Rouge pour les week-ends
        }
        
        if ($this->isRecurring) {
            return '#4ecdc4'; // Turquoise pour les récurrents
        }
        
        return '#45b7d1'; // Bleu pour les jours spéciaux
    }

    /**
     * Retourne le type de jour férié pour l'affichage
     */
    public function getTypeLabel(): string
    {
        return $this->isRecurring ? 'Récurrent' : 'Ponctuel';
    }

    /**
     * Retourne le statut pour l'affichage
     */
    public function getStatusLabel(): string
    {
        return $this->isActive ? 'Actif' : 'Inactif';
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->name ?? '',
            $this->date?->format('d/m/Y') ?? '',
            $this->getTypeLabel()
        );
    }
}