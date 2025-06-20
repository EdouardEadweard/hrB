<?php

namespace App\Repository;

use App\Entity\LeaveRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 *
 * @method LeaveRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method LeaveRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method LeaveRequest[]    findAll()
 * @method LeaveRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LeaveRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    /**
     * Trouver toutes les demandes de congés d'un employé spécifique
     */
    public function findByEmployee(User $employee, array $orderBy = ['submittedAt' => 'DESC']): array
    {
        $qb = $this->createQueryBuilder('lr')
            ->andWhere('lr.employee = :employee')
            ->setParameter('employee', $employee);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('lr.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouver les demandes en attente d'approbation
     */
    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('lr.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes approuvées par un manager
     */
    public function findApprovedByManager(User $manager): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.approvedBy = :manager')
            ->andWhere('lr.status = :status')
            ->setParameter('manager', $manager)
            ->setParameter('status', 'approved')
            ->orderBy('lr.processedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('lr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes par période
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.startDate >= :startDate')
            ->andWhere('lr.endDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('lr.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes qui se chevauchent avec une période donnée
     */
    public function findOverlappingRequests(\DateTime $startDate, \DateTime $endDate, User $employee = null): array
    {
        $qb = $this->createQueryBuilder('lr')
            ->andWhere('lr.startDate <= :endDate')
            ->andWhere('lr.endDate >= :startDate')
            ->andWhere('lr.status != :rejectedStatus')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('rejectedStatus', 'rejected');

        if ($employee) {
            $qb->andWhere('lr.employee = :employee')
               ->setParameter('employee', $employee);
        }

        return $qb->orderBy('lr.startDate', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouver les demandes par type de congé
     */
    public function findByLeaveType(int $leaveTypeId): array
    {
        return $this->createQueryBuilder('lr')
            ->join('lr.leaveType', 'lt')
            ->andWhere('lt.id = :leaveTypeId')
            ->setParameter('leaveTypeId', $leaveTypeId)
            ->orderBy('lr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les demandes par statut pour un employé
     */
    public function countByStatusForEmployee(User $employee): array
    {
        $result = $this->createQueryBuilder('lr')
            ->select('lr.status, COUNT(lr.id) as count')
            ->andWhere('lr.employee = :employee')
            ->setParameter('employee', $employee)
            ->groupBy('lr.status')
            ->getQuery()
            ->getResult();

        // Transformer le résultat en tableau associatif
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Calculer le total de jours de congés demandés par un employé pour une année
     */
    public function getTotalDaysRequestedByEmployeeForYear(User $employee, int $year): int
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        $result = $this->createQueryBuilder('lr')
            ->select('SUM(lr.numberOfDays)')
            ->andWhere('lr.employee = :employee')
            ->andWhere('lr.startDate >= :startDate')
            ->andWhere('lr.endDate <= :endDate')
            ->andWhere('lr.status IN (:statuses)')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statuses', ['approved', 'pending'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Trouver les demandes récentes (7 derniers jours)
     */
    public function findRecentRequests(): array
    {
        $sevenDaysAgo = new \DateTime('-7 days');

        return $this->createQueryBuilder('lr')
            ->andWhere('lr.submittedAt >= :sevenDaysAgo')
            ->setParameter('sevenDaysAgo', $sevenDaysAgo)
            ->orderBy('lr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes d'un département
     */
    public function findByDepartment(int $departmentId): array
    {
        return $this->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->join('e.department', 'd')
            ->andWhere('d.id = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('lr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes qui commencent bientôt (dans les 30 prochains jours)
     */
    public function findUpcomingRequests(): array
    {
        $today = new \DateTime();
        $thirtyDaysFromNow = new \DateTime('+30 days');

        return $this->createQueryBuilder('lr')
            ->andWhere('lr.startDate BETWEEN :today AND :thirtyDays')
            ->andWhere('lr.status = :status')
            ->setParameter('today', $today)
            ->setParameter('thirtyDays', $thirtyDaysFromNow)
            ->setParameter('status', 'approved')
            ->orderBy('lr.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('lr')
            ->leftJoin('lr.employee', 'e')
            ->leftJoin('lr.leaveType', 'lt')
            ->leftJoin('lr.approvedBy', 'ab');

        if (!empty($filters['employee_id'])) {
            $qb->andWhere('e.id = :employeeId')
               ->setParameter('employeeId', $filters['employee_id']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('lr.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['leave_type_id'])) {
            $qb->andWhere('lt.id = :leaveTypeId')
               ->setParameter('leaveTypeId', $filters['leave_type_id']);
        }

        if (!empty($filters['start_date'])) {
            $qb->andWhere('lr.startDate >= :startDate')
               ->setParameter('startDate', new \DateTime($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $qb->andWhere('lr.endDate <= :endDate')
               ->setParameter('endDate', new \DateTime($filters['end_date']));
        }

        if (!empty($filters['department_id'])) {
            $qb->join('e.department', 'd')
               ->andWhere('d.id = :departmentId')
               ->setParameter('departmentId', $filters['department_id']);
        }

        return $qb->orderBy('lr.submittedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Statistiques des demandes par mois pour une année donnée
     */
    public function getMonthlyStatsForYear(int $year): array
    {
        return $this->createQueryBuilder('lr')
            ->select('MONTH(lr.submittedAt) as month, COUNT(lr.id) as total')
            ->andWhere('YEAR(lr.submittedAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(LeaveRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LeaveRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
 * Trouve les demandes en attente pour un manager donné
 */
public function findPendingRequestsForManager($manager): array
{
    return $this->createQueryBuilder('lr')
        ->join('lr.employee', 'e')
        ->where('e.manager = :manager')
        ->andWhere('lr.status = :status')
        ->setParameter('manager', $manager)
        ->setParameter('status', 'pending')
        ->orderBy('lr.submittedAt', 'DESC')
        ->getQuery()
        ->getResult();
}

/**
 * Trouve toutes les demandes traitées par un manager
 */
public function findProcessedRequestsByManager($manager): array
{
    return $this->createQueryBuilder('lr')
        ->where('lr.approvedBy = :manager')
        ->andWhere('lr.status IN (:statuses)')
        ->setParameter('manager', $manager)
        ->setParameter('statuses', ['approved', 'rejected'])
        ->orderBy('lr.processedAt', 'DESC')
        ->getQuery()
        ->getResult();
}

/**
 * Compte le nombre total de demandes traitées par un manager
 */
public function countProcessedByManager($manager): int
{
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->where('lr.approvedBy = :manager')
        ->andWhere('lr.status IN (:statuses)')
        ->setParameter('manager', $manager)
        ->setParameter('statuses', ['approved', 'rejected'])
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes par manager et statut
 */
public function countByManagerAndStatus($manager, string $status): int
{
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->where('lr.approvedBy = :manager')
        ->andWhere('lr.status = :status')
        ->setParameter('manager', $manager)
        ->setParameter('status', $status)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes en attente pour un manager
 */
public function countPendingForManager($manager): int
{
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->join('lr.employee', 'e')
        ->where('e.manager = :manager')
        ->andWhere('lr.status = :status')
        ->setParameter('manager', $manager)
        ->setParameter('status', 'pending')
        ->getQuery()
        ->getSingleScalarResult();
}

// Ajoutez ces méthodes dans votre LeaveRequestRepository

/**
 * Compter les demandes approuvées aujourd'hui par un manager
 */
public function countApprovedTodayByManager(User $manager): int
{
    $today = new \DateTime('today');
    $tomorrow = new \DateTime('tomorrow');
    
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->join('lr.employee', 'e')
        ->where('e.manager = :manager')
        ->andWhere('lr.status = :status')
        ->andWhere('lr.processedAt >= :today')
        ->andWhere('lr.processedAt < :tomorrow')
        ->setParameter('manager', $manager)
        ->setParameter('status', 'approved')
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compter les demandes rejetées aujourd'hui par un manager
 */
public function countRejectedTodayByManager(User $manager): int
{
    $today = new \DateTime('today');
    $tomorrow = new \DateTime('tomorrow');
    
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->join('lr.employee', 'e')
        ->where('e.manager = :manager')
        ->andWhere('lr.status = :status')
        ->andWhere('lr.processedAt >= :today')
        ->andWhere('lr.processedAt < :tomorrow')
        ->setParameter('manager', $manager)
        ->setParameter('status', 'rejected')
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compter les demandes urgentes (qui commencent dans les 3 prochains jours)
 */
public function countUrgentRequestsForManager(User $manager): int
{
    $urgentDate = new \DateTime('+3 days');
    
    return $this->createQueryBuilder('lr')
        ->select('COUNT(lr.id)')
        ->join('lr.employee', 'e')
        ->where('e.manager = :manager')
        ->andWhere('lr.status = :status')
        ->andWhere('lr.startDate <= :urgentDate')
        ->setParameter('manager', $manager)
        ->setParameter('status', 'pending')
        ->setParameter('urgentDate', $urgentDate)
        ->getQuery()
        ->getSingleScalarResult();
}

}