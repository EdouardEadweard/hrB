<?php

namespace App\Controller\Manager;

use App\Entity\LeaveRequest;
use App\Entity\Notification;
use App\Form\Manager\ApprovalType;
use App\Repository\LeaveRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager/approval')]
#[IsGranted('ROLE_MANAGER')]
class ApprovalController extends AbstractController
{
   #[Route('/', name: 'manager_approval_index', methods: ['GET'])]
    public function index(LeaveRequestRepository $leaveRequestRepository): Response
    {
        /** @var User $manager */
        $manager = $this->getUser();

        // Récupérer les demandes en attente pour les employés sous la responsabilité du manager
        $pendingRequests = $leaveRequestRepository->findPendingRequestsForManager($manager);
        
        // Récupérer toutes les demandes traitées par ce manager
        $processedRequests = $leaveRequestRepository->findProcessedRequestsByManager($manager);

        // Combiner toutes les demandes pour l'affichage principal
        $allRequests = array_merge($pendingRequests, $processedRequests);
        
        // Trier par date de soumission (plus récentes en premier)
        usort($allRequests, function($a, $b) {
            return $b->getSubmittedAt() <=> $a->getSubmittedAt();
        });

        // Séparer les demandes par statut
        $pendingCount = count($pendingRequests);
        $approvedRequests = array_filter($processedRequests, fn($req) => $req->getStatus() === 'approved');
        $rejectedRequests = array_filter($processedRequests, fn($req) => $req->getStatus() === 'rejected');

        return $this->render('manager/approval/index.html.twig', [
            'leave_requests' => $allRequests,
            'pending_requests' => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
            'pending_count' => $pendingCount,
            'approved_count' => count($approvedRequests),
            'rejected_count' => count($rejectedRequests),
            // Utilisation des nouvelles méthodes du repository
            'approved_today' => $leaveRequestRepository->countApprovedTodayByManager($manager),
            'rejected_today' => $leaveRequestRepository->countRejectedTodayByManager($manager),
            'urgent_count' => $leaveRequestRepository->countUrgentRequestsForManager($manager),
        ]);
    }

    #[Route('/{id}', name: 'manager_approval_show', methods: ['GET'])]
    public function show(LeaveRequest $leaveRequest): Response
    {
        // Vérifier que le manager peut voir cette demande
        $this->checkManagerAccess($leaveRequest);

        return $this->render('manager/approval/show.html.twig', [
            'leaveRequest' => $leaveRequest,
        ]);
    }

