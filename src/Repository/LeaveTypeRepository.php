<?php

namespace App\Repository;

use App\Entity\LeaveType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveType>
 *
 * @method LeaveType|null find($id, $lockMode = null, $lockVersion = null)
 * @method LeaveType|null findOneBy(array $criteria, array $orderBy = null)
 * @method LeaveType[]    findAll()
 * @method LeaveType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LeaveTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveType::class);
    }

    /**
     * Trouve un type de congé par son code
     */
    public function findByCode(string $code): ?LeaveType
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Trouve tous les types de congés actifs
     */
    public function findActiveLeaveTypes(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types de congés inactifs
     */
    public function findInactiveLeaveTypes(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', false)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés payés
     */
    public function findPaidLeaveTypes(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isPaid = :paid')
            ->andWhere('lt.isActive = :active')
            ->setParameter('paid', true)
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés non payés
     */
    public function findUnpaidLeaveTypes(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isPaid = :paid')
            ->andWhere('lt.isActive = :active')
            ->setParameter('paid', false)
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés nécessitant une approbation
     */
    public function findLeaveTypesRequiringApproval(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.requiresApproval = :approval')
            ->andWhere('lt.isActive = :active')
            ->setParameter('approval', true)
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés sans approbation requise
     */
    public function findLeaveTypesWithoutApproval(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.requiresApproval = :approval')
            ->andWhere('lt.isActive = :active')
            ->setParameter('approval', false)
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des types de congés par nom, code ou description
     */
    public function searchLeaveTypes(string $query): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.name LIKE :query')
            ->orWhere('lt.code LIKE :query')
            ->orWhere('lt.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés par nombre maximum de jours
     */
    public function findByMaxDaysRange(int $minDays, int $maxDays): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.maxDaysPerYear BETWEEN :minDays AND :maxDays')
            ->andWhere('lt.isActive = :active')
            ->setParameter('minDays', $minDays)
            ->setParameter('maxDays', $maxDays)
            ->setParameter('active', true)
            ->orderBy('lt.maxDaysPerYear', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés avec le plus de jours autorisés
     */
    public function findWithMostDays(int $limit = 5): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lt.maxDaysPerYear', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés avec le moins de jours autorisés
     */
    public function findWithFewestDays(int $limit = 5): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->andWhere('lt.maxDaysPerYear > 0')
            ->setParameter('active', true)
            ->orderBy('lt.maxDaysPerYear', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés avec leurs demandes associées
     */
    public function findLeaveTypesWithRequests(): array
    {
        return $this->createQueryBuilder('lt')
            ->leftJoin('lt.leaveRequests', 'lr')
            ->where('lt.isActive = :active')
            ->andWhere('lr.id IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés sans demandes associées
     */
    public function findUnusedLeaveTypes(): array
    {
        return $this->createQueryBuilder('lt')
            ->leftJoin('lt.leaveRequests', 'lr')
            ->where('lt.isActive = :active')
            ->andWhere('lr.id IS NULL')
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de demandes par type de congé
     */
    public function countRequestsByLeaveType(): array
    {
        return $this->createQueryBuilder('lt')
            ->select('lt.id, lt.name, lt.code, COUNT(lr.id) as requestCount')
            ->leftJoin('lt.leaveRequests', 'lr')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('requestCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés par couleur
     */
    public function findByColor(string $color): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.color = :color')
            ->andWhere('lt.isActive = :active')
            ->setParameter('color', $color)
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés créés dans une période donnée
     */
    public function findByCreationDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('lt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés récemment créés (30 derniers jours)
     */
    public function findRecentlyCreated(): array
    {
        $thirtyDaysAgo = new \DateTime('-30 days');
        
        return $this->createQueryBuilder('lt')
            ->where('lt.createdAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->orderBy('lt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des types de congés
     */
    public function getLeaveTypeStatistics(): array
    {
        return $this->createQueryBuilder('lt')
            ->select('
                lt.id,
                lt.name,
                lt.code,
                lt.maxDaysPerYear,
                lt.isPaid,
                lt.requiresApproval,
                COUNT(DISTINCT lr.id) as totalRequests,
                COUNT(DISTINCT lb.id) as totalBalances,
                COUNT(DISTINCT lp.id) as totalPolicies
            ')
            ->leftJoin('lt.leaveRequests', 'lr')
            ->leftJoin('lt.leaveBalances', 'lb')
            ->leftJoin('lt.leavePolicies', 'lp')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés les plus utilisés
     */
    public function findMostUsedLeaveTypes(int $limit = 10): array
    {
        return $this->createQueryBuilder('lt')
            ->select('lt, COUNT(lr.id) as requestCount')
            ->leftJoin('lt.leaveRequests', 'lr')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('requestCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés avec leurs politiques associées
     */
    public function findLeaveTypesWithPolicies(): array
    {
        return $this->createQueryBuilder('lt')
            ->leftJoin('lt.leavePolicies', 'lp')
            ->where('lt.isActive = :active')
            ->andWhere('lp.isActive = :policyActive')
            ->setParameter('active', true)
            ->setParameter('policyActive', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés sans politiques associées
     */
    public function findLeaveTypesWithoutPolicies(): array
    {
        return $this->createQueryBuilder('lt')
            ->leftJoin('lt.leavePolicies', 'lp')
            ->where('lt.isActive = :active')
            ->andWhere('lp.id IS NULL')
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de types de congés actifs
     */
    public function countActiveLeaveTypes(): int
    {
        return $this->createQueryBuilder('lt')
            ->select('COUNT(lt.id)')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Active ou désactive un type de congé
     */
    public function toggleLeaveTypeStatus(int $leaveTypeId): bool
    {
        $leaveType = $this->find($leaveTypeId);
        if (!$leaveType) {
            return false;
        }

        $leaveType->setIsActive(!$leaveType->isIsActive());
        $this->getEntityManager()->persist($leaveType);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Vérifie si un code de type de congé existe déjà
     */
    public function codeExists(string $code, ?int $excludeLeaveTypeId = null): bool
    {
        $qb = $this->createQueryBuilder('lt')
            ->select('COUNT(lt.id)')
            ->where('lt.code = :code')
            ->setParameter('code', $code);

        if ($excludeLeaveTypeId) {
            $qb->andWhere('lt.id != :excludeLeaveTypeId')
               ->setParameter('excludeLeaveTypeId', $excludeLeaveTypeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un nom de type de congé existe déjà
     */
    public function nameExists(string $name, ?int $excludeLeaveTypeId = null): bool
    {
        $qb = $this->createQueryBuilder('lt')
            ->select('COUNT(lt.id)')
            ->where('lt.name = :name')
            ->setParameter('name', $name);

        if ($excludeLeaveTypeId) {
            $qb->andWhere('lt.id != :excludeLeaveTypeId')
               ->setParameter('excludeLeaveTypeId', $excludeLeaveTypeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Trouve les types de congés avec pagination
     */
    public function findLeaveTypesWithPagination(int $page = 1, int $limit = 10, bool $activeOnly = true): array
    {
        $offset = ($page - 1) * $limit;
        $qb = $this->createQueryBuilder('lt');
        
        if ($activeOnly) {
            $qb->where('lt.isActive = :active')
               ->setParameter('active', true);
        }
        
        return $qb->orderBy('lt.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés pour les listes déroulantes
     */
    public function findForDropdown(): array
    {
        return $this->createQueryBuilder('lt')
            ->select('lt.id, lt.name, lt.code, lt.color')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de congés par critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true);

        if (isset($criteria['isPaid'])) {
            $qb->andWhere('lt.isPaid = :isPaid')
               ->setParameter('isPaid', $criteria['isPaid']);
        }

        if (isset($criteria['requiresApproval'])) {
            $qb->andWhere('lt.requiresApproval = :requiresApproval')
               ->setParameter('requiresApproval', $criteria['requiresApproval']);
        }

        if (isset($criteria['minDays'])) {
            $qb->andWhere('lt.maxDaysPerYear >= :minDays')
               ->setParameter('minDays', $criteria['minDays']);
        }

        if (isset($criteria['maxDays'])) {
            $qb->andWhere('lt.maxDaysPerYear <= :maxDays')
               ->setParameter('maxDays', $criteria['maxDays']);
        }

        return $qb->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les couleurs utilisées par les types de congés
     */
    public function findUsedColors(): array
    {
        return $this->createQueryBuilder('lt')
            ->select('DISTINCT lt.color')
            ->where('lt.isActive = :active')
            ->andWhere('lt.color IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('lt.color', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Met à jour un type de congé
     */
    public function updateLeaveType(LeaveType $leaveType): void
    {
        $this->getEntityManager()->persist($leaveType);
        $this->getEntityManager()->flush();
    }
}