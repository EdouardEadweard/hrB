<?php

namespace App\Controller\Manager;

use App\Entity\TeamMember;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Manager\TeamMemberType;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager/team-member')]
#[IsGranted('ROLE_MANAGER')]
class TeamMemberController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TeamMemberRepository $teamMemberRepository;
    private TeamRepository $teamRepository;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TeamMemberRepository $teamMemberRepository,
        TeamRepository $teamRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->teamMemberRepository = $teamMemberRepository;
        $this->teamRepository = $teamRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/', name: 'manager_team_member_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();
    
    // Récupérer les équipes dirigées par le manager connecté
    $managedTeams = $this->teamRepository->findBy(['leader' => $currentUser, 'isActive' => true]);
    
    // Récupérer tous les membres des équipes du manager dans une liste plate
    $teamMembers = [];
    foreach ($managedTeams as $team) {
        $members = $this->teamMemberRepository->findBy(['team' => $team], ['joinedAt' => 'DESC']);
        $teamMembers = array_merge($teamMembers, $members);
    }

    // Trier par équipe puis par date d'adhésion
    usort($teamMembers, function($a, $b) {
        $teamCompare = strcmp($a->getTeam()->getName(), $b->getTeam()->getName());
        if ($teamCompare === 0) {
            return $b->getJoinedAt() <=> $a->getJoinedAt();
        }
        return $teamCompare;
    });

    return $this->render('manager/team_member/index.html.twig', [
        'teamMembers' => $teamMembers,
        'managedTeams' => $managedTeams,
        'stats' => [
            'totalMembers' => count($teamMembers),
            'activeMembers' => count(array_filter($teamMembers, fn($tm) => $tm->isActive())),
            'totalTeams' => count($managedTeams),
            'pendingRequests' => 0, // À adapter selon vos besoins
        ]
    ]);
    }

    #[Route('/team/{teamId}', name: 'manager_team_member_by_team', methods: ['GET'])]
    public function indexByTeam(int $teamId): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien cette équipe
        $team = $this->teamRepository->find($teamId);
        if (!$team || $team->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à gérer cette équipe.');
        }

        $teamMembers = $this->teamMemberRepository->findBy(['team' => $team], ['joinedAt' => 'DESC']);

        // Dans la méthode indexByTeam(), remplacez le return par :
        return $this->render('manager/team_member/index.html.twig', [
            'teamMembers' => [$team->getName() => $teamMembers], // Changé de team_members à teamMembers
            'managedTeams' => [$team], // Changé de managed_teams à managedTeams
            'selectedTeam' => $team, // Changé de selected_team à selectedTeam
            'stats' => [
                'totalMembers' => count($teamMembers),
                'activeMembers' => count(array_filter($teamMembers, fn($tm) => $tm->isActive())),
                'totalTeams' => 1,
                'pendingRequests' => 0,
            ]
        ]);
    }

    #[Route('/new', name: 'manager_team_member_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $currentUser = $this->getUser();
        $teamMember = new TeamMember();
        
        // Récupérer les équipes dirigées par le manager
        $managedTeams = $this->teamRepository->findBy(['leader' => $currentUser, 'isActive' => true]);
        
        if (empty($managedTeams)) {
            $this->addFlash('warning', 'Vous ne dirigez aucune équipe actuellement.');
            return $this->redirectToRoute('manager_team_member_index');
        }

        $form = $this->createForm(TeamMemberType::class, $teamMember, [
            'managed_teams' => $managedTeams,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que l'utilisateur n'est pas déjà membre de cette équipe
            $existingMember = $this->teamMemberRepository->findOneBy([
                'team' => $teamMember->getTeam(),
                'user' => $teamMember->getUser(),
                'isActive' => true
            ]);

            if ($existingMember) {
                $this->addFlash('error', 'Cet utilisateur est déjà membre de cette équipe.');
                return $this->render('manager/team_member/new.html.twig', [
                    'team_member' => $teamMember,
                    'form' => $form->createView(),
                ]);
            }

            $teamMember->setJoinedAt(new \DateTime());
            $teamMember->setIsActive(true);

            $this->entityManager->persist($teamMember);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le membre a été ajouté à l\'équipe avec succès.');
            return $this->redirectToRoute('manager_team_member_index');
        }

        return $this->render('manager/team_member/new.html.twig', [
            'team_member' => $teamMember,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'manager_team_member_show', methods: ['GET'])]
    public function show(TeamMember $teamMember): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien l'équipe du membre
        if ($teamMember->getTeam()->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir ce membre d\'équipe.');
        }

        return $this->render('manager/team_member/show.html.twig', [
            'team_member' => $teamMember,
        ]);
    }

    #[Route('/{id}/edit', name: 'manager_team_member_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TeamMember $teamMember): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien l'équipe du membre
        if ($teamMember->getTeam()->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce membre d\'équipe.');
        }

        // Récupérer les équipes dirigées par le manager
        $managedTeams = $this->teamRepository->findBy(['leader' => $currentUser, 'isActive' => true]);

        $form = $this->createForm(TeamMemberType::class, $teamMember, [
            'managed_teams' => $managedTeams,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si l'équipe a changé, vérifier que l'utilisateur n'est pas déjà membre de la nouvelle équipe
            $originalTeam = $this->entityManager->getUnitOfWork()->getOriginalEntityData($teamMember)['team'] ?? null;
            
            if ($originalTeam !== $teamMember->getTeam()) {
                $existingMember = $this->teamMemberRepository->findOneBy([
                    'team' => $teamMember->getTeam(),
                    'user' => $teamMember->getUser(),
                    'isActive' => true
                ]);

                if ($existingMember && $existingMember->getId() !== $teamMember->getId()) {
                    $this->addFlash('error', 'Cet utilisateur est déjà membre de cette équipe.');
                    return $this->render('manager/team_member/edit.html.twig', [
                        'team_member' => $teamMember,
                        'form' => $form->createView(),
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Le membre d\'équipe a été modifié avec succès.');
            return $this->redirectToRoute('manager_team_member_index');
        }

        return $this->render('manager/team_member/edit.html.twig', [
            'team_member' => $teamMember,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'manager_team_member_deactivate', methods: ['POST'])]
    public function deactivate(Request $request, TeamMember $teamMember): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien l'équipe du membre
        if ($teamMember->getTeam()->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à désactiver ce membre d\'équipe.');
        }

        if ($this->isCsrfTokenValid('deactivate'.$teamMember->getId(), $request->request->get('_token'))) {
            $teamMember->setIsActive(false);
            $teamMember->setLeftAt(new \DateTime());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Le membre a été retiré de l\'équipe avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('manager_team_member_index');
    }

    #[Route('/{id}/reactivate', name: 'manager_team_member_reactivate', methods: ['POST'])]
    public function reactivate(Request $request, TeamMember $teamMember): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien l'équipe du membre
        if ($teamMember->getTeam()->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à réactiver ce membre d\'équipe.');
        }

        if ($this->isCsrfTokenValid('reactivate', $request->request->get('_token'))) {
            // Vérifier qu'il n'y a pas déjà un membre actif avec le même utilisateur dans cette équipe
            $existingActiveMember = $this->teamMemberRepository->findOneBy([
                'team' => $teamMember->getTeam(),
                'user' => $teamMember->getUser(),
                'isActive' => true
            ]);

            if ($existingActiveMember) {
                $this->addFlash('error', 'Cet utilisateur est déjà membre actif de cette équipe.');
            } else {
                $teamMember->setIsActive(true);
                $teamMember->setLeftAt(null);
                $teamMember->setJoinedAt(new \DateTime()); // Nouvelle date d'adhésion
                
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Le membre a été réactivé dans l\'équipe avec succès.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('manager_team_member_index');
    }

    #[Route('/{id}/delete', name: 'manager_team_member_delete', methods: ['POST'])]
    public function delete(Request $request, TeamMember $teamMember): Response
    {
        $currentUser = $this->getUser();
        
        // Vérifier que le manager dirige bien l'équipe du membre
        if ($teamMember->getTeam()->getLeader() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce membre d\'équipe.');
        }

        if ($this->isCsrfTokenValid('delete'.$teamMember->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($teamMember);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Le membre d\'équipe a été supprimé définitivement.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('manager_team_member_index');
    }

    // Ajoutez cette méthode privée pour compter les membres actifs :
    private function countActiveMembers(array $teamMembers): int
    {
        $activeCount = 0;
        foreach ($teamMembers as $members) {
            foreach ($members as $member) {
                if ($member->getIsActive()) {
                    $activeCount++;
                }
            }
        }
        return $activeCount;
    }

}