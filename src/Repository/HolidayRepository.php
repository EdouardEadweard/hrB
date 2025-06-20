<?php

namespace App\Repository;

use App\Entity\Holiday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository pour la gestion des jours fériés
 * 
 * @extends ServiceEntityRepository<Holiday>
 */
class HolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Holiday::class);
    }

    /**
     * Sauvegarde un jour férié
     */
    public function save(Holiday $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un jour férié
     */
    public function remove(Holiday $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve tous les jours fériés actifs
     * 
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les jours fériés pour une année donnée
     * 
     * @param int $year L'année recherchée
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findByYear(int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('h')
            ->andWhere('h.date BETWEEN :start AND :end')
            ->andWhere('h.isActive = :active')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les jours fériés pour l'année courante
     * 
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findForCurrentYear(): array
    {
        $currentYear = (int) date('Y');
        return $this->findByYear($currentYear);
    }

    /**
     * Trouve tous les jours fériés dans une période donnée
     * 
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.date BETWEEN :start AND :end')
            ->andWhere('h.isActive = :active')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les jours fériés récurrents
     * 
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findRecurring(): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.isRecurring = :recurring')
            ->andWhere('h.isActive = :active')
            ->setParameter('recurring', true)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les jours fériés non récurrents
     * 
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findNonRecurring(): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.isRecurring = :recurring')
            ->andWhere('h.isActive = :active')
            ->setParameter('recurring', false)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si une date donnée est un jour férié
     * 
     * @param \DateTime $date La date à vérifier
     * @return bool True si c'est un jour férié, false sinon
     */
    public function isHoliday(\DateTime $date): bool
    {
        $result = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.date = :date')
            ->andWhere('h.isActive = :active')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Trouve un jour férié par sa date exacte
     * 
     * @param \DateTime $date
     * @return Holiday|null
     */
    public function findByDate(\DateTime $date): ?Holiday
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.date = :date')
            ->andWhere('h.isActive = :active')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre de jours fériés dans une période
     * 
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return int
     */
    public function countHolidaysInPeriod(\DateTime $startDate, \DateTime $endDate): int
    {
        return $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.date BETWEEN :start AND :end')
            ->andWhere('h.isActive = :active')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les jours fériés à venir (à partir d'aujourd'hui)
     * 
     * @param int $limit Nombre maximum de résultats
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findUpcoming(int $limit = 10): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('h')
            ->andWhere('h.date >= :today')
            ->andWhere('h.isActive = :active')
            ->setParameter('today', $today)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les jours fériés par nom
     * 
     * @param string $searchTerm Terme de recherche
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.name LIKE :searchTerm OR h.description LIKE :searchTerm')
            ->andWhere('h.isActive = :active')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les jours fériés avec pagination
     * 
     * @param int $page Numéro de page (commence à 1)
     * @param int $limit Nombre d'éléments par page
     * @return array ['data' => Holiday[], 'total' => int]
     */
    public function findAllPaginated(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = $this->createQueryBuilder('h')
            ->orderBy('h.date', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        $totalQuery = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->getQuery();

        return [
            'data' => $query->getResult(),
            'total' => $totalQuery->getSingleScalarResult()
        ];
    }

    /**
     * Trouve les jours fériés actifs avec pagination
     * 
     * @param int $page Numéro de page (commence à 1)
     * @param int $limit Nombre d'éléments par page
     * @return array ['data' => Holiday[], 'total' => int]
     */
    public function findActivePaginated(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $query = $this->createQueryBuilder('h')
            ->andWhere('h.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('h.date', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        $totalQuery = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.isActive = :active')
            ->setParameter('active', true)
            ->getQuery();

        return [
            'data' => $query->getResult(),
            'total' => $totalQuery->getSingleScalarResult()
        ];
    }

    /**
     * Trouve les jours fériés créés récemment
     * 
     * @param int $days Nombre de jours en arrière
     * @return Holiday[] Returns an array of Holiday objects
     */
    public function findRecentlyCreated(int $days = 7): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$days} days");

        return $this->createQueryBuilder('h')
            ->andWhere('h.createdAt >= :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('h.createdAt', 'DESC')
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
        return $this->createQueryBuilder('h')
            ->orderBy('h.date', 'ASC');
    }
}