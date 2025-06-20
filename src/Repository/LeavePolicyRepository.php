<?php

namespace App\Repository;

use App\Entity\LeavePolicy;
use App\Entity\Department;
use App\Entity\LeaveType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<LeavePolicy>
 *
 * @method LeavePolicy|null find($id, $lockMode = null, $lockVersion = null)
 * @method LeavePolicy|null findOneBy(array $criteria, array $orderBy = null)
 * @method LeavePolicy[]    findAll()
 * @method LeavePolicy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LeavePolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeavePolicy::class);
    }

    public function save(LeavePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LeavePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les politiques actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('lp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les politiques par département
     */
    public function findByDepartment(Department $department): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('lt')
            ->andWhere('lp.department = :department')
            ->andWhere('lp.isActive = :active')
            ->setParameter('department', $department)
            ->setParameter('active', true)
            ->orderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les politiques par type de congé
     */
    public function findByLeaveType(LeaveType $leaveType): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->addSelect('d')
            ->andWhere('lp.leaveType = :leaveType')
            ->andWhere('lp.isActive = :active')
            ->setParameter('leaveType', $leaveType)
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->addOrderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une politique spécifique pour un département et un type de congé
     */
    public function findPolicyForDepartmentAndLeaveType(Department $department, LeaveType $leaveType): ?LeavePolicy
    {
        return $this->createQueryBuilder('lp')
            ->andWhere('lp.department = :department')
            ->andWhere('lp.leaveType = :leaveType')
            ->andWhere('lp.isActive = :active')
            ->setParameter('department', $department)
            ->setParameter('leaveType', $leaveType)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si une politique existe déjà pour un département et type de congé
     */
    public function policyExistsForDepartmentAndLeaveType(Department $department, LeaveType $leaveType, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('lp')
            ->select('COUNT(lp.id)')
            ->andWhere('lp.department = :department')
            ->andWhere('lp.leaveType = :leaveType')
            ->andWhere('lp.isActive = :active')
            ->setParameter('department', $department)
            ->setParameter('leaveType', $leaveType)
            ->setParameter('active', true);

        if ($excludeId) {
            $qb->andWhere('lp.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        
        return $result > 0;
    }

    /**
     * Trouve les politiques créées récemment (30 derniers jours)
     */
    public function findRecentPolicies(): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('lp.createdAt >= :date')
            ->andWhere('lp.isActive = :active')
            ->setParameter('date', new \DateTime('-30 days'))
            ->setParameter('active', true)
            ->orderBy('lp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des politiques par nom ou description
     */
    public function searchPolicies(string $searchTerm): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('lp.name LIKE :search OR lp.description LIKE :search')
            ->andWhere('lp.isActive = :active')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->setParameter('active', true)
            ->orderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de politiques par département
     */
    public function countPoliciesByDepartment(): array
    {
        return $this->createQueryBuilder('lp')
            ->select('d.name as departmentName, COUNT(lp.id) as policyCount')
            ->leftJoin('lp.department', 'd')
            ->andWhere('lp.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('d.id')
            ->orderBy('policyCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de politiques par type de congé
     */
    public function countPoliciesByLeaveType(): array
    {
        return $this->createQueryBuilder('lp')
            ->select('lt.name as leaveTypeName, COUNT(lp.id) as policyCount')
            ->leftJoin('lp.leaveType', 'lt')
            ->andWhere('lp.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('lt.id')
            ->orderBy('policyCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les départements sans politique pour un type de congé donné
     */
    public function findDepartmentsWithoutPolicyForLeaveType(LeaveType $leaveType): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT d FROM App\Entity\Department d
                WHERE d.isActive = :active
                AND d.id NOT IN (
                    SELECT IDENTITY(lp.department) FROM App\Entity\LeavePolicy lp
                    WHERE lp.leaveType = :leaveType
                    AND lp.isActive = :policyActive
                )
            ')
            ->setParameter('active', true)
            ->setParameter('leaveType', $leaveType)
            ->setParameter('policyActive', true)
            ->getResult();
    }

    /**
     * Trouve les types de congé sans politique pour un département donné
     */
    public function findLeaveTypesWithoutPolicyForDepartment(Department $department): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT lt FROM App\Entity\LeaveType lt
                WHERE lt.isActive = :active
                AND lt.id NOT IN (
                    SELECT IDENTITY(lp.leaveType) FROM App\Entity\LeavePolicy lp
                    WHERE lp.department = :department
                    AND lp.isActive = :policyActive
                )
            ')
            ->setParameter('active', true)
            ->setParameter('department', $department)
            ->setParameter('policyActive', true)
            ->getResult();
    }

    /**
     * Trouve les politiques avec des règles spécifiques (filtrage par contenu JSON)
     */
    public function findPoliciesWithRule(string $ruleKey, $ruleValue = null): array
    {
        $qb = $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('JSON_EXTRACT(lp.rules, :ruleKey) IS NOT NULL')
            ->andWhere('lp.isActive = :active')
            ->setParameter('ruleKey', '$.' . $ruleKey)
            ->setParameter('active', true);

        if ($ruleValue !== null) {
            $qb->andWhere('JSON_EXTRACT(lp.rules, :ruleKey) = :ruleValue')
               ->setParameter('ruleValue', $ruleValue);
        }

        return $qb->orderBy('lp.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les politiques obsolètes (non modifiées depuis X mois)
     */
    public function findObsoletePolicies(int $months = 12): array
    {
        $date = new \DateTime('-' . $months . ' months');
        
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('lp.createdAt < :date')
            ->andWhere('lp.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('lp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active ou désactive une politique
     */
    public function togglePolicyStatus(LeavePolicy $policy): void
    {
        $policy->setIsActive(!$policy->getIsActive());
        $this->save($policy, true);
    }

    /**
     * Trouve les politiques les plus utilisées (basé sur les demandes de congé)
     */
    public function findMostUsedPolicies(int $limit = 10): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT lp, d, lt, COUNT(lr.id) as usageCount
                FROM App\Entity\LeavePolicy lp
                LEFT JOIN lp.department d
                LEFT JOIN lp.leaveType lt
                LEFT JOIN App\Entity\LeaveRequest lr WITH lr.leaveType = lp.leaveType
                LEFT JOIN App\Entity\User u WITH lr.employee = u AND u.department = lp.department
                WHERE lp.isActive = :active
                GROUP BY lp.id
                ORDER BY usageCount DESC
            ')
            ->setParameter('active', true)
            ->setMaxResults($limit)
            ->getResult();
    }

    /**
     * Valide qu'une politique peut être appliquée (vérifie les contraintes métier)
     */
    public function validatePolicyApplication(LeavePolicy $policy, array $context = []): array
    {
        $errors = [];
        
        // Vérifier si le département existe et est actif
        if (!$policy->getDepartment() || !$policy->getDepartment()->isActive()) {
            $errors[] = 'Le département associé n\'est pas actif';
        }
        
        // Vérifier si le type de congé existe et est actif
        if (!$policy->getLeaveType() || !$policy->getLeaveType()->isIsActive()) {
            $errors[] = 'Le type de congé associé n\'est pas actif';
        }
        
        // Vérifier l'unicité département/type de congé
        if ($this->policyExistsForDepartmentAndLeaveType(
            $policy->getDepartment(), 
            $policy->getLeaveType(), 
            $policy->getId()
        )) {
            $errors[] = 'Une politique existe déjà pour ce département et ce type de congé';
        }
        
        return $errors;
    }

    /**
     * Statistiques générales des politiques
     */
    public function getPolicyStats(): array
    {
        $qb = $this->createQueryBuilder('lp');
        
        $stats = [
            'total' => $qb->select('COUNT(lp.id)')->getQuery()->getSingleScalarResult(),
            'active' => $qb->select('COUNT(lp.id)')
                          ->andWhere('lp.isActive = :active')
                          ->setParameter('active', true)
                          ->getQuery()->getSingleScalarResult(),
            'inactive' => $qb->select('COUNT(lp.id)')
                            ->andWhere('lp.isActive = :active')
                            ->setParameter('active', false)
                            ->getQuery()->getSingleScalarResult(),
            'recent' => count($this->findRecentPolicies())
        ];
        
        return $stats;
    }

    /**
     * Export des politiques pour rapports
     */
    public function findForExport(): array
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt')
            ->andWhere('lp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.name', 'ASC')
            ->addOrderBy('lt.name', 'ASC')
            ->addOrderBy('lp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder de base pour les requêtes personnalisées
     */
    public function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('lp')
            ->leftJoin('lp.department', 'd')
            ->leftJoin('lp.leaveType', 'lt')
            ->addSelect('d', 'lt');
    }

    /**
     * Trouve les politiques applicables pour un utilisateur donné
     */
    public function findApplicablePoliciesForUser(int $userId): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT lp, d, lt
                FROM App\Entity\LeavePolicy lp
                LEFT JOIN lp.department d
                LEFT JOIN lp.leaveType lt
                LEFT JOIN App\Entity\User u WITH u.department = lp.department
                WHERE u.id = :userId
                AND lp.isActive = :active
                AND d.isActive = :departmentActive
                AND lt.isActive = :leaveTypeActive
                ORDER BY lt.name ASC, lp.name ASC
            ')
            ->setParameter('userId', $userId)
            ->setParameter('active', true)
            ->setParameter('departmentActive', true)
            ->setParameter('leaveTypeActive', true)
            ->getResult();
    }
}