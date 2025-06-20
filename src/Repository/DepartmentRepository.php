<?php

namespace App\Repository;

use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Department>
 *
 * @method Department|null find($id, $lockMode = null, $lockVersion = null)
 * @method Department|null findOneBy(array $criteria, array $orderBy = null)
 * @method Department[]    findAll()
 * @method Department[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepartmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Department::class);
    }

    /**
     * Trouve un département par son code
     */
    public function findByCode(string $code): ?Department
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Trouve tous les départements actifs
     */
    public function findActiveDepartments(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les départements inactifs
     */
    public function findInactiveDepartments(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = :active')
            ->setParameter('active', false)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des départements par nom ou code
     */
    public function searchDepartments(string $query): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.name LIKE :query')
            ->orWhere('d.code LIKE :query')
            ->orWhere('d.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements avec leurs employés
     */
    public function findDepartmentsWithEmployees(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employees', 'e')
            ->where('d.isActive = :active')
            ->andWhere('e.isActive = :employeeActive')
            ->setParameter('active', true)
            ->setParameter('employeeActive', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements sans employés
     */
    public function findEmptyDepartments(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employees', 'e')
            ->where('d.isActive = :active')
            ->andWhere('e.id IS NULL')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements gérés par un manager spécifique
     */
    public function findByManager(int $managerId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.manager = :managerId')
            ->andWhere('d.isActive = :active')
            ->setParameter('managerId', $managerId)
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements sans manager assigné
     */
    public function findDepartmentsWithoutManager(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.manager IS NULL')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de départements actifs
     */
    public function countActiveDepartments(): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'employés par département
     */
    public function countEmployeesByDepartment(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.id, d.name, d.code, COUNT(e.id) as employeeCount')
            ->leftJoin('d.employees', 'e', 'WITH', 'e.isActive = :employeeActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('employeeActive', true)
            ->groupBy('d.id')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements avec le nombre d'équipes
     */
    public function findDepartmentsWithTeamCount(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.id, d.name, d.code, COUNT(t.id) as teamCount')
            ->leftJoin('d.teams', 't', 'WITH', 't.isActive = :teamActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('teamActive', true)
            ->groupBy('d.id')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements créés dans une période donnée
     */
    public function findByCreationDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements récemment créés (30 derniers jours)
     */
    public function findRecentlyCreated(): array
    {
        $thirtyDaysAgo = new \DateTime('-30 days');
        
        return $this->createQueryBuilder('d')
            ->where('d.createdAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements avec leurs politiques de congés
     */
    public function findDepartmentsWithLeavePolicies(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.leavePolicies', 'lp')
            ->where('d.isActive = :active')
            ->andWhere('lp.isActive = :policyActive')
            ->setParameter('active', true)
            ->setParameter('policyActive', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements sans politiques de congés
     */
    public function findDepartmentsWithoutLeavePolicies(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.leavePolicies', 'lp')
            ->where('d.isActive = :active')
            ->andWhere('lp.id IS NULL')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques complètes des départements
     */
    public function getDepartmentStatistics(): array
    {
        return $this->createQueryBuilder('d')
            ->select('
                d.id,
                d.name,
                d.code,
                COUNT(DISTINCT e.id) as employeeCount,
                COUNT(DISTINCT t.id) as teamCount,
                COUNT(DISTINCT lp.id) as policyCount
            ')
            ->leftJoin('d.employees', 'e', 'WITH', 'e.isActive = :employeeActive')
            ->leftJoin('d.teams', 't', 'WITH', 't.isActive = :teamActive')
            ->leftJoin('d.leavePolicies', 'lp', 'WITH', 'lp.isActive = :policyActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('employeeActive', true)
            ->setParameter('teamActive', true)
            ->setParameter('policyActive', true)
            ->groupBy('d.id')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active ou désactive un département
     */
    public function toggleDepartmentStatus(int $departmentId): bool
    {
        $department = $this->find($departmentId);
        if (!$department) {
            return false;
        }

        $department->setIsActive(!$department->getActiveEmployees());
        $department->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->persist($department);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Vérifie si un code de département existe déjà
     */
    public function codeExists(string $code, ?int $excludeDepartmentId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.code = :code')
            ->setParameter('code', $code);

        if ($excludeDepartmentId) {
            $qb->andWhere('d.id != :excludeDepartmentId')
               ->setParameter('excludeDepartmentId', $excludeDepartmentId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un nom de département existe déjà
     */
    public function nameExists(string $name, ?int $excludeDepartmentId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.name = :name')
            ->setParameter('name', $name);

        if ($excludeDepartmentId) {
            $qb->andWhere('d.id != :excludeDepartmentId')
               ->setParameter('excludeDepartmentId', $excludeDepartmentId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Trouve les départements avec pagination
     */
    public function findDepartmentsWithPagination(int $page = 1, int $limit = 10, bool $activeOnly = true): array
    {
        $offset = ($page - 1) * $limit;
        $qb = $this->createQueryBuilder('d');
        
        if ($activeOnly) {
            $qb->where('d.isActive = :active')
               ->setParameter('active', true);
        }
        
        return $qb->orderBy('d.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements les plus actifs (avec le plus d'employés)
     */
    public function findMostActiveDepartments(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->select('d, COUNT(e.id) as employeeCount')
            ->leftJoin('d.employees', 'e', 'WITH', 'e.isActive = :employeeActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('employeeActive', true)
            ->groupBy('d.id')
            ->orderBy('employeeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements par ordre alphabétique pour les listes déroulantes
     */
    public function findForDropdown(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.id, d.name, d.code')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la date de modification d'un département
     */
    public function updateModificationDate(Department $department): void
    {
        $department->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->persist($department);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve les départements avec leurs managers (avec infos complètes)
     */
    public function findDepartmentsWithManagerDetails(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d, m')
            ->leftJoin('d.manager', 'm')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}