<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un utilisateur par son email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Trouve tous les utilisateurs actifs
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les employés d'un département
     */
    public function findByDepartment(int $departmentId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.department = :departmentId')
            ->andWhere('u.isActive = :active')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les managers
     */
    public function findManagers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = :active')
            ->setParameter('role', '"ROLE_MANAGER"')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les administrateurs
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = :active')
            ->setParameter('role', '"ROLE_ADMIN"')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les subordonnés d'un manager
     */
    public function findSubordinates(int $managerId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.manager = :managerId')
            ->andWhere('u.isActive = :active')
            ->setParameter('managerId', $managerId)
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des utilisateurs par nom, prénom ou email
     */
    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->andWhere('u.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs par poste
     */
    public function findByPosition(string $position): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.position = :position')
            ->andWhere('u.isActive = :active')
            ->setParameter('position', $position)
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs embauchés dans une période donnée
     */
    public function findByHireDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.hireDate BETWEEN :startDate AND :endDate')
            ->andWhere('u.isActive = :active')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('active', true)
            ->orderBy('u.hireDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total d'utilisateurs actifs
     */
    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'utilisateurs par département
     */
    public function countUsersByDepartment(): array
    {
        return $this->createQueryBuilder('u')
            ->select('d.name as departmentName, COUNT(u.id) as userCount')
            ->join('u.department', 'd')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('d.id')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec un rôle spécifique
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = :active')
            ->setParameter('role', '"' . $role . '"')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs qui n'ont pas de manager assigné
     */
    public function findUsersWithoutManager(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.manager IS NULL')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les nouveaux employés (embauchés dans les 30 derniers jours)
     */
    public function findNewEmployees(): array
    {
        $thirtyDaysAgo = new \DateTime('-30 days');
        
        return $this->createQueryBuilder('u')
            ->where('u.hireDate >= :thirtyDaysAgo')
            ->andWhere('u.isActive = :active')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->setParameter('active', true)
            ->orderBy('u.hireDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs ayant un anniversaire aujourd'hui
     */
    public function findUsersWithBirthdayToday(): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('u')
            ->where('DAY(u.hireDate) = :day')
            ->andWhere('MONTH(u.hireDate) = :month')
            ->andWhere('u.isActive = :active')
            ->setParameter('day', $today->format('d'))
            ->setParameter('month', $today->format('m'))
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec pagination
     */
    public function findUsersWithPagination(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un email existe déjà (pour validation)
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.email = :email')
            ->setParameter('email', $email);

        if ($excludeUserId) {
            $qb->andWhere('u.id != :excludeUserId')
               ->setParameter('excludeUserId', $excludeUserId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Active ou désactive un utilisateur
     */
    public function toggleUserStatus(int $userId): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        $user->setIsActive(!$user->isActive());
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Met à jour la dernière connexion d'un utilisateur
     */
    public function updateLastLogin(User $user): void
    {
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // Dans src/Repository/UserRepository.php
    public function findUsersWithFilters(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.department', 'd')
            ->addSelect('d');

        if (!empty($filters['search'])) {
            $qb->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['department'])) {
            $qb->andWhere('u.department = :department')
            ->setParameter('department', $filters['department']);
        }

        if (isset($filters['isActive']) && $filters['isActive'] !== '') {
            $qb->andWhere('u.isActive = :isActive')
            ->setParameter('isActive', $filters['isActive'] === '1');
        }

        // Pour les rôles, utiliser une requête SQL native
        if (!empty($filters['role'])) {
            $conn = $this->getEntityManager()->getConnection();
            $sql = 'SELECT id FROM user WHERE JSON_SEARCH(roles, "one", ?) IS NOT NULL';
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([$filters['role']]);
            $userIds = $result->fetchFirstColumn();
            
            if (!empty($userIds)) {
                $qb->andWhere('u.id IN (:userIds)')
                ->setParameter('userIds', $userIds);
            } else {
                $qb->andWhere('1 = 0'); // Aucun résultat
            }
        }

        return $qb->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');
    }
}