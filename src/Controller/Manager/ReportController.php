<?php

namespace App\Controller\Manager;

use App\Entity\User;
use App\Entity\LeaveRequest;
use App\Entity\Attendance;
use App\Entity\Department;
use App\Entity\Team;
use App\Repository\UserRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\AttendanceRepository;
use App\Repository\DepartmentRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager/report')]
#[IsGranted('ROLE_MANAGER')]
class ReportController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private LeaveRequestRepository $leaveRequestRepository;
    private AttendanceRepository $attendanceRepository;
    private DepartmentRepository $departmentRepository;
    private TeamRepository $teamRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        LeaveRequestRepository $leaveRequestRepository,
        AttendanceRepository $attendanceRepository,
        DepartmentRepository $departmentRepository,
        TeamRepository $teamRepository
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->leaveRequestRepository = $leaveRequestRepository;
        $this->attendanceRepository = $attendanceRepository;
        $this->departmentRepository = $departmentRepository;
        $this->teamRepository = $teamRepository;
    }

    #[Route('/', name: 'manager_report_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();
        
        // Récupérer les filtres depuis la requête
        $startDate = $request->query->get('start_date', date('Y-m-01')); // Premier jour du mois
        $endDate = $request->query->get('end_date', date('Y-m-t')); // Dernier jour du mois
        $departmentId = $request->query->get('department_id');
        $teamId = $request->query->get('team_id');

        // Récupérer les départements et équipes pour les filtres
        $departments = $this->departmentRepository->findBy(['isActive' => true]);
        $teams = $this->teamRepository->findBy(['isActive' => true]);

        // Construire les critères de base pour les employés sous la responsabilité du manager
        $employeeCriteria = ['isActive' => true];
        
        // Si un département spécifique est sélectionné
        if ($departmentId) {
            $employeeCriteria['department'] = $departmentId;
        }

        // Récupérer les employés selon les critères
        $employees = $this->userRepository->findBy($employeeCriteria);

        // Filtrer les employés selon l'équipe si nécessaire
        if ($teamId) {
            $team = $this->teamRepository->find($teamId);
            if ($team) {
                $teamMemberIds = [];
                foreach ($team->getTeamMembers() as $teamMember) {
                    if ($teamMember->isActive()) {
                        $teamMemberIds[] = $teamMember->getUser()->getId();
                    }
                }
                $employees = array_filter($employees, function($employee) use ($teamMemberIds) {
                    return in_array($employee->getId(), $teamMemberIds);
                });
            }
        }

        // Générer les données du rapport
        $reportData = $this->generateReportData($employees, $startDate, $endDate);

        return $this->render('manager/report/index.html.twig', [
            'reportData' => $reportData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'team_id' => $teamId
            ],
            'departments' => $departments,
            'teams' => $teams,
            'employees' => $employees
        ]);
    }

    #[Route('/leave-summary', name: 'manager_report_leave_summary', methods: ['GET'])]
    public function leaveSummary(Request $request): Response
    {
        $startDate = $request->query->get('start_date', date('Y-01-01')); // Début d'année
        $endDate = $request->query->get('end_date', date('Y-12-31')); // Fin d'année
        $departmentId = $request->query->get('department_id');

        // Récupérer les départements pour le filtre
        $departments = $this->departmentRepository->findBy(['isActive' => true]);

        // Construire la requête pour les demandes de congés
        $queryBuilder = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->leftJoin('lr.employee', 'e')
            ->leftJoin('lr.leaveType', 'lt')
            ->where('lr.startDate >= :startDate')
            ->andWhere('lr.endDate <= :endDate')
            ->andWhere('e.isActive = :isActive')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isActive', true);

        if ($departmentId) {
            $queryBuilder->andWhere('e.department = :departmentId')
                        ->setParameter('departmentId', $departmentId);
        }

        $leaveRequests = $queryBuilder->getQuery()->getResult();

        // Traiter les données pour le résumé
        $leaveSummary = $this->processLeaveSummary($leaveRequests);

        return $this->render('manager/report/leave_summary.html.twig', [
            'leaveSummary' => $leaveSummary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId
            ],
            'departments' => $departments
        ]);
    }

    #[Route('/attendance-summary', name: 'manager_report_attendance_summary', methods: ['GET'])]
    public function attendanceSummary(Request $request): Response
    {
        $startDate = $request->query->get('start_date', date('Y-m-01'));
        $endDate = $request->query->get('end_date', date('Y-m-t'));
        $departmentId = $request->query->get('department_id');

        $departments = $this->departmentRepository->findBy(['isActive' => true]);

        // Construire la requête pour les présences
        $queryBuilder = $this->attendanceRepository->createQueryBuilder('a')
            ->leftJoin('a.employee', 'e')
            ->where('a.workDate >= :startDate')
            ->andWhere('a.workDate <= :endDate')
            ->andWhere('e.isActive = :isActive')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isActive', true);

        if ($departmentId) {
            $queryBuilder->andWhere('e.department = :departmentId')
                        ->setParameter('departmentId', $departmentId);
        }

        $attendanceRecords = $queryBuilder->getQuery()->getResult();

        // Traiter les données de présence
        $attendanceSummary = $this->processAttendanceSummary($attendanceRecords);

        return $this->render('manager/report/attendance_summary.html.twig', [
            'attendanceSummary' => $attendanceSummary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId
            ],
            'departments' => $departments
        ]);
    }

    #[Route('/employee/{id}', name: 'manager_report_employee_detail', methods: ['GET'])]
    public function employeeDetail(User $employee, Request $request): Response
    {
        $startDate = $request->query->get('start_date', date('Y-m-01'));
        $endDate = $request->query->get('end_date', date('Y-m-t'));

        // Récupérer les demandes de congés de l'employé
        $leaveRequests = $this->leaveRequestRepository->findBy([
            'employee' => $employee
        ]);

        // Filtrer par dates
        $filteredLeaveRequests = array_filter($leaveRequests, function($request) use ($startDate, $endDate) {
            return $request->getStartDate() >= new \DateTime($startDate) && 
                   $request->getEndDate() <= new \DateTime($endDate);
        });

        // Récupérer les présences de l'employé
        $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.workDate >= :startDate')
            ->andWhere('a.workDate <= :endDate')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();

        // Calculer les statistiques de l'employé
        $employeeStats = $this->calculateEmployeeStats($employee, $filteredLeaveRequests, $attendanceRecords);

        return $this->render('manager/report/employee_detail.html.twig', [
            'employee' => $employee,
            'leaveRequests' => $filteredLeaveRequests,
            'attendanceRecords' => $attendanceRecords,
            'employeeStats' => $employeeStats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    private function generateReportData(array $employees, string $startDate, string $endDate): array
    {
        $reportData = [
            'summary' => [
                'totalEmployees' => count($employees),
                'totalLeaveRequests' => 0,
                'approvedLeaveRequests' => 0,
                'pendingLeaveRequests' => 0,
                'rejectedLeaveRequests' => 0,
                'totalLeaveDays' => 0,
                'averageWorkingHours' => 0
            ],
            'employees' => []
        ];

        foreach ($employees as $employee) {
            // Récupérer les demandes de congés pour la période
            $leaveRequests = $this->leaveRequestRepository->createQueryBuilder('lr')
                ->where('lr.employee = :employee')
                ->andWhere('lr.startDate >= :startDate')
                ->andWhere('lr.endDate <= :endDate')
                ->setParameter('employee', $employee)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            // Récupérer les présences pour la période
            $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
                ->where('a.employee = :employee')
                ->andWhere('a.workDate >= :startDate')
                ->andWhere('a.workDate <= :endDate')
                ->setParameter('employee', $employee)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            // Calculer les statistiques pour cet employé
            $employeeData = [
                'employee' => $employee,
                'leaveRequestsCount' => count($leaveRequests),
                'approvedLeaves' => 0,
                'pendingLeaves' => 0,
                'rejectedLeaves' => 0,
                'totalLeaveDays' => 0,
                'attendanceDays' => count($attendanceRecords),
                'totalWorkedHours' => 0,
                'averageWorkingHours' => 0
            ];

            // Traiter les demandes de congés
            foreach ($leaveRequests as $leaveRequest) {
                switch ($leaveRequest->getStatus()) {
                    case 'approved':
                        $employeeData['approvedLeaves']++;
                        $reportData['summary']['approvedLeaveRequests']++;
                        break;
                    case 'pending':
                        $employeeData['pendingLeaves']++;
                        $reportData['summary']['pendingLeaveRequests']++;
                        break;
                    case 'rejected':
                        $employeeData['rejectedLeaves']++;
                        $reportData['summary']['rejectedLeaveRequests']++;
                        break;
                }
                $employeeData['totalLeaveDays'] += $leaveRequest->getNumberOfDays();
            }

            // Traiter les présences
            foreach ($attendanceRecords as $attendance) {
                $employeeData['totalWorkedHours'] += $attendance->getWorkedHours();
            }

            if ($employeeData['attendanceDays'] > 0) {
                $employeeData['averageWorkingHours'] = round($employeeData['totalWorkedHours'] / $employeeData['attendanceDays'], 2);
            }

            // Mettre à jour les totaux
            $reportData['summary']['totalLeaveRequests'] += $employeeData['leaveRequestsCount'];
            $reportData['summary']['totalLeaveDays'] += $employeeData['totalLeaveDays'];

            $reportData['employees'][] = $employeeData;
        }

        // Calculer la moyenne des heures de travail
        $totalWorkedHours = array_sum(array_column($reportData['employees'], 'totalWorkedHours'));
        $totalAttendanceDays = array_sum(array_column($reportData['employees'], 'attendanceDays'));
        
        if ($totalAttendanceDays > 0) {
            $reportData['summary']['averageWorkingHours'] = round($totalWorkedHours / $totalAttendanceDays, 2);
        }

        return $reportData;
    }

    private function processLeaveSummary(array $leaveRequests): array
    {
        $summary = [
            'byType' => [],
            'byStatus' => [],
            'byMonth' => [],
            'totalDays' => 0,
            'totalRequests' => count($leaveRequests)
        ];

        foreach ($leaveRequests as $leaveRequest) {
            $typeName = $leaveRequest->getLeaveType()->getName();
            $status = $leaveRequest->getStatus();
            $month = $leaveRequest->getStartDate()->format('Y-m');

            // Par type
            if (!isset($summary['byType'][$typeName])) {
                $summary['byType'][$typeName] = ['count' => 0, 'days' => 0];
            }
            $summary['byType'][$typeName]['count']++;
            $summary['byType'][$typeName]['days'] += $leaveRequest->getNumberOfDays();

            // Par statut
            if (!isset($summary['byStatus'][$status])) {
                $summary['byStatus'][$status] = ['count' => 0, 'days' => 0];
            }
            $summary['byStatus'][$status]['count']++;
            $summary['byStatus'][$status]['days'] += $leaveRequest->getNumberOfDays();

            // Par mois
            if (!isset($summary['byMonth'][$month])) {
                $summary['byMonth'][$month] = ['count' => 0, 'days' => 0];
            }
            $summary['byMonth'][$month]['count']++;
            $summary['byMonth'][$month]['days'] += $leaveRequest->getNumberOfDays();

            $summary['totalDays'] += $leaveRequest->getNumberOfDays();
        }

        return $summary;
    }

    private function processAttendanceSummary(array $attendanceRecords): array
    {
        $summary = [
            'totalRecords' => count($attendanceRecords),
            'totalWorkedHours' => 0,
            'averageWorkedHours' => 0,
            'byStatus' => [],
            'byEmployee' => []
        ];

        foreach ($attendanceRecords as $attendance) {
            $status = $attendance->getStatus();
            $employeeId = $attendance->getEmployee()->getId();
            $employeeName = $attendance->getEmployee()->getFirstName() . ' ' . $attendance->getEmployee()->getLastName();

            // Par statut
            if (!isset($summary['byStatus'][$status])) {
                $summary['byStatus'][$status] = ['count' => 0, 'hours' => 0];
            }
            $summary['byStatus'][$status]['count']++;
            $summary['byStatus'][$status]['hours'] += $attendance->getWorkedHours();

            // Par employé
            if (!isset($summary['byEmployee'][$employeeId])) {
                $summary['byEmployee'][$employeeId] = [
                    'name' => $employeeName,
                    'count' => 0,
                    'hours' => 0
                ];
            }
            $summary['byEmployee'][$employeeId]['count']++;
            $summary['byEmployee'][$employeeId]['hours'] += $attendance->getWorkedHours();

            $summary['totalWorkedHours'] += $attendance->getWorkedHours();
        }

        if ($summary['totalRecords'] > 0) {
            $summary['averageWorkedHours'] = round($summary['totalWorkedHours'] / $summary['totalRecords'], 2);
        }

        return $summary;
    }

    private function calculateEmployeeStats(User $employee, array $leaveRequests, array $attendanceRecords): array
    {
        return [
            'totalLeaveRequests' => count($leaveRequests),
            'totalLeaveDays' => array_sum(array_map(fn($lr) => $lr->getNumberOfDays(), $leaveRequests)),
            'approvedLeaves' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'approved')),
            'pendingLeaves' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'pending')),
            'rejectedLeaves' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'rejected')),
            'totalAttendanceDays' => count($attendanceRecords),
            'totalWorkedHours' => array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords)),
            'averageWorkedHours' => count($attendanceRecords) > 0 ? 
                round(array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords)) / count($attendanceRecords), 2) : 0
        ];
    }

