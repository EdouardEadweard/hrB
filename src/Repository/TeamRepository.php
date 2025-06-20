<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository pour la gestion des équipes
 * 
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * Sauvegarde une équipe
     */
    public function save(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une équipe
     */
    public function remove(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les équipes actives
     * 
     * @return Team[] Returns an array of Team objects
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes par département
     * 
     * @param Department $department Le département
     * @return Team[] Returns an array of Team objects
     */
    public function findByDepartment(Department $department): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.department = :department')
            ->andWhere('t.isActive = :active')
            ->setParameter('department', $department)
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes dirigées par un chef d'équipe
     * 
     * @param User $leader Le chef d'équipe
     * @return Team[] Returns an array of Team objects
     */
    public function findByLeader(User $leader): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.leader = :leader')
            ->andWhere('t.isActive = :active')
            ->setParameter('leader', $leader)
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes dont un utilisateur est membre
     * 
     * @param User $user L'utilisateur
     * @return Team[] Returns an array of Team objects
     */
    public function findByMember(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMembers', 'tm')
            ->andWhere('tm.user = :user')
            ->andWhere('tm.isActive = :memberActive')
            ->andWhere('t.isActive = :teamActive')
            ->setParameter('user', $user)
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une équipe avec ses membres actifs
     * 
     * @param int $teamId ID de l'équipe
     * @return Team|null
     */
    public function findWithActiveMembers(int $teamId): ?Team
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMembers', 'tm')
            ->leftJoin('tm.user', 'u')
            ->addSelect('tm', 'u')
            ->andWhere('t.id = :teamId')
            ->andWhere('tm.isActive = :active OR tm.id IS NULL')
            ->setParameter('teamId', $teamId)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les équipes avec le nombre de membres
     * 
     * @param Department|null $department Filtrer par département (optionnel)
     * @return array Returns an array with team info and member count
     */
    public function findWithMemberCount(Department $department = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t, COUNT(tm.id) as memberCount')
            ->leftJoin('t.teamMembers', 'tm', 'WITH', 'tm.isActive = :memberActive')
            ->andWhere('t.isActive = :teamActive')
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC');

        if ($department) {
            $qb->andWhere('t.department = :department')
               ->setParameter('department', $department);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de membres actifs d'une équipe
     * 
     * @param Team $team L'équipe
     * @return int Nombre de membres actifs
     */
    public function countActiveMembers(Team $team): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(tm.id)')
            ->leftJoin('t.teamMembers', 'tm')
            ->andWhere('t = :team')
            ->andWhere('tm.isActive = :active')
            ->setParameter('team', $team)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les équipes sans chef d'équipe
     * 
     * @return Team[] Returns an array of Team objects
     */
    public function findWithoutLeader(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.leader IS NULL')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes avec peu de membres (moins de X membres)
     * 
     * @param int $maxMembers Nombre maximum de membres pour être considérée comme "petite"
     * @return array Returns an array with team info
     */
    public function findSmallTeams(int $maxMembers = 3): array
    {
        return $this->createQueryBuilder('t')
            ->select('t, COUNT(tm.id) as memberCount')
            ->leftJoin('t.teamMembers', 'tm', 'WITH', 'tm.isActive = :memberActive')
            ->andWhere('t.isActive = :teamActive')
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->groupBy('t.id')
            ->having('COUNT(tm.id) <= :maxMembers')
            ->setParameter('maxMembers', $maxMembers)
            ->orderBy('memberCount', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes les plus importantes (plus de X membres)
     * 
     * @param int $minMembers Nombre minimum de membres
     * @return array Returns an array with team info
     */
    public function findLargeTeams(int $minMembers = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t, COUNT(tm.id) as memberCount')
            ->leftJoin('t.teamMembers', 'tm', 'WITH', 'tm.isActive = :memberActive')
            ->andWhere('t.isActive = :teamActive')
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->groupBy('t.id')
            ->having('COUNT(tm.id) >= :minMembers')
            ->setParameter('minMembers', $minMembers)
            ->orderBy('memberCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les équipes par nom ou description
     * 
     * @param string $searchTerm Terme de recherche
     * @return Team[] Returns an array of Team objects
     */
    public function searchByNameOrDescription(string $searchTerm): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.name LIKE :searchTerm OR t.description LIKE :searchTerm')
            ->andWhere('t.isActive = :active')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes créées récemment
     * 
     * @param int $days Nombre de jours en arrière
     * @return Team[] Returns an array of Team objects
     */
    public function findRecentlyCreated(int $days = 30): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$days} days");

        return $this->createQueryBuilder('t')
            ->andWhere('t.createdAt >= :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des équipes par département
     * 
     * @return array Statistiques par département
     */
    public function getTeamStatsByDepartment(): array
    {
        return $this->createQueryBuilder('t')
            ->select('
                d.name as department_name,
                d.id as department_id,
                COUNT(t.id) as team_count,
                COUNT(tm.id) as total_members
            ')
            ->leftJoin('t.department', 'd')
            ->leftJoin('t.teamMembers', 'tm', 'WITH', 'tm.isActive = :memberActive')
            ->andWhere('t.isActive = :teamActive')
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->groupBy('d.id, d.name')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes avec leurs dernières activités (ajouts de membres)
     * 
     * @param int $limit Nombre maximum de résultats
     * @return array Returns team data with last activity
     */
    public function findWithLastActivity(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t, MAX(tm.joinedAt) as last_activity')
            ->leftJoin('t.teamMembers', 'tm')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('t.id')
            ->orderBy('last_activity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur peut être chef d'équipe
     * (pas déjà chef d'une autre équipe active)
     * 
     * @param User $user L'utilisateur à vérifier
     * @param Team|null $excludeTeam Équipe à exclure de la vérification
     * @return bool True si l'utilisateur peut être chef
     */
    public function canBeLeader(User $user, Team $excludeTeam = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.leader = :user')
            ->andWhere('t.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true);

        if ($excludeTeam) {
            $qb->andWhere('t != :excludeTeam')
               ->setParameter('excludeTeam', $excludeTeam);
        }

        $count = $qb->getQuery()->getSingleScalarResult();
        
        return $count == 0;
    }

    /**
     * Trouve toutes les équipes avec pagination
     * 
     * @param int $page Numéro de page (commence à 1)
     * @param int $limit Nombre d'éléments par page
     * @param Department|null $department Filtrer par département
     * @return array ['data' => Team[], 'total' => int]
     */
    public function findAllPaginated(int $page = 1, int $limit = 10, Department $department = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $countQb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if ($department) {
            $qb->andWhere('t.department = :department')
               ->setParameter('department', $department);
            
            $countQb->andWhere('t.department = :department')
                    ->setParameter('department', $department);
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $countQb->getQuery()->getSingleScalarResult()
        ];
    }

    /**
     * Trouve les équipes actives avec pagination
     * 
     * @param int $page Numéro de page (commence à 1)
     * @param int $limit Nombre d'éléments par page
     * @param Department|null $department Filtrer par département
     * @return array ['data' => Team[], 'total' => int]
     */
    public function findActivePaginated(int $page = 1, int $limit = 10, Department $department = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $countQb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true);

        if ($department) {
            $qb->andWhere('t.department = :department')
               ->setParameter('department', $department);
            
            $countQb->andWhere('t.department = :department')
                    ->setParameter('department', $department);
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $countQb->getQuery()->getSingleScalarResult()
        ];
    }

    /**
     * Recherche multicritères pour les équipes
     * 
     * @param array $criteria Critères de recherche
     * @return Team[] Returns an array of Team objects
     */
    public function searchByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('t');

        if (isset($criteria['name'])) {
            $qb->andWhere('t.name LIKE :name')
               ->setParameter('name', '%' . $criteria['name'] . '%');
        }

        if (isset($criteria['department'])) {
            $qb->andWhere('t.department = :department')
               ->setParameter('department', $criteria['department']);
        }

        if (isset($criteria['leader'])) {
            $qb->andWhere('t.leader = :leader')
               ->setParameter('leader', $criteria['leader']);
        }

        if (isset($criteria['is_active'])) {
            $qb->andWhere('t.isActive = :active')
               ->setParameter('active', $criteria['is_active']);
        }

        if (isset($criteria['min_members'])) {
            $qb->leftJoin('t.teamMembers', 'tm_min', 'WITH', 'tm_min.isActive = :memberActive')
               ->setParameter('memberActive', true)
               ->groupBy('t.id')
               ->having('COUNT(tm_min.id) >= :minMembers')
               ->setParameter('minMembers', $criteria['min_members']);
        }

        if (isset($criteria['max_members'])) {
            if (!isset($criteria['min_members'])) {
                $qb->leftJoin('t.teamMembers', 'tm_max', 'WITH', 'tm_max.isActive = :memberActive')
                   ->setParameter('memberActive', true)
                   ->groupBy('t.id');
            }
            $qb->having('COUNT(tm_max.id) <= :maxMembers')
               ->setParameter('maxMembers', $criteria['max_members']);
        }

        return $qb->orderBy('t.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les équipes disponibles pour un utilisateur 
     * (équipes de son département où il n'est pas encore membre)
     * 
     * @param User $user L'utilisateur
     * @return Team[] Returns an array of Team objects
     */
    public function findAvailableForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMembers', 'tm', 'WITH', 'tm.user = :user AND tm.isActive = :memberActive')
            ->andWhere('t.department = :department')
            ->andWhere('t.isActive = :teamActive')
            ->andWhere('tm.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('department', $user->getDepartment())
            ->setParameter('memberActive', true)
            ->setParameter('teamActive', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Query Builder de base pour réutilisation
     * 
     * @return QueryBuilder
     */
    public function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC');
    }
}