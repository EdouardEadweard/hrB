<?php
// Script pour créer/mettre à jour l'utilisateur admin
// À exécuter via console Symfony : php bin/console app:create-admin

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create or update admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        
        // Chercher l'utilisateur existant
        $user = $userRepository->findOneBy(['email' => 'admin@hrflow.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('admin@hrflow.com');
            $output->writeln('Creating new admin user...');
        } else {
            $output->writeln('Updating existing admin user...');
        }
        
        // Définir le mot de passe
        $plainPassword = 'admin123'; // Changez selon vos besoins
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setFirstName('Admin');
        $user->setLastName('User');
        $user->setIsActive(true);
        
        // Si vous avez des champs obligatoires, les définir ici
        // $user->setHireDate(new \DateTime());
        // $user->setPosition('Administrator');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $output->writeln("Admin user created/updated successfully!");
        $output->writeln("Email: admin@hrflow.com");
        $output->writeln("Password: {$plainPassword}");
        
        return Command::SUCCESS;
    }
}