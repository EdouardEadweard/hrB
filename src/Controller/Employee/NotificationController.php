<?php

namespace App\Controller\Employee;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/notification')]
#[IsGranted('ROLE_EMPLOYEE')]
class NotificationController extends AbstractController
{
    #[Route('/', name: 'employee_notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        // Récupérer les notifications de l'utilisateur connecté
        $notifications = $notificationRepository->findBy(
            ['recipient' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        // Séparer les notifications lues et non lues
        $unreadNotifications = [];
        $readNotifications = [];
        
        foreach ($notifications as $notification) {
            if ($notification->isRead()) {
                $readNotifications[] = $notification;
            } else {
                $unreadNotifications[] = $notification;
            }
        }

        return $this->render('employee/notification/index.html.twig', [
            'unread_notifications' => $unreadNotifications,
            'read_notifications' => $readNotifications,
             'notifications' => $notifications, //
            'total_notifications' => count($notifications),
            'unread_count' => count($unreadNotifications),
        ]);
    }

    #[Route('/{id}', name: 'employee_notification_show', methods: ['GET'])]
    public function show(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur connecté est bien le destinataire
        if ($notification->getRecipient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette notification.');
        }

        // Marquer la notification comme lue si elle ne l'est pas encore
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
            $entityManager->flush();
        }

        return $this->render('employee/notification/show.html.twig', [
            'notification' => $notification,
        ]);
    }

    #[Route('/{id}/mark-as-read', name: 'employee_notification_mark_read', methods: ['POST'])]
    public function markAsRead(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur connecté est bien le destinataire
        if ($notification->getRecipient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette notification.');
        }

        // Marquer comme lue
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Notification marquée comme lue.');
        }

        return $this->redirectToRoute('employee_notification_index');
    }

    #[Route('/mark-all-read', name: 'employee_notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): Response
    {
        // Récupérer toutes les notifications non lues de l'utilisateur
        $unreadNotifications = $notificationRepository->findBy([
            'recipient' => $this->getUser(),
            'isRead' => false
        ]);

        $count = 0;
        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
            $count++;
        }

        if ($count > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d notification(s) marquée(s) comme lue(s).', $count));
        } else {
            $this->addFlash('info', 'Aucune notification non lue trouvée.');
        }

        return $this->redirectToRoute('employee_notification_index');
    }

    #[Route('/{id}/delete', name: 'employee_notification_delete', methods: ['POST'])]
    public function delete(Request $request, Notification $notification, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur connecté est bien le destinataire
        if ($notification->getRecipient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette notification.');
        }

        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete'.$notification->getId(), $request->request->get('_token'))) {
            $entityManager->remove($notification);
            $entityManager->flush();
            $this->addFlash('success', 'Notification supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('employee_notification_index');
    }

    #[Route('/unread-count', name: 'employee_notification_unread_count', methods: ['GET'])]
    public function getUnreadCount(NotificationRepository $notificationRepository): Response
    {
        // Compter les notifications non lues pour l'utilisateur connecté
        $unreadCount = $notificationRepository->count([
            'recipient' => $this->getUser(),
            'isRead' => false
        ]);

        // Retourner une réponse JSON pour les appels AJAX
        return $this->json(['unread_count' => $unreadCount]);
    }

    #[Route('/filter/{type}', name: 'employee_notification_filter', methods: ['GET'])]
    public function filterByType(string $type, NotificationRepository $notificationRepository): Response
    {
        // Types de notifications valides
        $validTypes = ['leave_request', 'leave_approved', 'leave_rejected', 'system', 'reminder'];
        
        if (!in_array($type, $validTypes)) {
            throw $this->createNotFoundException('Type de notification invalide.');
        }

        $notifications = $notificationRepository->findBy(
            [
                'recipient' => $this->getUser(),
                'type' => $type
            ],
            ['createdAt' => 'DESC']
        );

        return $this->render('employee/notification/index.html.twig', [
            'unread_notifications' => array_filter($notifications, fn($n) => !$n->isRead()),
            'read_notifications' => array_filter($notifications, fn($n) => $n->isRead()),
            'total_notifications' => count($notifications),
            'unread_count' => count(array_filter($notifications, fn($n) => !$n->isRead())),
            'current_filter' => $type,
        ]);
    }

    #[Route('/recent', name: 'employee_notification_recent', methods: ['GET'])]
    public function getRecent(NotificationRepository $notificationRepository): Response
    {
        // Récupérer les 5 dernières notifications
        $recentNotifications = $notificationRepository->findBy(
            ['recipient' => $this->getUser()],
            ['createdAt' => 'DESC'],
            5
        );

        return $this->render('employee/notification/_recent.html.twig', [
            'notifications' => $recentNotifications,
        ]);
    }

    #[Route('/clear-all', name: 'employee_notification_clear_all', methods: ['POST'])]
    public function clearAll(Request $request, NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): Response
    {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('clear_all', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('employee_notification_index');
        }

        // Récupérer toutes les notifications de l'utilisateur
        $notifications = $notificationRepository->findBy(['recipient' => $this->getUser()]);

        $count = count($notifications);
        foreach ($notifications as $notification) {
            $entityManager->remove($notification);
        }

        if ($count > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d notification(s) supprimée(s).', $count));
        } else {
            $this->addFlash('info', 'Aucune notification à supprimer.');
        }

        return $this->redirectToRoute('employee_notification_index');
    }
}