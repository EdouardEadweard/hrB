<?php

namespace App\Controller\Employee;

use App\Entity\LeaveRequest;
use App\Entity\LeaveType;
use App\Entity\LeaveBalance;
use App\Entity\Notification;
use App\Form\Employee\LeaveRequestType;
use App\Repository\LeaveRequestRepository;
use App\Repository\LeaveTypeRepository;
use App\Repository\LeaveBalanceRepository;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/leave-request')]
#[IsGranted('ROLE_EMPLOYEE')]
class LeaveRequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LeaveRequestRepository $leaveRequestRepository,
        private LeaveTypeRepository $leaveTypeRepository,
        private LeaveBalanceRepository $leaveBalanceRepository,
        private NotificationRepository $notificationRepository
    ) {
    }

    #[Route('/', name: 'app_employee_leave_request_index', methods: ['GET'])]
    public function index(): Response
    {
         $user = $this->getUser();
        $leaveRequests = $this->leaveRequestRepository->findBy(
            ['employee' => $user],
            ['createdAt' => 'DESC']
        );

        // Calculer les statistiques
        $stats = [
            'total' => count($leaveRequests),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];

        foreach ($leaveRequests as $request) {
            switch ($request->getStatus()) {
                case LeaveRequest::STATUS_PENDING:
                    $stats['pending']++;
                    break;
                case LeaveRequest::STATUS_APPROVED:
                    $stats['approved']++;
                    break;
                case LeaveRequest::STATUS_REJECTED:
                    $stats['rejected']++;
                    break;
            }
        }

        return $this->render('employee/leave_request/index.html.twig', [
            'leave_requests' => $leaveRequests,
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'app_employee_leave_request_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $leaveRequest = new LeaveRequest();
        $leaveRequest->setEmployee($user);
        $leaveRequest->setStatus(LeaveRequest::STATUS_PENDING);
        $leaveRequest->setSubmittedAt(new \DateTimeImmutable());

        $form = $this->createForm(LeaveRequestType::class, $leaveRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer le nombre de jours
            $startDate = $leaveRequest->getStartDate();
            $endDate = $leaveRequest->getEndDate();
            $numberOfDays = $this->calculateWorkingDays($startDate, $endDate);
            $leaveRequest->setNumberOfDays($numberOfDays);

            // Vérifier le solde disponible
            $leaveType = $leaveRequest->getLeaveType();
            $currentYear = (int) date('Y');
            $leaveBalance = $this->leaveBalanceRepository->findOneBy([
                'employee' => $user,
                'leaveType' => $leaveType,
                'year' => $currentYear
            ]);

            if ($leaveBalance && $leaveBalance->getRemainingDays() < $numberOfDays) {
                $this->addFlash('error', 'Solde insuffisant pour ce type de congé. Jours disponibles: ' . $leaveBalance->getRemainingDays());
                return $this->render('employee/leave_request/new.html.twig', [
                    'leave_request' => $leaveRequest,
                    'form' => $form,
                ]);
            }

            $this->entityManager->persist($leaveRequest);
            $this->entityManager->flush();

            // Créer une notification pour le manager
            $this->createNotificationForManager($leaveRequest);

            $this->addFlash('success', 'Votre demande de congé a été soumise avec succès.');

            return $this->redirectToRoute('app_employee_leave_request_index');
        }

        return $this->render('employee/leave_request/new.html.twig', [
            'leave_request' => $leaveRequest,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_employee_leave_request_show', methods: ['GET'])]
    public function show(LeaveRequest $leaveRequest): Response
    {
        // Vérifier que l'employé ne peut voir que ses propres demandes
        if ($leaveRequest->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette demande.');
        }

        return $this->render('employee/leave_request/show.html.twig', [
            'leave_request' => $leaveRequest,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_employee_leave_request_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LeaveRequest $leaveRequest): Response
    {
        // Vérifier que l'employé ne peut modifier que ses propres demandes
        if ($leaveRequest->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette demande.');
        }

        // Vérifier que la demande peut encore être modifiée
        if ($leaveRequest->getStatus() !== LeaveRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Vous ne pouvez modifier que les demandes en attente.');
            return $this->redirectToRoute('app_employee_leave_request_index');
        }

        $form = $this->createForm(LeaveRequestType::class, $leaveRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le nombre de jours
            $startDate = $leaveRequest->getStartDate();
            $endDate = $leaveRequest->getEndDate();
            $numberOfDays = $this->calculateWorkingDays($startDate, $endDate);
            $leaveRequest->setNumberOfDays($numberOfDays);

            // Vérifier à nouveau le solde disponible
            $user = $this->getUser();
            $leaveType = $leaveRequest->getLeaveType();
            $currentYear = (int) date('Y');
            $leaveBalance = $this->leaveBalanceRepository->findOneBy([
                'employee' => $user,
                'leaveType' => $leaveType,
                'year' => $currentYear
            ]);

            if ($leaveBalance && $leaveBalance->getRemainingDays() < $numberOfDays) {
                $this->addFlash('error', 'Solde insuffisant pour ce type de congé. Jours disponibles: ' . $leaveBalance->getRemainingDays());
                return $this->render('employee/leave_request/edit.html.twig', [
                    'leave_request' => $leaveRequest,
                    'form' => $form,
                ]);
            }

            $this->entityManager->flush();

            // Créer une notification de modification pour le manager
            $this->createNotificationForManager($leaveRequest, 'modified');

            $this->addFlash('success', 'Votre demande de congé a été modifiée avec succès.');

            return $this->redirectToRoute('app_employee_leave_request_index');
        }

        return $this->render('employee/leave_request/edit.html.twig', [
            'leaveRequest' => $leaveRequest,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_employee_leave_request_delete', methods: ['POST'])]
    public function delete(Request $request, LeaveRequest $leaveRequest): Response
    {
        // Vérifier que l'employé ne peut supprimer que ses propres demandes
        if ($leaveRequest->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette demande.');
        }

        // Vérifier que la demande peut encore être supprimée
        if ($leaveRequest->getStatus() !== LeaveRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que les demandes en attente.');
            return $this->redirectToRoute('app_employee_leave_request_index');
        }

        if ($this->isCsrfTokenValid('delete' . $leaveRequest->getId(), $request->request->get('_token'))) {
            // Créer une notification d'annulation pour le manager
            $this->createNotificationForManager($leaveRequest, 'cancelled');

            $this->entityManager->remove($leaveRequest);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande de congé a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_employee_leave_request_index');
    }

    #[Route('/pending', name: 'app_employee_leave_request_pending', methods: ['GET'])]
    public function pending(): Response
    {
        $user = $this->getUser();
        $pendingRequests = $this->leaveRequestRepository->findBy([
            'employee' => $user,
            'status' => LeaveRequest::STATUS_PENDING
        ], ['createdAt' => 'DESC']);

        return $this->render('employee/leave_request/pending.html.twig', [
            'pending_requests' => $pendingRequests,
        ]);
    }

    #[Route('/approved', name: 'app_employee_leave_request_approved', methods: ['GET'])]
    public function approved(): Response
    {
        $user = $this->getUser();
        $approvedRequests = $this->leaveRequestRepository->findBy([
            'employee' => $user,
            'status' => LeaveRequest::STATUS_APPROVED
        ], ['createdAt' => 'DESC']);

        return $this->render('employee/leave_request/approved.html.twig', [
            'approved_requests' => $approvedRequests,
        ]);
    }

    #[Route('/rejected', name: 'app_employee_leave_request_rejected', methods: ['GET'])]
    public function rejected(): Response
    {
        $user = $this->getUser();
        $rejectedRequests = $this->leaveRequestRepository->findBy([
            'employee' => $user,
            'status' => LeaveRequest::STATUS_REJECTED
        ], ['createdAt' => 'DESC']);

        return $this->render('employee/leave_request/rejected.html.twig', [
            'rejected_requests' => $rejectedRequests,
        ]);
    }

    /**
     * Calculer le nombre de jours ouvrables entre deux dates
     */
    private function calculateWorkingDays(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $days = 0;
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('N');
            // Exclure samedi (6) et dimanche (7)
            if ($dayOfWeek < 6) {
                $days++;
            }
            $currentDate->modify('+1 day');
        }

        return $days;
    }

    /**
     * Créer une notification pour le manager
     */
    private function createNotificationForManager(LeaveRequest $leaveRequest, string $action = 'submitted'): void
    {
        $user = $this->getUser();
        $manager = $user->getManager();

        if (!$manager) {
            return;
        }

        $notification = new Notification();
        $notification->setRecipient($manager);
        $notification->setSender($user);
        $notification->setLeaveRequest($leaveRequest);
        $notification->setType('leave_request');
        $notification->setIsRead(false);

        switch ($action) {
            case 'submitted':
                $notification->setTitle('Nouvelle demande de congé');
                $notification->setMessage(sprintf(
                    '%s %s a soumis une nouvelle demande de congé du %s au %s.',
                    $user->getFirstName(),
                    $user->getLastName(),
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y')
                ));
                break;
            
            case 'modified':
                $notification->setTitle('Demande de congé modifiée');
                $notification->setMessage(sprintf(
                    '%s %s a modifié sa demande de congé du %s au %s.',
                    $user->getFirstName(),
                    $user->getLastName(),
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y')
                ));
                break;
            
            case 'cancelled':
                $notification->setTitle('Demande de congé annulée');
                $notification->setMessage(sprintf(
                    '%s %s a annulé sa demande de congé du %s au %s.',
                    $user->getFirstName(),
                    $user->getLastName(),
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y')
                ));
                break;
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}