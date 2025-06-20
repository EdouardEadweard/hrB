<?php

namespace App\Controller\Admin;

use App\Entity\Holiday;
use App\Form\Admin\HolidayType;
use App\Repository\HolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/holiday')]
#[IsGranted('ROLE_ADMIN')]
class HolidayController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HolidayRepository $holidayRepository
    ) {
    }

    /**
     * Liste tous les jours fériés
     */
    #[Route('/', name: 'admin_holiday_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $year = $request->query->get('year', date('Y'));
        $status = $request->query->get('status', 'all');
        
        $queryBuilder = $this->holidayRepository->createQueryBuilder('h');
        
        // Filtrer par année
        if ($year !== 'all') {
            $queryBuilder->andWhere('h.date >= :startDate AND h.date <= :endDate')
                ->setParameter('startDate', new \DateTime($year . '-01-01'))
                ->setParameter('endDate', new \DateTime($year . '-12-31'));
        }
        
        // Filtrer par statut
        if ($status === 'active') {
            $queryBuilder->andWhere('h.isActive = :active')
                ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $queryBuilder->andWhere('h.isActive = :active')
                ->setParameter('active', false);
        }
        
        $holidays = $queryBuilder->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Récupérer les années disponibles pour le filtre
        $availableYears = $this->holidayRepository->createQueryBuilder('h')
            ->select('DISTINCT h.date')
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();

            // Puis extraire les années en PHP :
        $years = array_unique(array_map(function($holiday) {
            return $holiday['date']->format('Y');
        }, $availableYears));
        rsort($years);

        return $this->render('admin/holiday/index.html.twig', [
            'holidays' => $holidays,
            'current_year' => $year,
            'current_status' => $status,
            'available_years' => array_column($availableYears, 'year'),
        ]);
    }

    /**
     * Affiche les détails d'un jour férié
     */
    #[Route('/{id}', name: 'admin_holiday_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Holiday $holiday): Response
    {
        // Calculer les occurrences futures si récurrent
        $futureOccurrences = [];
        if ($holiday->isRecurring()) {
            $currentYear = (int)date('Y');
            for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                $futureDate = new \DateTime($year . '-' . $holiday->getDate()->format('m-d'));
                if ($futureDate > new \DateTime()) {
                    $futureOccurrences[] = $futureDate;
                }
            }
        }

        return $this->render('admin/holiday/show.html.twig', [
            'holiday' => $holiday,
            'future_occurrences' => $futureOccurrences,
        ]);
    }

    /**
     * Formulaire de création d'un nouveau jour férié
     */
    #[Route('/new', name: 'admin_holiday_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $holiday = new Holiday();
        $form = $this->createForm(HolidayType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier s'il n'existe pas déjà un jour férié à cette date
            $existingHoliday = $this->holidayRepository->findOneBy([
                'date' => $holiday->getDate(),
                'isActive' => true
            ]);

            if ($existingHoliday) {
                $this->addFlash('error', 'Un jour férié existe déjà pour cette date : ' . $existingHoliday->getName());
                return $this->render('admin/holiday/new.html.twig', [
                    'holiday' => $holiday,
                    'form' => $form,
                ]);
            }

            $holiday->setCreatedAt(new \DateTime());
            $holiday->setIsActive(true);
            
            $this->entityManager->persist($holiday);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le jour férié a été créé avec succès.');

            return $this->redirectToRoute('admin_holiday_index');
        }

        return $this->render('admin/holiday/new.html.twig', [
            'holiday' => $holiday,
            'form' => $form,
        ]);
    }

    /**
     * Formulaire de modification d'un jour férié
     */
    #[Route('/{id}/edit', name: 'admin_holiday_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Holiday $holiday): Response
    {
        $originalDate = clone $holiday->getDate();
        
        $form = $this->createForm(HolidayType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier s'il n'existe pas déjà un jour férié à cette nouvelle date (si changée)
            if ($originalDate != $holiday->getDate()) {
                $existingHoliday = $this->holidayRepository->findOneBy([
                    'date' => $holiday->getDate(),
                    'isActive' => true
                ]);

                if ($existingHoliday && $existingHoliday->getId() !== $holiday->getId()) {
                    $this->addFlash('error', 'Un jour férié existe déjà pour cette date : ' . $existingHoliday->getName());
                    return $this->render('admin/holiday/edit.html.twig', [
                        'holiday' => $holiday,
                        'form' => $form,
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Le jour férié a été modifié avec succès.');

            return $this->redirectToRoute('admin_holiday_show', ['id' => $holiday->getId()]);
        }

        return $this->render('admin/holiday/edit.html.twig', [
            'holiday' => $holiday,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'un jour férié (soft delete - désactivation)
     */
    #[Route('/{id}/delete', name: 'admin_holiday_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Holiday $holiday): Response
    {
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete' . $holiday->getId(), $request->request->get('_token'))) {
            
            // Vérifier si le jour férié est dans le futur ou en cours
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            
            if ($holiday->getDate() >= $today) {
                $this->addFlash('warning', 'Attention : vous désactivez un jour férié futur ou en cours.');
            }

            // Soft delete - désactiver le jour férié
            $holiday->setIsActive(false);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le jour férié a été désactivé avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_holiday_index');
    }

    /**
     * Réactivation d'un jour férié
     */
    #[Route('/{id}/activate', name: 'admin_holiday_activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(Request $request, Holiday $holiday): Response
    {
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('activate' . $holiday->getId(), $request->request->get('_token'))) {
            
            // Vérifier s'il n'existe pas déjà un jour férié actif à cette date
            $existingHoliday = $this->holidayRepository->findOneBy([
                'date' => $holiday->getDate(),
                'isActive' => true
            ]);

            if ($existingHoliday && $existingHoliday->getId() !== $holiday->getId()) {
                $this->addFlash('error', 'Impossible de réactiver ce jour férié car un autre jour férié actif existe déjà pour cette date : ' . $existingHoliday->getName());
                return $this->redirectToRoute('admin_holiday_show', ['id' => $holiday->getId()]);
            }

            $holiday->setIsActive(true);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le jour férié a été réactivé avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_holiday_show', ['id' => $holiday->getId()]);
    }

    /**
     * Calendrier des jours fériés
     */
    #[Route('/calendar/{year}', name: 'admin_holiday_calendar', methods: ['GET'])]
    public function calendar(int $year = null): Response
    {
        if ($year === null) {
            $year = (int)date('Y');
        }

        // Récupérer tous les jours fériés de l'année
        $holidays = $this->holidayRepository->createQueryBuilder('h')
            ->where('h.date >= :startDate AND h.date <= :endDate')
            ->andWhere('h.isActive = :active')
            ->setParameter('startDate', new \DateTime($year . '-01-01'))
            ->setParameter('endDate', new \DateTime($year . '-12-31'))
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Organiser les jours fériés par mois
        $holidaysByMonth = [];
        foreach ($holidays as $holiday) {
            $month = $holiday->getDate()->format('n');
            $holidaysByMonth[$month][] = $holiday;
        }

        return $this->render('admin/holiday/calendar.html.twig', [
            'year' => $year,
            'holidays' => $holidays,
            'holidays_by_month' => $holidaysByMonth,
            'months' => [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ]
        ]);
    }

    /**
     * Génération automatique des jours fériés récurrents
     */
    #[Route('/generate/{year}', name: 'admin_holiday_generate', methods: ['GET', 'POST'])]
    public function generateRecurring(Request $request, int $year): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('generate' . $year, $request->request->get('_token'))) {
            
            // Récupérer tous les jours fériés récurrents
            $recurringHolidays = $this->holidayRepository->findBy([
                'isRecurring' => true,
                'isActive' => true
            ]);

            $generated = 0;
            $skipped = 0;

            foreach ($recurringHolidays as $recurringHoliday) {
                // Créer la date pour l'année demandée
                $newDate = new \DateTime($year . '-' . $recurringHoliday->getDate()->format('m-d'));
                
                // Vérifier si ce jour férié n'existe pas déjà pour cette année
                $existingHoliday = $this->holidayRepository->findOneBy([
                    'date' => $newDate,
                    'isActive' => true
                ]);

                if (!$existingHoliday) {
                    $newHoliday = new Holiday();
                    $newHoliday->setName($recurringHoliday->getName());
                    $newHoliday->setDate($newDate);
                    $newHoliday->setIsRecurring(true);
                    $newHoliday->setDescription($recurringHoliday->getDescription());
                    $newHoliday->setIsActive(true);
                    $newHoliday->setCreatedAt(new \DateTime());

                    $this->entityManager->persist($newHoliday);
                    $generated++;
                } else {
                    $skipped++;
                }
            }

            if ($generated > 0) {
                $this->entityManager->flush();
                $this->addFlash('success', "$generated jour(s) férié(s) généré(s) pour l'année $year.");
            }

            if ($skipped > 0) {
                $this->addFlash('info', "$skipped jour(s) férié(s) ignoré(s) car déjà existant(s).");
            }

            if ($generated === 0 && $skipped === 0) {
                $this->addFlash('info', 'Aucun jour férié récurrent trouvé à générer.');
            }

            return $this->redirectToRoute('admin_holiday_calendar', ['year' => $year]);
        }

        $recurringHolidays = $this->holidayRepository->findBy([
            'isRecurring' => true,
            'isActive' => true
        ], ['date' => 'ASC']);

        return $this->render('admin/holiday/generate.html.twig', [
            'year' => $year,
            'recurring_holidays' => $recurringHolidays,
        ]);
    }

    /**
     * Export des jours fériés en CSV
     */
    #[Route('/export/{year}', name: 'admin_holiday_export', methods: ['GET'])]
    public function export(int $year = null): Response
    {
        if ($year === null) {
            $year = (int)date('Y');
        }

        $holidays = $this->holidayRepository->createQueryBuilder('h')
            ->where('h.date >= :startDate AND h.date <= :endDate')
            ->setParameter('startDate', new \DateTime($year . '-01-01'))
            ->setParameter('endDate', new \DateTime($year . '-12-31'))
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="holidays_' . $year . '.csv"');

        $output = fopen('php://temp', 'r+');
        
        // En-têtes CSV
        fputcsv($output, [
            'ID',
            'Nom',
            'Date',
            'Jour de la semaine',
            'Récurrent',
            'Description',
            'Statut',
            'Date de création'
        ]);

        // Données
        foreach ($holidays as $holiday) {
            $dayOfWeek = [
                'Sunday' => 'Dimanche',
                'Monday' => 'Lundi',
                'Tuesday' => 'Mardi',
                'Wednesday' => 'Mercredi',
                'Thursday' => 'Jeudi',
                'Friday' => 'Vendredi',
                'Saturday' => 'Samedi'
            ];

            fputcsv($output, [
                $holiday->getId(),
                $holiday->getName(),
                $holiday->getDate()->format('d/m/Y'),
                $dayOfWeek[$holiday->getDate()->format('l')],
                $holiday->isRecurring() ? 'Oui' : 'Non',
                $holiday->getDescription(),
                $holiday->isActive() ? 'Actif' : 'Inactif',
                $holiday->getCreatedAt() ? $holiday->getCreatedAt()->format('d/m/Y H:i') : ''
            ]);
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    /**
     * Recherche de jours fériés
     */
    #[Route('/search', name: 'admin_holiday_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $month = $request->query->get('month', '');
        $recurring = $request->query->get('recurring', '');
        $isActive = $request->query->get('active', '');
        
        $holidays = [];
        
        if (!empty($query) || !empty($month) || $recurring !== '' || $isActive !== '') {
            $queryBuilder = $this->holidayRepository->createQueryBuilder('h');

            if (!empty($query)) {
                $queryBuilder->andWhere('h.name LIKE :query OR h.description LIKE :query')
                    ->setParameter('query', '%' . $query . '%');
            }

            if (!empty($month)) {
                $queryBuilder->andWhere('MONTH(h.date) = :month')
                    ->setParameter('month', (int)$month);
            }

            if ($recurring !== '') {
                $queryBuilder->andWhere('h.isRecurring = :recurring')
                    ->setParameter('recurring', $recurring === '1');
            }

            if ($isActive !== '') {
                $queryBuilder->andWhere('h.isActive = :isActive')
                    ->setParameter('isActive', $isActive === '1');
            }

            $holidays = $queryBuilder->orderBy('h.date', 'ASC')->getQuery()->getResult();
        }

        return $this->render('admin/holiday/search.html.twig', [
            'holidays' => $holidays,
            'query' => $query,
            'selected_month' => $month,
            'selected_recurring' => $recurring,
            'selected_active' => $isActive,
            'months' => [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ]
        ]);
    }
}