// Ajoutez cette méthode à votre classe ReportController

#[Route('/show/{type}', name: 'app_manager_report_show', methods: ['GET', 'POST'])]
public function show(string $type, Request $request): Response
{
     // Types de rapports disponibles
        $availableTypes = ['general', 'leave', 'attendance', 'productivity', 'department', 'detailed'];
        
        // Normaliser le type (enlever les espaces, convertir en minuscules)
        $type = strtolower(trim($type));
        
        if (!in_array($type, $availableTypes)) {
            // Ajouter un message d'erreur plus explicite
            $this->addFlash('error', sprintf('Type de rapport "%s" non valide. Types disponibles: %s', 
                $type, implode(', ', $availableTypes)));
            
            // Rediriger vers la page d'index des rapports
            return $this->redirectToRoute('manager_report_index');
        }

        $currentUser = $this->getUser();
        
        // Récupérer les filtres depuis la requête (GET et POST)
        $filters = [
            'start_date' => $request->get('start_date', date('Y-m-01')),
            'end_date' => $request->get('end_date', date('Y-m-t')),
            'department_id' => $request->get('department_id'),
            'team_id' => $request->get('team_id'),
            'employee_id' => $request->get('employee_id'),
            'status' => $request->get('status'),
            'export_format' => $request->get('export_format')
        ];

        // Récupérer les données de référence pour les filtres
        $departments = $this->departmentRepository->findBy(['isActive' => true]);
        $teams = $this->teamRepository->findBy(['isActive' => true]);
        
        // Récupérer les employés selon les critères
        $employees = $this->getFilteredEmployees($filters);

        // Générer le rapport selon le type
        try {
            $reportData = $this->generateReportByType($type, $filters, $employees);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du rapport: ' . $e->getMessage());
            return $this->redirectToRoute('manager_report_index');
        }

        // Gestion de l'export si demandé
        if ($request->get('export_format')) {
            try {
                return $this->handleExport($type, $reportData, $filters);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'export: ' . $e->getMessage());
            }
        }

        return $this->render('manager/report/show.html.twig', [
            'type' => $type,
            'reportData' => $reportData,
            'filters' => $filters,
            'departments' => $departments,
            'teams' => $teams,
            'employees' => $employees,
            'availableTypes' => $availableTypes
        ]);
}

    // 4. Ajoutez une route pour lister les types de rapports disponibles
    #[Route('/types', name: 'manager_report_types', methods: ['GET'])]
    public function listTypes(): Response
    {
        $availableTypes = [
            'general' => 'Rapport Général',
            'leave' => 'Rapport des Congés', 
            'attendance' => 'Rapport de Présence',
            'productivity' => 'Rapport de Productivité',
            'department' => 'Rapport par Département',
            'detailed' => 'Rapport Détaillé'  // NOUVEAU TYPE
        ];

        return $this->render('manager/report/types.html.twig', [
            'availableTypes' => $availableTypes
        ]);
    }
    
