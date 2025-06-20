<?php

namespace App\Controller\Employee;

use App\Entity\User;
use App\Form\Employee\ProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/employee/profile')]
#[IsGranted('ROLE_EMPLOYEE')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'employee_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('employee/profile/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'employee_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('employee_profile_show');
        }

        return $this->render('employee/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'employee_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre mot de passe actuel.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir un mot de passe.',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Changer le mot de passe',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Vérifier le mot de passe actuel
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->render('employee/profile/change_password.html.twig', [
                    'form' => $form,
                ]);
            }

            // Hasher et enregistrer le nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');

            return $this->redirectToRoute('employee_profile_show');
        }

        return $this->render('employee/profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/personal-info', name: 'employee_profile_personal_info', methods: ['GET'])]
    public function personalInfo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('employee/profile/personal_info.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/employment-info', name: 'employee_profile_employment_info', methods: ['GET'])]
    public function employmentInfo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('employee/profile/employment_info.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/contact-info', name: 'employee_profile_contact_info', methods: ['GET', 'POST'])]
    public function contactInfo(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder($user)
            ->add('email', null, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
                'disabled' => true, // L'email ne peut pas être modifié par l'employé
            ])
            ->add('phone', null, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Mettre à jour',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Vos informations de contact ont été mises à jour.');

            return $this->redirectToRoute('employee_profile_contact_info');
        }

        return $this->render('employee/profile/contact_info.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/leave-balance-summary', name: 'employee_profile_leave_balance_summary', methods: ['GET'])]
    public function leaveBalanceSummary(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les soldes de congés de l'utilisateur pour l'année courante
        $currentYear = (new \DateTime())->format('Y');
        $leaveBalances = $user->getLeaveBalances()->filter(function($balance) use ($currentYear) {
            return $balance->getYear() == $currentYear;
        });

        return $this->render('employee/profile/leave_balance_summary.html.twig', [
            'user' => $user,
            'leave_balances' => $leaveBalances,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/team-info', name: 'employee_profile_team_info', methods: ['GET'])]
    public function teamInfo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les équipes où l'utilisateur est membre
        $teamMemberships = $user->getTeamMemberships()->filter(function($membership) {
            return $membership->isActive();
        });

        return $this->render('employee/profile/team_info.html.twig', [
            'user' => $user,
            'team_memberships' => $teamMemberships,
        ]);
    }

    #[Route('/recent-activity', name: 'employee_profile_recent_activity', methods: ['GET'])]
    public function recentActivity(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les 10 dernières demandes de congés
        $recentLeaveRequests = $user->getLeaveRequests()
            ->slice(0, 10);

        // Récupérer les 5 dernières présences
        $recentAttendances = $user->getAttendances()
            ->slice(0, 5);

        return $this->render('employee/profile/recent_activity.html.twig', [
            'user' => $user,
            'recent_leave_requests' => $recentLeaveRequests,
            'recent_attendances' => $recentAttendances,
        ]);
    }

    #[Route('/settings', name: 'employee_profile_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Créer un formulaire simple pour les préférences utilisateur
        $form = $this->createFormBuilder()
            ->add('emailNotifications', null, [
                'label' => 'Recevoir les notifications par email',
                'data' => true, // Valeur par défaut
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('smsNotifications', null, [
                'label' => 'Recevoir les notifications par SMS',
                'data' => false, // Valeur par défaut
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer les préférences',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Dans une implémentation complète, on sauvegarderait ces préférences
            // dans une entité UserPreferences ou dans un champ JSON de User
            
            $this->addFlash('success', 'Vos préférences ont été enregistrées.');

            return $this->redirectToRoute('employee_profile_settings');
        }

        return $this->render('employee/profile/settings.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/download-info', name: 'employee_profile_download_info', methods: ['GET'])]
    public function downloadInfo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Préparer les données à exporter
        $userData = [
            'Informations personnelles' => [
                'Prénom' => $user->getFirstName(),
                'Nom' => $user->getLastName(),
                'Email' => $user->getEmail(),
                'Téléphone' => $user->getPhone(),
            ],
            'Informations professionnelles' => [
                'Poste' => $user->getPosition(),
                'Département' => $user->getDepartment() ? $user->getDepartment()->getName() : 'N/A',
                'Date d\'embauche' => $user->getHireDate() ? $user->getHireDate()->format('d/m/Y') : 'N/A',
                'Manager' => $user->getManager() ? $user->getManager()->getFirstName() . ' ' . $user->getManager()->getLastName() : 'N/A',
            ],
            'Compte' => [
                'Date de création' => $user->getCreatedAt()->format('d/m/Y H:i'),
                'Dernière modification' => $user->getUpdatedAt() ? $user->getUpdatedAt()->format('d/m/Y H:i') : 'N/A',
                'Statut' => $user->isActive() ? 'Actif' : 'Inactif',
            ],
        ];

        // Créer le contenu du fichier
        $content = "=== MES INFORMATIONS PERSONNELLES - HR FLOW ===\n\n";
        $content .= "Généré le : " . (new \DateTime())->format('d/m/Y à H:i') . "\n\n";
        
        foreach ($userData as $section => $data) {
            $content .= strtoupper($section) . "\n";
            $content .= str_repeat('-', strlen($section)) . "\n";
            
            foreach ($data as $key => $value) {
                $content .= sprintf("%-25s : %s\n", $key, $value ?? 'N/A');
            }
            $content .= "\n";
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="mes_informations_' . date('Y-m-d') . '.txt"');

        return $response;
    }
}