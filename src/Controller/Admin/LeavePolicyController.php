<?php

namespace App\Controller\Admin;

use App\Entity\LeavePolicy;
use App\Form\Admin\LeavePolicyType;
use App\Repository\LeavePolicyRepository;
use App\Repository\DepartmentRepository;
use App\Repository\LeaveTypeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/leave-policy')]
#[IsGranted('ROLE_ADMIN')]
class LeavePolicyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LeavePolicyRepository $leavePolicyRepository,
        private DepartmentRepository $departmentRepository,
        private LeaveTypeRepository $leaveTypeRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Liste toutes les politiques de congés
     */
    #[Route('/', name: 'admin_leave_policy_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $departmentId = $request->query->get('department', '');
        $leaveTypeId = $request->query->get('leave_type', '');
        $status = $request->query->get('status', 'all');
        
        $queryBuilder = $this->leavePolicyRepository->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt');
        
        // Filtrer par département
        if (!empty($departmentId)) {
            $queryBuilder->andWhere('lp.department = :departmentId')
                ->setParameter('departmentId', $departmentId);
        }
        
        // Filtrer par type de congé
        if (!empty($leaveTypeId)) {
            $queryBuilder->andWhere('lp.leaveType = :leaveTypeId')
                ->setParameter('leaveTypeId', $leaveTypeId);
        }
        
        // Filtrer par statut
        if ($status === 'active') {
            $queryBuilder->andWhere('lp.isActive = :active')
                ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $queryBuilder->andWhere('lp.isActive = :active')
                ->setParameter('active', false);
        }
        
        $leavePolicies = $queryBuilder->orderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();

        $departments = $this->departmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $leaveTypes = $this->leaveTypeRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('admin/leave_policy/index.html.twig', [
            'leavePolicies' => $leavePolicies,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'current_department' => $departmentId,
            'current_leave_type' => $leaveTypeId,
            'current_status' => $status,
        ]);
    }

    /**
     * Affiche les détails d'une politique de congés
     */
    #[Route('/{id}', name: 'admin_leave_policy_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(LeavePolicy $leavePolicy): Response
    {
        // Décoder les règles JSON pour l'affichage
        $rules = $leavePolicy->getRules();
        
        // Compter les employés affectés par cette politique
        $affectedEmployees = 0;
        if ($leavePolicy->getDepartment()) {
            $affectedEmployees = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.department = :department')
                ->andWhere('u.isActive = :active')
                ->setParameter('department', $leavePolicy->getDepartment())
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->render('admin/leave_policy/show.html.twig', [
            'leave_policy' => $leavePolicy,
            'rules' => $rules,
            'affected_employees' => $affectedEmployees,
        ]);
    }

    /**
     * Formulaire de création d'une nouvelle politique de congés
     */
    #[Route('/new', name: 'admin_leave_policy_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $leavePolicy = new LeavePolicy();
        
        // Définir des règles par défaut
        $defaultRules = [
            'min_notice_days' => 7,
            'max_consecutive_days' => 30,
            'require_manager_approval' => true,
            'require_hr_approval' => false,
            'blackout_periods' => [],
            'max_requests_per_month' => 2,
            'allow_partial_days' => true,
            'carry_over_allowed' => false,
            'carry_over_max_days' => 0,
            'restrictions' => []
        ];
        $leavePolicy->setRules($defaultRules);
        
        $form = $this->createForm(LeavePolicyType::class, $leavePolicy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier s'il n'existe pas déjà une politique pour ce département/type de congé
            $existingPolicy = $this->leavePolicyRepository->findOneBy([
                'department' => $leavePolicy->getDepartment(),
                'leaveType' => $leavePolicy->getLeaveType(),
                'isActive' => true
            ]);

            if ($existingPolicy) {
                $this->addFlash('error', 'Une politique active existe déjà pour ce département et ce type de congé.');
                return $this->render('admin/leave_policy/new.html.twig', [
                    'leave_policy' => $leavePolicy,
                    'form' => $form,
                ]);
            }

            $leavePolicy->setCreatedAt(new \DateTime());
            $leavePolicy->setIsActive(true);
            
            $this->entityManager->persist($leavePolicy);
            $this->entityManager->flush();

            $this->addFlash('success', 'La politique de congés a été créée avec succès.');

            return $this->redirectToRoute('admin_leave_policy_index');
        }

        return $this->render('admin/leave_policy/new.html.twig', [
            'leave_policy' => $leavePolicy,
            'form' => $form,
        ]);
    }

    /**
     * Formulaire de modification d'une politique de congés
     */
    #[Route('/{id}/edit', name: 'admin_leave_policy_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, LeavePolicy $leavePolicy): Response
    {
        $originalDepartment = $leavePolicy->getDepartment();
        $originalLeaveType = $leavePolicy->getLeaveType();
        
        $form = $this->createForm(LeavePolicyType::class, $leavePolicy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier s'il n'existe pas déjà une politique pour ce département/type de congé (si changé)
            if ($originalDepartment !== $leavePolicy->getDepartment() || 
                $originalLeaveType !== $leavePolicy->getLeaveType()) {
                
                $existingPolicy = $this->leavePolicyRepository->findOneBy([
                    'department' => $leavePolicy->getDepartment(),
                    'leaveType' => $leavePolicy->getLeaveType(),
                    'isActive' => true
                ]);

                if ($existingPolicy && $existingPolicy->getId() !== $leavePolicy->getId()) {
                    $this->addFlash('error', 'Une politique active existe déjà pour ce département et ce type de congé.');
                    return $this->render('admin/leave_policy/edit.html.twig', [
                        'leave_policy' => $leavePolicy,
                        'form' => $form,
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'La politique de congés a été modifiée avec succès.');

            return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
        }

        return $this->render('admin/leave_policy/edit.html.twig', [
            'leave_policy' => $leavePolicy,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'une politique de congés (soft delete - désactivation)
     */
    #[Route('/{id}/delete', name: 'admin_leave_policy_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, LeavePolicy $leavePolicy): Response
    {
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete' . $leavePolicy->getId(), $request->request->get('_token'))) {
            
            // Vérifier s'il y a des demandes de congés en cours utilisant cette politique
            $pendingRequests = $this->entityManager->createQuery(
                'SELECT COUNT(lr.id) FROM App\Entity\LeaveRequest lr 
                 JOIN lr.employee e 
                 WHERE e.department = :department 
                 AND lr.leaveType = :leaveType 
                 AND lr.status = :status'
            )
            ->setParameter('department', $leavePolicy->getDepartment())
            ->setParameter('leaveType', $leavePolicy->getLeaveType())
            ->setParameter('status', 'pending')
            ->getSingleScalarResult();

            if ($pendingRequests > 0) {
                $this->addFlash('warning', 'Cette politique est liée à ' . $pendingRequests . ' demande(s) de congés en attente. Elle sera désactivée mais les demandes existantes ne seront pas affectées.');
            }

            // Soft delete - désactiver la politique
            $leavePolicy->setIsActive(false);
            $this->entityManager->flush();

            $this->addFlash('success', 'La politique de congés a été désactivée avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_leave_policy_index');
    }

    /**
     * Réactivation d'une politique de congés
     */
    #[Route('/{id}/activate', name: 'admin_leave_policy_activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(Request $request, LeavePolicy $leavePolicy): Response
    {
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('activate' . $leavePolicy->getId(), $request->request->get('_token'))) {
            
            // Vérifier s'il n'existe pas déjà une politique active pour ce département/type de congé
            $existingPolicy = $this->leavePolicyRepository->findOneBy([
                'department' => $leavePolicy->getDepartment(),
                'leaveType' => $leavePolicy->getLeaveType(),
                'isActive' => true
            ]);

            if ($existingPolicy && $existingPolicy->getId() !== $leavePolicy->getId()) {
                $this->addFlash('error', 'Impossible de réactiver cette politique car une autre politique active existe déjà pour ce département et ce type de congé : ' . $existingPolicy->getName());
                return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
            }

            // Vérifier que le département et le type de congé sont actifs
            if (!$leavePolicy->getDepartment()->isActive()) {
                $this->addFlash('error', 'Impossible de réactiver cette politique car le département associé est désactivé.');
                return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
            }

            if (!$leavePolicy->getLeaveType()->isIsActive()) {
                $this->addFlash('error', 'Impossible de réactiver cette politique car le type de congé associé est désactivé.');
                return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
            }

            $leavePolicy->setIsActive(true);
            $this->entityManager->flush();

            $this->addFlash('success', 'La politique de congés a été réactivée avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
    }

    /**
     * Duplication d'une politique de congés
     */
    #[Route('/{id}/duplicate', name: 'admin_leave_policy_duplicate', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, LeavePolicy $originalPolicy): Response
    {
        $leavePolicy = new LeavePolicy();
        $leavePolicy->setName($originalPolicy->getName() . ' (Copie)');
        $leavePolicy->setDescription($originalPolicy->getDescription());
        $leavePolicy->setRules($originalPolicy->getRules());
        
        $form = $this->createForm(LeavePolicyType::class, $leavePolicy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier s'il n'existe pas déjà une politique pour ce département/type de congé
            $existingPolicy = $this->leavePolicyRepository->findOneBy([
                'department' => $leavePolicy->getDepartment(),
                'leaveType' => $leavePolicy->getLeaveType(),
                'isActive' => true
            ]);

            if ($existingPolicy) {
                $this->addFlash('error', 'Une politique active existe déjà pour ce département et ce type de congé.');
                return $this->render('admin/leave_policy/duplicate.html.twig', [
                    'original_policy' => $originalPolicy,
                    'leave_policy' => $leavePolicy,
                    'form' => $form,
                ]);
            }

            $leavePolicy->setCreatedAt(new \DateTime());
            $leavePolicy->setIsActive(true);
            
            $this->entityManager->persist($leavePolicy);
            $this->entityManager->flush();

            $this->addFlash('success', 'La politique de congés a été dupliquée avec succès.');

            return $this->redirectToRoute('admin_leave_policy_show', ['id' => $leavePolicy->getId()]);
        }

        return $this->render('admin/leave_policy/duplicate.html.twig', [
            'original_policy' => $originalPolicy,
            'leave_policy' => $leavePolicy,
            'form' => $form,
        ]);
    }

    /**
     * Aperçu des employés affectés par une politique
     */
    #[Route('/{id}/affected-employees', name: 'admin_leave_policy_affected_employees', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function affectedEmployees(LeavePolicy $leavePolicy): Response
    {
        $employees = [];
        
        if ($leavePolicy->getDepartment()) {
            $employees = $this->userRepository->createQueryBuilder('u')
                ->where('u.department = :department')
                ->andWhere('u.isActive = :active')
                ->setParameter('department', $leavePolicy->getDepartment())
                ->setParameter('active', true)
                ->orderBy('u.lastName', 'ASC')
                ->addOrderBy('u.firstName', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/leave_policy/affected_employees.html.twig', [
            'leave_policy' => $leavePolicy,
            'employees' => $employees,
        ]);
    }

    /**
     * Validation des règles d'une politique
     */
    #[Route('/{id}/validate-rules', name: 'admin_leave_policy_validate_rules', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function validateRules(LeavePolicy $leavePolicy): Response
    {
        $rules = $leavePolicy->getRules();
        $errors = [];
        $warnings = [];

        // Validation des règles
        if (isset($rules['min_notice_days']) && $rules['min_notice_days'] < 0) {
            $errors[] = 'Le nombre minimum de jours de préavis ne peut pas être négatif.';
        }

        if (isset($rules['max_consecutive_days']) && $rules['max_consecutive_days'] < 1) {
            $errors[] = 'Le nombre maximum de jours consécutifs doit être au moins de 1.';
        }

        if (isset($rules['max_requests_per_month']) && $rules['max_requests_per_month'] < 1) {
            $errors[] = 'Le nombre maximum de demandes par mois doit être au moins de 1.';
        }

        if (isset($rules['carry_over_max_days']) && $rules['carry_over_max_days'] < 0) {
            $errors[] = 'Le nombre maximum de jours reportables ne peut pas être négatif.';
        }

        // Avertissements
        if (isset($rules['min_notice_days']) && $rules['min_notice_days'] > 30) {
            $warnings[] = 'Un préavis de plus de 30 jours peut être contraignant pour les employés.';
        }

        if (isset($rules['max_consecutive_days']) && $rules['max_consecutive_days'] > 90) {
            $warnings[] = 'Autoriser plus de 90 jours consécutifs peut poser des problèmes organisationnels.';
        }

        // Vérification de cohérence avec le type de congé
        $leaveType = $leavePolicy->getLeaveType();
        if ($leaveType && isset($rules['max_consecutive_days'])) {
            if ($rules['max_consecutive_days'] > $leaveType->getMaxDaysPerYear()) {
                $warnings[] = 'Le nombre maximum de jours consécutifs dépasse le nombre de jours annuels autorisés pour ce type de congé.';
            }
        }

        return $this->render('admin/leave_policy/validate_rules.html.twig', [
            'leave_policy' => $leavePolicy,
            'rules' => $rules,
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Export des politiques de congés en CSV
     */
    #[Route('/export', name: 'admin_leave_policy_export', methods: ['GET'])]
    public function export(): Response
    {
        $leavePolicies = $this->leavePolicyRepository->findBy([], ['name' => 'ASC']);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="leave_policies_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://temp', 'r+');
        
        // En-têtes CSV
        fputcsv($output, [
            'ID',
            'Nom',
            'Description',
            'Département',
            'Type de congé',
            'Préavis minimum (jours)',
            'Jours consécutifs max',
            'Approbation manager requise',
            'Approbation RH requise',
            'Demandes max/mois',
            'Jours partiels autorisés',
            'Report autorisé',
            'Jours reportables max',
            'Statut',
            'Date création'
        ]);

        // Données
        foreach ($leavePolicies as $policy) {
            $rules = $policy->getRules();
            
            fputcsv($output, [
                $policy->getId(),
                $policy->getName(),
                $policy->getDescription(),
                $policy->getDepartment() ? $policy->getDepartment()->getName() : '',
                $policy->getLeaveType() ? $policy->getLeaveType()->getName() : '',
                $rules['min_notice_days'] ?? '',
                $rules['max_consecutive_days'] ?? '',
                isset($rules['require_manager_approval']) ? ($rules['require_manager_approval'] ? 'Oui' : 'Non') : '',
                isset($rules['require_hr_approval']) ? ($rules['require_hr_approval'] ? 'Oui' : 'Non') : '',
                $rules['max_requests_per_month'] ?? '',
                isset($rules['allow_partial_days']) ? ($rules['allow_partial_days'] ? 'Oui' : 'Non') : '',
                isset($rules['carry_over_allowed']) ? ($rules['carry_over_allowed'] ? 'Oui' : 'Non') : '',
                $rules['carry_over_max_days'] ?? '',
                $policy->getisActive() ? 'Actif' : 'Inactif',
                $policy->getCreatedAt() ? $policy->getCreatedAt()->format('d/m/Y H:i') : ''
            ]);
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }
}