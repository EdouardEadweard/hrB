<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\LoginType;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Page de connexion
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Dernier nom d'utilisateur saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        // Créer le formulaire de connexion
        $form = $this->createForm(LoginType::class, [
            'email' => $lastUsername
        ]);

        return $this->render('security/login.html.twig', [
            'form' => $form->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
            'page_title' => 'Connexion - HR Flow'
        ]);
    }

    /**
     * Page d'inscription
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérifier si l'email existe déjà
                $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
                if ($existingUser) {
                    $this->addFlash('error', 'Un compte avec cette adresse email existe déjà.');
                    return $this->render('security/register.html.twig', [
                        'form' => $form->createView(),
                        'page_title' => 'Inscription - HR Flow'
                    ]);
                }

                // Hasher le mot de passe
                $hashedPassword = $this->passwordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                );
                $user->setPassword($hashedPassword);

                // Définir les valeurs par défaut
                $user->setRoles(['ROLE_EMPLOYEE']); // Rôle employé par défaut
                $user->setIsActive(true);
                $user->setCreatedAt(new \DateTimeImmutable());
                $user->setUpdatedAt(new \DateTimeImmutable());

                // Sauvegarder l'utilisateur
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.');
                
                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Inscription - HR Flow'
        ]);
    }

    /**
     * Déconnexion
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut être vide car elle sera interceptée par la clé logout dans security.yaml
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé logout dans security.yaml');
    }

    /**
     * Page de mot de passe oublié
     */
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if (!$email) {
                $this->addFlash('error', 'Veuillez saisir votre adresse email.');
                return $this->render('security/forgot_password.html.twig', [
                    'page_title' => 'Mot de passe oublié - HR Flow'
                ]);
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            
            if ($user) {
                // Générer un token de réinitialisation (simulation)
                $resetToken = bin2hex(random_bytes(32));
                
                // Dans un vrai projet, on stockerait ce token en base et on enverrait un email
                // Ici on simule juste le processus
                $this->addFlash('info', 'Si votre adresse email est enregistrée dans notre système, vous recevrez un lien de réinitialisation par email.');
            } else {
                // Ne pas révéler si l'email existe ou non pour des raisons de sécurité
                $this->addFlash('info', 'Si votre adresse email est enregistrée dans notre système, vous recevrez un lien de réinitialisation par email.');
            }
            
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'page_title' => 'Mot de passe oublié - HR Flow'
        ]);
    }

    /**
     * Vérification de l'accès selon le rôle
     */
    #[Route('/access-denied', name: 'app_access_denied')]
    public function accessDenied(): Response
    {
        return $this->render('security/access_denied.html.twig', [
            'page_title' => 'Accès refusé - HR Flow'
        ]);
    }

    /**
     * Redirection vers le dashboard approprié selon le rôle
     */
    private function redirectToDashboard(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->redirectToRoute('admin_dashboard');
        } elseif (in_array('ROLE_MANAGER', $roles)) {
            return $this->redirectToRoute('manager_dashboard');
        } else {
            return $this->redirectToRoute('employee_dashboard');
        }
    }

    /**
     * Méthode utilitaire pour vérifier si un utilisateur est actif
     */
    private function isUserActive(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Méthode utilitaire pour enregistrer la dernière connexion
     */
    private function updateLastLogin(User $user): void
    {
        // Si on avait un champ lastLoginAt dans l'entité User
        // $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Page de première connexion (changement de mot de passe obligatoire)
     */
    #[Route('/first-login', name: 'app_first_login')]
    public function firstLogin(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si c'est vraiment la première connexion
        // (dans un vrai projet, on aurait un champ firstLogin dans User)
        
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
                return $this->render('security/first_login.html.twig', [
                    'page_title' => 'Première connexion - HR Flow'
                ]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/first_login.html.twig', [
                    'page_title' => 'Première connexion - HR Flow'
                ]);
            }

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('security/first_login.html.twig', [
                    'page_title' => 'Première connexion - HR Flow'
                ]);
            }

            // Hasher et sauvegarder le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');
            
            return $this->redirectToDashboard();
        }

        return $this->render('security/first_login.html.twig', [
            'page_title' => 'Première connexion - HR Flow'
        ]);
    }
}