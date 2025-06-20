<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-users',
    description: 'Crée des utilisateurs de test pour HR Flow',
)]
class CreateUsersCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('with-departments', null, InputOption::VALUE_NONE, 'Créer aussi les départements')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer la création même si des utilisateurs existent')
            ->setHelp('Cette commande crée 10 utilisateurs de test avec leurs départements pour HR Flow');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Vérifier si des utilisateurs existent déjà
        if (!$input->getOption('force')) {
            $existingUsers = $this->entityManager->getRepository(User::class)->findAll();
            if (count($existingUsers) > 0) {
                $io->warning('Des utilisateurs existent déjà. Utilisez --force pour continuer.');
                return Command::FAILURE;
            }
        }

        $io->title('Création des utilisateurs HR Flow');

        // Créer les départements si demandé
        if ($input->getOption('with-departments')) {
            $this->createDepartments($io);
        }

        // Récupérer les départements
        $departments = $this->getDepartments();
        if (empty($departments)) {
            $io->error('Aucun département trouvé. Utilisez --with-departments pour les créer.');
            return Command::FAILURE;
        }

        // Créer les utilisateurs
        $users = $this->createUsers($departments, $io);
        
        // Assigner les managers
        $this->assignManagers($users, $io);
        
        // Assigner les managers de département
        $this->assignDepartmentManagers($users, $departments, $io);

        $io->success('Tous les utilisateurs ont été créés avec succès !');
        $this->displayUsersTable($users, $io);

        return Command::SUCCESS;
    }

    private function createDepartments(SymfonyStyle $io): void
    {
        $io->section('Création des départements');

        $departmentsData = [
            ['name' => 'Ressources Humaines', 'code' => 'RH', 'description' => 'Département des ressources humaines'],
            ['name' => 'Informatique', 'code' => 'IT', 'description' => 'Département informatique et développement'],
            ['name' => 'Commercial', 'code' => 'COM', 'description' => 'Département commercial et ventes'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Département financier et comptabilité'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Département marketing et communication'],
        ];

        foreach ($departmentsData as $data) {
            // Vérifier si le département existe déjà
            $existingDept = $this->entityManager->getRepository(Department::class)
                ->findOneBy(['code' => $data['code']]);
            
            if (!$existingDept) {
                $department = new Department();
                $department->setName($data['name']);
                $department->setCode($data['code']);
                $department->setDescription($data['description']);
                $department->setIsActive(true);
                $department->setCreatedAt(new \DateTimeImmutable());
                $department->setUpdatedAt(new \DateTimeImmutable());

                $this->entityManager->persist($department);
                $io->writeln("✓ Département {$data['name']} créé");
            } else {
                $io->writeln("- Département {$data['name']} existe déjà");
            }
        }

        $this->entityManager->flush();
    }

    private function getDepartments(): array
    {
        return [
            'RH' => $this->entityManager->getRepository(Department::class)->findOneBy(['code' => 'RH']),
            'IT' => $this->entityManager->getRepository(Department::class)->findOneBy(['code' => 'IT']),
            'COM' => $this->entityManager->getRepository(Department::class)->findOneBy(['code' => 'COM']),
            'FIN' => $this->entityManager->getRepository(Department::class)->findOneBy(['code' => 'FIN']),
            'MKT' => $this->entityManager->getRepository(Department::class)->findOneBy(['code' => 'MKT']),
        ];
    }

    private function createUsers(array $departments, SymfonyStyle $io): array
    {
        $io->section('Création des utilisateurs');

        $usersData = [
            [
                'email' => 'marie.dubois@hrflow.com',
                'firstName' => 'Marie',
                'lastName' => 'Dubois',
                'phone' => '+33123456701',
                'position' => 'Directrice des Ressources Humaines',
                'department' => 'RH',
                'roles' => ['ROLE_ADMIN'],
                'hireDate' => '2020-01-15',
            ],
            [
                'email' => 'pierre.martin@hrflow.com',
                'firstName' => 'Pierre',
                'lastName' => 'Martin',
                'phone' => '+33123456702',
                'position' => 'Chef de Département IT',
                'department' => 'IT',
                'roles' => ['ROLE_MANAGER'],
                'hireDate' => '2019-03-20',
            ],
            [
                'email' => 'sophie.bernard@hrflow.com',
                'firstName' => 'Sophie',
                'lastName' => 'Bernard',
                'phone' => '+33123456703',
                'position' => 'Développeuse Senior',
                'department' => 'IT',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2021-06-10',
            ],
            [
                'email' => 'julien.rousseau@hrflow.com',
                'firstName' => 'Julien',
                'lastName' => 'Rousseau',
                'phone' => '+33123456704',
                'position' => 'Chef des Ventes',
                'department' => 'COM',
                'roles' => ['ROLE_MANAGER'],
                'hireDate' => '2020-09-01',
            ],
            [
                'email' => 'alice.petit@hrflow.com',
                'firstName' => 'Alice',
                'lastName' => 'Petit',
                'phone' => '+33123456705',
                'position' => 'Commerciale Junior',
                'department' => 'COM',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2022-02-14',
            ],
            [
                'email' => 'thomas.moreau@hrflow.com',
                'firstName' => 'Thomas',
                'lastName' => 'Moreau',
                'phone' => '+33123456706',
                'position' => 'Comptable Senior',
                'department' => 'FIN',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2019-11-30',
            ],
            [
                'email' => 'emma.garcia@hrflow.com',
                'firstName' => 'Emma',
                'lastName' => 'Garcia',
                'phone' => '+33123456707',
                'position' => 'Assistante RH',
                'department' => 'RH',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2021-04-12',
            ],
            [
                'email' => 'maxime.laurent@hrflow.com',
                'firstName' => 'Maxime',
                'lastName' => 'Laurent',
                'phone' => '+33123456708',
                'position' => 'Chef Marketing',
                'department' => 'MKT',
                'roles' => ['ROLE_MANAGER'],
                'hireDate' => '2020-07-08',
            ],
            [
                'email' => 'clara.simon@hrflow.com',
                'firstName' => 'Clara',
                'lastName' => 'Simon',
                'phone' => '+33123456709',
                'position' => 'Designer Graphique',
                'department' => 'MKT',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2022-01-20',
            ],
            [
                'email' => 'lucas.david@hrflow.com',
                'firstName' => 'Lucas',
                'lastName' => 'David',
                'phone' => '+33123456710',
                'position' => 'Développeur Junior',
                'department' => 'IT',
                'roles' => ['ROLE_EMPLOYE'],
                'hireDate' => '2023-03-15',
            ],
        ];

        $users = [];
        foreach ($usersData as $userData) {
            // Vérifier si l'utilisateur existe déjà
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);
            
            if ($existingUser) {
                $io->writeln("- Utilisateur {$userData['email']} existe déjà");
                $users[] = $existingUser;
                continue;
            }

            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setPhone($userData['phone']);
            $user->setPosition($userData['position']);
            $user->setRoles($userData['roles']);
            $user->setHireDate(new \DateTime($userData['hireDate']));
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            // Mot de passe par défaut : "password123"
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            
            // Assigner le département
            if (isset($departments[$userData['department']])) {
                $user->setDepartment($departments[$userData['department']]);
            }

            $this->entityManager->persist($user);
            $users[] = $user;
            
            $io->writeln("✓ Utilisateur {$userData['firstName']} {$userData['lastName']} créé");
        }

        $this->entityManager->flush();
        return $users;
    }

    private function assignManagers(array $users, SymfonyStyle $io): void
    {
        $io->section('Attribution des managers');

        // Mapping des relations manager/employé par email
        $managerRelations = [
            'pierre.martin@hrflow.com' => 'marie.dubois@hrflow.com',      // Pierre -> Marie
            'sophie.bernard@hrflow.com' => 'pierre.martin@hrflow.com',    // Sophie -> Pierre
            'julien.rousseau@hrflow.com' => 'marie.dubois@hrflow.com',    // Julien -> Marie
            'alice.petit@hrflow.com' => 'julien.rousseau@hrflow.com',     // Alice -> Julien
            'thomas.moreau@hrflow.com' => 'marie.dubois@hrflow.com',      // Thomas -> Marie
            'emma.garcia@hrflow.com' => 'marie.dubois@hrflow.com',        // Emma -> Marie
            'maxime.laurent@hrflow.com' => 'marie.dubois@hrflow.com',     // Maxime -> Marie
            'clara.simon@hrflow.com' => 'maxime.laurent@hrflow.com',      // Clara -> Maxime
            'lucas.david@hrflow.com' => 'pierre.martin@hrflow.com',       // Lucas -> Pierre
        ];

        // Créer un mapping email -> user pour faciliter les recherches
        $usersByEmail = [];
        foreach ($users as $user) {
            $usersByEmail[$user->getEmail()] = $user;
        }

        foreach ($managerRelations as $employeeEmail => $managerEmail) {
            if (isset($usersByEmail[$employeeEmail]) && isset($usersByEmail[$managerEmail])) {
                $employee = $usersByEmail[$employeeEmail];
                $manager = $usersByEmail[$managerEmail];
                
                $employee->setManager($manager);
                $io->writeln("✓ {$employee->getFirstName()} {$employee->getLastName()} -> Manager: {$manager->getFirstName()} {$manager->getLastName()}");
            }
        }

        $this->entityManager->flush();
    }

    private function assignDepartmentManagers(array $users, array $departments, SymfonyStyle $io): void
    {
        $io->section('Attribution des managers de département');

        // Mapping des managers de département par email
        $departmentManagerMapping = [
            'RH' => 'marie.dubois@hrflow.com',
            'IT' => 'pierre.martin@hrflow.com',
            'COM' => 'julien.rousseau@hrflow.com',
            'FIN' => 'thomas.moreau@hrflow.com',
            'MKT' => 'maxime.laurent@hrflow.com',
        ];

        // Créer un mapping email -> user
        $usersByEmail = [];
        foreach ($users as $user) {
            $usersByEmail[$user->getEmail()] = $user;
        }

        foreach ($departmentManagerMapping as $deptCode => $managerEmail) {
            if (isset($departments[$deptCode]) && isset($usersByEmail[$managerEmail])) {
                $department = $departments[$deptCode];
                $manager = $usersByEmail[$managerEmail];
                
                $department->setManager($manager);
                $io->writeln("✓ Département {$department->getName()} -> Manager: {$manager->getFirstName()} {$manager->getLastName()}");
            }
        }

        $this->entityManager->flush();
    }

    private function displayUsersTable(array $users, SymfonyStyle $io): void
    {
        $io->section('Résumé des utilisateurs créés');
        
        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                $user->getFirstName() . ' ' . $user->getLastName(),
                $user->getEmail(),
                $user->getPosition(),
                $user->getDepartment() ? $user->getDepartment()->getName() : 'N/A',
                implode(', ', $user->getRoles()),
                $user->getManager() ? $user->getManager()->getFirstName() . ' ' . $user->getManager()->getLastName() : 'Aucun',
            ];
        }

        $io->table(
            ['Nom', 'Email', 'Poste', 'Département', 'Rôles', 'Manager'],
            $tableData
        );

        $io->note('Mot de passe par défaut pour tous les utilisateurs : password123');
    }
}