private function getFilteredEmployees(array $filters): array
{
    $criteria = ['isActive' => true];
    
    if ($filters['department_id']) {
        $criteria['department'] = $filters['department_id'];
    }

    $employees = $this->userRepository->findBy($criteria);

    // Filtrer par équipe si spécifiée
    if ($filters['team_id']) {
        $team = $this->teamRepository->find($filters['team_id']);
        if ($team) {
            $teamMemberIds = [];
            foreach ($team->getTeamMembers() as $teamMember) {
                if ($teamMember->isActive()) {
                    $teamMemberIds[] = $teamMember->getUser()->getId();
                }
            }
            $employees = array_filter($employees, function($employee) use ($teamMemberIds) {
                return in_array($employee->getId(), $teamMemberIds);
            });
        }
    }

    // Filtrer par employé spécifique si spécifié
    if ($filters['employee_id']) {
        $employees = array_filter($employees, function($employee) use ($filters) {
            return $employee->getId() == $filters['employee_id'];
        });
    }

    return $employees;
}

private function generateReportByType(string $type, array $filters, array $employees): array
{
    switch ($type) {
        case 'general':
            return $this->generateGeneralReport($employees, $filters);
        case 'leave':
            return $this->generateLeaveReport($employees, $filters);
        case 'attendance':
            return $this->generateAttendanceReport($employees, $filters);
        case 'productivity':
            return $this->generateProductivityReport($employees, $filters);
        case 'department':
            return $this->generateDepartmentReport($filters);
        case 'detailed':  // NOUVEAU CASE
            return $this->generateDetailedReport($employees, $filters);
        default:
            throw new \InvalidArgumentException('Type de rapport non supporté');
    }
}

