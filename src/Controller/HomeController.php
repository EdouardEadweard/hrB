<?php

namespace App\Controller;

use App\Repository\LeaveRequestRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Repository\DepartmentRepository;
use App\Repository\AttendanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    private LeaveRequestRepository $leaveRequestRepository;
    private NotificationRepository $notificationRepository;
    private UserRepository $userRepository;
    private DepartmentRepository $departmentRepository;
    private AttendanceRepository $attendanceRepository;

    public function __construct(
        LeaveRequestRepository $leaveRequestRepository,
        NotificationRepository $notificationRepository,
        UserRepository $userRepository,
        DepartmentRepository $departmentRepository,
        AttendanceRepository $attendanceRepository
    ) {
        $this->leaveRequestRepository = $leaveRequestRepository;
        $this->notificationRepository = $notificationRepository;
        $this->userRepository = $userRepository;
        $this->departmentRepository = $departmentRepository;
        $this->attendanceRepository = $attendanceRepository;
    }

    /**
     * Page d'accueil publique
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Si l'utilisateur est connecté, le rediriger vers son tableau de bord
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Sinon, afficher la page d'accueil publique
        return $this->render('home/index.html.twig', [
            'page_title' => 'HR Flow - Gestion des Ressources Humaines',
            'welcome_message' => 'Bienvenue sur HR Flow, votre solution de gestion des congés et absences'
        ]);
    }

    /**
     * Tableau de bord principal (redirection selon le rôle)
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        $roles = $user->getRoles();

        // Redirection selon le rôle principal de l'utilisateur
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->redirectToRoute('admin_dashboard');
        } elseif (in_array('ROLE_MANAGER', $roles)) {
            return $this->redirectToRoute('manager_dashboard');
        } else {
            return $this->redirectToRoute('employee_dashboard');
        }
    }

    /**
     * Tableau de bord administrateur
     */
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        // Récupérer les statistiques générales pour l'admin
        $totalUsers = $this->userRepository->count(['isActive' => true]);
        $totalDepartments = $this->departmentRepository->count(['isActive' => true]);
        $pendingLeaveRequests = $this->leaveRequestRepository->count(['status' => 'pending']);
        $todayAttendance = $this->attendanceRepository->findBy([
            'workDate' => new \DateTime('today')
        ]);

        // Récupérer les demandes récentes
        $recentLeaveRequests = $this->leaveRequestRepository->findBy(
            [],
            ['submittedAt' => 'DESC'],
            5
        );

        // Récupérer les notifications non lues
        $unreadNotifications = $this->notificationRepository->findBy([
            'recipient' => $this->getUser(),
            'isRead' => false
        ], ['createdAt' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'page_title' => 'Tableau de bord - Administration',
            'total_users' => $totalUsers,
            'total_departments' => $totalDepartments,
            'pending_leave_requests' => $pendingLeaveRequests,
            'today_attendance_count' => count($todayAttendance),
            'recent_leave_requests' => $recentLeaveRequests,
            'unread_notifications' => $unreadNotifications,
            'user' => $this->getUser()
        ]);
    }

    /**
     * Tableau de bord manager
     */
    #[Route('/manager/dashboard', name: 'manager_dashboard')]
    #[IsGranted('ROLE_MANAGER')]
    public function managerDashboard(): Response
    {
        $user = $this->getUser();

        // Récupérer les employés sous la responsabilité du manager
        $managedEmployees = $this->userRepository->findBy([
            'manager' => $user,
            'isActive' => true
        ]);

        // Récupérer les demandes de congés en attente pour ses employés
        $pendingApprovals = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->where('e.manager = :manager')
            ->andWhere('lr.status = :status')
            ->setParameter('manager', $user)
            ->setParameter('status', 'pending')
            ->orderBy('lr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Récupérer les demandes récentes de son équipe
        $recentTeamRequests = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->where('e.manager = :manager')
            ->setParameter('manager', $user)
            ->orderBy('lr.submittedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Récupérer les notifications non lues
        $unreadNotifications = $this->notificationRepository->findBy([
            'recipient' => $user,
            'isRead' => false
        ], ['createdAt' => 'DESC'], 5);

        // Présences d'aujourd'hui pour l'équipe
        $todayTeamAttendance = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.employee', 'e')
            ->where('e.manager = :manager')
            ->andWhere('a.workDate = :today')
            ->setParameter('manager', $user)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getResult();

        return $this->render('manager/dashboard.html.twig', [
            'page_title' => 'Tableau de bord - Manager',
            'managed_employees' => $managedEmployees,
            'pending_approvals' => $pendingApprovals,
            'pending_count' => count($pendingApprovals),
            'recent_team_requests' => $recentTeamRequests,
            'unread_notifications' => $unreadNotifications,
            'today_team_attendance' => $todayTeamAttendance,
            'user' => $user
        ]);
    }

    /**
     * Tableau de bord employé
     */
    #[Route('/employee/dashboard', name: 'employee_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function employeeDashboard(): Response
    {
        $user = $this->getUser();

        // Récupérer les demandes de congés de l'employé
        $userLeaveRequests = $this->leaveRequestRepository->findBy([
            'employee' => $user
        ], ['submittedAt' => 'DESC'], 5);

        // Compter les demandes par statut
        $pendingRequests = $this->leaveRequestRepository->count([
            'employee' => $user,
            'status' => 'pending'
        ]);

        $approvedRequests = $this->leaveRequestRepository->count([
            'employee' => $user,
            'status' => 'approved'
        ]);

        // Récupérer les notifications non lues
        $unreadNotifications = $this->notificationRepository->findBy([
            'recipient' => $user,
            'isRead' => false
        ], ['createdAt' => 'DESC'], 5);

        // Récupérer les présences récentes
        $recentAttendance = $this->attendanceRepository->findBy([
            'employee' => $user
        ], ['workDate' => 'DESC'], 5);

        // Calculer les heures travaillées cette semaine
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('sunday this week');
        
        $weeklyAttendance = $this->attendanceRepository->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->setParameter('employee', $user)
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getResult();

        $weeklyHours = array_sum(array_map(function($attendance) {
            return $attendance->getWorkedHours();
        }, $weeklyAttendance));

        return $this->render('employee/dashboard.html.twig', [
            'page_title' => 'Tableau de bord - Employé',
            'user_leave_requests' => $userLeaveRequests,
            'pending_requests_count' => $pendingRequests,
            'approved_requests_count' => $approvedRequests,
            'unread_notifications' => $unreadNotifications,
            'recent_attendance' => $recentAttendance,
            'weekly_hours' => $weeklyHours,
            'user' => $user
        ]);
    }

    /**
     * Page d'aide et documentation
     */
    #[Route('/help', name: 'app_help')]
    #[IsGranted('ROLE_USER')]
    public function help(): Response
    {
        return $this->render('home/help.html.twig', [
            'page_title' => 'Aide - HR Flow'
        ]);
    }

    /**
     * Page de contact/support
     */
    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig', [
            'page_title' => 'Contact - HR Flow'
        ]);
    }

    /**
     * Recherche globale (pour la barre de recherche dans le header)
     */
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(): Response
    {
        $query = $_GET['q'] ?? '';
        $results = [];

        if (strlen($query) >= 3) {
            $user = $this->getUser();
            $roles = $user->getRoles();

            // Recherche selon les droits de l'utilisateur
            if (in_array('ROLE_ADMIN', $roles)) {
                // Admin peut chercher partout
                $results['users'] = $this->userRepository->createQueryBuilder('u')
                    ->where('u.firstName LIKE :query OR u.lastName LIKE :query OR u.email LIKE :query')
                    ->setParameter('query', '%' . $query . '%')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getResult();

                $results['departments'] = $this->departmentRepository->createQueryBuilder('d')
                    ->where('d.name LIKE :query')
                    ->setParameter('query', '%' . $query . '%')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getResult();
            } elseif (in_array('ROLE_MANAGER', $roles)) {
                // Manager peut chercher dans son équipe
                $results['employees'] = $this->userRepository->createQueryBuilder('u')
                    ->where('u.manager = :manager')
                    ->andWhere('u.firstName LIKE :query OR u.lastName LIKE :query')
                    ->setParameter('manager', $user)
                    ->setParameter('query', '%' . $query . '%')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getResult();
            }

            // Recherche dans ses propres demandes pour tous les utilisateurs
            $results['leave_requests'] = $this->leaveRequestRepository->createQueryBuilder('lr')
                ->where('lr.employee = :employee')
                ->andWhere('lr.reason LIKE :query')
                ->setParameter('employee', $user)
                ->setParameter('query', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('home/search_results.html.twig', [
            'page_title' => 'Résultats de recherche',
            'query' => $query,
            'results' => $results
        ]);
    }
}