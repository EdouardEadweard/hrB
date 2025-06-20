<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Form\Admin\TeamType;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/team', name: 'admin_team_')]
#[IsGranted('ROLE_ADMIN')]
class TeamController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TeamRepository $teamRepository;
    private UserRepository $userRepository;
    private DepartmentRepository $departmentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TeamRepository $teamRepository,
        UserRepository $userRepository,
        DepartmentRepository $departmentRepository
    ) {
        $this->entityManager = $entityManager;
        $this->teamRepository = $teamRepository;
        $this->userRepository = $userRepository;
        $this->departmentRepository = $departmentRepository;
    }

    /**
     * Liste toutes les équipes
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $search = $request->query->get('search', '');
        $departmentId = $request->query->get('department', '');
        $status = $request->query->get('status', '');

        // Récupération des équipes avec filtres
        $criteria = [];
        
        if ($departmentId) {
            $department = $this->departmentRepository->find($departmentId);
            if ($department) {
                $criteria['department'] = $department;
            }
        }

        if ($status !== '') {
            $criteria['isActive'] = $status === '1';
        }

        if ($search) {
            $teams = $this->teamRepository->findBySearchCriteria($search, $criteria, $page, $limit);
            $totalTeams = $this->teamRepository->countBySearchCriteria($search, $criteria);
        } else {
            $teams = $this->teamRepository->findBy(
                $criteria,
                ['name' => 'ASC'],
                $limit,
                ($page - 1) * $limit
            );
            $totalTeams = $this->teamRepository->count($criteria);
        }

        $totalPages = ceil($totalTeams / $limit);

        // Récupération des départements pour le filtre
        $departments = $this->departmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('admin/team/index.html.twig', [
            'teams' => $teams,
            'departments' => $departments,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalTeams' => $totalTeams,
            'search' => $search,
            'selectedDepartment' => $departmentId,
            'selectedStatus' => $status,
        ]);
    }

    /**
     * Affiche les détails d'une équipe
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Team $team): Response
    {
        // Récupération des membres actifs de l'équipe
        $activeMembers = $this->teamRepository->findActiveMembersByTeam($team);
        
        // Récupération de l'historique des membres
        $memberHistory = $this->teamRepository->findMemberHistoryByTeam($team);

        // Statistiques de l'équipe
        $stats = [
            'totalMembers' => count($activeMembers),
            'totalRequests' => $this->teamRepository->countLeaveRequestsByTeam($team),
            'pendingRequests' => $this->teamRepository->countPendingLeaveRequestsByTeam($team),
        ];

        return $this->render('admin/team/show.html.twig', [
            'team' => $team,
            'activeMembers' => $activeMembers,
            'memberHistory' => $memberHistory,
            'stats' => $stats,
        ]);
    }

    /**
     * Crée une nouvelle équipe
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom dans le département
                $existingTeam = $this->teamRepository->findOneBy([
                    'name' => $team->getName(),
                    'department' => $team->getDepartment()
                ]);

                if ($existingTeam) {
                    $this->addFlash('error', 'Une équipe avec ce nom existe déjà dans ce département.');
                    return $this->render('admin/team/new.html.twig', [
                        'team' => $team,
                        'form' => $form->createView(),
                    ]);
                }

                $team->setCreatedAt(new \DateTime());
                $team->setIsActive(true);

                $this->entityManager->persist($team);
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'équipe a été créée avec succès.');
                return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création de l\'équipe.');
            }
        }

        return $this->render('admin/team/new.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Modifie une équipe existante
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Team $team): Response
    {
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom dans le département (excluant l'équipe actuelle)
                $existingTeam = $this->teamRepository->findOneBy([
                    'name' => $team->getName(),
                    'department' => $team->getDepartment()
                ]);

                if ($existingTeam && $existingTeam->getId() !== $team->getId()) {
                    $this->addFlash('error', 'Une équipe avec ce nom existe déjà dans ce département.');
                    return $this->render('admin/team/edit.html.twig', [
                        'team' => $team,
                        'form' => $form->createView(),
                    ]);
                }

                $this->entityManager->flush();

                $this->addFlash('success', 'L\'équipe a été modifiée avec succès.');
                return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'équipe.');
            }
        }

        return $this->render('admin/team/edit.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Supprime une équipe
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('delete' . $team->getId(), $request->request->get('_token'))) {
            try {
                // Vérification si l'équipe a des membres actifs
                $activeMembers = $this->teamRepository->findActiveMembersByTeam($team);
                
                if (count($activeMembers) > 0) {
                    $this->addFlash('error', 'Impossible de supprimer une équipe qui contient des membres actifs.');
                    return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
                }

                $this->entityManager->remove($team);
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'équipe a été supprimée avec succès.');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression de l\'équipe.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_team_index');
    }

    /**
     * Active/désactive une équipe
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('toggle_status' . $team->getId(), $request->request->get('_token'))) {
            try {
                $team->setIsActive(!$team->isActive());
                $this->entityManager->flush();

                $status = $team->isActive() ? 'activée' : 'désactivée';
                $this->addFlash('success', "L'équipe a été {$status} avec succès.");

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }

    /**
     * Ajoute un membre à l'équipe
     */
    #[Route('/{id}/add-member', name: 'add_member', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMember(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('add_member' . $team->getId(), $request->request->get('_token'))) {
            $userId = $request->request->get('user_id');
            
            if ($userId) {
                $user = $this->userRepository->find($userId);
                
                if ($user) {
                    try {
                        // Vérification si l'utilisateur n'est pas déjà membre de l'équipe
                        $existingMembership = $this->teamRepository->findActiveMembershipByUserAndTeam($user, $team);
                        
                        if ($existingMembership) {
                            $this->addFlash('error', 'Cet utilisateur est déjà membre de l\'équipe.');
                        } else {
                            $this->teamRepository->addMemberToTeam($user, $team);
                            $this->addFlash('success', 'Le membre a été ajouté à l\'équipe avec succès.');
                        }

                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout du membre.');
                    }
                } else {
                    $this->addFlash('error', 'Utilisateur introuvable.');
                }
            } else {
                $this->addFlash('error', 'Veuillez sélectionner un utilisateur.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }

    /**
     * Retire un membre de l'équipe
     */
    #[Route('/{id}/remove-member/{memberId}', name: 'remove_member', methods: ['POST'], requirements: ['id' => '\d+', 'memberId' => '\d+'])]
    public function removeMember(Request $request, Team $team, int $memberId): Response
    {
        if ($this->isCsrfTokenValid('remove_member' . $memberId, $request->request->get('_token'))) {
            try {
                $this->teamRepository->removeMemberFromTeam($memberId);
                $this->addFlash('success', 'Le membre a été retiré de l\'équipe avec succès.');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression du membre.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }

    /**
     * Exporte la liste des équipes au format CSV
     */
    #[Route('/export/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $teams = $this->teamRepository->findAllWithDetails();

        $csvContent = "Nom,Description,Département,Chef d'équipe,Membres actifs,Statut,Date de création\n";
        
        foreach ($teams as $team) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%d,%s,%s\n",
                $this->escapeCsv($team->getName()),
                $this->escapeCsv($team->getDescription() ?? ''),
                $this->escapeCsv($team->getDepartment() ? $team->getDepartment()->getName() : ''),
                $this->escapeCsv($team->getLeader() ? $team->getLeader()->getFirstName() . ' ' . $team->getLeader()->getLastName() : ''),
                count($this->teamRepository->findActiveMembersByTeam($team)),
                $team->isActive() ? 'Actif' : 'Inactif',
                $team->getCreatedAt()->format('d/m/Y')
            );
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="equipes_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Échappe les valeurs pour le CSV
     */
    private function escapeCsv(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Active une équipe
     */
    #[Route('/{id}/activate', name: 'activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('activate' . $team->getId(), $request->request->get('_token'))) {
            $team->setIsActive(true);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'équipe a été activée avec succès.');
        }
        return $this->redirectToRoute('admin_team_index');
    }

    /**
     * Désactive une équipe
     */
    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deactivate(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('deactivate' . $team->getId(), $request->request->get('_token'))) {
            $team->setIsActive(false);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'équipe a été désactivée avec succès.');
        }
        return $this->redirectToRoute('admin_team_index');
    }

    /**
     * Gère les membres d'une équipe
     */
    #[Route('/{id}/members', name: 'members', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function members(Team $team): Response
    {
        // Logique pour gérer les membres
        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }
}