// 3. Nouvelle méthode pour générer le rapport détaillé
private function generateDetailedReport(array $employees, array $filters): array
{
    $startDate = $filters['start_date'];
    $endDate = $filters['end_date'];
    
    $report = [
        'title' => 'Rapport Détaillé',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'summary' => [
            'totalEmployees' => count($employees),
            'totalLeaveRequests' => 0,
            'totalAttendanceRecords' => 0,
            'totalWorkedHours' => 0,
            'totalLeaveDays' => 0,
            'averageAttendanceRate' => 0
        ],
        'employeeDetails' => [],
        'departmentBreakdown' => [],
        'timeAnalysis' => []
    ];

    $allLeaveRequests = [];
    $allAttendanceRecords = [];
    $departmentStats = [];

    foreach ($employees as $employee) {
        // Récupérer les demandes de congés
        $leaveRequests = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->where('lr.employee = :employee')
            ->andWhere('lr.startDate >= :startDate')
            ->andWhere('lr.endDate <= :endDate')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // Récupérer les présences
        $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.workDate >= :startDate')
            ->andWhere('a.workDate <= :endDate')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();

        $allLeaveRequests = array_merge($allLeaveRequests, $leaveRequests);
        $allAttendanceRecords = array_merge($allAttendanceRecords, $attendanceRecords);

        // Calculer les statistiques détaillées pour chaque employé
        $employeeStats = [
            'employee' => $employee,
            'personal' => [
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'email' => $employee->getEmail(),
                'department' => $employee->getDepartment() ? $employee->getDepartment()->getName() : 'N/A',
                'position' => $employee->getPosition() ?? 'N/A'
            ],
            'leaves' => [
                'total' => count($leaveRequests),
                'approved' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'approved')),
                'pending' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'pending')),
                'rejected' => count(array_filter($leaveRequests, fn($lr) => $lr->getStatus() === 'rejected')),
                'totalDays' => array_sum(array_map(fn($lr) => $lr->getNumberOfDays(), $leaveRequests)),
                'details' => $leaveRequests
            ],
            'attendance' => [
                'totalDays' => count($attendanceRecords),
                'totalHours' => array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords)),
                'averageHours' => count($attendanceRecords) > 0 ? 
                    round(array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords)) / count($attendanceRecords), 2) : 0,
                'punctuality' => $this->calculatePunctuality($attendanceRecords),
                'details' => $attendanceRecords
            ],
            'performance' => [
                'attendanceRate' => $this->calculateAttendanceRate($employee, $startDate, $endDate),
                'productivityScore' => $this->calculateProductivityScore($attendanceRecords, $leaveRequests),
                'reliability' => $this->calculateReliability($attendanceRecords)
            ]
        ];

        $report['employeeDetails'][] = $employeeStats;

        // Statistiques par département
        $deptName = $employee->getDepartment() ? $employee->getDepartment()->getName() : 'Non assigné';
        if (!isset($departmentStats[$deptName])) {
            $departmentStats[$deptName] = [
                'employeeCount' => 0,
                'totalLeaves' => 0,
                'totalAttendance' => 0,
                'totalHours' => 0
            ];
        }
        $departmentStats[$deptName]['employeeCount']++;
        $departmentStats[$deptName]['totalLeaves'] += count($leaveRequests);
        $departmentStats[$deptName]['totalAttendance'] += count($attendanceRecords);
        $departmentStats[$deptName]['totalHours'] += array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords));
    }

    // Finaliser les statistiques globales
    $report['summary']['totalLeaveRequests'] = count($allLeaveRequests);
    $report['summary']['totalAttendanceRecords'] = count($allAttendanceRecords);
    $report['summary']['totalWorkedHours'] = array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $allAttendanceRecords));
    $report['summary']['totalLeaveDays'] = array_sum(array_map(fn($lr) => $lr->getNumberOfDays(), $allLeaveRequests));

    // Calculer le taux de présence moyen
    $workingDays = $this->calculateWorkingDaysBetween($startDate, $endDate);
    if ($workingDays > 0 && count($employees) > 0) {
        $report['summary']['averageAttendanceRate'] = round(
            (count($allAttendanceRecords) / ($workingDays * count($employees))) * 100, 2
        );
    }

    $report['departmentBreakdown'] = $departmentStats;
    $report['timeAnalysis'] = $this->generateTimeAnalysis($allAttendanceRecords, $allLeaveRequests);

    return $report;
}

