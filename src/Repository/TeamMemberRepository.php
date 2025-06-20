<?php

namespace App\Repository;

use App\Entity\TeamMember;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<TeamMember>
 *
 * @method TeamMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeamMember|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeamMember[]    findAll()
 * @method TeamMember[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    public function save(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve tous les membres actifs d'une équipe
     */
    public function findActiveByTeam(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.team = :team')
            ->andWhere('tm.isActive = :active')
            ->setParameter('team', $team)
            ->setParameter('active', true)
            ->orderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les membres d'une équipe (actifs et inactifs)
     */
    public function findAllByTeam(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->addSelect('u')
            ->andWhere('tm.team = :team')
            ->setParameter('team', $team)
            ->orderBy('tm.isActive', 'DESC')
            ->addOrderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les équipes d'un utilisateur
     */
    public function findTeamsByUser(User $user): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.team', 't')
            ->addSelect('t')
            ->andWhere('tm.user = :user')
            ->andWhere('tm.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('tm.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur est membre d'une équipe spécifique
     */
    public function isUserInTeam(User $user, Team $team): bool
    {
        $result = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.user = :user')
            ->andWhere('tm.team = :team')
            ->andWhere('tm.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('team', $team)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Trouve le membre actif d'une équipe pour un utilisateur donné
     */
    public function findActiveTeamMember(User $user, Team $team): ?TeamMember
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.user = :user')
            ->andWhere('tm.team = :team')
            ->andWhere('tm.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('team', $team)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre de membres actifs dans une équipe
     */
    public function countActiveMembers(Team $team): int
    {
        return $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.team = :team')
            ->andWhere('tm.isActive = :active')
            ->setParameter('team', $team)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les membres qui ont quitté une équipe
     */
    public function findFormerMembers(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->addSelect('u')
            ->andWhere('tm.team = :team')
            ->andWhere('tm.isActive = :active')
            ->andWhere('tm.leftAt IS NOT NULL')
            ->setParameter('team', $team)
            ->setParameter('active', false)
            ->orderBy('tm.leftAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les membres d'équipe gérées par un manager
     */
    public function findByManager(User $manager): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.team', 't')
            ->leftJoin('tm.user', 'u')
            ->addSelect('t', 'u')
            ->andWhere('t.leader = :manager')
            ->andWhere('tm.isActive = :active')
            ->setParameter('manager', $manager)
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les membres d'équipe par département
     */
    public function findByDepartment(int $departmentId): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.team', 't')
            ->leftJoin('tm.user', 'u')
            ->addSelect('t', 'u')
            ->andWhere('t.department = :departmentId')
            ->andWhere('tm.isActive = :active')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des membres d'équipe par nom/prénom
     */
    public function searchMembers(string $searchTerm, ?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->leftJoin('tm.team', 't')
            ->addSelect('u', 't')
            ->andWhere('tm.isActive = :active')
            ->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%');

        if ($team) {
            $qb->andWhere('tm.team = :team')
               ->setParameter('team', $team);
        }

        return $qb->orderBy('u.lastName', 'ASC')
                  ->addOrderBy('u.firstName', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les membres récemment ajoutés (derniers 30 jours)
     */
    public function findRecentMembers(?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->leftJoin('tm.team', 't')
            ->addSelect('u', 't')
            ->andWhere('tm.joinedAt >= :date')
            ->andWhere('tm.isActive = :active')
            ->setParameter('date', new \DateTime('-30 days'))
            ->setParameter('active', true);

        if ($team) {
            $qb->andWhere('tm.team = :team')
               ->setParameter('team', $team);
        }

        return $qb->orderBy('tm.joinedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Désactive un membre d'équipe (au lieu de le supprimer)
     */
    public function deactivateMember(TeamMember $teamMember): void
    {
        $teamMember->setIsActive(false);
        $teamMember->setLeftAt(new \DateTime());
        
        $this->save($teamMember, true);
    }

    /**
     * Réactive un membre d'équipe
     */
    public function reactivateMember(TeamMember $teamMember): void
    {
        $teamMember->setIsActive(true);
        $teamMember->setLeftAt(null);
        $teamMember->setJoinedAt(new \DateTime());
        
        $this->save($teamMember, true);
    }

    /**
     * Statistiques des membres par équipe
     */
    public function getMembershipStats(): array
    {
        return $this->createQueryBuilder('tm')
            ->select('t.name as teamName, COUNT(tm.id) as memberCount')
            ->leftJoin('tm.team', 't')
            ->andWhere('tm.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('t.id')
            ->orderBy('memberCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes sans membres actifs
     */
    public function findTeamsWithoutMembers(): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT t FROM App\Entity\Team t
                WHERE t.id NOT IN (
                    SELECT IDENTITY(tm.team) FROM App\Entity\TeamMember tm
                    WHERE tm.isActive = :active
                )
                AND t.isActive = :teamActive
            ')
            ->setParameter('active', true)
            ->setParameter('teamActive', true)
            ->getResult();
    }

    /**
     * QueryBuilder de base pour les requêtes personnalisées
     */
    public function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->leftJoin('tm.team', 't')
            ->addSelect('u', 't');
    }
}