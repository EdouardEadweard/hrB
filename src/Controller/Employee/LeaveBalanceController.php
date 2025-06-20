<?php

namespace App\Controller\Employee;

use App\Entity\LeaveBalance;
use App\Entity\LeaveType;
use App\Repository\LeaveBalanceRepository;
use App\Repository\LeaveTypeRepository;
use App\Repository\LeaveRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/leave-balance')]
#[IsGranted('ROLE_EMPLOYEE')]
class LeaveBalanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LeaveBalanceRepository $leaveBalanceRepository,
        private LeaveTypeRepository $leaveTypeRepository,
        private LeaveRequestRepository $leaveRequestRepository
    ) {
    }

    #[Route('/', name: 'app_employee_leave_balance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $currentYear = $request->query->get('year', date('Y'));
        
        // Récupérer tous les soldes de l'employé pour l'année sélectionnée
        $leaveBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $currentYear
        ]);

        // Initialiser les soldes manquants si nécessaire
        $this->initializeMissingBalances($user, (int) $currentYear);

        // Récupérer à nouveau après initialisation
        $leaveBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $currentYear
        ]);

        // Calculer les statistiques globales
        $totalDaysAllowed = 0;
        $totalDaysUsed = 0;
        $totalDaysRemaining = 0;

        foreach ($leaveBalances as $balance) {
            $totalDaysAllowed += $balance->getTotalDays();
            $totalDaysUsed += $balance->getUsedDays();
            $totalDaysRemaining += $balance->getRemainingDays();
        }

        // Récupérer les années disponibles pour le filtre
        $availableYears = $this->getAvailableYears($user);

        return $this->render('employee/leave_balance/index.html.twig', [
        'leaveBalances' => $leaveBalances,        // ← Changé de 'leave_balances' à 'leaveBalances'
        'selectedYear' => $currentYear,           // ← Ajout de cette variable aussi utilisée dans le template
        'totalDays' => $totalDaysAllowed,         // ← Renommé pour correspondre au template
        'usedDays' => $totalDaysUsed,            // ← Renommé pour correspondre au template
        'remainingDays' => $totalDaysRemaining,   // ← Renommé pour correspondre au template
        'current_year' => $currentYear,
        'available_years' => $availableYears,
        'total_days_allowed' => $totalDaysAllowed,
        'total_days_used' => $totalDaysUsed,
        'total_days_remaining' => $totalDaysRemaining,
]);
    }

    #[Route('/{id}', name: 'app_employee_leave_balance_show', methods: ['GET'])]
    public function show(LeaveBalance $leaveBalance, Request $request): Response
    {
        // Vérifier que l'employé ne peut voir que ses propres soldes
        if ($leaveBalance->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce solde.');
        }

        // Récupérer l'historique des demandes pour ce type de congé et cette année
        $leaveRequests = $this->leaveRequestRepository->findLeaveRequestsByEmployeeTypeAndYear(
            $this->getUser(),
            $leaveBalance->getLeaveType(),
            $leaveBalance->getYear()
        );

        // Calculer les détails par mois
        $monthlyDetails = $this->calculateMonthlyUsage(
            $leaveRequests,
            $leaveBalance->getYear()
        );

        return $this->render('employee/leave_balance/show.html.twig', [
            'leave_balance' => $leaveBalance,
            'leave_requests' => $leaveRequests,
            'monthly_details' => $monthlyDetails,
        ]);
    }

    #[Route('/summary', name: 'app_employee_leave_balance_summary', methods: ['GET'])]
    public function summary(): Response
    {
        $user = $this->getUser();
        $currentYear = (int) date('Y');
        
        // Récupérer le résumé des soldes pour l'année en cours
        $currentYearBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $currentYear
        ]);

        // Récupérer les soldes de l'année précédente pour comparaison
        $previousYear = $currentYear - 1;
        $previousYearBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $previousYear
        ]);

        // Créer un tableau de comparaison
        $comparison = [];
        foreach ($currentYearBalances as $currentBalance) {
            $leaveTypeId = $currentBalance->getLeaveType()->getId();
            $previousBalance = null;
            
            foreach ($previousYearBalances as $prevBalance) {
                if ($prevBalance->getLeaveType()->getId() === $leaveTypeId) {
                    $previousBalance = $prevBalance;
                    break;
                }
            }

            $comparison[] = [
                'leave_type' => $currentBalance->getLeaveType(),
                'current' => $currentBalance,
                'previous' => $previousBalance,
                'usage_trend' => $this->calculateUsageTrend($currentBalance, $previousBalance),
            ];
        }

        // Calculer les prochaines échéances (demandes approuvées à venir)
        $upcomingLeaves = $this->leaveRequestRepository->findUpcomingApprovedLeaves($user);

        return $this->render('employee/leave_balance/summary.html.twig', [
            'comparison' => $comparison,
            'current_year' => $currentYear,
            'previous_year' => $previousYear,
            'upcoming_leaves' => $upcomingLeaves,
        ]);
    }

    #[Route('/refresh', name: 'app_employee_leave_balance_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $user = $this->getUser();
        $year = $request->request->get('year', date('Y'));
        
        if ($this->isCsrfTokenValid('refresh_balance', $request->request->get('_token'))) {
            // Recalculer tous les soldes pour l'année donnée
            $this->recalculateBalances($user, (int) $year);
            
            $this->addFlash('success', 'Les soldes ont été mis à jour avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_employee_leave_balance_index', ['year' => $year]);
    }

    #[Route('/export/{year}', name: 'app_employee_leave_balance_export', methods: ['GET'])]
    public function export(int $year): Response
    {
        $user = $this->getUser();
        
        // Récupérer tous les soldes pour l'année
        $leaveBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $year
        ]);

        // Récupérer toutes les demandes de l'année
        $leaveRequests = $this->leaveRequestRepository->findBy([
            'employee' => $user
        ]);

        // Filtrer les demandes par année
        $yearRequests = array_filter($leaveRequests, function($request) use ($year) {
            return $request->getStartDate()->format('Y') == $year;
        });

        // Créer le contenu CSV
        $csvContent = $this->generateCSVContent($leaveBalances, $yearRequests, $year);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="soldes_conges_' . $year . '.csv"');

        return $response;
    }

    /**
     * Initialiser les soldes manquants pour un employé
     */
    private function initializeMissingBalances($user, int $year): void
    {
        $leaveTypes = $this->leaveTypeRepository->findBy(['isActive' => true]);
        $existingBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $year
        ]);

        $existingTypeIds = array_map(function($balance) {
            return $balance->getLeaveType()->getId();
        }, $existingBalances);

        foreach ($leaveTypes as $leaveType) {
            if (!in_array($leaveType->getId(), $existingTypeIds)) {
                $balance = new LeaveBalance();
                $balance->setEmployee($user);
                $balance->setLeaveType($leaveType);
                $balance->setYear($year);
                $balance->setTotalDays($leaveType->getMaxDaysPerYear());
                $balance->setUsedDays(0);
                $balance->setRemainingDays($leaveType->getMaxDaysPerYear());
                $balance->setLastUpdated(new \DateTime());

                $this->entityManager->persist($balance);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Récupérer les années disponibles pour l'employé
     */
    private function getAvailableYears($user): array
    {
        $years = [];
        $currentYear = (int) date('Y');
        $hireYear = $user->getHireDate() ? (int) $user->getHireDate()->format('Y') : $currentYear;
        
        // De l'année d'embauche à l'année prochaine
        for ($year = $hireYear; $year <= $currentYear + 1; $year++) {
            $years[] = $year;
        }

        return array_reverse($years); // Plus récent en premier
    }

    /**
     * Calculer l'utilisation mensuelle
     */
    private function calculateMonthlyUsage(array $leaveRequests, int $year): array
    {
        $monthlyUsage = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthlyUsage[$month] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'days_used' => 0,
                'requests' => []
            ];
        }

        foreach ($leaveRequests as $request) {
            if ($request->getStatus() === 'approved' && 
                $request->getStartDate()->format('Y') == $year) {
                
                $month = (int) $request->getStartDate()->format('n');
                $monthlyUsage[$month]['days_used'] += $request->getNumberOfDays();
                $monthlyUsage[$month]['requests'][] = $request;
            }
        }

        return $monthlyUsage;
    }

    /**
     * Calculer la tendance d'utilisation
     */
    private function calculateUsageTrend(?LeaveBalance $current, ?LeaveBalance $previous): string
    {
        if (!$current || !$previous) {
            return 'no_data';
        }

        $currentUsagePercent = ($current->getTotalDays() > 0) 
            ? ($current->getUsedDays() / $current->getTotalDays()) * 100 
            : 0;
        
        $previousUsagePercent = ($previous->getTotalDays() > 0) 
            ? ($previous->getUsedDays() / $previous->getTotalDays()) * 100 
            : 0;

        $difference = $currentUsagePercent - $previousUsagePercent;

        if ($difference > 10) {
            return 'increasing';
        } elseif ($difference < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Recalculer les soldes pour un employé et une année donnée
     */
    private function recalculateBalances($user, int $year): void
    {
        $leaveBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user,
            'year' => $year
        ]);

        foreach ($leaveBalances as $balance) {
            // Calculer les jours utilisés à partir des demandes approuvées
            $usedDays = $this->leaveRequestRepository->calculateUsedDaysByEmployeeTypeAndYear(
                $user,
                $balance->getLeaveType(),
                $year
            );

            $balance->setUsedDays($usedDays);
            $balance->setRemainingDays($balance->getTotalDays() - $usedDays);
            $balance->setLastUpdated(new \DateTime());
        }

        $this->entityManager->flush();
    }

    /**
     * Générer le contenu CSV pour l'export
     */
    private function generateCSVContent(array $balances, array $requests, int $year): string
    {
        $csv = [];
        
        // En-têtes
        $csv[] = [
            'Type de congé',
            'Jours alloués',
            'Jours utilisés',
            'Jours restants',
            'Pourcentage utilisé',
            'Dernière mise à jour'
        ];

        // Données des soldes
        foreach ($balances as $balance) {
            $percentage = ($balance->getTotalDays() > 0) 
                ? round(($balance->getUsedDays() / $balance->getTotalDays()) * 100, 1) 
                : 0;

            $csv[] = [
                $balance->getLeaveType()->getName(),
                $balance->getTotalDays(),
                $balance->getUsedDays(),
                $balance->getRemainingDays(),
                $percentage . '%',
                $balance->getLastUpdated()->format('d/m/Y H:i')
            ];
        }

        // Ligne vide
        $csv[] = [];
        
        // Détail des demandes
        $csv[] = ['Détail des demandes ' . $year];
        $csv[] = [
            'Date début',
            'Date fin',
            'Type',
            'Jours',
            'Statut',
            'Motif'
        ];

        foreach ($requests as $request) {
            $csv[] = [
                $request->getStartDate()->format('d/m/Y'),
                $request->getEndDate()->format('d/m/Y'),
                $request->getLeaveType()->getName(),
                $request->getNumberOfDays(),
                $request->getStatus(),
                $request->getReason()
            ];
        }

        // Convertir en CSV
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(';', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return $output;
    }
}