    #[Route('/{id}/approve', name: 'manager_approval_approve', methods: ['GET', 'POST'])]
    public function approve(Request $request, LeaveRequest $leaveRequest, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que le manager peut traiter cette demande
        $this->checkManagerAccess($leaveRequest);

        // Vérifier que la demande est en attente
        if ($leaveRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('manager_approval_index');
        }

        $form = $this->createForm(ApprovalType::class, $leaveRequest, [
            'action_type' => 'approve'
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Approuver la demande
            $leaveRequest->setStatus('approved');
            $leaveRequest->setApprovedBy($this->getUser());
            $leaveRequest->setProcessedAt(new \DateTimeImmutable());
            
            // Mettre à jour le solde de congés si nécessaire
            $this->updateLeaveBalance($leaveRequest);
            
            // Créer une notification pour l'employé
            $this->createNotification(
                $leaveRequest->getEmployee(),
                'Demande de congé approuvée',
                sprintf(
                    'Votre demande de congé du %s au %s a été approuvée par %s.',
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y'),
                    $this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName()
                ),
                'leave_approved',
                $leaveRequest,
                $entityManager
            );

            $entityManager->flush();

            $this->addFlash('success', 'La demande de congé a été approuvée avec succès.');

            return $this->redirectToRoute('manager_approval_index');
        }

        return $this->render('manager/approval/approve.html.twig', [
            'leaveRequest' => $leaveRequest,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/reject', name: 'manager_approval_reject', methods: ['GET', 'POST'])]
    public function reject(Request $request, LeaveRequest $leaveRequest, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que le manager peut traiter cette demande
        $this->checkManagerAccess($leaveRequest);

        // Vérifier que la demande est en attente
        if ($leaveRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('manager_approval_index');
        }

        $form = $this->createForm(ApprovalType::class, $leaveRequest, [
            'action_type' => 'reject'
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Rejeter la demande - définir les valeurs directement sur l'entité
            $leaveRequest->setStatus('rejected');
            $leaveRequest->setApprovedBy($this->getUser());
            $leaveRequest->setProcessedAt(new \DateTimeImmutable());

            // Créer une notification pour l'employé
            $this->createNotification(
                $leaveRequest->getEmployee(),
                'Demande de congé rejetée',
                sprintf(
                    'Votre demande de congé du %s au %s a été rejetée par %s. Motif: %s',
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y'),
                    $this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName(),
                    $leaveRequest->getManagerComment() ?: 'Aucun motif spécifié'
                ),
                'leave_rejected',
                $leaveRequest,
                $entityManager
            );

            $entityManager->flush();

            $this->addFlash('success', 'La demande de congé a été rejetée.');

            return $this->redirectToRoute('manager_approval_index');
        }

        return $this->render('manager/approval/reject.html.twig', [
            'leaveRequest' => $leaveRequest,
            'form' => $form,
        ]);
    }

    #[Route('/pending', name: 'manager_approval_pending', methods: ['GET'])]
    public function pending(LeaveRequestRepository $leaveRequestRepository): Response
    {
        /** @var User $manager */
        $manager = $this->getUser();

        $pendingRequests = $leaveRequestRepository->findPendingRequestsForManager($manager);

        return $this->render('manager/approval/pending.html.twig', [
            'pending_requests' => $pendingRequests,
        ]);
    }

    #[Route('/processed', name: 'manager_approval_processed', methods: ['GET'])]
    public function processed(LeaveRequestRepository $leaveRequestRepository): Response
    {
        /** @var User $manager */
        $manager = $this->getUser();

        $processedRequests = $leaveRequestRepository->findProcessedRequestsByManager($manager);

        return $this->render('manager/approval/processed.html.twig', [
            'processed_requests' => $processedRequests,
        ]);
    }

    #[Route('/bulk-action', name: 'manager_approval_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, LeaveRequestRepository $leaveRequestRepository, EntityManagerInterface $entityManager): Response
    {
        $action = $request->request->get('action');
        $requestIds = $request->request->all('request_ids');

        if (!$action || !$requestIds) {
            $this->addFlash('error', 'Aucune action ou demande sélectionnée.');
            return $this->redirectToRoute('manager_approval_index');
        }

        $validActions = ['approve', 'reject'];
        if (!in_array($action, $validActions)) {
            $this->addFlash('error', 'Action invalide.');
            return $this->redirectToRoute('manager_approval_index');
        }

        $processedCount = 0;
        $comment = $request->request->get('bulk_comment', '');

        foreach ($requestIds as $requestId) {
            $leaveRequest = $leaveRequestRepository->find($requestId);
            
            if (!$leaveRequest || $leaveRequest->getStatus() !== 'pending') {
                continue;
            }

            // Vérifier l'accès du manager
            try {
                $this->checkManagerAccess($leaveRequest);
            } catch (\Exception $e) {
                continue;
            }

            // Traiter la demande
            $leaveRequest->setStatus($action === 'approve' ? 'approved' : 'rejected');
            $leaveRequest->setApprovedBy($this->getUser());
            $leaveRequest->setProcessedAt(new \DateTimeImmutable());
            
            if ($comment) {
                $leaveRequest->setManagerComment($comment);
            }

            if ($action === 'approve') {
                $this->updateLeaveBalance($leaveRequest);
            }

            // Créer une notification
            $this->createNotification(
                $leaveRequest->getEmployee(),
                $action === 'approve' ? 'Demande de congé approuvée' : 'Demande de congé rejetée',
                sprintf(
                    'Votre demande de congé du %s au %s a été %s par %s.',
                    $leaveRequest->getStartDate()->format('d/m/Y'),
                    $leaveRequest->getEndDate()->format('d/m/Y'),
                    $action === 'approve' ? 'approuvée' : 'rejetée',
                    $this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName()
                ),
                $action === 'approve' ? 'leave_approved' : 'leave_rejected',
                $leaveRequest,
                $entityManager
            );

            $processedCount++;
        }

        $entityManager->flush();

        if ($processedCount > 0) {
            $actionText = $action === 'approve' ? 'approuvées' : 'rejetées';
            $this->addFlash('success', sprintf('%d demande(s) %s avec succès.', $processedCount, $actionText));
        } else {
            $this->addFlash('warning', 'Aucune demande n\'a pu être traitée.');
        }

        return $this->redirectToRoute('manager_approval_index');
    }

    #[Route('/filter/{status}', name: 'manager_approval_filter', methods: ['GET'])]
    public function filterByStatus(string $status, LeaveRequestRepository $leaveRequestRepository): Response
    {
        $validStatuses = ['pending', 'approved', 'rejected'];
        
        if (!in_array($status, $validStatuses)) {
            throw $this->createNotFoundException('Statut invalide.');
        }

        /** @var User $manager */
        $manager = $this->getUser();

        if ($status === 'pending') {
            $requests = $leaveRequestRepository->findPendingRequestsForManager($manager);
        } else {
            $requests = $leaveRequestRepository->findBy([
                'approvedBy' => $manager,
                'status' => $status
            ], ['processedAt' => 'DESC']);
        }

        return $this->render('manager/approval/filter.html.twig', [
            'requests' => $requests,
            'current_status' => $status,
        ]);
    }

    #[Route('/employee/{id}/history', name: 'manager_approval_employee_history', methods: ['GET'])]
    public function employeeHistory(int $id, UserRepository $userRepository, LeaveRequestRepository $leaveRequestRepository): Response
    {
        $employee = $userRepository->find($id);
        
        if (!$employee) {
            throw $this->createNotFoundException('Employé non trouvé.');
        }

        // Vérifier que cet employé est sous la responsabilité du manager
        if ($employee->getManager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir l\'historique de cet employé.');
        }

        $leaveHistory = $leaveRequestRepository->findBy(
            ['employee' => $employee],
            ['submittedAt' => 'DESC']
        );

        return $this->render('manager/approval/employee_history.html.twig', [
            'employee' => $employee,
            'leave_history' => $leaveHistory,
        ]);
    }

    #[Route('/statistics', name: 'manager_approval_statistics', methods: ['GET'])]
    public function statistics(LeaveRequestRepository $leaveRequestRepository): Response
    {
        /** @var User $manager */
        $manager = $this->getUser();

        // Statistiques des demandes traitées par ce manager
        $stats = [
            'total_processed' => $leaveRequestRepository->countProcessedByManager($manager),
            'total_approved' => $leaveRequestRepository->countByManagerAndStatus($manager, 'approved'),
            'total_rejected' => $leaveRequestRepository->countByManagerAndStatus($manager, 'rejected'),
            'pending_count' => $leaveRequestRepository->countPendingForManager($manager),
        ];

        // Calcul du taux d'approbation
        $stats['approval_rate'] = $stats['total_processed'] > 0 
            ? round(($stats['total_approved'] / $stats['total_processed']) * 100, 2)
            : 0;

        return $this->render('manager/approval/statistics.html.twig', [
            'statistics' => $stats,
        ]);
    }

    /**
     * Vérifier que le manager a accès à cette demande de congé
     */
    private function checkManagerAccess(LeaveRequest $leaveRequest): void
    {
        $manager = $this->getUser();
        
        // Vérifier que l'employé qui a fait la demande est sous la responsabilité de ce manager
        if ($leaveRequest->getEmployee()->getManager() !== $manager) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas traiter cette demande.');
        }
    }

    /**
     * Mettre à jour le solde de congés après approbation
     */
    private function updateLeaveBalance(LeaveRequest $leaveRequest): void
    {
        $employee = $leaveRequest->getEmployee();
        $leaveType = $leaveRequest->getLeaveType();
        $numberOfDays = $leaveRequest->getNumberOfDays();
        $year = $leaveRequest->getStartDate()->format('Y');

        // Trouver le solde de congés correspondant
        $leaveBalance = null;
        foreach ($employee->getLeaveBalances() as $balance) {
            if ($balance->getLeaveType() === $leaveType && $balance->getYear() == $year) {
                $leaveBalance = $balance;
                break;
            }
        }

        if ($leaveBalance) {
            $leaveBalance->setUsedDays($leaveBalance->getUsedDays() + $numberOfDays);
            $leaveBalance->setRemainingDays($leaveBalance->getTotalDays() - $leaveBalance->getUsedDays());
            $leaveBalance->setLastUpdated(new \DateTime());
        }
    }

    /**
     * Créer une notification pour l'employé
     */
    private function createNotification(
        $recipient, 
        string $title, 
        string $message, 
        string $type, 
        LeaveRequest $leaveRequest, 
        EntityManagerInterface $entityManager
    ): void {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setSender($this->getUser());
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setLeaveRequest($leaveRequest);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());

        $entityManager->persist($notification);
    }
}