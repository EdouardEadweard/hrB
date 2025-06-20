<?php

namespace App\Controller\Employee;

use App\Entity\Attendance;
use App\Form\Employee\AttendanceType;
use App\Repository\AttendanceRepository;
use App\Repository\HolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/attendance')]
#[IsGranted('ROLE_EMPLOYEE')]
class AttendanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttendanceRepository $attendanceRepository,
        private HolidayRepository $holidayRepository
    ) {
    }

    #[Route('/', name: 'app_employee_attendance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        // Pour les tests : si pas d'utilisateur connecté, utiliser des données vides
        if (!$user) {
            return $this->render('employee/attendance/index.html.twig', [
                'attendances' => [],
                'current_period' => date('Y-m'),
                'view' => $request->query->get('view', 'month'),
                'summary' => [
                    'total_hours' => 0,
                    'present_days' => 0,
                    'absent_days' => 0,
                    'working_days' => 0,
                    'attendance_rate' => 0
                ],
                'available_periods' => [],
                'test_mode' => true
            ]);
        }

        $currentMonth = $request->query->get('month', date('Y-m'));
        $view = $request->query->get('view', 'month'); // month, week, day
        
        // Parser le mois sélectionné
        $dateFilter = new \DateTime($currentMonth . '-01');
        
        $attendances = [];
        $summary = [];
        
        switch ($view) {
            case 'day':
                $selectedDate = $request->query->get('date', date('Y-m-d'));
                $attendances = $this->attendanceRepository->findBy([
                    'employee' => $user,
                    'workDate' => new \DateTime($selectedDate)
                ]);
                $summary = $this->calculateDailySummary($user, new \DateTime($selectedDate));
                break;
                
            case 'week':
                $weekStart = $request->query->get('week', date('Y-m-d'));
                $startDate = new \DateTime($weekStart);
                $startDate->modify('monday this week');
                $endDate = clone $startDate;
                $endDate->modify('+6 days');
                
                $attendances = $this->attendanceRepository->findAttendancesByDateRange(
                    $user, $startDate, $endDate
                );
                $summary = $this->calculateWeeklySummary($user, $startDate, $endDate);
                break;
                
            default: // month
                $startDate = clone $dateFilter;
                $endDate = clone $dateFilter;
                $endDate->modify('last day of this month');
                
                $attendances = $this->attendanceRepository->findAttendancesByDateRange(
                    $user, $startDate, $endDate
                );
                $summary = $this->calculateMonthlySummary($user, $dateFilter);
                break;
        }

        // Récupérer les mois/périodes disponibles
        $availablePeriods = $this->getAvailablePeriods($user);
        
        return $this->render('employee/attendance/index.html.twig', [
            'attendances' => $attendances,
            'current_period' => $currentMonth,
            'view' => $view,
            'summary' => $summary,
            'available_periods' => $availablePeriods,
        ]);
    }

    #[Route('/new', name: 'app_employee_attendance_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $attendance = new Attendance();
        $attendance->setEmployee($user);
        $attendance->setWorkDate(new \DateTime());
        $attendance->setStatus('present');

        $form = $this->createForm(AttendanceType::class, $attendance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier qu'il n'existe pas déjà une présence pour cette date
            $existingAttendance = $this->attendanceRepository->findOneBy([
                'employee' => $user,
                'workDate' => $attendance->getWorkDate()
            ]);

            if ($existingAttendance) {
                $this->addFlash('error', 'Une présence existe déjà pour cette date.');
                return $this->render('employee/attendance/new.html.twig', [
                    'attendance' => $attendance,
                    'form' => $form,
                ]);
            }

            // Vérifier que ce n'est pas un jour férié
            if ($this->isHoliday($attendance->getWorkDate())) {
                $this->addFlash('warning', 'Attention: cette date correspond à un jour férié.');
            }

            // Calculer les heures travaillées si check-in et check-out sont renseignés
            if ($attendance->getCheckIn() && $attendance->getCheckOut()) {
                $workedHours = $this->calculateWorkedHours(
                    $attendance->getCheckIn(),
                    $attendance->getCheckOut()
                );
                $attendance->setWorkedHours($workedHours);
            }

            $this->entityManager->persist($attendance);
            $this->entityManager->flush();

            $this->addFlash('success', 'Présence enregistrée avec succès.');

            return $this->redirectToRoute('app_employee_attendance_index');
        }

        return $this->render('employee/attendance/new.html.twig', [
            'attendance' => $attendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_employee_attendance_show', methods: ['GET'])]
    public function show(Attendance $attendance): Response
    {
        // Vérifier que l'employé ne peut voir que ses propres présences
        if ($attendance->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette présence.');
        }

        return $this->render('employee/attendance/show.html.twig', [
            'attendance' => $attendance,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_employee_attendance_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Attendance $attendance): Response
    {
        // Vérifier que l'employé ne peut modifier que ses propres présences
        if ($attendance->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette présence.');
        }

        // Vérifier que la présence peut encore être modifiée (max 7 jours)
        $daysDifference = $attendance->getWorkDate()->diff(new \DateTime())->days;
        if ($daysDifference > 7) {
            $this->addFlash('error', 'Vous ne pouvez modifier que les présences des 7 derniers jours.');
            return $this->redirectToRoute('app_employee_attendance_index');
        }

        $form = $this->createForm(AttendanceType::class, $attendance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer les heures travaillées
            if ($attendance->getCheckIn() && $attendance->getCheckOut()) {
                $workedHours = $this->calculateWorkedHours(
                    $attendance->getCheckIn(),
                    $attendance->getCheckOut()
                );
                $attendance->setWorkedHours($workedHours);
            } else {
                $attendance->setWorkedHours(0);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Présence modifiée avec succès.');

            return $this->redirectToRoute('app_employee_attendance_index');
        }

        return $this->render('employee/attendance/edit.html.twig', [
            'attendance' => $attendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_employee_attendance_delete', methods: ['POST'])]
    public function delete(Request $request, Attendance $attendance): Response
    {
        // Vérifier que l'employé ne peut supprimer que ses propres présences
        if ($attendance->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette présence.');
        }

        // Vérifier que la présence peut encore être supprimée (max 3 jours)
        $daysDifference = $attendance->getWorkDate()->diff(new \DateTime())->days;
        if ($daysDifference > 3) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que les présences des 3 derniers jours.');
            return $this->redirectToRoute('app_employee_attendance_index');
        }

        if ($this->isCsrfTokenValid('delete' . $attendance->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($attendance);
            $this->entityManager->flush();

            $this->addFlash('success', 'Présence supprimée avec succès.');
        }

        return $this->redirectToRoute('app_employee_attendance_index');
    }

    #[Route('/check-in', name: 'app_employee_attendance_check_in', methods: ['POST'])]
    public function checkIn(Request $request): Response
    {
        $user = $this->getUser();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Vérifier s'il existe déjà une présence pour aujourd'hui
        $existingAttendance = $this->attendanceRepository->findOneBy([
            'employee' => $user,
            'workDate' => $today
        ]);

        if ($existingAttendance && $existingAttendance->getCheckIn()) {
            $this->addFlash('error', 'Vous avez déjà pointé votre arrivée aujourd\'hui à ' . 
                $existingAttendance->getCheckIn()->format('H:i'));
            return $this->redirectToRoute('app_employee_attendance_index');
        }

        if ($this->isCsrfTokenValid('check_in', $request->request->get('_token'))) {
            $now = new \DateTime();
            
            if ($existingAttendance) {
                $existingAttendance->setCheckIn($now);
                $attendance = $existingAttendance;
            } else {
                $attendance = new Attendance();
                $attendance->setEmployee($user);
                $attendance->setWorkDate($today);
                $attendance->setCheckIn($now);
                $attendance->setStatus('present');
                $this->entityManager->persist($attendance);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Arrivée enregistrée à ' . $now->format('H:i'));
        }

        return $this->redirectToRoute('app_employee_attendance_index');
    }

    #[Route('/check-out', name: 'app_employee_attendance_check_out', methods: ['POST'])]
    public function checkOut(Request $request): Response
    {
        $user = $this->getUser();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $attendance = $this->attendanceRepository->findOneBy([
            'employee' => $user,
            'workDate' => $today
        ]);

        if (!$attendance || !$attendance->getCheckIn()) {
            $this->addFlash('error', 'Vous devez d\'abord enregistrer votre arrivée.');
            return $this->redirectToRoute('app_employee_attendance_index');
        }

        if ($attendance->getCheckOut()) {
            $this->addFlash('error', 'Vous avez déjà pointé votre départ aujourd\'hui à ' . 
                $attendance->getCheckOut()->format('H:i'));
            return $this->redirectToRoute('app_employee_attendance_index');
        }

        if ($this->isCsrfTokenValid('check_out', $request->request->get('_token'))) {
            $now = new \DateTime();
            $attendance->setCheckOut($now);
            
            // Calculer les heures travaillées
            $workedHours = $this->calculateWorkedHours($attendance->getCheckIn(), $now);
            $attendance->setWorkedHours($workedHours);

            $this->entityManager->flush();

            $this->addFlash('success', 'Départ enregistré à ' . $now->format('H:i') . 
                ' (' . $workedHours . 'h travaillées)');
        }

        return $this->redirectToRoute('app_employee_attendance_index');
    }

    #[Route('/report/{month}', name: 'app_employee_attendance_report', methods: ['GET'])]
    public function report(string $month): Response
    {
        $user = $this->getUser();
        $dateFilter = new \DateTime($month . '-01');
        
        $startDate = clone $dateFilter;
        $endDate = clone $dateFilter;
        $endDate->modify('last day of this month');
        
        $attendances = $this->attendanceRepository->findAttendancesByDateRange(
            $user, $startDate, $endDate
        );
        
        $report = $this->generateMonthlyReport($user, $dateFilter, $attendances);
        
        return $this->render('employee/attendance/report.html.twig', [
            'report' => $report,
            'month' => $month,
            'attendances' => $attendances,
        ]);
    }

    #[Route('/export/{month}', name: 'app_employee_attendance_export', methods: ['GET'])]
    public function export(string $month): Response
    {
        $user = $this->getUser();
        $dateFilter = new \DateTime($month . '-01');
        
        $startDate = clone $dateFilter;
        $endDate = clone $dateFilter;
        $endDate->modify('last day of this month');
        
        $attendances = $this->attendanceRepository->findAttendancesByDateRange(
            $user, $startDate, $endDate
        );
        
        $csvContent = $this->generateCSVContent($attendances, $month);
        
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="presences_' . $month . '.csv"');
        
        return $response;
    }

    /**
     * Calculer les heures travaillées entre deux timestamps
     */
    private function calculateWorkedHours(\DateTimeInterface $checkIn, \DateTimeInterface $checkOut): int
    {
        $diff = $checkOut->getTimestamp() - $checkIn->getTimestamp();
        return (int) round($diff / 3600); // Convertir en heures
    }

    /**
     * Vérifier si une date est un jour férié
     */
    private function isHoliday(\DateTimeInterface $date): bool
    {
        $holiday = $this->holidayRepository->findOneBy([
            'date' => $date,
            'isActive' => true
        ]);
        
        return $holiday !== null;
    }

    /**
     * Calculer le résumé journalier
     */
    private function calculateDailySummary($user, \DateTimeInterface $date): array
    {
        $attendance = $this->attendanceRepository->findOneBy([
            'employee' => $user,
            'workDate' => $date
        ]);

        return [
            'date' => $date,
            'attendance' => $attendance,
            'is_holiday' => $this->isHoliday($date),
            'is_weekend' => in_array($date->format('N'), [6, 7]),
        ];
    }

    /**
     * Calculer le résumé hebdomadaire
     */
    private function calculateWeeklySummary($user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $attendances = $this->attendanceRepository->findAttendancesByDateRange($user, $startDate, $endDate);
        
        $totalHours = 0;
        $presentDays = 0;
        $lateDays = 0;
        
        foreach ($attendances as $attendance) {
            $totalHours += $attendance->getWorkedHours();
            if ($attendance->getStatus() === 'present') {
                $presentDays++;
            }
            if ($attendance->getCheckIn() && $attendance->getCheckIn()->format('H:i') > '09:00') {
                $lateDays++;
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_hours' => $totalHours,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'average_hours' => $presentDays > 0 ? round($totalHours / $presentDays, 1) : 0,
        ];
    }

    /**
     * Calculer le résumé mensuel
     */
    private function calculateMonthlySummary($user, \DateTimeInterface $month): array
    {

    $startDate = clone $month;
    $endDate = \DateTime::createFromInterface($month);
    $endDate->modify('last day of this month');
    
    $attendances = $this->attendanceRepository->findAttendancesByDateRange($user, $startDate, $endDate);
    
    $totalHours = 0;
    $presentDays = 0;
    $absentDays = 0;
    $lateDays = 0;
    $workingDays = $this->countWorkingDays($startDate, $endDate);
    
    foreach ($attendances as $attendance) {
        $totalHours += $attendance->getWorkedHours();
        
        switch ($attendance->getStatus()) {
            case 'present':
                $presentDays++;
                break;
            case 'absent':
                $absentDays++;
                break;
        }
        
        if ($attendance->getCheckIn() && $attendance->getCheckIn()->format('H:i') > '09:00') {
            $lateDays++;
        }
    }

    return [
        'month' => $month,
        'working_days' => $workingDays,
        'total_hours' => $totalHours,
        'present_days' => $presentDays,
        'absent_days' => $absentDays,
        'late_days' => $lateDays,
        'attendance_rate' => $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 0,
        'average_hours' => $presentDays > 0 ? round($totalHours / $presentDays, 1) : 0,
    ];
    }

    /**
     * Compter les jours ouvrables
     */
    private function countWorkingDays(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $count = 0;
        //$current = clone $startDate;
        $current = \DateTime::createFromInterface($startDate);
        
        while ($current <= $endDate) {
            if (!in_array($current->format('N'), [6, 7]) && !$this->isHoliday($current)) {
                $count++;
            }
            $current->modify('+1 day');
        }
        
        return $count;
    }

    /**
     * Récupérer les périodes disponibles
     */
    private function getAvailablePeriods($user): array
    {
        $periods = [];
        $currentDate = new \DateTime();
        $startDate = $user->getHireDate() ?: new \DateTime('-1 year');
        
        $current = clone $startDate;
        $current->modify('first day of this month');
        
        while ($current <= $currentDate) {
            $periods[] = $current->format('Y-m');
            $current->modify('+1 month');
        }
        
        return array_reverse($periods);
    }

    /**
     * Générer un rapport mensuel détaillé
     */
    private function generateMonthlyReport($user, \DateTimeInterface $month, array $attendances): array
    {

           // Validation de l'entrée
    if (!$month instanceof \DateTime && !$month instanceof \DateTimeImmutable) {
        throw new \InvalidArgumentException('Le paramètre $month doit être une instance de DateTime');
    }

    // Conversion en DateTime standard si nécessaire
    $monthDate = \DateTime::createFromInterface($month);

    $summary = $this->calculateMonthlySummary($user, $monthDate);
    
    // Analyse par semaine
    $weeklyAnalysis = [];
    $current = clone $monthDate;
    $weekNumber = 1;
    
    while ($current->format('Y-m') === $monthDate->format('Y-m')) {
        $weekStart = clone $current;
        $weekStart->modify('monday this week');
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
        
        if ($weekEnd->format('Y-m') !== $monthDate->format('Y-m')) {
            $weekEnd = clone $monthDate;
            $weekEnd->modify('last day of this month');
        }
        
        $weeklyAnalysis[] = $this->calculateWeeklySummary($user, $weekStart, $weekEnd);
        $current->modify('+1 week');
        $weekNumber++;
    }
    
    return [
        'summary' => $summary,
        'weekly_analysis' => $weeklyAnalysis,
        'tardiness_trend' => $this->calculateTardinessTrend($attendances),
    ];

    }

    /**
     * Calculer la tendance des retards
     */
    private function calculateTardinessTrend(array $attendances): array
    {
        $weeklyLateCount = [];
        
        foreach ($attendances as $attendance) {
            if ($attendance->getCheckIn()) {
                $week = $attendance->getWorkDate()->format('W');
                if (!isset($weeklyLateCount[$week])) {
                    $weeklyLateCount[$week] = 0;
                }
                
                if ($attendance->getCheckIn()->format('H:i') > '09:00') {
                    $weeklyLateCount[$week]++;
                }
            }
        }
        
        return $weeklyLateCount;
    }

    /**
     * Générer le contenu CSV
     */
    private function generateCSVContent(array $attendances, string $month): string
    {
        $csv = [];
        
        // En-têtes
        $csv[] = [
            'Date',
            'Arrivée',
            'Départ',
            'Heures travaillées',
            'Statut',
            'Notes'
        ];
        
        foreach ($attendances as $attendance) {
            $csv[] = [
                $attendance->getWorkDate()->format('d/m/Y'),
                $attendance->getCheckIn() ? $attendance->getCheckIn()->format('H:i') : '',
                $attendance->getCheckOut() ? $attendance->getCheckOut()->format('H:i') : '',
                $attendance->getWorkedHours() . 'h',
                $attendance->getStatus(),
                $attendance->getNotes() ?: ''
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