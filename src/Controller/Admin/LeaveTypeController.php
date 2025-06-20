<?php

namespace App\Controller\Admin;

use App\Entity\LeaveType;
use App\Form\Admin\LeaveTypeType;
use App\Repository\LeaveTypeRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\LeaveBalanceRepository;
use App\Repository\LeavePolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/leave-type')]
#[IsGranted('ROLE_ADMIN')]
class LeaveTypeController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LeaveTypeRepository $leaveTypeRepository;
    private LeaveRequestRepository $leaveRequestRepository;
    private LeaveBalanceRepository $leaveBalanceRepository;
    private LeavePolicyRepository $leavePolicyRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LeaveTypeRepository $leaveTypeRepository,
        LeaveRequestRepository $leaveRequestRepository,
        LeaveBalanceRepository $leaveBalanceRepository,
        LeavePolicyRepository $leavePolicyRepository
    ) {
        $this->entityManager = $entityManager;
        $this->leaveTypeRepository = $leaveTypeRepository;
        $this->leaveRequestRepository = $leaveRequestRepository;
        $this->leaveBalanceRepository = $leaveBalanceRepository;
        $this->leavePolicyRepository = $leavePolicyRepository;
    }

    #[Route('/', name: 'admin_leave_type_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all'); // all, active, inactive
        $requiresApproval = $request->query->get('approval', 'all'); // all, yes, no
        $isPaid = $request->query->get('paid', 'all'); // all, yes, no
        $sortBy = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        // Construction de la requête avec filtres
        $queryBuilder = $this->leaveTypeRepository->createQueryBuilder('lt');

        // Recherche par nom, code ou description
        if (!empty($search)) {
            $queryBuilder
                ->andWhere('lt.name LIKE :search OR lt.code LIKE :search OR lt.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtre par statut
        if ($status !== 'all') {
            $isActive = $status === 'active';
            $queryBuilder
                ->andWhere('lt.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // Filtre par approbation requise
        if ($requiresApproval !== 'all') {
            $needsApproval = $requiresApproval === 'yes';
            $queryBuilder
                ->andWhere('lt.requiresApproval = :requiresApproval')
                ->setParameter('requiresApproval', $needsApproval);
        }

        // Filtre par type payé/non payé
        if ($isPaid !== 'all') {
            $paidStatus = $isPaid === 'yes';
            $queryBuilder
                ->andWhere('lt.isPaid = :isPaid')
                ->setParameter('isPaid', $paidStatus);
        }

        // Tri
        $validSortFields = ['name', 'code', 'maxDaysPerYear', 'createdAt', 'isActive'];
        if (in_array($sortBy, $validSortFields)) {
            $queryBuilder->orderBy('lt.' . $sortBy, strtoupper($order) === 'DESC' ? 'DESC' : 'ASC');
        }

        // Pagination
        $totalLeaveTypes = (clone $queryBuilder)->select('COUNT(lt.id)')->getQuery()->getSingleScalarResult();
        $leaveTypes = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalLeaveTypes / $limit);

        // Statistiques détaillées pour le dashboard
        $stats = [
            'total' => $this->leaveTypeRepository->count([]),
            'active' => $this->leaveTypeRepository->count(['isActive' => true]),
            'inactive' => $this->leaveTypeRepository->count(['isActive' => false]),
            'requires_approval' => $this->leaveTypeRepository->count(['requiresApproval' => true]),
            'paid' => $this->leaveTypeRepository->count(['isPaid' => true]),
            'unpaid' => $this->leaveTypeRepository->count(['isPaid' => false]),
        ];

        // Calcul des moyennes pour insights UX
        $allTypes = $this->leaveTypeRepository->findAll();
        $avgMaxDays = 0;
        if (!empty($allTypes)) {
            $totalMaxDays = array_sum(array_map(fn($lt) => $lt->getMaxDaysPerYear() ?: 0, $allTypes));
            $avgMaxDays = round($totalMaxDays / count($allTypes), 1);
        }
        $stats['avg_max_days'] = $avgMaxDays;

        return $this->render('admin/leave_type/index.html.twig', [
            'leave_types' => $leaveTypes,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'requiresApproval' => $requiresApproval,
            'isPaid' => $isPaid,
            'sortBy' => $sortBy,
            'order' => $order,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLeaveTypes' => $totalLeaveTypes,
        ]);
    }

    #[Route('/new', name: 'admin_leave_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $leaveType = new LeaveType();
        
        // Pré-remplissage intelligent pour améliorer l'UX
        $leaveType->setIsActive(true);
        $leaveType->setRequiresApproval(true);
        $leaveType->setIsPaid(true);
        $leaveType->setMaxDaysPerYear(25); // Valeur par défaut standard
        $leaveType->setColor('#007bff'); // Couleur par défaut
        $leaveType->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(LeaveTypeType::class, $leaveType);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérification de l'unicité du code
                $existingByCode = $this->leaveTypeRepository->findOneBy(['code' => $leaveType->getCode()]);
                if ($existingByCode) {
                    $this->addFlash('error', 'Un type de congé avec ce code existe déjà.');
                    return $this->render('admin/leave_type/new.html.twig', [
                        'leave_type' => $leaveType,
                        'form' => $form->createView(),
                    ]);
                }

                // Vérification de l'unicité du nom
                $existingByName = $this->leaveTypeRepository->findOneBy(['name' => $leaveType->getName()]);
                if ($existingByName) {
                    $this->addFlash('error', 'Un type de congé avec ce nom existe déjà.');
                    return $this->render('admin/leave_type/new.html.twig', [
                        'leave_type' => $leaveType,
                        'form' => $form->createView(),
                    ]);
                }

                // Validation métier
                if ($leaveType->getMaxDaysPerYear() && $leaveType->getMaxDaysPerYear() < 0) {
                    $this->addFlash('error', 'Le nombre maximum de jours par an ne peut pas être négatif.');
                    return $this->render('admin/leave_type/new.html.twig', [
                        'leave_type' => $leaveType,
                        'form' => $form->createView(),
                    ]);
                }

                try {
                    $this->entityManager->persist($leaveType);
                    $this->entityManager->flush();

                    $this->addFlash('success', sprintf(
                        'Le type de congé "%s" a été créé avec succès.',
                        $leaveType->getName()
                    ));

                    // Redirection intelligente
                    /*$submitButton = $form->getClickedButton();
                    if ($submitButton && $submitButton->getName() === 'saveAndAdd') {
                        return $this->redirectToRoute('admin_leave_type_new');
                    }*/

                    // Vérification alternative du bouton cliqué
                    if ($request->request->has('saveAndAdd')) {
                        return $this->redirectToRoute('admin_leave_type_new');
                    }

                    return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la création du type de congé.');
                }
            } else {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/leave_type/new.html.twig', [
            'leave_type' => $leaveType,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_leave_type_show', methods: ['GET'])]
    public function show(LeaveType $leaveType): Response
    {
        // Récupération des données associées pour enrichir la vue
        $leaveRequests = $this->leaveRequestRepository->findBy(['leaveType' => $leaveType], ['createdAt' => 'DESC'], 10);
        $totalRequests = $this->leaveRequestRepository->count(['leaveType' => $leaveType]);
        
        // Statistiques des demandes par statut
        $requestStats = [
            'pending' => $this->leaveRequestRepository->count(['leaveType' => $leaveType, 'status' => 'pending']),
            'approved' => $this->leaveRequestRepository->count(['leaveType' => $leaveType, 'status' => 'approved']),
            'rejected' => $this->leaveRequestRepository->count(['leaveType' => $leaveType, 'status' => 'rejected']),
        ];

        // Soldes de congés actifs pour ce type
        $activeBalances = $this->leaveBalanceRepository->count(['leaveType' => $leaveType]);
        
        // Politiques associées
        $policies = $this->leavePolicyRepository->findBy(['leaveType' => $leaveType]);
        
        // Calculs pour insights UX
        $currentYear = (new \DateTime())->format('Y');
        $yearlyRequests = $this->leaveRequestRepository->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.leaveType = :leaveType')
            ->andWhere('YEAR(lr.startDate) = :year')
            ->setParameter('leaveType', $leaveType)
            ->setParameter('year', $currentYear)
            ->getQuery()
            ->getSingleScalarResult();

        $stats = [
            'total_requests' => $totalRequests,
            'yearly_requests' => $yearlyRequests,
            'active_balances' => $activeBalances,
            'total_policies' => count($policies),
            'request_stats' => $requestStats,
        ];

        // Vérification des permissions
        $canEdit = $this->isGranted('ROLE_ADMIN');
        $canDelete = $this->isGranted('ROLE_ADMIN') && $totalRequests === 0 && $activeBalances === 0;

        return $this->render('admin/leave_type/show.html.twig', [
            'leave_type' => $leaveType,
            'recent_requests' => $leaveRequests,
            'policies' => $policies,
            'stats' => $stats,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_leave_type_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LeaveType $leaveType): Response
    {
        // Sauvegarde des valeurs originales
        $originalName = $leaveType->getName();
        $originalCode = $leaveType->getCode();
        $originalMaxDays = $leaveType->getMaxDaysPerYear();

        $form = $this->createForm(LeaveTypeType::class, $leaveType);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérification de l'unicité du code si modifié
                if ($leaveType->getCode() !== $originalCode) {
                    $existingByCode = $this->leaveTypeRepository->findOneBy(['code' => $leaveType->getCode()]);
                    if ($existingByCode && $existingByCode->getId() !== $leaveType->getId()) {
                        $this->addFlash('error', 'Un type de congé avec ce code existe déjà.');
                        return $this->render('admin/leave_type/edit.html.twig', [
                            'leave_type' => $leaveType,
                            'form' => $form->createView(),
                        ]);
                    }
                }

                // Vérification de l'unicité du nom si modifié
                if ($leaveType->getName() !== $originalName) {
                    $existingByName = $this->leaveTypeRepository->findOneBy(['name' => $leaveType->getName()]);
                    if ($existingByName && $existingByName->getId() !== $leaveType->getId()) {
                        $this->addFlash('error', 'Un type de congé avec ce nom existe déjà.');
                        return $this->render('admin/leave_type/edit.html.twig', [
                            'leave_type' => $leaveType,
                            'form' => $form->createView(),
                        ]);
                    }
                }

                // Validation métier
                if ($leaveType->getMaxDaysPerYear() && $leaveType->getMaxDaysPerYear() < 0) {
                    $this->addFlash('error', 'Le nombre maximum de jours par an ne peut pas être négatif.');
                    return $this->render('admin/leave_type/edit.html.twig', [
                        'leave_type' => $leaveType,
                        'form' => $form->createView(),
                    ]);
                }

                // Avertissement si réduction du nombre de jours max
                if ($originalMaxDays && $leaveType->getMaxDaysPerYear() && 
                    $leaveType->getMaxDaysPerYear() < $originalMaxDays) {
                    $this->addFlash('warning', sprintf(
                        'Attention : La réduction du nombre maximum de jours (de %d à %d) peut affecter les soldes existants.',
                        $originalMaxDays,
                        $leaveType->getMaxDaysPerYear()
                    ));
                }

                try {
                    $this->entityManager->flush();

                    $this->addFlash('success', sprintf(
                        'Le type de congé "%s" a été modifié avec succès.',
                        $leaveType->getName()
                    ));

                    return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la modification du type de congé.');
                }
            } else {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/leave_type/edit.html.twig', [
            'leave_type' => $leaveType,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_leave_type_delete', methods: ['POST'])]
    public function delete(Request $request, LeaveType $leaveType): Response
    {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('delete' . $leaveType->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);
        }

        // Vérification des contraintes métier
        $requestCount = $this->leaveRequestRepository->count(['leaveType' => $leaveType]);
        $balanceCount = $this->leaveBalanceRepository->count(['leaveType' => $leaveType]);
        $policyCount = $this->leavePolicyRepository->count(['leaveType' => $leaveType]);

        $constraints = [];
        if ($requestCount > 0) {
            $constraints[] = sprintf('%d demande(s) de congé', $requestCount);
        }
        if ($balanceCount > 0) {
            $constraints[] = sprintf('%d solde(s) de congé', $balanceCount);
        }
        if ($policyCount > 0) {
            $constraints[] = sprintf('%d politique(s) de congé', $policyCount);
        }

        if (!empty($constraints)) {
            $this->addFlash('error', sprintf(
                'Impossible de supprimer le type de congé "%s" car il est associé à : %s.',
                $leaveType->getName(),
                implode(', ', $constraints)
            ));
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);
        }

        try {
            $leaveTypeName = $leaveType->getName();
            $this->entityManager->remove($leaveType);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le type de congé "%s" a été supprimé avec succès.',
                $leaveTypeName
            ));

            return $this->redirectToRoute('admin_leave_type_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du type de congé.');
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);
        }
    }

    #[Route('/{id}/toggle-status', name: 'admin_leave_type_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, LeaveType $leaveType): Response
    {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('toggle' . $leaveType->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);
        }

        try {
            $oldStatus = $leaveType->isIsActive();
            $leaveType->setIsActive(!$oldStatus);
            
            $this->entityManager->flush();

            $statusText = $leaveType->isIsActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', sprintf(
                'Le type de congé "%s" a été %s avec succès.',
                $leaveType->getName(),
                $statusText
            ));

            // Avertissement si désactivation avec demandes en cours
            if (!$leaveType->isIsActive()) {
                $pendingRequests = $this->leaveRequestRepository->count([
                    'leaveType' => $leaveType,
                    'status' => 'pending'
                ]);
                if ($pendingRequests > 0) {
                    $this->addFlash('warning', sprintf(
                        'Attention : %d demande(s) en cours utilisent ce type de congé désactivé.',
                        $pendingRequests
                    ));
                }
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
        }

        return $this->redirectToRoute('admin_leave_type_show', ['id' => $leaveType->getId()]);
    }

    #[Route('/bulk-action', name: 'admin_leave_type_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $selectedIds = $request->request->all('selected_leave_types');

        if (empty($selectedIds)) {
            $this->addFlash('warning', 'Aucun type de congé sélectionné.');
            return $this->redirectToRoute('admin_leave_type_index');
        }

        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_leave_type_index');
        }

        $leaveTypes = $this->leaveTypeRepository->findBy(['id' => $selectedIds]);
        $processedCount = 0;
        $skippedCount = 0;

        try {
            foreach ($leaveTypes as $leaveType) {
                switch ($action) {
                    case 'activate':
                        $leaveType->setIsActive(true);
                        $processedCount++;
                        break;
                    
                    case 'deactivate':
                        $leaveType->setIsActive(false);
                        $processedCount++;
                        break;
                    
                    case 'delete':
                        $requestCount = $this->leaveRequestRepository->count(['leaveType' => $leaveType]);
                        $balanceCount = $this->leaveBalanceRepository->count(['leaveType' => $leaveType]);
                        
                        if ($requestCount === 0 && $balanceCount === 0) {
                            $this->entityManager->remove($leaveType);
                            $processedCount++;
                        } else {
                            $skippedCount++;
                        }
                        break;
                    
                    case 'require_approval':
                        $leaveType->setRequiresApproval(true);
                        $processedCount++;
                        break;
                    
                    case 'no_approval':
                        $leaveType->setRequiresApproval(false);
                        $processedCount++;
                        break;
                }
            }

            $this->entityManager->flush();

            if ($processedCount > 0) {
                $actionText = match($action) {
                    'activate' => 'activés',
                    'deactivate' => 'désactivés',
                    'delete' => 'supprimés',
                    'require_approval' => 'modifiés (approbation requise)',
                    'no_approval' => 'modifiés (sans approbation)',
                    default => 'traités'
                };

                $this->addFlash('success', sprintf(
                    '%d type(s) de congé %s avec succès.',
                    $processedCount,
                    $actionText
                ));
            }

            if ($skippedCount > 0) {
                $this->addFlash('warning', sprintf(
                    '%d type(s) de congé non traités (contraintes existantes).',
                    $skippedCount
                ));
            }

            if ($processedCount === 0 && $skippedCount === 0) {
                $this->addFlash('warning', 'Aucun type de congé n\'a pu être traité.');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du traitement en lot.');
        }

        return $this->redirectToRoute('admin_leave_type_index');
    }

    #[Route('/{id}/duplicate', name: 'admin_leave_type_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, LeaveType $originalLeaveType): Response
    {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('duplicate' . $originalLeaveType->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $originalLeaveType->getId()]);
        }

        try {
            $newLeaveType = new LeaveType();
            $newLeaveType->setName($originalLeaveType->getName() . ' (Copie)');
            $newLeaveType->setCode($originalLeaveType->getCode() . '_COPY');
            $newLeaveType->setDescription($originalLeaveType->getDescription());
            $newLeaveType->setMaxDaysPerYear($originalLeaveType->getMaxDaysPerYear());
            $newLeaveType->setRequiresApproval($originalLeaveType->isRequiresApproval());
            $newLeaveType->setIsPaid($originalLeaveType->isIsPaid());
            $newLeaveType->setColor($originalLeaveType->getColor());
            $newLeaveType->setIsActive($originalLeaveType->isIsActive());
            $newLeaveType->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($newLeaveType);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le type de congé "%s" a été dupliqué avec succès.',
                $originalLeaveType->getName()
            ));

            return $this->redirectToRoute('admin_leave_type_edit', ['id' => $newLeaveType->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du type de congé.');
            return $this->redirectToRoute('admin_leave_type_show', ['id' => $originalLeaveType->getId()]);
        }
    }
}