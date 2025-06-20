<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Department;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    private UserRepository $userRepository;
    private DepartmentRepository $departmentRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        UserRepository $userRepository,
        DepartmentRepository $departmentRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->userRepository = $userRepository;
        $this->departmentRepository = $departmentRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Liste des utilisateurs avec pagination et filtres
     */
    #[Route('/', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Paramètres de pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Paramètres de filtrage pour améliorer l'UX
        $searchTerm = $request->query->get('search', '');
        $departmentId = $request->query->getInt('department');
        $isActive = $request->query->get('status');
        $role = $request->query->get('role');

        // Construction de la requête avec critères
        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.department', 'd')
            ->addSelect('d');

        // Filtre par terme de recherche (nom, prénom, email)
        if (!empty($searchTerm)) {
            $queryBuilder
                ->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Filtre par département
        if ($departmentId > 0) {
            $queryBuilder
                ->andWhere('u.department = :department')
                ->setParameter('department', $departmentId);
        }

        // Filtre par statut actif/inactif
        if ($isActive !== null && $isActive !== '') {
            $queryBuilder
                ->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive === '1');
        }

        // Filtre par rôle
        if (!empty($role)) {
            $queryBuilder
                ->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $role . '"%');
        }

        // Ordre par défaut
        $queryBuilder->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');

        // Compter le total pour la pagination
        $totalQuery = clone $queryBuilder;
        $total = $totalQuery->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Récupérer les utilisateurs pour la page courante
        $users = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Calculer les informations de pagination
        $totalPages = ceil($total / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;

        // Récupérer les départements pour le filtre
        $departments = $this->departmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'departments' => $departments,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'has_next' => $hasNextPage,
                'has_previous' => $hasPreviousPage,
                'items_per_page' => $limit
            ],
            'filters' => [
                'search' => $searchTerm,
                'department' => $departmentId,
                'status' => $isActive,
                'role' => $role
            ]
        ]);
    }

    /**
     * Affichage détaillé d'un utilisateur
     */
    #[Route('/{id}', name: 'admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        // Récupérer les statistiques de l'utilisateur pour enrichir l'UX
        $stats = [
            'total_leave_requests' => $this->entityManager
                ->getRepository('App\Entity\LeaveRequest')
                ->count(['employee' => $user]),
            'pending_requests' => $this->entityManager
                ->getRepository('App\Entity\LeaveRequest')
                ->count(['employee' => $user, 'status' => 'pending']),
            'approved_requests' => $this->entityManager
                ->getRepository('App\Entity\LeaveRequest')
                ->count(['employee' => $user, 'status' => 'approved'])
        ];

        // Récupérer les demandes récentes
        $recentRequests = $this->entityManager
            ->getRepository('App\Entity\LeaveRequest')
            ->findBy(
                ['employee' => $user],
                ['createdAt' => 'DESC'],
                5
            );

        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'recent_requests' => $recentRequests
        ]);
    }

    /**
     * Création d'un nouvel utilisateur
     */
    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        
        // Définir des valeurs par défaut pour améliorer l'UX
        $user->setIsActive(true);
        $user->setHireDate(new \DateTime());
        $user->setRoles(['ROLE_EMPLOYEE']);

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Hash du mot de passe
                    $plainPassword = $form->get('plainPassword')->getData();
                    if (!empty($plainPassword)) {
                        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                        $user->setPassword($hashedPassword);
                    }

                    // Définir les timestamps
                    $user->setCreatedAt(new \DateTimeImmutable());
                    $user->setUpdatedAt(new \DateTimeImmutable());

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // Message de succès avec détails pour une meilleure UX
                    $this->addFlash('success', sprintf(
                        'L\'utilisateur "%s %s" a été créé avec succès.',
                        $user->getFirstName(),
                        $user->getLastName()
                    ));

                    return $this->redirectToRoute('admin_user_index');

                } catch (\Exception $e) {
                    // Gestion d'erreur avec message utilisateur friendly
                    $this->addFlash('error', 'Une erreur est survenue lors de la création de l\'utilisateur. Veuillez réessayer.');
                }
            } else {
                // Message d'erreur pour les erreurs de validation
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Modification d'un utilisateur existant
     */
    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, User $user): Response
    {
        $originalEmail = $user->getEmail();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Gérer le changement de mot de passe (optionnel en modification)
                    $plainPassword = $form->get('plainPassword')->getData();
                    if (!empty($plainPassword)) {
                        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                        $user->setPassword($hashedPassword);
                    }
                    //dd($form);
                    // La date updatedAt sera mise à jour automatiquement par le callback @PreUpdate
                    $this->entityManager->flush();

                    $message = sprintf('L\'utilisateur "%s %s" a été modifié avec succès.', 
                        $user->getFirstName(), 
                        $user->getLastName()
                    );

                    if ($originalEmail !== $user->getEmail()) {
                        $message .= sprintf(' Email changé de "%s" vers "%s".', $originalEmail, $user->getEmail());
                    }

                    $this->addFlash('success', $message);
                    return $this->redirectToRoute('admin_user_index');

                } catch (\Exception $e) {
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('error', 'Erreur: ' . $e->getMessage());
                    } else {
                        $this->addFlash('error', 'Une erreur est survenue lors de la modification.');
                    }
                }
            } else {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'un utilisateur (soft delete)
     */
    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        // Vérification du token CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try {
                // Vérifier si l'utilisateur peut être supprimé (pas de dépendances critiques)
                $hasActiveRequests = $this->entityManager
                    ->getRepository('App\Entity\LeaveRequest')
                    ->count(['employee' => $user, 'status' => 'pending']);

                if ($hasActiveRequests > 0) {
                    $this->addFlash('warning', sprintf(
                        'Impossible de supprimer "%s %s" : %d demande(s) de congé en attente.',
                        $user->getFirstName(),
                        $user->getLastName(),
                        $hasActiveRequests
                    ));
                    return $this->redirectToRoute('admin_user_index');
                }

                // Soft delete : désactiver l'utilisateur au lieu de le supprimer
                $user->setIsActive(false);
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                $this->addFlash('success', sprintf(
                    'L\'utilisateur "%s %s" a été désactivé avec succès.',
                    $user->getFirstName(),
                    $user->getLastName()
                ));

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression de l\'utilisateur.');
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Activation/Désactivation rapide d'un utilisateur
     */
    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $user->getId(), $request->request->get('_token'))) {
            try {
                $user->setIsActive(!$user->isActive());
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                $status = $user->isActive() ? 'activé' : 'désactivé';
                $this->addFlash('success', sprintf(
                    'L\'utilisateur "%s %s" a été %s.',
                    $user->getFirstName(),
                    $user->getLastName(),
                    $status
                ));

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
            }
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Export des utilisateurs en CSV
     */
    #[Route('/export', name: 'admin_user_export', methods: ['GET'])]
    public function export(): Response
    {
        $users = $this->userRepository->findBy(['isActive' => true], ['lastName' => 'ASC']);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="utilisateurs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Poste', 
            'Département', 'Date d\'embauche', 'Statut', 'Rôles'
        ], ';');

        // Données
        foreach ($users as $user) {
            fputcsv($output, [
                $user->getId(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getEmail(),
                $user->getPhone(),
                $user->getPosition(),
                $user->getDepartment()?->getName(),
                $user->getHireDate()?->format('d/m/Y'),
                $user->isActive() ? 'Actif' : 'Inactif',
                implode(', ', $user->getRoles())
            ], ';');
        }

        fclose($output);

        return $response;
    }

    /**
     * Désactivation d'un utilisateur
     */
    #[Route('/{id}/deactivate', name: 'admin_user_deactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deactivate(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('deactivate' . $user->getId(), $request->request->get('_token'))) {
            try {
                $user->setIsActive(false);
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                $this->addFlash('success', sprintf(
                    'L\'utilisateur "%s %s" a été désactivé.',
                    $user->getFirstName(),
                    $user->getLastName()
                ));

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la désactivation.');
            }
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * Activation d'un utilisateur
     */
    #[Route('/{id}/activate', name: 'admin_user_activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('activate' . $user->getId(), $request->request->get('_token'))) {
            try {
                $user->setIsActive(true);
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                $this->addFlash('success', sprintf(
                    'L\'utilisateur "%s %s" a été activé.',
                    $user->getFirstName(),
                    $user->getLastName()
                ));

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'activation.');
            }
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

}