// 4. Méthodes utilitaires pour le rapport détaillé
private function calculatePunctuality(array $attendanceRecords): array
{
    $punctuality = ['onTime' => 0, 'late' => 0, 'veryLate' => 0];
    
    foreach ($attendanceRecords as $record) {
        if (!$record->getCheckInTime()) continue;
        
        $checkIn = $record->getCheckInTime();
        $scheduledStart = clone $record->getWorkDate();
        $scheduledStart->setTime(9, 0); // 09:00 par défaut
        
        $lateMinutes = ($checkIn->getTimestamp() - $scheduledStart->getTimestamp()) / 60;
        
        if ($lateMinutes <= 5) {
            $punctuality['onTime']++;
        } elseif ($lateMinutes <= 15) {
            $punctuality['late']++;
        } else {
            $punctuality['veryLate']++;
        }
    }
    
    return $punctuality;
}

private function calculateAttendanceRate(User $employee, string $startDate, string $endDate): float
{
    $workingDays = $this->calculateWorkingDaysBetween($startDate, $endDate);
    $attendanceDays = $this->attendanceRepository->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.employee = :employee')
        ->andWhere('a.workDate >= :startDate')
        ->andWhere('a.workDate <= :endDate')
        ->setParameter('employee', $employee)
        ->setParameter('startDate', $startDate)
        ->setParameter('endDate', $endDate)
        ->getQuery()
        ->getSingleScalarResult();
    
    return $workingDays > 0 ? round(($attendanceDays / $workingDays) * 100, 2) : 0;
}

private function calculateProductivityScore(array $attendanceRecords, array $leaveRequests): float
{
    // Score basé sur les heures travaillées et les absences
    $totalHours = array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords));
    $totalDays = count($attendanceRecords);
    $leaveDays = array_sum(array_map(fn($lr) => $lr->getNumberOfDays(), $leaveRequests));
    
    if ($totalDays === 0) return 0;
    
    $averageHours = $totalHours / $totalDays;
    $baseScore = ($averageHours / 8) * 100; // Score basé sur 8h/jour
    
    // Pénalité pour les congés excessifs (plus de 10% du temps)
    $leaveRatio = $leaveDays / max($totalDays + $leaveDays, 1);
    if ($leaveRatio > 0.1) {
        $baseScore *= (1 - ($leaveRatio - 0.1));
    }
    
    return round(min($baseScore, 100), 2);
}

private function calculateReliability(array $attendanceRecords): float
{
    if (empty($attendanceRecords)) return 0;
    
    $reliableCount = 0;
    foreach ($attendanceRecords as $record) {
        // Considérer comme fiable si présent et avec des heures normales
        if ($record->getWorkedHours() >= 6) {
            $reliableCount++;
        }
    }
    
    return round(($reliableCount / count($attendanceRecords)) * 100, 2);
}

