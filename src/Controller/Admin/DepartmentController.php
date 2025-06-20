<?php

namespace App\Controller\Admin;

use App\Entity\Department;
use App\Form\Admin\DepartmentType;
use App\Repository\DepartmentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/department')]
#[IsGranted('ROLE_ADMIN')]
class DepartmentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private DepartmentRepository $departmentRepository;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        DepartmentRepository $departmentRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->departmentRepository = $departmentRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/', name: 'admin_department_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Paramètres de recherche et filtrage pour améliorer l'UX
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all'); // all, active, inactive
        $sortBy = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        // Construction de la requête avec filtres
        $queryBuilder = $this->departmentRepository->createQueryBuilder('d')
            ->leftJoin('d.manager', 'm')
            ->addSelect('m');

        // Recherche par nom ou code
        if (!empty($search)) {
            $queryBuilder
                ->andWhere('d.name LIKE :search OR d.code LIKE :search OR d.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtre par statut
        if ($status !== 'all') {
            $isActive = $status === 'active';
            $queryBuilder
                ->andWhere('d.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // Tri
        $validSortFields = ['name', 'code', 'createdAt', 'isActive'];
        if (in_array($sortBy, $validSortFields)) {
            $queryBuilder->orderBy('d.' . $sortBy, strtoupper($order) === 'DESC' ? 'DESC' : 'ASC');
        }

        // Pagination
        $totalDepartments = (clone $queryBuilder)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $departments = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalDepartments / $limit);

        // Statistiques pour le dashboard (amélioration UX)
        $stats = [
            'total' => $this->departmentRepository->count([]),
            'active' => $this->departmentRepository->count(['isActive' => true]),
            'inactive' => $this->departmentRepository->count(['isActive' => false]),
            'without_manager' => $this->departmentRepository->count(['manager' => null])
        ];

        return $this->render('admin/department/index.html.twig', [
            'departments' => $departments,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'order' => $order,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalDepartments' => $totalDepartments,
        ]);
    }

    #[Route('/new', name: 'admin_department_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $department = new Department();
        
        // Pré-remplissage pour améliorer l'UX
        $department->setIsActive(true);
        $department->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérification de l'unicité du code département
                $existingDepartment = $this->departmentRepository->findOneBy(['code' => $department->getCode()]);
                if ($existingDepartment) {
                    $this->addFlash('error', 'Un département avec ce code existe déjà.');
                    return $this->render('admin/department/new.html.twig', [
                        'department' => $department,
                        'form' => $form->createView(),
                    ]);
                }

                try {
                    $this->entityManager->persist($department);
                    $this->entityManager->flush();

                    $this->addFlash('success', sprintf(
                        'Le département "%s" a été créé avec succès.',
                        $department->getName()
                    ));

                    // Redirection intelligente basée sur l'action utilisateur
                    /*$submitButton = $form->getClickedButton();
                    if ($submitButton && $submitButton->getName() === 'saveAndAdd') {
                        return $this->redirectToRoute('admin_department_new');
                    }*/

                    // Vérification alternative du bouton cliqué
                    if ($request->request->has('saveAndAdd')) {
                        return $this->redirectToRoute('admin_department_new');
                    }

                    return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la création du département.');
                }
            } else {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/department/new.html.twig', [
            'department' => $department,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_department_show', methods: ['GET'])]
    public function show(Department $department): Response
    {
        // Récupération des données associées pour enrichir la vue
        $employees = $this->userRepository->findBy(['department' => $department], ['lastName' => 'ASC']);
        $employeeCount = count($employees);
        
        // Statistiques du département
        $activeEmployees = array_filter($employees, fn($emp) => $emp->isActive());
        $stats = [
            'total_employees' => $employeeCount,
            'active_employees' => count($activeEmployees),
            'inactive_employees' => $employeeCount - count($activeEmployees),
        ];

        // Vérification des permissions pour les actions
        $canEdit = $this->isGranted('ROLE_ADMIN');
        $canDelete = $this->isGranted('ROLE_ADMIN') && $employeeCount === 0;

        return $this->render('admin/department/show.html.twig', [
            'department' => $department,
            'employees' => $employees,
            'stats' => $stats,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_department_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Department $department): Response
    {
        // Sauvegarde des valeurs originales pour la comparaison
        $originalName = $department->getName();
        $originalCode = $department->getCode();

        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérification de l'unicité du code si modifié
                if ($department->getCode() !== $originalCode) {
                    $existingDepartment = $this->departmentRepository->findOneBy(['code' => $department->getCode()]);
                    if ($existingDepartment && $existingDepartment->getId() !== $department->getId()) {
                        $this->addFlash('error', 'Un département avec ce code existe déjà.');
                        return $this->render('admin/department/edit.html.twig', [
                            'department' => $department,
                            'form' => $form->createView(),
                        ]);
                    }
                }

                try {
                    $department->setUpdatedAt(new \DateTimeImmutable());
                    $this->entityManager->flush();

                    $this->addFlash('success', sprintf(
                        'Le département "%s" a été modifié avec succès.',
                        $department->getName()
                    ));

                    return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la modification du département.');
                }
            } else {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/department/edit.html.twig', [
            'department' => $department,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_department_delete', methods: ['POST'])]
    public function delete(Request $request, Department $department): Response
    {
        // Vérification du token CSRF pour la sécurité
        if (!$this->isCsrfTokenValid('delete' . $department->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);
        }

        // Vérification des contraintes métier
        $employeeCount = $this->userRepository->count(['department' => $department]);
        if ($employeeCount > 0) {
            $this->addFlash('error', sprintf(
                'Impossible de supprimer le département "%s" car il contient %d employé(s).',
                $department->getName(),
                $employeeCount
            ));
            return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);
        }

        try {
            $departmentName = $department->getName();
            $this->entityManager->remove($department);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le département "%s" a été supprimé avec succès.',
                $departmentName
            ));

            return $this->redirectToRoute('admin_department_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du département.');
            return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);
        }
    }

    #[Route('/{id}/toggle-status', name: 'admin_department_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Department $department): Response
    {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('toggle' . $department->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);
        }

        try {
            $oldStatus = $department->isActive();
            $department->setIsActive(!$oldStatus);
            $department->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            $statusText = $department->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', sprintf(
                'Le département "%s" a été %s avec succès.',
                $department->getName(),
                $statusText
            ));

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
        }

        return $this->redirectToRoute('admin_department_show', ['id' => $department->getId()]);
    }

    #[Route('/bulk-action', name: 'admin_department_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $selectedIds = $request->request->all('selected_departments');

        if (empty($selectedIds)) {
            $this->addFlash('warning', 'Aucun département sélectionné.');
            return $this->redirectToRoute('admin_department_index');
        }

        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_department_index');
        }

        $departments = $this->departmentRepository->findBy(['id' => $selectedIds]);
        $processedCount = 0;

        try {
            foreach ($departments as $department) {
                switch ($action) {
                    case 'activate':
                        $department->setIsActive(true);
                        $department->setUpdatedAt(new \DateTimeImmutable());
                        $processedCount++;
                        break;
                    
                    case 'deactivate':
                        $department->setIsActive(false);
                        $department->setUpdatedAt(new \DateTimeImmutable());
                        $processedCount++;
                        break;
                    
                    case 'delete':
                        $employeeCount = $this->userRepository->count(['department' => $department]);
                        if ($employeeCount === 0) {
                            $this->entityManager->remove($department);
                            $processedCount++;
                        }
                        break;
                }
            }

            $this->entityManager->flush();

            if ($processedCount > 0) {
                $actionText = match($action) {
                    'activate' => 'activés',
                    'deactivate' => 'désactivés',
                    'delete' => 'supprimés',
                    default => 'traités'
                };

                $this->addFlash('success', sprintf(
                    '%d département(s) %s avec succès.',
                    $processedCount,
                    $actionText
                ));
            } else {
                $this->addFlash('warning', 'Aucun département n\'a pu être traité.');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du traitement en lot.');
        }

        return $this->redirectToRoute('admin_department_index');
    }
}