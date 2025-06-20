<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\LeaveRequest;
use App\Entity\Attendance;
use App\Entity\Notification;
use App\Entity\Holiday;
use App\Repository\UserRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\AttendanceRepository;
use App\Repository\NotificationRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveBalanceRepository;
use App\Repository\DepartmentRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private LeaveRequestRepository $leaveRequestRepository;
    private AttendanceRepository $attendanceRepository;
    private NotificationRepository $notificationRepository;
    private HolidayRepository $holidayRepository;
    private LeaveBalanceRepository $leaveBalanceRepository;
    private DepartmentRepository $departmentRepository;
    private TeamRepository $teamRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        LeaveRequestRepository $leaveRequestRepository,
        AttendanceRepository $attendanceRepository,
        NotificationRepository $notificationRepository,
        HolidayRepository $holidayRepository,
        LeaveBalanceRepository $leaveBalanceRepository,
        DepartmentRepository $departmentRepository,
        TeamRepository $teamRepository
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->leaveRequestRepository = $leaveRequestRepository;
        $this->attendanceRepository = $attendanceRepository;
        $this->notificationRepository = $notificationRepository;
        $this->holidayRepository = $holidayRepository;
        $this->leaveBalanceRepository = $leaveBalanceRepository;
        $this->departmentRepository = $departmentRepository;
        $this->teamRepository = $teamRepository;
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $userRoles = $currentUser->getRoles();

        // Données communes pour tous les utilisateurs
        $commonData = $this->getCommonDashboardData($currentUser);

        // Données spécifiques selon le rôle
        $specificData = [];
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $specificData = $this->getAdminDashboardData();
            $template = 'admin/dashboard.html.twig';
        } elseif (in_array('ROLE_MANAGER', $userRoles)) {
            $specificData = $this->getManagerDashboardData($currentUser);
            $template = 'manager/dashboard.html.twig';
        } else {
            $specificData = $this->getEmployeeDashboardData($currentUser);
            $template = 'employee/dashboard.html.twig';
        }

        return $this->render($template, array_merge($commonData, $specificData));
    }

    /**
     * Données communes à tous les utilisateurs pour améliorer l'UX
     */
    private function getCommonDashboardData(User $user): array
    {
        // Notifications non lues (amélioration UX : alertes visuelles)
        $unreadNotifications = $this->notificationRepository->findBy([
            'recipient' => $user,
            'isRead' => false
        ], ['createdAt' => 'DESC'], 5);

        // Jours fériés à venir (amélioration UX : planification)
        $upcomingHolidays = $this->holidayRepository->createQueryBuilder('h')
            ->where('h.date >= :today')
            ->andWhere('h.isActive = true')
            ->setParameter('today', new \DateTime())
            ->orderBy('h.date', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // Solde de congés de l'utilisateur (amélioration UX : information contextuelle)
        $leaveBalances = $this->leaveBalanceRepository->findBy([
            'employee' => $user
        ]);

        return [
            'current_user' => $user,
            'unread_notifications' => $unreadNotifications,
            'unread_notifications_count' => count($unreadNotifications),
            'upcoming_holidays' => $upcomingHolidays,
            'leave_balances' => $leaveBalances,
            'current_date' => new \DateTime(),
        ];
    }

    // Dans votre DashboardController.php, ajoutez cette méthode de vérification

/**
 * Vérification et initialisation des données manquantes
 */
private function ensureAllDataExists(array $data): array
{
    // Vérifier que toutes les variables requises existent
    $requiredVars = [
        'total_employees' => 0,
        'total_departments' => 0,
        'total_teams' => 0,
        'pending_leave_count' => 0,
        'leave_status_stats' => [
            'APPROVED' => 0,
            'PENDING' => 0,
            'REJECTED' => 0,
            'CANCELLED' => 0,
            'total' => 0
        ],
        'monthly_leaves_trends' => [],
        'advanced_attendance_stats' => [
            'current_month' => [
                'total_days' => 0,
                'avg_hours' => 0,
                'total_hours' => 0,
                'active_employees' => 0,
            ],
            'last_month' => [
                'total_days' => 0,
                'avg_hours' => 0,
                'total_hours' => 0,
            ],
            'variations' => [
                'avg_hours_variation' => 0,
                'total_hours_variation' => 0,
            ]
        ],
        'department_absenteeism_stats' => [],
        'leave_type_distribution' => [],
        'employees_with_pending_requests' => [],
    ];

    foreach ($requiredVars as $key => $defaultValue) {
        if (!isset($data[$key])) {
            $data[$key] = $defaultValue;
        }
    }

    return $data;
}

    /**
     * Données spécifiques au tableau de bord admin
     */
    private function getAdminDashboardData(): array
    {
        // Statistiques globales existantes
        $totalEmployees = $this->userRepository->count(['isActive' => true]);
        $totalDepartments = $this->departmentRepository->count(['isActive' => true]);
        $totalTeams = $this->teamRepository->count(['isActive' => true]);

        // Demandes de congés en attente existantes
        $pendingLeaveRequests = $this->leaveRequestRepository->findBy([
            'status' => 'PENDING'
        ], ['submittedAt' => 'DESC'], 10);

        // Demandes récentes existantes
        $recentLeaveRequests = $this->leaveRequestRepository->findBy(
            [],
            ['submittedAt' => 'DESC'],
            5
        );

        // === NOUVELLES ANALYTICS AJOUTÉES ===

        // 1. Statistiques des congés par statut
        $leaveStatusStats = $this->getLeaveRequestStatsByStatus();

        // 2. Tendances mensuelles des demandes de congés
        $monthlyLeavesTrends = $this->getMonthlyLeavesTrends();

        // 3. Statistiques de présence avancées
        $advancedAttendanceStats = $this->getAdvancedAttendanceStats();

        // 4. Top départements par absentéisme
        $departmentAbsenteeismStats = $this->getDepartmentAbsenteeismStats();

        // 5. Répartition des congés par type
        $leaveTypeDistribution = $this->getLeaveTypeDistribution();

        // 6. Employés avec le plus de demandes en attente
        $employeesWithPendingRequests = $this->getEmployeesWithMostPendingRequests();

        // Statistiques existantes du mois en cours
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');
        
        $monthlyAttendanceStats = $this->attendanceRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as total_records')
            ->addSelect('AVG(a.workedHours) as avg_hours')
            ->where('a.workDate >= :start_date')
            ->andWhere('a.workDate < :end_date')
            ->setParameter('start_date', $currentMonth)
            ->setParameter('end_date', $nextMonth)
            ->getQuery()
            ->getSingleResult();

        $data = [
        'role' => 'admin',
        'total_employees' => $totalEmployees,
        'total_departments' => $totalDepartments,
        'total_teams' => $totalTeams,
        'pending_leave_requests' => $pendingLeaveRequests,
        'pending_leave_count' => count($pendingLeaveRequests),
        'recent_leave_requests' => $recentLeaveRequests,
        'monthly_attendance_stats' => $monthlyAttendanceStats,
        
        // Nouvelles données analytics
        'leave_status_stats' => $leaveStatusStats,
        'monthly_leaves_trends' => $monthlyLeavesTrends,
        'advanced_attendance_stats' => $advancedAttendanceStats,
        'department_absenteeism_stats' => $departmentAbsenteeismStats,
        'leave_type_distribution' => $leaveTypeDistribution,
        'employees_with_pending_requests' => $employeesWithPendingRequests,
    ];

    return $this->ensureAllDataExists($data);
    }

    /**
     * Statistiques des demandes de congés par statut
     */
    private function getLeaveRequestStatsByStatus(): array
    {
        $stats = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('lr.status')
            ->addSelect('COUNT(lr.id) as count')
            ->groupBy('lr.status')
            ->getQuery()
            ->getResult();

        $formattedStats = [
            'PENDING' => 0,
            'APPROVED' => 0,
            'REJECTED' => 0,
            'CANCELLED' => 0
        ];

        foreach ($stats as $stat) {
            $formattedStats[$stat['status']] = (int)$stat['count'];
        }

        $formattedStats['total'] = array_sum($formattedStats);
        
        return $formattedStats;
    }

    /**
     * Tendances mensuelles des demandes de congés (6 derniers mois)
     */
    private function getMonthlyLeavesTrends(): array
    {
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $startDate = new \DateTime("first day of -{$i} months");
            $endDate = new \DateTime("last day of -{$i} months");
            
            $monthStats = $this->leaveRequestRepository->createQueryBuilder('lr')
                ->select('COUNT(lr.id) as total_requests')
                ->addSelect('SUM(CASE WHEN lr.status = :approved THEN 1 ELSE 0 END) as approved_requests')
                ->addSelect('SUM(CASE WHEN lr.status = :rejected THEN 1 ELSE 0 END) as rejected_requests')
                ->addSelect('SUM(CASE WHEN lr.status = :pending THEN 1 ELSE 0 END) as pending_requests')
                ->where('lr.submittedAt >= :start_date')
                ->andWhere('lr.submittedAt <= :end_date')
                ->setParameter('start_date', $startDate)
                ->setParameter('end_date', $endDate)
                ->setParameter('approved', 'APPROVED')
                ->setParameter('rejected', 'REJECTED')
                ->setParameter('pending', 'PENDING')
                ->getQuery()
                ->getSingleResult();

            $trends[] = [
                'month' => $startDate->format('M Y'),
                'month_key' => $startDate->format('Y-m'),
                'total_requests' => (int)$monthStats['total_requests'],
                'approved_requests' => (int)$monthStats['approved_requests'],
                'rejected_requests' => (int)$monthStats['rejected_requests'],
                'pending_requests' => (int)$monthStats['pending_requests'],
            ];
        }
        
        return $trends;
    }

    /**
     * Statistiques de présence avancées
     */
    private function getAdvancedAttendanceStats(): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
        $currentMonthEnd = new \DateTime('last day of this month');
        $lastMonthEnd = new \DateTime('last day of last month');

        // Stats mois en cours
        $currentMonthStats = $this->attendanceRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as total_days')
            ->addSelect('AVG(a.workedHours) as avg_hours')
            ->addSelect('SUM(a.workedHours) as total_hours')
            ->addSelect('COUNT(DISTINCT a.employee) as active_employees')
            ->where('a.workDate >= :start_date')
            ->andWhere('a.workDate <= :end_date')
            ->setParameter('start_date', $currentMonth)
            ->setParameter('end_date', $currentMonthEnd)
            ->getQuery()
            ->getSingleResult();

        // Stats mois précédent
        $lastMonthStats = $this->attendanceRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as total_days')
            ->addSelect('AVG(a.workedHours) as avg_hours')
            ->addSelect('SUM(a.workedHours) as total_hours')
            ->where('a.workDate >= :start_date')
            ->andWhere('a.workDate <= :end_date')
            ->setParameter('start_date', $lastMonth)
            ->setParameter('end_date', $lastMonthEnd)
            ->getQuery()
            ->getSingleResult();

        // Calcul des variations
        $avgHoursVariation = $lastMonthStats['avg_hours'] > 0 
            ? (($currentMonthStats['avg_hours'] - $lastMonthStats['avg_hours']) / $lastMonthStats['avg_hours']) * 100 
            : 0;

        $totalHoursVariation = $lastMonthStats['total_hours'] > 0 
            ? (($currentMonthStats['total_hours'] - $lastMonthStats['total_hours']) / $lastMonthStats['total_hours']) * 100 
            : 0;

        return [
            'current_month' => [
                'total_days' => (int)$currentMonthStats['total_days'],
                'avg_hours' => round((float)$currentMonthStats['avg_hours'], 2),
                'total_hours' => (int)$currentMonthStats['total_hours'],
                'active_employees' => (int)$currentMonthStats['active_employees'],
            ],
            'last_month' => [
                'total_days' => (int)$lastMonthStats['total_days'],
                'avg_hours' => round((float)$lastMonthStats['avg_hours'], 2),
                'total_hours' => (int)$lastMonthStats['total_hours'],
            ],
            'variations' => [
                'avg_hours_variation' => round($avgHoursVariation, 1),
                'total_hours_variation' => round($totalHoursVariation, 1),
            ]
        ];
    }

    /**
     * Statistiques d'absentéisme par département
     */
    private function getDepartmentAbsenteeismStats(): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');

        // Récupérer tous les départements actifs
        $departments = $this->departmentRepository->findBy(['isActive' => true]);
        $stats = [];

        foreach ($departments as $department) {
            // Compter les employés du département
            $totalEmployees = $this->userRepository->count([
                'department' => $department,
                'isActive' => true
            ]);

            // Compter les congés approuvés du mois
            $approvedLeaves = $this->leaveRequestRepository->createQueryBuilder('lr')
                ->select('COUNT(lr.id) as leave_count')
                ->innerJoin('lr.employee', 'e')
                ->where('e.department = :department')
                ->andWhere('lr.status = :approved')
                ->andWhere('lr.startDate >= :start_date')
                ->andWhere('lr.startDate < :end_date')
                ->setParameter('department', $department)
                ->setParameter('approved', 'APPROVED')
                ->setParameter('start_date', $currentMonth)
                ->setParameter('end_date', $nextMonth)
                ->getQuery()
                ->getSingleResult();

            $absenteeismRate = $totalEmployees > 0 
                ? round(((int)$approvedLeaves['leave_count'] / $totalEmployees) * 100, 1)
                : 0;

            $stats[] = [
                'department_name' => $department->getName(),
                'total_employees' => $totalEmployees,
                'approved_leaves' => (int)$approvedLeaves['leave_count'],
                'absenteeism_rate' => $absenteeismRate
            ];
        }

        // Trier par taux d'absentéisme décroissant
        usort($stats, function($a, $b) {
            return $b['absenteeism_rate'] <=> $a['absenteeism_rate'];
        });

        return array_slice($stats, 0, 5); // Top 5
    }

    /**
     * Répartition des congés par type
     */
    private function getLeaveTypeDistribution(): array
    {
        // Assurez-vous d'avoir un champ 'type' dans votre entité LeaveRequest
        // Si vous n'avez pas ce champ, adaptez selon votre structure
        $distribution = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('lr.type')
            ->addSelect('COUNT(lr.id) as count')
            ->where('lr.status = :approved')
            ->andWhere('lr.startDate >= :start_date')
            ->groupBy('lr.type')
            ->setParameter('approved', 'APPROVED')
            ->setParameter('start_date', new \DateTime('-12 months'))
            ->getQuery()
            ->getResult();

        $total = array_sum(array_column($distribution, 'count'));
        
        $formattedDistribution = [];
        foreach ($distribution as $item) {
            $percentage = $total > 0 ? round(((int)$item['count'] / $total) * 100, 1) : 0;
            $formattedDistribution[] = [
                'type' => $item['type'] ?? 'Non spécifié',
                'count' => (int)$item['count'],
                'percentage' => $percentage
            ];
        }

        return $formattedDistribution;
    }

    /**
     * Employés avec le plus de demandes en attente
     */
    private function getEmployeesWithMostPendingRequests(): array
    {
        $results = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('e.firstName, e.lastName, e.email')
            ->addSelect('COUNT(lr.id) as pending_count')
            ->innerJoin('lr.employee', 'e')
            ->where('lr.status = :pending')
            ->groupBy('e.id')
            ->orderBy('pending_count', 'DESC')
            ->setParameter('pending', 'PENDING')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Données spécifiques au tableau de bord manager
     */
    private function getManagerDashboardData(User $manager): array
    {
        // Équipes gérées par le manager
        $managedTeams = $this->teamRepository->findBy(['leader' => $manager]);
        
        // Employés sous la responsabilité du manager
        $managedEmployees = $this->userRepository->findBy([
            'manager' => $manager,
            'isActive' => true
        ]);

        // Demandes de congés à approuver (amélioration UX : workflow simplifié)
        $leaveRequestsToApprove = [];
        foreach ($managedEmployees as $employee) {
            $requests = $this->leaveRequestRepository->findBy([
                'employee' => $employee,
                'status' => 'PENDING'
            ], ['submittedAt' => 'DESC']);
            $leaveRequestsToApprove = array_merge($leaveRequestsToApprove, $requests);
        }

        // Présences récentes de l'équipe (amélioration UX : suivi en temps réel)
        $teamAttendanceToday = [];
        $today = new \DateTime();
        foreach ($managedEmployees as $employee) {
            $attendance = $this->attendanceRepository->findOneBy([
                'employee' => $employee,
                'workDate' => $today
            ]);
            if ($attendance) {
                $teamAttendanceToday[] = $attendance;
            }
        }

        // Statistiques de l'équipe
        $teamStats = [
            'total_team_members' => count($managedEmployees),
            'present_today' => count($teamAttendanceToday),
            'pending_approvals' => count($leaveRequestsToApprove),
        ];

        // AJOUTEZ ces calculs pour les statistiques affichées dans le template :
    
        // Calcul des demandes approuvées ce mois
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');
        
        $approvedThisMonth = (int) $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->innerJoin('lr.employee', 'e')
            ->where('e.manager = :manager')
            ->andWhere('lr.status = :approved')
            ->andWhere('lr.submittedAt >= :start_date')
            ->andWhere('lr.submittedAt < :end_date')
            ->setParameter('manager', $manager)
            ->setParameter('approved', 'APPROVED')
            ->setParameter('start_date', $currentMonth)
            ->setParameter('end_date', $nextMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Calcul des absents aujourd'hui
        $today = new \DateTime();
        $absentToday = (int) $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('COUNT(DISTINCT lr.employee)')
            ->innerJoin('lr.employee', 'e')
            ->where('e.manager = :manager')
            ->andWhere('lr.status = :approved')
            ->andWhere(':today BETWEEN lr.startDate AND lr.endDate')
            ->setParameter('manager', $manager)
            ->setParameter('approved', 'APPROVED')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

            dump([
                'team_members_count' => count($managedEmployees),
                'pending_requests_count' => count($leaveRequestsToApprove),
                'approved_this_month' => $approvedThisMonth,
                'absent_today' => $absentToday,
            ]);

        return [
            'role' => 'manager',
            'managed_teams' => $managedTeams,
            'managed_employees' => $managedEmployees,
            'leave_requests_to_approve' => $leaveRequestsToApprove,
            'team_attendance_today' => $teamAttendanceToday,
            'team_stats' => $teamStats,
            
            // AJOUTEZ ces nouvelles variables :
            'team_members_count' => count($managedEmployees),
            'pending_requests_count' => count($leaveRequestsToApprove),
            'approved_this_month' => $approvedThisMonth,
            'absent_today' => $absentToday,
            'recent_requests' => array_slice($leaveRequestsToApprove, 0, 5), // 5 plus récentes
            'team_members' => $managedEmployees, // Pour la section équipe
        ];
    }

    /**
     * Données spécifiques au tableau de bord employé
     */
    private function getEmployeeDashboardData(User $employee): array
    {
        // Demandes de congés de l'employé (amélioration UX : suivi personnel)
        $myLeaveRequests = $this->leaveRequestRepository->findBy([
            'employee' => $employee
        ], ['submittedAt' => 'DESC'], 5);

        // Présences récentes (amélioration UX : historique personnel)
        $myRecentAttendance = $this->attendanceRepository->findBy([
            'employee' => $employee
        ], ['workDate' => 'DESC'], 7);

        // Statistiques personnelles du mois
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');
        
        $monthlyPersonalStats = $this->attendanceRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as days_worked')
            ->addSelect('SUM(a.workedHours) as total_hours')
            ->addSelect('AVG(a.workedHours) as avg_daily_hours')
            ->where('a.employee = :employee')
            ->andWhere('a.workDate >= :start_date')
            ->andWhere('a.workDate < :end_date')
            ->setParameter('employee', $employee)
            ->setParameter('start_date', $currentMonth)
            ->setParameter('end_date', $nextMonth)
            ->getQuery()
            ->getSingleResult();

        // Demandes en attente (amélioration UX : statut clair)
        $pendingRequests = $this->leaveRequestRepository->findBy([
            'employee' => $employee,
            'status' => 'PENDING'
        ]);

        return [
            'role' => 'employee',
            'my_leave_requests' => $myLeaveRequests,
            'my_recent_attendance' => $myRecentAttendance,
            'monthly_personal_stats' => $monthlyPersonalStats,
            'pending_requests' => $pendingRequests,
            'pending_requests_count' => count($pendingRequests),
        ];
    }

    /**
     * Action rapide pour marquer les notifications comme lues
     * Amélioration UX : interaction AJAX possible
     */
    #[Route('/notifications/mark-read/{id}', name: 'app_dashboard_notification_mark_read', methods: ['POST'])]
    public function markNotificationAsRead(int $id): Response
    {
        $notification = $this->notificationRepository->find($id);
        
        if (!$notification || $notification->getRecipient() !== $this->getUser()) {
            throw $this->createNotFoundException('Notification non trouvée');
        }

        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTime());
        
        $this->entityManager->flush();

        $this->addFlash('success', 'Notification marquée comme lue');
        
        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Action rapide pour marquer toutes les notifications comme lues
     * Amélioration UX : action groupée
     */
    #[Route('/notifications/mark-all-read', name: 'app_dashboard_notifications_mark_all_read', methods: ['POST'])]
    public function markAllNotificationsAsRead(): Response
    {
        $unreadNotifications = $this->notificationRepository->findBy([
            'recipient' => $this->getUser(),
            'isRead' => false
        ]);

        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues');
        
        return $this->redirectToRoute('app_dashboard');
    }
}