private function generateTimeAnalysis(array $attendanceRecords, array $leaveRequests): array
{
    $analysis = [
        'peakHours' => [],
        'averageByDay' => [],
        'monthlyTrends' => [],
        'leavePatterns' => []
    ];
    
    // Analyse des heures de pointe
    $hourlyData = [];
    foreach ($attendanceRecords as $record) {
        if ($record->getCheckInTime()) {
            $hour = $record->getCheckInTime()->format('H');
            $hourlyData[$hour] = ($hourlyData[$hour] ?? 0) + 1;
        }
    }
    arsort($hourlyData);
    $analysis['peakHours'] = array_slice($hourlyData, 0, 3, true);
    
    // Analyse par jour de la semaine
    $dayData = [];
    foreach ($attendanceRecords as $record) {
        $day = $record->getWorkDate()->format('N'); // 1=Lundi, 7=Dimanche
        $dayName = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][$day];
        $dayData[$dayName] = ($dayData[$dayName] ?? 0) + $record->getWorkedHours();
    }
    $analysis['averageByDay'] = $dayData;
    
    return $analysis;
}

    private function generateGeneralReport(array $employees, array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        
        $report = [
            'title' => 'Rapport Général',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'totalEmployees' => count($employees),
                'totalDepartments' => 0,
                'totalTeams' => 0,
                'activeLeaveRequests' => 0,
                'totalWorkingDays' => 0,
                'averageAttendanceRate' => 0
            ],
            'details' => [],
            'charts' => []
        ];

        // Calculer les départements et équipes uniques
        $departments = [];
        $teams = [];
        
        foreach ($employees as $employee) {
            if ($employee->getDepartment()) {
                $departments[$employee->getDepartment()->getId()] = $employee->getDepartment();
            }
            
            foreach ($employee->getTeamMembershipss() as $teamMembership) {
                if ($teamMembership->isActive()) {
                    $teams[$teamMembership->getTeam()->getId()] = $teamMembership->getTeam();
                }
            }
        }
        
        $report['summary']['totalDepartments'] = count($departments);
        $report['summary']['totalTeams'] = count($teams);

        // Calculer les statistiques détaillées pour chaque employé
        foreach ($employees as $employee) {
            $employeeStats = $this->calculateDetailedEmployeeStats($employee, $startDate, $endDate);
            $report['details'][] = $employeeStats;
            
            $report['summary']['activeLeaveRequests'] += $employeeStats['pendingLeaves'];
            $report['summary']['totalWorkingDays'] += $employeeStats['attendanceDays'];
        }

        // Calculer le taux de présence moyen
        $totalPossibleDays = $this->calculateWorkingDaysBetween($startDate, $endDate) * count($employees);
        if ($totalPossibleDays > 0) {
            $report['summary']['averageAttendanceRate'] = round(
                ($report['summary']['totalWorkingDays'] / $totalPossibleDays) * 100, 2
            );
        }

        // Données pour les graphiques
        $report['charts'] = [
            'attendanceByDepartment' => $this->getAttendanceByDepartment($employees, $startDate, $endDate),
            'leavesByType' => $this->getLeavesByType($employees, $startDate, $endDate),
            'monthlyTrends' => $this->getMonthlyTrends($employees, $startDate, $endDate)
        ];

        return $report;
    }

    private function generateLeaveReport(array $employees, array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $status = $filters['status'];
        
        $report = [
            'title' => 'Rapport des Congés',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'totalRequests' => 0,
                'approvedRequests' => 0,
                'pendingRequests' => 0,
                'rejectedRequests' => 0,
                'totalDays' => 0,
                'averageDaysPerRequest' => 0
            ],
            'details' => [],
            'byType' => [],
            'byEmployee' => []
        ];

        $allLeaveRequests = [];

        foreach ($employees as $employee) {
            $queryBuilder = $this->leaveRequestRepository->createQueryBuilder('lr')
                ->where('lr.employee = :employee')
                ->andWhere('lr.startDate >= :startDate')
                ->andWhere('lr.endDate <= :endDate')
                ->setParameter('employee', $employee)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);

            if ($status) {
                $queryBuilder->andWhere('lr.status = :status')
                            ->setParameter('status', $status);
            }

            $leaveRequests = $queryBuilder->getQuery()->getResult();
            $allLeaveRequests = array_merge($allLeaveRequests, $leaveRequests);
            
            if (!empty($leaveRequests)) {
                $employeeLeaveData = [
                    'employee' => $employee,
                    'requests' => $leaveRequests,
                    'totalDays' => array_sum(array_map(fn($lr) => $lr->getNumberOfDays(), $leaveRequests)),
                    'byStatus' => []
                ];

                // Regrouper par statut pour cet employé
                foreach ($leaveRequests as $request) {
                    $status = $request->getStatus();
                    if (!isset($employeeLeaveData['byStatus'][$status])) {
                        $employeeLeaveData['byStatus'][$status] = ['count' => 0, 'days' => 0];
                    }
                    $employeeLeaveData['byStatus'][$status]['count']++;
                    $employeeLeaveData['byStatus'][$status]['days'] += $request->getNumberOfDays();
                }

                $report['byEmployee'][] = $employeeLeaveData;
            }
        }

        // Calculer les statistiques globales
        foreach ($allLeaveRequests as $leaveRequest) {
            $report['summary']['totalRequests']++;
            $report['summary']['totalDays'] += $leaveRequest->getNumberOfDays();
            
            switch ($leaveRequest->getStatus()) {
                case 'approved':
                    $report['summary']['approvedRequests']++;
                    break;
                case 'pending':
                    $report['summary']['pendingRequests']++;
                    break;
                case 'rejected':
                    $report['summary']['rejectedRequests']++;
                    break;
            }

            // Regrouper par type
            $typeName = $leaveRequest->getLeaveType()->getName();
            if (!isset($report['byType'][$typeName])) {
                $report['byType'][$typeName] = ['count' => 0, 'days' => 0];
            }
            $report['byType'][$typeName]['count']++;
            $report['byType'][$typeName]['days'] += $leaveRequest->getNumberOfDays();
        }

        if ($report['summary']['totalRequests'] > 0) {
            $report['summary']['averageDaysPerRequest'] = round(
                $report['summary']['totalDays'] / $report['summary']['totalRequests'], 2
            );
        }

        $report['details'] = $allLeaveRequests;

        return $report;
    }

    #[Route('/debug/{type}', name: 'manager_report_debug', methods: ['GET'])]
    public function debug(string $type = null): Response
    {
        $availableTypes = ['general', 'leave', 'attendance', 'productivity', 'department'];
        
        return new Response(sprintf(
            'Type reçu: "%s"<br>Types disponibles: %s<br>Type valide: %s',
            $type ?? 'NULL',
            implode(', ', $availableTypes),
            $type && in_array($type, $availableTypes) ? 'OUI' : 'NON'
        ));
    }

