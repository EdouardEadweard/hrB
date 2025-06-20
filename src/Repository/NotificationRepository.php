<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\LeaveRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Trouver toutes les notifications d'un utilisateur
     */
    public function findByRecipient(User $recipient, array $orderBy = ['createdAt' => 'DESC']): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('n.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouver les notifications non lues d'un utilisateur
     */
    public function findUnreadByRecipient(User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les notifications non lues d'un utilisateur
     */
    public function countUnreadByRecipient(User $recipient): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver les notifications lues d'un utilisateur
     */
    public function findReadByRecipient(User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', true)
            ->orderBy('n.readAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications envoyées par un utilisateur
     */
    public function findBySender(User $sender): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.sender = :sender')
            ->setParameter('sender', $sender)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications liées à une demande de congé
     */
    public function findByLeaveRequest(LeaveRequest $leaveRequest): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.leaveRequest = :leaveRequest')
            ->setParameter('leaveRequest', $leaveRequest)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications récentes (X derniers jours)
     */
    public function findRecentNotifications(int $days = 7): array
    {
        $dateLimit = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('n')
            ->andWhere('n.createdAt >= :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications récentes pour un utilisateur
     */
    public function findRecentByRecipient(User $recipient, int $days = 7): array
    {
        $dateLimit = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.createdAt >= :dateLimit')
            ->setParameter('recipient', $recipient)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications par type et destinataire
     */
    public function findByTypeAndRecipient(string $type, User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('type', $type)
            ->setParameter('recipient', $recipient)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
            $this->save($notification, true);
        }
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsReadForRecipient(User $recipient): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->set('n.readAt', ':readAt')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :currentIsRead')
            ->setParameter('isRead', true)
            ->setParameter('readAt', new \DateTime())
            ->setParameter('recipient', $recipient)
            ->setParameter('currentIsRead', false);

        return $qb->getQuery()->execute();
    }

    /**
     * Marquer les notifications d'un type spécifique comme lues pour un utilisateur
     */
    public function markAsReadByTypeForRecipient(string $type, User $recipient): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->set('n.readAt', ':readAt')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.type = :type')
            ->andWhere('n.isRead = :currentIsRead')
            ->setParameter('isRead', true)
            ->setParameter('readAt', new \DateTime())
            ->setParameter('recipient', $recipient)
            ->setParameter('type', $type)
            ->setParameter('currentIsRead', false);

        return $qb->getQuery()->execute();
    }

    /**
     * Supprimer les anciennes notifications lues (plus de X jours)
     */
    public function deleteOldReadNotifications(int $daysOld = 30): int
    {
        $dateLimit = new \DateTime("-{$daysOld} days");

        $qb = $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.readAt < :dateLimit')
            ->setParameter('isRead', true)
            ->setParameter('dateLimit', $dateLimit);

        return $qb->getQuery()->execute();
    }

    /**
     * Obtenir les statistiques des notifications par type
     */
    public function getNotificationStatsbyType(): array
    {
        return $this->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as total, SUM(CASE WHEN n.isRead = false THEN 1 ELSE 0 END) as unread')
            ->groupBy('n.type')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les statistiques des notifications pour un utilisateur
     */
    public function getUserNotificationStats(User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->select('
                n.type,
                COUNT(n.id) as total,
                SUM(CASE WHEN n.isRead = false THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN n.isRead = true THEN 1 ELSE 0 END) as read
            ')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->groupBy('n.type')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications en attente de lecture depuis X jours
     */
    public function findOldUnreadNotifications(int $daysOld = 7): array
    {
        $dateLimit = new \DateTime("-{$daysOld} days");

        return $this->createQueryBuilder('n')
            ->join('n.recipient', 'r')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.createdAt < :dateLimit')
            ->setParameter('isRead', false)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications par période
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les notifications les plus récentes avec limite
     */
    public function findLatestNotifications(int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->join('n.recipient', 'r')
            ->join('n.sender', 's')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications pour les managers (notifications liées aux approbations)
     */
    public function findManagerNotifications(User $manager): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :manager')
            ->andWhere('n.type IN (:managerTypes)')
            ->setParameter('manager', $manager)
            ->setParameter('managerTypes', [
                'leave_request_submitted',
                'leave_request_pending_approval',
                'team_notification'
            ])
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les notifications pour les employés
     */
    public function findEmployeeNotifications(User $employee): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :employee')
            ->andWhere('n.type IN (:employeeTypes)')
            ->setParameter('employee', $employee)
            ->setParameter('employeeTypes', [
                'leave_request_approved',
                'leave_request_rejected',
                'leave_balance_updated',
                'general_announcement'
            ])
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec filtres multiples
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.recipient', 'r')
            ->leftJoin('n.sender', 's')
            ->leftJoin('n.leaveRequest', 'lr');

        if (!empty($filters['recipient_id'])) {
            $qb->andWhere('r.id = :recipientId')
               ->setParameter('recipientId', $filters['recipient_id']);
        }

        if (!empty($filters['sender_id'])) {
            $qb->andWhere('s.id = :senderId')
               ->setParameter('senderId', $filters['sender_id']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['is_read'])) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', (bool) $filters['is_read']);
        }

        if (!empty($filters['start_date'])) {
            $qb->andWhere('n.createdAt >= :startDate')
               ->setParameter('startDate', new \DateTime($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $qb->andWhere('n.createdAt <= :endDate')
               ->setParameter('endDate', new \DateTime($filters['end_date']));
        }

        if (!empty($filters['leave_request_id'])) {
            $qb->andWhere('lr.id = :leaveRequestId')
               ->setParameter('leaveRequestId', $filters['leave_request_id']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('n.title LIKE :search OR n.message LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('n.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Créer une notification système
     */
    public function createSystemNotification(
        User $recipient,
        string $title,
        string $message,
        string $type = 'system',
        LeaveRequest $leaveRequest = null
    ): Notification {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());
        
        if ($leaveRequest) {
            $notification->setLeaveRequest($leaveRequest);
        }

        $this->save($notification, true);
        return $notification;
    }

    /**
     * Créer une notification de demande de congé
     */
    public function createLeaveRequestNotification(
        User $recipient,
        User $sender,
        LeaveRequest $leaveRequest,
        string $type
    ): Notification {
        $titles = [
            'leave_request_submitted' => 'Nouvelle demande de congé',
            'leave_request_approved' => 'Demande de congé approuvée',
            'leave_request_rejected' => 'Demande de congé rejetée',
            'leave_request_cancelled' => 'Demande de congé annulée'
        ];

        $messages = [
            'leave_request_submitted' => 'Une nouvelle demande de congé a été soumise par ' . $sender->getFirstName() . ' ' . $sender->getLastName(),
            'leave_request_approved' => 'Votre demande de congé du ' . $leaveRequest->getStartDate()->format('d/m/Y') . ' au ' . $leaveRequest->getEndDate()->format('d/m/Y') . ' a été approuvée',
            'leave_request_rejected' => 'Votre demande de congé du ' . $leaveRequest->getStartDate()->format('d/m/Y') . ' au ' . $leaveRequest->getEndDate()->format('d/m/Y') . ' a été rejetée',
            'leave_request_cancelled' => 'La demande de congé du ' . $leaveRequest->getStartDate()->format('d/m/Y') . ' au ' . $leaveRequest->getEndDate()->format('d/m/Y') . ' a été annulée'
        ];

        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setSender($sender);
        $notification->setLeaveRequest($leaveRequest);
        $notification->setTitle($titles[$type] ?? 'Notification de congé');
        $notification->setMessage($messages[$type] ?? 'Notification concernant votre demande de congé');
        $notification->setType($type);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());

        $this->save($notification, true);
        return $notification;
    }

    public function save(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}