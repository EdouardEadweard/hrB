<?php

namespace App\Repository;

use App\Entity\LeaveBalance;
use App\Entity\User;
use App\Entity\LeaveType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<LeaveBalance>
 *
 * @method LeaveBalance|null find($id, $lockMode = null, $lockVersion = null)
 * @method LeaveBalance|null findOneBy(array $criteria, array $orderBy = null)
 * @method LeaveBalance[]    findAll()
 * @method LeaveBalance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LeaveBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveBalance::class);
    }

    /**
     * Trouver le solde de congés d'un employé pour une année donnée
     */
    public function findByEmployeeAndYear(User $employee, int $year): array
    {
        return $this->createQueryBuilder('lb')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.employee = :employee')
            ->andWhere('lb.year = :year')
            ->setParameter('employee', $employee)
            ->setParameter('year', $year)
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver un solde spécifique pour un employé, année et type de congé
     */
    public function findOneByEmployeeYearAndType(User $employee, int $year, LeaveType $leaveType): ?LeaveBalance
    {
        return $this->createQueryBuilder('lb')
            ->andWhere('lb.employee = :employee')
            ->andWhere('lb.year = :year')
            ->andWhere('lb.leaveType = :leaveType')
            ->setParameter('employee', $employee)
            ->setParameter('year', $year)
            ->setParameter('leaveType', $leaveType)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver tous les soldes pour l'année courante
     */
    public function findForCurrentYear(): array
    {
        $currentYear = (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('year', $currentYear)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->addOrderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les soldes d'un employé pour l'année courante
     */
    public function findCurrentYearBalancesByEmployee(User $employee): array
    {
        $currentYear = (int) date('Y');
        
        return $this->findByEmployeeAndYear($employee, $currentYear);
    }

    /**
     * Trouver les employés avec des soldes de congés négatifs
     */
    public function findEmployeesWithNegativeBalance(int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.remainingDays < 0')
            ->andWhere('lb.year = :year')
            ->setParameter('year', $year)
            ->orderBy('lb.remainingDays', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les employés avec des soldes de congés faibles (moins de X jours)
     */
    public function findEmployeesWithLowBalance(int $threshold = 5, int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.remainingDays <= :threshold')
            ->andWhere('lb.remainingDays >= 0')
            ->andWhere('lb.year = :year')
            ->setParameter('threshold', $threshold)
            ->setParameter('year', $year)
            ->orderBy('lb.remainingDays', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les soldes par type de congé pour une année
     */
    public function findByLeaveTypeAndYear(LeaveType $leaveType, int $year): array
    {
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->andWhere('lb.leaveType = :leaveType')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('leaveType', $leaveType)
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les soldes par département pour une année
     */
    public function findByDepartmentAndYear(int $departmentId, int $year): array
    {
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('e.department', 'd')
            ->join('lb.leaveType', 'lt')
            ->andWhere('d.id = :departmentId')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->addOrderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculer les statistiques des soldes pour un département
     */
    public function getDepartmentBalanceStats(int $departmentId, int $year): array
    {
        $result = $this->createQueryBuilder('lb')
            ->select('
                lt.name as leaveTypeName,
                SUM(lb.totalDays) as totalAllocated,
                SUM(lb.usedDays) as totalUsed,
                SUM(lb.remainingDays) as totalRemaining,
                AVG(lb.remainingDays) as averageRemaining,
                COUNT(lb.id) as employeeCount
            ')
            ->join('lb.employee', 'e')
            ->join('e.department', 'd')
            ->join('lb.leaveType', 'lt')
            ->andWhere('d.id = :departmentId')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Obtenir les soldes totaux par type de congé pour toute l'entreprise
     */
    public function getCompanyWideBalanceStats(int $year): array
    {
        return $this->createQueryBuilder('lb')
            ->select('
                lt.name as leaveTypeName,
                lt.code as leaveTypeCode,
                SUM(lb.totalDays) as totalAllocated,
                SUM(lb.usedDays) as totalUsed,
                SUM(lb.remainingDays) as totalRemaining,
                AVG(lb.remainingDays) as averageRemaining,
                COUNT(lb.id) as employeeCount
            ')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les employés qui n'ont pas utilisé de congés
     */
    public function findEmployeesWithNoUsedLeave(int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.usedDays = 0')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les employés qui ont utilisé tous leurs congés
     */
    public function findEmployeesWithFullyUsedLeave(int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.remainingDays = 0')
            ->andWhere('lb.totalDays > 0')
            ->andWhere('lb.year = :year')
            ->andWhere('e.isActive = :active')
            ->setParameter('year', $year)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mettre à jour le solde après utilisation de congés
     */
    public function updateUsedDays(User $employee, LeaveType $leaveType, int $year, int $daysUsed): bool
    {
        $balance = $this->findOneByEmployeeYearAndType($employee, $year, $leaveType);
        
        if (!$balance) {
            return false;
        }

        $balance->setUsedDays($balance->getUsedDays() + $daysUsed);
        $balance->setRemainingDays($balance->getTotalDays() - $balance->getUsedDays());
        $balance->setLastUpdated(new \DateTime());

        $this->save($balance, true);
        return true;
    }

    /**
     * Réinitialiser les soldes pour une nouvelle année
     */
    public function initializeBalancesForNewYear(int $newYear): int
    {
        // Cette méthode pourrait être utilisée pour créer les soldes de base
        // pour tous les employés actifs au début d'une nouvelle année
        $em = $this->getEntityManager();
        $userRepository = $em->getRepository(User::class);
        $leaveTypeRepository = $em->getRepository(LeaveType::class);
        
        $activeEmployees = $userRepository->findBy(['isActive' => true]);
        $activeLeaveTypes = $leaveTypeRepository->findBy(['isActive' => true]);
        
        $createdBalances = 0;
        
        foreach ($activeEmployees as $employee) {
            foreach ($activeLeaveTypes as $leaveType) {
                // Vérifier si le solde existe déjà
                $existingBalance = $this->findOneByEmployeeYearAndType($employee, $newYear, $leaveType);
                
                if (!$existingBalance) {
                    $balance = new LeaveBalance();
                    $balance->setEmployee($employee);
                    $balance->setLeaveType($leaveType);
                    $balance->setYear($newYear);
                    $balance->setTotalDays($leaveType->getMaxDaysPerYear());
                    $balance->setUsedDays(0);
                    $balance->setRemainingDays($leaveType->getMaxDaysPerYear());
                    $balance->setLastUpdated(new \DateTime());
                    
                    $em->persist($balance);
                    $createdBalances++;
                }
            }
        }
        
        $em->flush();
        return $createdBalances;
    }

    /**
     * Trouver les soldes avec des données incohérentes
     */
    public function findInconsistentBalances(int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.remainingDays != (lb.totalDays - lb.usedDays)')
            ->andWhere('lb.year = :year')
            ->setParameter('year', $year)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec filtres multiples
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->join('lb.leaveType', 'lt')
            ->leftJoin('e.department', 'd');

        if (!empty($filters['employee_id'])) {
            $qb->andWhere('e.id = :employeeId')
               ->setParameter('employeeId', $filters['employee_id']);
        }

        if (!empty($filters['department_id'])) {
            $qb->andWhere('d.id = :departmentId')
               ->setParameter('departmentId', $filters['department_id']);
        }

        if (!empty($filters['leave_type_id'])) {
            $qb->andWhere('lt.id = :leaveTypeId')
               ->setParameter('leaveTypeId', $filters['leave_type_id']);
        }

        if (!empty($filters['year'])) {
            $qb->andWhere('lb.year = :year')
               ->setParameter('year', $filters['year']);
        }

        if (isset($filters['low_balance']) && $filters['low_balance']) {
            $threshold = $filters['low_balance_threshold'] ?? 5;
            $qb->andWhere('lb.remainingDays <= :threshold')
               ->andWhere('lb.remainingDays >= 0')
               ->setParameter('threshold', $threshold);
        }

        if (isset($filters['negative_balance']) && $filters['negative_balance']) {
            $qb->andWhere('lb.remainingDays < 0');
        }

        if (isset($filters['fully_used']) && $filters['fully_used']) {
            $qb->andWhere('lb.remainingDays = 0')
               ->andWhere('lb.totalDays > 0');
        }

        if (isset($filters['unused']) && $filters['unused']) {
            $qb->andWhere('lb.usedDays = 0');
        }

        return $qb->orderBy('e.lastName', 'ASC')
                  ->addOrderBy('e.firstName', 'ASC')
                  ->addOrderBy('lt.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Obtenir l'historique des soldes d'un employé sur plusieurs années
     */
    public function getEmployeeBalanceHistory(User $employee, int $yearsBack = 3): array
    {
        $currentYear = (int) date('Y');
        $startYear = $currentYear - $yearsBack;
        
        return $this->createQueryBuilder('lb')
            ->join('lb.leaveType', 'lt')
            ->andWhere('lb.employee = :employee')
            ->andWhere('lb.year BETWEEN :startYear AND :currentYear')
            ->setParameter('employee', $employee)
            ->setParameter('startYear', $startYear)
            ->setParameter('currentYear', $currentYear)
            ->orderBy('lb.year', 'DESC')
            ->addOrderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(LeaveBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LeaveBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}