private function generateAttendanceReport(array $employees, array $filters): array
{
    $startDate = $filters['start_date'];
    $endDate = $filters['end_date'];
    
    $report = [
        'title' => 'Rapport de Présence',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'summary' => [
            'totalRecords' => 0,
            'totalHours' => 0,
            'averageHoursPerDay' => 0,
            'attendanceRate' => 0,
            'lateArrivals' => 0,
            'earlyDepartures' => 0
        ],
        'byEmployee' => [],
        'dailyStats' => []
    ];

    $allAttendanceRecords = [];
    $workingDays = $this->calculateWorkingDaysBetween($startDate, $endDate);

    foreach ($employees as $employee) {
        $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.workDate >= :startDate')
            ->andWhere('a.workDate <= :endDate')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.workDate', 'ASC')
            ->getQuery()
            ->getResult();

        $allAttendanceRecords = array_merge($allAttendanceRecords, $attendanceRecords);

        if (!empty($attendanceRecords)) {
            $employeeAttendance = [
                'employee' => $employee,
                'records' => $attendanceRecords,
                'totalDays' => count($attendanceRecords),
                'totalHours' => array_sum(array_map(fn($ar) => $ar->getWorkedHours(), $attendanceRecords)),
                'averageHours' => 0,
                'attendanceRate' => 0,
                'lateCount' => 0,
                'earlyDepartureCount' => 0
            ];

            // Calculer les statistiques individuelles avec une meilleure gestion des heures
            foreach ($attendanceRecords as $record) {
                $checkInTime = $record->getCheckInTime();
                $checkOutTime = $record->getCheckOutTime();
                
                // Créer des objets DateTime pour la comparaison avec une date de référence
                $lateThreshold = clone $record->getWorkDate();
                $lateThreshold->setTime(9, 0); // 09:00
                
                $earlyThreshold = clone $record->getWorkDate();
                $earlyThreshold->setTime(17, 0); // 17:00
                
                if ($checkInTime && $checkInTime > $lateThreshold) {
                    $employeeAttendance['lateCount']++;
                }
                if ($checkOutTime && $checkOutTime < $earlyThreshold) {
                    $employeeAttendance['earlyDepartureCount']++;
                }
            }

            if ($employeeAttendance['totalDays'] > 0) {
                $employeeAttendance['averageHours'] = round(
                    $employeeAttendance['totalHours'] / $employeeAttendance['totalDays'], 2
                );
            }
            
            if ($workingDays > 0) {
                $employeeAttendance['attendanceRate'] = round(
                    ($employeeAttendance['totalDays'] / $workingDays) * 100, 2
                );
            }

            $report['byEmployee'][] = $employeeAttendance;
        }
    }

    // Calculer les statistiques globales avec la même logique corrigée
    foreach ($allAttendanceRecords as $record) {
        $report['summary']['totalRecords']++;
        $report['summary']['totalHours'] += $record->getWorkedHours();
        
        $checkInTime = $record->getCheckInTime();
        $checkOutTime = $record->getCheckOutTime();
        
        $lateThreshold = clone $record->getWorkDate();
        $lateThreshold->setTime(9, 0);
        
        $earlyThreshold = clone $record->getWorkDate();
        $earlyThreshold->setTime(17, 0);
        
        if ($checkInTime && $checkInTime > $lateThreshold) {
            $report['summary']['lateArrivals']++;
        }
        if ($checkOutTime && $checkOutTime < $earlyThreshold) {
            $report['summary']['earlyDepartures']++;
        }

        // Regrouper par jour pour les statistiques quotidiennes
        $dateKey = $record->getWorkDate()->format('Y-m-d');
        if (!isset($report['dailyStats'][$dateKey])) {
            $report['dailyStats'][$dateKey] = [
                'date' => $record->getWorkDate(),
                'totalEmployees' => 0,
                'totalHours' => 0,
                'averageHours' => 0
            ];
        }
        $report['dailyStats'][$dateKey]['totalEmployees']++;
        $report['dailyStats'][$dateKey]['totalHours'] += $record->getWorkedHours();
    }

    // Finaliser les statistiques quotidiennes
    foreach ($report['dailyStats'] as &$dailyStat) {
        if ($dailyStat['totalEmployees'] > 0) {
            $dailyStat['averageHours'] = round(
                $dailyStat['totalHours'] / $dailyStat['totalEmployees'], 2
            );
        }
    }

    if ($report['summary']['totalRecords'] > 0) {
        $report['summary']['averageHoursPerDay'] = round(
            $report['summary']['totalHours'] / $report['summary']['totalRecords'], 2
        );
        
        if ($workingDays * count($employees) > 0) {
            $report['summary']['attendanceRate'] = round(
                ($report['summary']['totalRecords'] / ($workingDays * count($employees))) * 100, 2
            );
        }
    }

    return $report;
}

