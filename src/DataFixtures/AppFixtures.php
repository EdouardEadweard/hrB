<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Department;
use App\Entity\LeaveType;
use App\Entity\LeaveRequest;
use App\Entity\LeaveBalance;
use App\Entity\Notification;
use App\Entity\Holiday;
use App\Entity\Attendance;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\LeavePolicy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Créer les départements
        $departments = $this->createDepartments($manager);
        
        // 2. Créer les utilisateurs
        $users = $this->createUsers($manager, $departments);
        
        // 3. Assigner les managers aux départements
        $this->assignDepartmentManagers($manager, $departments, $users);
        
        // 4. Créer les types de congés
        $leaveTypes = $this->createLeaveTypes($manager);
        
        // 5. Créer les jours fériés
        $this->createHolidays($manager);
        
        // 6. Créer les équipes
        $teams = $this->createTeams($manager, $departments, $users);
        
        // 7. Créer les membres d'équipe
        $this->createTeamMembers($manager, $teams, $users);
        
        // 8. Créer les politiques de congés
        $this->createLeavePolicies($manager, $departments, $leaveTypes);
        
        // 9. Créer les soldes de congés
        $this->createLeaveBalances($manager, $users, $leaveTypes);
        
        // 10. Créer les demandes de congés
        $leaveRequests = $this->createLeaveRequests($manager, $users, $leaveTypes);
        
        // 11. Créer les notifications
        $this->createNotifications($manager, $users, $leaveRequests);
        
        // 12. Créer les présences
        $this->createAttendances($manager, $users);

        $manager->flush();
    }

    private function createDepartments(ObjectManager $manager): array
    {
        $departmentsData = [
            ['name' => 'Direction Générale', 'code' => 'DG', 'description' => 'Direction générale de l\'entreprise'],
            ['name' => 'Ressources Humaines', 'code' => 'RH', 'description' => 'Gestion des ressources humaines'],
            ['name' => 'Informatique', 'code' => 'IT', 'description' => 'Service informatique et développement'],
            ['name' => 'Comptabilité', 'code' => 'COMPTA', 'description' => 'Service comptabilité et finance'],
            ['name' => 'Commercial', 'code' => 'COM', 'description' => 'Service commercial et ventes'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Service marketing et communication'],
            ['name' => 'Production', 'code' => 'PROD', 'description' => 'Service de production'],
            ['name' => 'Qualité', 'code' => 'QUAL', 'description' => 'Contrôle qualité'],
            ['name' => 'Logistique', 'code' => 'LOG', 'description' => 'Gestion logistique'],
            ['name' => 'Maintenance', 'code' => 'MAINT', 'description' => 'Service maintenance']
        ];

        $departments = [];
        foreach ($departmentsData as $index => $data) {
            $department = new Department();
            $department->setName($data['name']);
            $department->setCode($data['code']);
            $department->setDescription($data['description']);
            $department->setIsActive(true);
            $department->setCreatedAt(new \DateTimeImmutable());
            $department->setUpdatedAt(new \DateTimeImmutable());

            $manager->persist($department);
            $departments[] = $department;
            $this->addReference('department_' . $index, $department);
        }

        return $departments;
    }

    private function createUsers(ObjectManager $manager, array $departments): array
    {
        $usersData = [
            ['email' => 'admin@hrflow.com', 'firstName' => 'Admin', 'lastName' => 'System', 'roles' => ['ROLE_ADMIN'], 'position' => 'Administrateur', 'phone' => '0123456789'],
            ['email' => 'marie.dubois@hrflow.com', 'firstName' => 'Marie', 'lastName' => 'Dubois', 'roles' => ['ROLE_MANAGER'], 'position' => 'Directrice RH', 'phone' => '0123456790'],
            ['email' => 'jean.martin@hrflow.com', 'firstName' => 'Jean', 'lastName' => 'Martin', 'roles' => ['ROLE_MANAGER'], 'position' => 'Chef de projet IT', 'phone' => '0123456791'],
            ['email' => 'sophie.bernard@hrflow.com', 'firstName' => 'Sophie', 'lastName' => 'Bernard', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Développeuse', 'phone' => '0123456792'],
            ['email' => 'pierre.robert@hrflow.com', 'firstName' => 'Pierre', 'lastName' => 'Robert', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Comptable', 'phone' => '0123456793'],
            ['email' => 'claire.petit@hrflow.com', 'firstName' => 'Claire', 'lastName' => 'Petit', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Commercial', 'phone' => '0123456794'],
            ['email' => 'lucas.durand@hrflow.com', 'firstName' => 'Lucas', 'lastName' => 'Durand', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Designer', 'phone' => '0123456795'],
            ['email' => 'emma.leroy@hrflow.com', 'firstName' => 'Emma', 'lastName' => 'Leroy', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Assistante RH', 'phone' => '0123456796'],
            ['email' => 'thomas.moreau@hrflow.com', 'firstName' => 'Thomas', 'lastName' => 'Moreau', 'roles' => ['ROLE_MANAGER'], 'position' => 'Chef des ventes', 'phone' => '0123456797'],
            ['email' => 'julie.simon@hrflow.com', 'firstName' => 'Julie', 'lastName' => 'Simon', 'roles' => ['ROLE_EMPLOYEE'], 'position' => 'Technicienne', 'phone' => '0123456798']
        ];

        $users = [];
        foreach ($usersData as $index => $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setRoles($data['roles']);
            $user->setPosition($data['position']);
            $user->setPhone($data['phone']);
            $user->setHireDate(new \DateTime('-' . rand(1, 60) . ' months'));
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            // Mot de passe par défaut : "password"
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
            $user->setPassword($hashedPassword);
            
            // Assigner un département aléatoire
            $user->setDepartment($departments[array_rand($departments)]);

            $manager->persist($user);
            $users[] = $user;
            $this->addReference('user_' . $index, $user);
        }

        return $users;
    }

    private function assignDepartmentManagers(ObjectManager $manager, array $departments, array $users): void
    {
        // Assigner des managers aux départements
        $managers = array_filter($users, fn($user) => in_array('ROLE_MANAGER', $user->getRoles()));
        
        foreach ($departments as $index => $department) {
            if (!empty($managers)) {
                $randomManager = $managers[array_rand($managers)];
                $department->setManager($randomManager->getId());
            }
        }
    }

    private function createLeaveTypes(ObjectManager $manager): array
    {
        $leaveTypesData = [
            ['name' => 'Congés payés', 'code' => 'CP', 'description' => 'Congés payés annuels', 'maxDays' => 25, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#28a745'],
            ['name' => 'Congé maladie', 'code' => 'CM', 'description' => 'Arrêt maladie', 'maxDays' => 90, 'requiresApproval' => false, 'isPaid' => true, 'color' => '#dc3545'],
            ['name' => 'Congé maternité', 'code' => 'MAT', 'description' => 'Congé maternité', 'maxDays' => 112, 'requiresApproval' => false, 'isPaid' => true, 'color' => '#e83e8c'],
            ['name' => 'Congé paternité', 'code' => 'PAT', 'description' => 'Congé paternité', 'maxDays' => 25, 'requiresApproval' => false, 'isPaid' => true, 'color' => '#6f42c1'],
            ['name' => 'RTT', 'code' => 'RTT', 'description' => 'Réduction du temps de travail', 'maxDays' => 10, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#17a2b8'],
            ['name' => 'Congé sans solde', 'code' => 'CSS', 'description' => 'Congé sans rémunération', 'maxDays' => 365, 'requiresApproval' => true, 'isPaid' => false, 'color' => '#6c757d'],
            ['name' => 'Formation', 'code' => 'FORM', 'description' => 'Congé formation', 'maxDays' => 30, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#fd7e14'],
            ['name' => 'Congé exceptionnel', 'code' => 'EXCEPT', 'description' => 'Événements familiaux', 'maxDays' => 5, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#20c997'],
            ['name' => 'Récupération', 'code' => 'RECUP', 'description' => 'Heures de récupération', 'maxDays' => 15, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#ffc107'],
            ['name' => 'Télétravail', 'code' => 'TT', 'description' => 'Journée de télétravail', 'maxDays' => 104, 'requiresApproval' => true, 'isPaid' => true, 'color' => '#007bff']
        ];

        $leaveTypes = [];
        foreach ($leaveTypesData as $index => $data) {
            $leaveType = new LeaveType();
            $leaveType->setName($data['name']);
            $leaveType->setCode($data['code']);
            $leaveType->setDescription($data['description']);
            $leaveType->setMaxDaysPerYear($data['maxDays']);
            $leaveType->setRequiresApproval($data['requiresApproval']);
            $leaveType->setIsPaid($data['isPaid']);
            $leaveType->setColor($data['color']);
            $leaveType->setIsActive(true);
            $leaveType->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($leaveType);
            $leaveTypes[] = $leaveType;
            $this->addReference('leave_type_' . $index, $leaveType);
        }

        return $leaveTypes;
    }

    private function createHolidays(ObjectManager $manager): void
    {
        $holidaysData = [
            ['name' => 'Nouvel An', 'date' => '2024-01-01', 'isRecurring' => true, 'description' => 'Premier jour de l\'année'],
            ['name' => 'Lundi de Pâques', 'date' => '2024-04-01', 'isRecurring' => true, 'description' => 'Lundi de Pâques'],
            ['name' => 'Fête du Travail', 'date' => '2024-05-01', 'isRecurring' => true, 'description' => 'Fête du travail'],
            ['name' => 'Victoire 1945', 'date' => '2024-05-08', 'isRecurring' => true, 'description' => 'Victoire du 8 mai 1945'],
            ['name' => 'Ascension', 'date' => '2024-05-09', 'isRecurring' => true, 'description' => 'Jeudi de l\'Ascension'],
            ['name' => 'Lundi de Pentecôte', 'date' => '2024-05-20', 'isRecurring' => true, 'description' => 'Lundi de Pentecôte'],
            ['name' => 'Fête Nationale', 'date' => '2024-07-14', 'isRecurring' => true, 'description' => 'Fête nationale française'],
            ['name' => 'Assomption', 'date' => '2024-08-15', 'isRecurring' => true, 'description' => 'Assomption'],
            ['name' => 'Toussaint', 'date' => '2024-11-01', 'isRecurring' => true, 'description' => 'Toussaint'],
            ['name' => 'Armistice 1918', 'date' => '2024-11-11', 'isRecurring' => true, 'description' => 'Armistice du 11 novembre 1918']
        ];

        foreach ($holidaysData as $data) {
            $holiday = new Holiday();
            $holiday->setName($data['name']);
            $holiday->setDate(new \DateTime($data['date']));
            $holiday->setIsRecurring($data['isRecurring']);
            $holiday->setDescription($data['description']);
            $holiday->setIsActive(true);
            $holiday->setCreatedAt(new \DateTime());

            $manager->persist($holiday);
        }
    }

    private function createTeams(ObjectManager $manager, array $departments, array $users): array
    {
        $teamsData = [
            ['name' => 'Équipe Développement', 'description' => 'Équipe de développement logiciel'],
            ['name' => 'Équipe Marketing Digital', 'description' => 'Équipe marketing numérique'],
            ['name' => 'Équipe Ventes Nord', 'description' => 'Équipe commerciale région Nord'],
            ['name' => 'Équipe Ventes Sud', 'description' => 'Équipe commerciale région Sud'],
            ['name' => 'Équipe Support Client', 'description' => 'Support et service client'],
            ['name' => 'Équipe Finance', 'description' => 'Équipe comptabilité et finance'],
            ['name' => 'Équipe RH', 'description' => 'Équipe ressources humaines'],
            ['name' => 'Équipe Production A', 'description' => 'Équipe de production ligne A'],
            ['name' => 'Équipe Qualité', 'description' => 'Équipe contrôle qualité'],
            ['name' => 'Équipe Logistique', 'description' => 'Équipe gestion logistique']
        ];

        $teams = [];
        $managers = array_filter($users, fn($user) => in_array('ROLE_MANAGER', $user->getRoles()));

        foreach ($teamsData as $index => $data) {
            $team = new Team();
            $team->setName($data['name']);
            $team->setDescription($data['description']);
            $team->setIsActive(true);
            $team->setCreatedAt(new \DateTime());
            
            // Assigner un leader et un département
            $team->setLeader($managers[array_rand($managers)]->getId());
            $team->setDepartment($departments[array_rand($departments)]);

            $manager->persist($team);
            $teams[] = $team;
            $this->addReference('team_' . $index, $team);
        }

        return $teams;
    }

    private function createTeamMembers(ObjectManager $manager, array $teams, array $users): void
    {
        // Assigner des utilisateurs aux équipes
        foreach ($teams as $team) {
            $teamMemberCount = rand(2, 5); // 2 à 5 membres par équipe
            $selectedUsers = array_rand($users, $teamMemberCount);
            
            if (!is_array($selectedUsers)) {
                $selectedUsers = [$selectedUsers];
            }

            foreach ($selectedUsers as $userIndex) {
                $teamMember = new TeamMember();
                $teamMember->setTeam($team);
                $teamMember->setUser($users[$userIndex]);
                $teamMember->setJoinedAt(new \DateTime('-' . rand(1, 24) . ' months'));
                $teamMember->setIsActive(true);

                $manager->persist($teamMember);
            }
        }
    }

    private function createLeavePolicies(ObjectManager $manager, array $departments, array $leaveTypes): void
    {
        $policiesData = [
            ['name' => 'Politique Standard CP', 'description' => 'Politique standard pour les congés payés', 'rules' => ['max_consecutive_days' => 15, 'min_notice_days' => 7]],
            ['name' => 'Politique RTT', 'description' => 'Politique pour les RTT', 'rules' => ['max_consecutive_days' => 5, 'min_notice_days' => 3]],
            ['name' => 'Politique Télétravail', 'description' => 'Règles de télétravail', 'rules' => ['max_days_per_week' => 2, 'advance_booking_required' => true]],
            ['name' => 'Politique Formation', 'description' => 'Politique de congé formation', 'rules' => ['budget_required' => true, 'manager_approval' => true]],
            ['name' => 'Politique Récupération', 'description' => 'Gestion des heures de récupération', 'rules' => ['max_accumulation_hours' => 40]],
            ['name' => 'Politique Exceptionnelle', 'description' => 'Congés exceptionnels', 'rules' => ['justification_required' => true]],
            ['name' => 'Politique Direction', 'description' => 'Politique spéciale direction', 'rules' => ['flexible_schedule' => true]],
            ['name' => 'Politique Stagiaires', 'description' => 'Politique pour les stagiaires', 'rules' => ['limited_leave_days' => 5]],
            ['name' => 'Politique Temps Partiel', 'description' => 'Adaptation pour temps partiel', 'rules' => ['prorated_calculation' => true]],
            ['name' => 'Politique Urgence', 'description' => 'Procédure d\'urgence', 'rules' => ['immediate_approval' => true]]
        ];

        foreach ($policiesData as $index => $data) {
            $policy = new LeavePolicy();
            $policy->setName($data['name']);
            $policy->setDescription($data['description']);
            $policy->setRules($data['rules']);
            $policy->setIsActive(true);
            $policy->setCreatedAt(new \DateTime());
            $policy->setDepartment($departments[array_rand($departments)]);
            $policy->setLeaveType($leaveTypes[array_rand($leaveTypes)]);

            $manager->persist($policy);
        }
    }

    private function createLeaveBalances(ObjectManager $manager, array $users, array $leaveTypes): void
    {
        $currentYear = (int)date('Y');
        
        foreach ($users as $user) {
            // Créer des soldes pour les principaux types de congés
            $mainLeaveTypes = array_slice($leaveTypes, 0, 5); // Les 5 premiers types
            
            foreach ($mainLeaveTypes as $leaveType) {
                $balance = new LeaveBalance();
                $balance->setEmployee($user);
                $balance->setLeaveType($leaveType);
                $balance->setYear($currentYear);
                $balance->setTotalDays($leaveType->getMaxDaysPerYear());
                $balance->setUsedDays(rand(0, min(10, $leaveType->getMaxDaysPerYear())));
                $balance->setRemainingDays($balance->getTotalDays() - $balance->getUsedDays());
                $balance->setLastUpdated(new \DateTime());

                $manager->persist($balance);
            }
        }
    }

    private function createLeaveRequests(ObjectManager $manager, array $users, array $leaveTypes): array
    {
        $statuses = ['pending', 'approved', 'rejected', 'cancelled'];
        $leaveRequests = [];

        for ($i = 0; $i < 10; $i++) {
            $request = new LeaveRequest();
            $startDate = new \DateTime('+' . rand(1, 60) . ' days');
            $numberOfDays = rand(1, 5);
            $endDate = clone $startDate;
            $endDate->modify('+' . ($numberOfDays - 1) . ' days');
            
            $request->setEmployee($users[array_rand($users)]);
            $request->setLeaveType($leaveTypes[array_rand($leaveTypes)]);
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setNumberOfDays($numberOfDays);
            $request->setReason('Demande de congé pour raisons personnelles');
            $request->setStatus($statuses[array_rand($statuses)]);
            $request->setSubmittedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            $request->setCreatedAt(new \DateTimeImmutable());
            $request->setUpdatedAt(new \DateTimeImmutable());

            if ($request->getStatus() !== 'pending') {
                $managers = array_filter($users, fn($user) => in_array('ROLE_MANAGER', $user->getRoles()));
                $request->setApprovedBy($managers[array_rand($managers)]);
                $request->setProcessedAt(new \DateTimeImmutable('-' . rand(1, 15) . ' days'));
                
                if ($request->getStatus() === 'rejected') {
                    $request->setManagerComment('Période non compatible avec les besoins du service');
                }
            }

            $manager->persist($request);
            $leaveRequests[] = $request;
        }

        return $leaveRequests;
    }

    private function createNotifications(ObjectManager $manager, array $users, array $leaveRequests): void
    {
        $notificationTypes = ['leave_request', 'leave_approval', 'leave_rejection', 'reminder', 'system'];
        $titles = [
            'Nouvelle demande de congé',
            'Demande approuvée',
            'Demande refusée',
            'Rappel',
            'Notification système'
        ];

        for ($i = 0; $i < 10; $i++) {
            $notification = new Notification();
            $type = $notificationTypes[array_rand($notificationTypes)];
            
            $notification->setTitle($titles[array_rand($titles)]);
            $notification->setMessage('Message de notification automatique du système HR Flow');
            $notification->setType($type);
            $notification->setIsRead(rand(0, 1) === 1);
            $notification->setRecipient($users[array_rand($users)]);
            $notification->setSender($users[array_rand($users)]);
            $notification->setCreatedAt(new \DateTime('-' . rand(1, 7) . ' days'));
            
            if ($notification->getReadAt()) {
                $notification->setReadAt(new \DateTime('-' . rand(1, 5) . ' days'));
            }
            
            if (!empty($leaveRequests)) {
                $notification->setLeaveRequest($leaveRequests[array_rand($leaveRequests)]);
            }

            $manager->persist($notification);
        }
    }

    private function createAttendances(ObjectManager $manager, array $users): void
    {
        $statuses = ['present', 'absent', 'late', 'half_day'];
        
        // Créer 10 enregistrements de présence récents
        for ($i = 0; $i < 10; $i++) {
            $attendance = new Attendance();
            $workDate = new \DateTime('-' . rand(1, 30) . ' days');
            $checkIn = clone $workDate;
            $checkIn->setTime(8, rand(0, 30), 0); // Entre 8h00 et 8h30
            
            $attendance->setEmployee($users[array_rand($users)]);
            $attendance->setWorkDate($workDate);
            $attendance->setCheckIn($checkIn);
            $attendance->setStatus($statuses[array_rand($statuses)]);
            
            if ($attendance->getStatus() === 'present') {
                $checkOut = clone $checkIn;
                $checkOut->setTime(17, rand(0, 30), 0); // Entre 17h00 et 17h30
                $attendance->setCheckOut($checkOut);
                $attendance->setWorkedHours(8);
            } elseif ($attendance->getStatus() === 'half_day') {
                $checkOut = clone $checkIn;
                $checkOut->setTime(12, 0, 0);
                $attendance->setCheckOut($checkOut);
                $attendance->setWorkedHours(4);
            }
            
            $attendance->setNotes('Saisie automatique');
            $attendance->setCreatedAt(new \DateTime());

            $manager->persist($attendance);
        }
    }
}