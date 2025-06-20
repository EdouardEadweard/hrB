<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository pour la gestion des présences/absences
 * 
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    /**
     * Sauvegarde une présence
     */
    public function save(Attendance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une présence
     */
    public function remove(Attendance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les présences d'un employé
     * 
     * @param User $employee L'employé
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByEmployee(User $employee): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les présences d'un employé pour une période donnée
     * 
     * @param User $employee L'employé
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findAttendancesByDateRange(User $employee, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->setParameter('employee', $employee)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les présences d'un employé pour le mois courant
     * 
     * @param User $employee L'employé
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByEmployeeCurrentMonth(User $employee): array
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month');
        
        return $this->findAttendancesByDateRange($employee, $startDate, $endDate);
    }

    /**
     * Trouve les présences d'un employé pour la semaine courante
     * 
     * @param User $employee L'employé
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByEmployeeCurrentWeek(User $employee): array
    {
        $startDate = new \DateTime('monday this week');
        $endDate = new \DateTime('sunday this week');
        
        return $this->findAttendancesByDateRange($employee, $startDate, $endDate);
    }

    /**
     * Trouve la présence d'un employé pour une date spécifique
     * 
     * @param User $employee L'employé
     * @param \DateTime $date La date de travail
     * @return Attendance|null
     */
    public function findByEmployeeAndDate(User $employee, \DateTime $date): ?Attendance
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate = :date')
            ->setParameter('employee', $employee)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les présences pour une date donnée
     * 
     * @param \DateTime $date La date de travail
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByDate(\DateTime $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.workDate = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('a.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les présences par statut
     * 
     * @param string $status Le statut (présent, absent, retard, etc.)
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les présences par statut pour un employé
     * 
     * @param User $employee L'employé
     * @param string $status Le statut
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findByEmployeeAndStatus(User $employee, string $status): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.status = :status')
            ->setParameter('employee', $employee)
            ->setParameter('status', $status)
            ->orderBy('a.workDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total d'heures travaillées par un employé sur une période
     * 
     * @param User $employee L'employé
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return int Total d'heures travaillées
     */
    public function getTotalWorkedHoursByPeriod(User $employee, \DateTime $startDate, \DateTime $endDate): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.workedHours)')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->setParameter('employee', $employee)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }

    /**
     * Calcule le total d'heures travaillées par un employé pour le mois courant
     * 
     * @param User $employee L'employé
     * @return int Total d'heures travaillées
     */
    public function getTotalWorkedHoursCurrentMonth(User $employee): int
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month');
        
        return $this->getTotalWorkedHoursByPeriod($employee, $startDate, $endDate);
    }

    /**
     * Compte les jours de présence d'un employé sur une période
     * 
     * @param User $employee L'employé
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return int Nombre de jours de présence
     */
    public function countPresenceDaysByPeriod(User $employee, \DateTime $startDate, \DateTime $endDate): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->andWhere('a.status = :status')
            ->setParameter('employee', $employee)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', 'présent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les absences d'un employé sur une période
     * 
     * @param User $employee L'employé
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return int Nombre d'absences
     */
    public function countAbsencesByPeriod(User $employee, \DateTime $startDate, \DateTime $endDate): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->andWhere('a.status = :status')
            ->setParameter('employee', $employee)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', 'absent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les retards d'un employé sur une période
     * 
     * @param User $employee L'employé
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return int Nombre de retards
     */
    public function countLateArrivalsByPeriod(User $employee, \DateTime $startDate, \DateTime $endDate): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->andWhere('a.status = :status')
            ->setParameter('employee', $employee)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', 'retard')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les employés présents aujourd'hui
     * 
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findPresentToday(): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.workDate = :today')
            ->andWhere('a.status = :status')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('status', 'présent')
            ->orderBy('a.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les employés absents aujourd'hui
     * 
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findAbsentToday(): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.workDate = :today')
            ->andWhere('a.status = :status')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('status', 'absent')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les employés en retard aujourd'hui
     * 
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findLateToday(): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.workDate = :today')
            ->andWhere('a.status = :status')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('status', 'retard')
            ->orderBy('a.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les présences sans heure de sortie (toujours au travail)
     * 
     * @param \DateTime $date La date (par défaut aujourd'hui)
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findStillAtWork(\DateTime $date = null): array
    {
        if (!$date) {
            $date = new \DateTime();
        }
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.workDate = :date')
            ->andWhere('a.checkIn IS NOT NULL')
            ->andWhere('a.checkOut IS NULL')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('a.checkIn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques de présence pour un département sur une période
     * 
     * @param int $departmentId ID du département
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return array Statistiques
     */
    public function getAttendanceStatsByDepartment(int $departmentId, \DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('
                COUNT(a.id) as total_records,
                SUM(CASE WHEN a.status = :present THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = :absent THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = :late THEN 1 ELSE 0 END) as late_count,
                SUM(a.workedHours) as total_hours
            ')
            ->leftJoin('a.employee', 'u')
            ->andWhere('u.department = :department')
            ->andWhere('a.workDate BETWEEN :start AND :end')
            ->setParameter('department', $departmentId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('present', 'présent')
            ->setParameter('absent', 'absent')
            ->setParameter('late', 'retard');

        $result = $qb->getQuery()->getSingleResult();
        
        return [
            'total_records' => (int) $result['total_records'],
            'present_count' => (int) $result['present_count'],
            'absent_count' => (int) $result['absent_count'],
            'late_count' => (int) $result['late_count'],
            'total_hours' => (int) $result['total_hours']
        ];
    }

    /**
     * Trouve les présences avec pagination
     * 
     * @param int $page Numéro de page (commence à 1)
     * @param int $limit Nombre d'éléments par page
     * @param User|null $employee Filtrer par employé (optionnel)
     * @return array ['data' => Attendance[], 'total' => int]
     */
    public function findAllPaginated(int $page = 1, int $limit = 10, User $employee = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.workDate', 'DESC')
            ->addOrderBy('a.checkIn', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($employee) {
            $qb->andWhere('a.employee = :employee')
               ->setParameter('employee', $employee);
            
            $countQb->andWhere('a.employee = :employee')
                    ->setParameter('employee', $employee);
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $countQb->getQuery()->getSingleScalarResult()
        ];
    }

    /**
     * Recherche les présences par critères multiples
     * 
     * @param array $criteria Critères de recherche
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function searchByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('a');

        if (isset($criteria['employee'])) {
            $qb->andWhere('a.employee = :employee')
               ->setParameter('employee', $criteria['employee']);
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('a.workDate >= :start_date')
               ->setParameter('start_date', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('a.workDate <= :end_date')
               ->setParameter('end_date', $criteria['end_date']);
        }

        if (isset($criteria['min_hours'])) {
            $qb->andWhere('a.workedHours >= :min_hours')
               ->setParameter('min_hours', $criteria['min_hours']);
        }

        if (isset($criteria['max_hours'])) {
            $qb->andWhere('a.workedHours <= :max_hours')
               ->setParameter('max_hours', $criteria['max_hours']);
        }

        return $qb->orderBy('a.workDate', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les dernières présences créées
     * 
     * @param int $limit Nombre maximum de résultats
     * @return Attendance[] Returns an array of Attendance objects
     */
    public function findRecentlyCreated(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
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
        return $this->createQueryBuilder('a')
            ->orderBy('a.workDate', 'DESC');
    }
}