private function generateProductivityReport(array $employees, array $filters): array
{
    // Ce rapport combine présence et congés pour évaluer la productivité
    $attendanceReport = $this->generateAttendanceReport($employees, $filters);
    $leaveReport = $this->generateLeaveReport($employees, $filters);
    
    return [
        'title' => 'Rapport de Productivité',
        'period' => $filters,
        'attendance' => $attendanceReport,
        'leaves' => $leaveReport,
        'productivity' => [
            'workEfficiency' => $this->calculateWorkEfficiency($employees, $filters),
            'availabilityRate' => $this->calculateAvailabilityRate($employees, $filters)
        ]
    ];
}

private function generateDepartmentReport(array $filters): array
{
    $departments = $this->departmentRepository->findBy(['isActive' => true]);
    
    $report = [
        'title' => 'Rapport par Département',
        'period' => $filters,
        'departments' => []
    ];

    foreach ($departments as $department) {
        $employees = $this->userRepository->findBy([
            'department' => $department,
            'isActive' => true
        ]);

        if (!empty($employees)) {
            $departmentData = [
                'department' => $department,
                'employeeCount' => count($employees),
                'attendance' => $this->generateAttendanceReport($employees, $filters),
                'leaves' => $this->generateLeaveReport($employees, $filters)
            ];

            $report['departments'][] = $departmentData;
        }
    }

    return $report;
}

private function handleExport(string $type, array $reportData, array $filters): Response
{
    $format = $filters['export_format'];
    $filename = sprintf('rapport_%s_%s_%s', $type, date('Y-m-d'), $format);

    switch ($format) {
        case 'pdf':
            return $this->exportToPdf($reportData, $filename);
        case 'excel':
            return $this->exportToExcel($reportData, $filename);
        case 'csv':
            return $this->exportToCsv($reportData, $filename);
        default:
            throw new \InvalidArgumentException('Format d\'export non supporté');
    }
}

// Méthodes utilitaires

private function calculateWorkingDaysBetween(string $startDate, string $endDate): int
{
    $start = new \DateTime($startDate);
    $end = new \DateTime($endDate);
    $workingDays = 0;

    while ($start <= $end) {
        if ($start->format('N') < 6) { // Lundi à vendredi
            $workingDays++;
        }
        $start->add(new \DateInterval('P1D'));
    }

    return $workingDays;
}

private function calculateDetailedEmployeeStats(User $employee, string $startDate, string $endDate): array
{
    // Utiliser les méthodes existantes du repository ou créer des requêtes personnalisées
    $leaveRequests = $this->leaveRequestRepository->createQueryBuilder('lr')
        ->where('lr.employee = :employee')
        ->andWhere('lr.startDate >= :startDate')
        ->andWhere('lr.endDate <= :endDate')
        ->setParameter('employee', $employee)
        ->setParameter('startDate', $startDate)
        ->setParameter('endDate', $endDate)
        ->getQuery()
        ->getResult();
        
    $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
        ->where('a.employee = :employee')
        ->andWhere('a.workDate >= :startDate')
        ->andWhere('a.workDate <= :endDate')
        ->setParameter('employee', $employee)
        ->setParameter('startDate', $startDate)
        ->setParameter('endDate', $endDate)
        ->getQuery()
        ->getResult();
    
    return $this->calculateEmployeeStats($employee, $leaveRequests, $attendanceRecords);
}

private function getAttendanceByDepartment(array $employees, string $startDate, string $endDate): array
{
    // Implementation pour les graphiques
    return [];
}

private function getLeavesByType(array $employees, string $startDate, string $endDate): array
{
    // Implementation pour les graphiques
    return [];
}

private function getMonthlyTrends(array $employees, string $startDate, string $endDate): array
{
    // Implementation pour les graphiques
    return [];
}

private function calculateWorkEfficiency(array $employees, array $filters): float
{
    // Calcul de l'efficacité au travail
    return 0.0;
}

private function calculateAvailabilityRate(array $employees, array $filters): float
{
    // Calcul du taux de disponibilité
    return 0.0;
}

private function exportToPdf(array $reportData, string $filename): Response
{
    // Implementation de l'export PDF
    throw new \Exception('Export PDF non implémenté');
}

private function exportToExcel(array $reportData, string $filename): Response
{
    // Implementation de l'export Excel
    throw new \Exception('Export Excel non implémenté');
}

private function exportToCsv(array $reportData, string $filename): Response
{
    // Implementation de l'export CSV
    throw new \Exception('Export CSV non implémenté');
}


}