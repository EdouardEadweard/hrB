<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250529083314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE attendance (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, work_date DATE NOT NULL, check_in DATETIME DEFAULT NULL, check_out DATETIME DEFAULT NULL, worked_hours INT DEFAULT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_6DE30D918C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE department (id INT AUTO_INCREMENT NOT NULL, manager_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(10) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CD1DE18A77153098 (code), INDEX IDX_CD1DE18A783E3463 (manager_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE holiday (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, date DATE NOT NULL, is_recurring TINYINT(1) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leave_balance (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, leave_type_id INT NOT NULL, year INT NOT NULL, total_days INT NOT NULL, used_days INT NOT NULL, remaining_days INT NOT NULL, last_updated DATETIME NOT NULL, INDEX IDX_EAAB67198C03F15C (employee_id), INDEX IDX_EAAB67198313F474 (leave_type_id), UNIQUE INDEX unique_employee_leave_type_year (employee_id, leave_type_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leave_policy (id INT AUTO_INCREMENT NOT NULL, department_id INT NOT NULL, leave_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, rules JSON NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_C6BB9BB9AE80F5DF (department_id), INDEX IDX_C6BB9BB98313F474 (leave_type_id), UNIQUE INDEX unique_department_leave_type_policy (department_id, leave_type_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leave_request (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, leave_type_id INT NOT NULL, approved_by_id INT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, number_of_days INT NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, manager_comment LONGTEXT DEFAULT NULL, submitted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7DC8F7788C03F15C (employee_id), INDEX IDX_7DC8F7788313F474 (leave_type_id), INDEX IDX_7DC8F7782D234F6A (approved_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leave_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, max_days_per_year INT NOT NULL, requires_approval TINYINT(1) NOT NULL, is_paid TINYINT(1) NOT NULL, color VARCHAR(7) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_E2BC439177153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, sender_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, type VARCHAR(50) NOT NULL, is_read TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, leaveRequest_id INT DEFAULT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX IDX_BF5476CAF624B39D (sender_id), INDEX IDX_BF5476CAF5EC012 (leaveRequest_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, leader_id INT DEFAULT NULL, department_id INT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_C4E0A61F73154ED4 (leader_id), INDEX IDX_C4E0A61FAE80F5DF (department_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE team_member (id INT AUTO_INCREMENT NOT NULL, team_id INT NOT NULL, user_id INT NOT NULL, joined_at DATETIME NOT NULL, left_at DATETIME DEFAULT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_6FFBDA1296CD8AE (team_id), INDEX IDX_6FFBDA1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, department_id INT DEFAULT NULL, manager_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, hire_date DATE DEFAULT NULL, position VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649AE80F5DF (department_id), INDEX IDX_8D93D649783E3463 (manager_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D918C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE department ADD CONSTRAINT FK_CD1DE18A783E3463 FOREIGN KEY (manager_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_balance ADD CONSTRAINT FK_EAAB67198C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_balance ADD CONSTRAINT FK_EAAB67198313F474 FOREIGN KEY (leave_type_id) REFERENCES leave_type (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_policy ADD CONSTRAINT FK_C6BB9BB9AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_policy ADD CONSTRAINT FK_C6BB9BB98313F474 FOREIGN KEY (leave_type_id) REFERENCES leave_type (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F7788C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F7788313F474 FOREIGN KEY (leave_type_id) REFERENCES leave_type (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F7782D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAF5EC012 FOREIGN KEY (leaveRequest_id) REFERENCES leave_request (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F73154ED4 FOREIGN KEY (leader_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD CONSTRAINT FK_C4E0A61FAE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD CONSTRAINT FK_8D93D649AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD CONSTRAINT FK_8D93D649783E3463 FOREIGN KEY (manager_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D918C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE department DROP FOREIGN KEY FK_CD1DE18A783E3463
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_balance DROP FOREIGN KEY FK_EAAB67198C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_balance DROP FOREIGN KEY FK_EAAB67198313F474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_policy DROP FOREIGN KEY FK_C6BB9BB9AE80F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_policy DROP FOREIGN KEY FK_C6BB9BB98313F474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F7788C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F7788313F474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F7782D234F6A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAF624B39D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAF5EC012
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F73154ED4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61FAE80F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP FOREIGN KEY FK_8D93D649AE80F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP FOREIGN KEY FK_8D93D649783E3463
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE attendance
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE department
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE holiday
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE leave_balance
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE leave_policy
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE leave_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE leave_type
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE team
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE team_member
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
