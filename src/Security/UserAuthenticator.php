<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Détermine si cette classe d'authentification doit être utilisée pour cette requête
     */
    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    /**
     * Authentifie l'utilisateur basé sur les informations de la requête
     */
    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

         // DEBUG : Log les valeurs
        error_log("DEBUG - Email: " . $email);
        error_log("DEBUG - Password: " . $password);
        
        // ... reste du code

        // Stocker l'email en session pour le pré-remplir en cas d'erreur
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                // Charger l'utilisateur depuis la base de données
                $user = $this->entityManager->getRepository(User::class)->findOneBy([
                    'email' => $userIdentifier
                ]);

                if (!$user) {
                    throw new UserNotFoundException('Email non trouvé.');
                }

                // Vérifier si l'utilisateur est actif
                if (!$user->getIsActive()) {
                    throw new AuthenticationException('Compte désactivé. Contactez votre administrateur.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_token')),
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * Appelé quand l'authentification réussit
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        
        // Mettre à jour la dernière connexion
        $user->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();

        // Ajouter un message de succès
        $request->getSession()->getFlashBag()->add(
            'success', 
            'Bienvenue ' . $user->getFirstName() . ' ! Connexion réussie.'
        );

        // Redirection selon le rôle de l'utilisateur
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        // Redirection par défaut selon les rôles
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }
        
        if (in_array('ROLE_MANAGER', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('manager_dashboard'));
        }
        
        // Redirection par défaut pour les employés
        return new RedirectResponse($this->urlGenerator->generate('employee_dashboard'));
    }

    /**
     * Appelé quand l'authentification échoue
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Ajouter un message d'erreur personnalisé
        $errorMessage = $this->getCustomErrorMessage($exception);
        
        $request->getSession()->getFlashBag()->add('error', $errorMessage);

        // Log de la tentative de connexion échouée
        $this->logFailedLogin($request, $exception);

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    /**
     * Retourne l'URL de la page de connexion
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    /**
     * Personnalise les messages d'erreur selon le type d'exception
     */
    private function getCustomErrorMessage(AuthenticationException $exception): string
    {
        $message = $exception->getMessage();

        // Messages personnalisés selon le type d'erreur
        if (strpos($message, 'Email non trouvé') !== false) {
            return 'Cette adresse email n\'est pas reconnue. Vérifiez votre saisie.';
        }

        if (strpos($message, 'Compte désactivé') !== false) {
            return 'Votre compte a été désactivé. Contactez votre administrateur.';
        }

        if (strpos($message, 'Invalid credentials') !== false || 
            strpos($message, 'Bad credentials') !== false) {
            return 'Mot de passe incorrect. Vérifiez votre saisie.';
        }

        if (strpos($message, 'Invalid CSRF token') !== false) {
            return 'Erreur de sécurité. Veuillez réessayer.';
        }

        // Message générique pour les autres erreurs
        return 'Erreur de connexion. Vérifiez vos identifiants.';
    }

    /**
     * Log des tentatives de connexion échouées
     */
    private function logFailedLogin(Request $request, AuthenticationException $exception): void
    {
        $email = $request->request->get('email', 'unknown');
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent', 'unknown');
        $timestamp = new \DateTime();

        // Dans un contexte réel, vous pourriez utiliser le logger Symfony
        error_log(sprintf(
            '[%s] Failed login attempt - Email: %s, IP: %s, User-Agent: %s, Error: %s',
            $timestamp->format('Y-m-d H:i:s'),
            $email,
            $ip,
            $userAgent,
            $exception->getMessage()
        ));

        // Optionnel : Enregistrer en base de données pour audit
        $this->recordFailedLoginAttempt($email, $ip, $exception->getMessage());
    }

    /**
     * Enregistre les tentatives échouées en base (optionnel)
     */
    private function recordFailedLoginAttempt(string $email, string $ip, string $error): void
    {
        try {
            // Vous pourriez créer une entité LoginAttempt pour traquer cela
            // Pour l'instant, on se contente d'un log simple
            
            // Vérifier si l'utilisateur existe
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            
            if ($user) {
                // Increment failed login attempts counter
                $failedAttempts = $user->getFailedLoginAttempts() ?? 0;
                $user->setFailedLoginAttempts($failedAttempts + 1);
                $user->setLastFailedLoginAt(new \DateTime());
                
                // Bloquer le compte après 5 tentatives échouées (optionnel)
                if ($failedAttempts >= 4) { // 5ème tentative
                    $user->setIsActive(false);
                    $user->setAccountLockedAt(new \DateTime());
                }
                
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            // Ne pas faire échouer l'authentification si l'enregistrement échoue
            error_log('Failed to record login attempt: ' . $e->getMessage());
        }
    }

    /**
     * Réinitialise le compteur de tentatives échouées lors d'une connexion réussie
     */
    public function resetFailedLoginAttempts(User $user): void
    {
        if ($user->getFailedLoginAttempts() > 0) {
            $user->setFailedLoginAttempts(0);
            $user->setLastFailedLoginAt(null);
            $user->setAccountLockedAt(null);
            $this->entityManager->flush();
        }
    }

    /**
     * Vérifie si un compte est temporairement bloqué
     */
    public function isAccountLocked(User $user): bool
    {
        if (!$user->getAccountLockedAt()) {
            return false;
        }

        // Débloquer automatiquement après 30 minutes
        $lockoutDuration = new \DateInterval('PT30M'); // 30 minutes
        $unlockTime = clone $user->getAccountLockedAt();
        $unlockTime->add($lockoutDuration);

        if (new \DateTime() > $unlockTime) {
            // Débloquer le compte automatiquement
            $user->setIsActive(true);
            $user->setAccountLockedAt(null);
            $user->setFailedLoginAttempts(0);
            $this->entityManager->flush();
            return false;
        }

        return true;
    }

    /**
     * Retourne le temps restant avant déblocage du compte
     */
    public function getRemainingLockoutTime(User $user): ?\DateInterval
    {
        if (!$user->getAccountLockedAt()) {
            return null;
        }

        $lockoutDuration = new \DateInterval('PT30M');
        $unlockTime = clone $user->getAccountLockedAt();
        $unlockTime->add($lockoutDuration);
        $now = new \DateTime();

        if ($now > $unlockTime) {
            return null;
        }

        return $now->diff($unlockTime);
    }

    /**
     * Génère un token de réinitialisation de mot de passe
     */
    public function generatePasswordResetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setPasswordResetToken($token);
        $user->setPasswordResetRequestedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        return $token;
    }

    /**
     * Valide un token de réinitialisation de mot de passe
     */
    public function isPasswordResetTokenValid(User $user, string $token): bool
    {
        if ($user->getPasswordResetToken() !== $token) {
            return false;
        }

        if (!$user->getPasswordResetRequestedAt()) {
            return false;
        }

        // Token valide pendant 1 heure
        $validUntil = clone $user->getPasswordResetRequestedAt();
        $validUntil->add(new \DateInterval('PT1H'));

        return new \DateTime() <= $validUntil;
    }

    /**
     * Nettoie le token de réinitialisation après utilisation
     */
    public function clearPasswordResetToken(User $user): void
    {
        $user->setPasswordResetToken(null);
        $user->setPasswordResetRequestedAt(null);
        $this->entityManager->flush();
    }

    /**
     * Vérifie les permissions d'accès selon les rôles
     */
    public function checkRoleAccess(User $user, string $requiredRole): bool
    {
        $roles = $user->getRoles();
        
        // Admin a accès à tout
        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }
        
        // Vérifier le rôle spécifique
        return in_array($requiredRole, $roles);
    }

    /**
     * Retourne la route de redirection appropriée selon le rôle
     */
    public function getDefaultRedirectRoute(User $user): string
    {
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'admin_dashboard';
        }
        
        if (in_array('ROLE_MANAGER', $roles)) {
            return 'manager_dashboard';
        }
        
        return 'employee_dashboard';
    }
}