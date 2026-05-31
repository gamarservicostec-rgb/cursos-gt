<?php
namespace Config;

use PDO;
use PDOException;

require_once __DIR__ . '/AppConfig.php';

/**
 * Classe Database
 * 
 * Gerenciador Singleton de conexões PDO com o MySQL.
 * Evita a abertura de múltiplas conexões síncronas na mesma requisição.
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = "mysql:host=" . AppConfig::$DB_HOST . ";dbname=" . AppConfig::$DB_NAME . ";charset=" . AppConfig::$DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Desabilita emulação para evitar SQL injection secundário
        ];

        try {
            $this->connection = new PDO($dsn, AppConfig::$DB_USER, AppConfig::$DB_PASS, $options);
            $this->autoMigrate();
        } catch (PDOException $e) {
            // Em modo desenvolvimento exibe o erro, em produção oculta
            if (AppConfig::$DEV_MODE) {
                die("Erro na conexão com o Banco de Dados: " . $e->getMessage());
            } else {
                die("Erro interno. Não foi possível conectar ao servidor de dados.");
            }
        }
    }

    /**
     * Auto-migração silenciosa: detecta a ausência de tabelas cruciais e as sincroniza de forma transparente.
     */
    private function autoMigrate() {
        try {
            // Verifica se a tabela categories e support_tickets existem. Se não existirem, dispara exceção de PDO e roda a migração.
            $this->connection->query("SELECT 1 FROM `categories` LIMIT 1");
            $this->connection->query("SELECT 1 FROM `support_tickets` LIMIT 1");
        } catch (\PDOException $e) {
            // Executa a reestruturação e população das sementes de forma transparente
            $this->runSilently();
        }
    }

    /**
     * Cria todas as tabelas necessárias e garante o hash BCRYPT do Administrador padrão.
     */
    private function runSilently() {
        try {
            $db = $this->connection;
            
            // 1. Cria a tabela categories
            $db->exec("
                CREATE TABLE IF NOT EXISTS `categories` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(100) UNIQUE NOT NULL,
                    `sort_order` INT DEFAULT 0,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Inserção de categorias sementes se vazia
            $countStmt = $db->query("SELECT COUNT(*) FROM `categories`");
            if ($countStmt->fetchColumn() == 0) {
                $db->exec("
                    INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES 
                    ('Segurança de Elite', 'seguranca-de-elite', 1),
                    ('Tecnologia & Dev', 'tecnologia-dev', 2),
                    ('Negócios & Marketing', 'negocios-marketing', 3)
                ");
            }

            // Coluna category_id na tabela courses
            $columnsStmt = $db->query("DESCRIBE `courses`");
            $columns = [];
            while ($row = $columnsStmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'] ?? $row['field'] ?? '';
            }
            $columns = array_filter($columns);

            if (!in_array('category_id', $columns)) {
                $db->exec("
                    ALTER TABLE `courses` 
                    ADD COLUMN `category_id` INT DEFAULT NULL,
                    ADD CONSTRAINT `fk_courses_category` 
                    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
                    ON DELETE SET NULL;
                ");
                $db->exec("UPDATE `courses` SET `category_id` = 1 WHERE `id` = 1");
                $db->exec("UPDATE `courses` SET `category_id` = 2 WHERE `id` = 2");
            }

            // Novas colunas na tabela courses para suporte híbrido
            $columnsStmt = $db->query("DESCRIBE `courses`");
            $columns = [];
            while ($row = $columnsStmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'] ?? $row['field'] ?? '';
            }
            $columns = array_filter($columns);

            if (!in_array('duration_days', $columns)) {
                $db->exec("ALTER TABLE `courses` ADD COLUMN `duration_days` INT DEFAULT NULL");
            }
            if (!in_array('weekdays_only', $columns)) {
                $db->exec("ALTER TABLE `courses` ADD COLUMN `weekdays_only` TINYINT(1) DEFAULT 1");
            }
            if (!in_array('available_hours', $columns)) {
                $db->exec("ALTER TABLE `courses` ADD COLUMN `available_hours` VARCHAR(255) DEFAULT NULL");
            }

            // Novas colunas na tabela enrollments para suporte híbrido (horário do aluno)
            $columnsEnrollStmt = $db->query("DESCRIBE `enrollments`");
            $columnsEnroll = [];
            while ($row = $columnsEnrollStmt->fetch(\PDO::FETCH_ASSOC)) {
                $columnsEnroll[] = $row['Field'] ?? $row['field'] ?? '';
            }
            $columnsEnroll = array_filter($columnsEnroll);

            if (!in_array('schedule_time', $columnsEnroll)) {
                $db->exec("ALTER TABLE `enrollments` ADD COLUMN `schedule_time` VARCHAR(50) DEFAULT NULL");
            }

            // Novas colunas na tabela physical_attendance para suporte híbrido (time_slot da chamada)
            $columnsAttStmt = $db->query("DESCRIBE `physical_attendance`");
            $columnsAtt = [];
            while ($row = $columnsAttStmt->fetch(\PDO::FETCH_ASSOC)) {
                $columnsAtt[] = $row['Field'] ?? $row['field'] ?? '';
            }
            $columnsAtt = array_filter($columnsAtt);

            if (!in_array('time_slot', $columnsAtt)) {
                $db->exec("ALTER TABLE `physical_attendance` ADD COLUMN `time_slot` VARCHAR(50) DEFAULT NULL");
            }

            // 2. Sistema de Avaliações Técnicas
            $db->exec("
                CREATE TABLE IF NOT EXISTS `quizzes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `course_id` INT NOT NULL,
                    `module_id` INT DEFAULT NULL,
                    `title` VARCHAR(150) NOT NULL,
                    `min_score` INT DEFAULT 70,
                    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
                    INDEX `idx_quizzes_course` (`course_id`),
                    INDEX `idx_quizzes_module` (`module_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            $db->exec("
                CREATE TABLE IF NOT EXISTS `questions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `quiz_id` INT NOT NULL,
                    `question_text` TEXT NOT NULL,
                    `type` ENUM('dissertativa', 'unica_escolha', 'multipla_escolha') DEFAULT 'unica_escolha',
                    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
                    INDEX `idx_questions_quiz` (`quiz_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS `question_options` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `question_id` INT NOT NULL,
                    `option_text` TEXT NOT NULL,
                    `is_correct` TINYINT(1) DEFAULT 0,
                    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
                    INDEX `idx_options_question` (`question_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS `quiz_attempts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `quiz_id` INT NOT NULL,
                    `score` INT NOT NULL,
                    `passed` TINYINT(1) NOT NULL DEFAULT 0,
                    `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
                    INDEX `idx_attempts_user` (`user_id`),
                    INDEX `idx_attempts_quiz` (`quiz_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Sistema de Certificação HD
            $db->exec("
                CREATE TABLE IF NOT EXISTS `certificate_templates` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `course_id` INT UNIQUE NOT NULL,
                    `background_url` VARCHAR(255) DEFAULT NULL,
                    `logo_url` VARCHAR(255) DEFAULT NULL,
                    `signature_url` VARCHAR(255) DEFAULT NULL,
                    `text_layout` TEXT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS `certificates` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `course_id` INT NOT NULL,
                    `certificate_code` VARCHAR(100) UNIQUE NOT NULL,
                    `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
                    INDEX `idx_certificates_user` (`user_id`),
                    INDEX `idx_certificates_code` (`certificate_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 4. Sistema de Tickets de Suporte
            $db->exec("
                CREATE TABLE IF NOT EXISTS `support_tickets` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `subject` VARCHAR(200) NOT NULL,
                    `status` ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    INDEX `idx_tickets_user` (`user_id`),
                    INDEX `idx_tickets_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS `support_messages` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `ticket_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `message` TEXT NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    INDEX `idx_messages_ticket` (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 5. Garante a existência e a senha do Administrador em BCRYPT
            $email = 'admin@cursosgt.com.br';
            $password = 'admin123';
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $name = 'Diretoria GT';

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Força a redefinição de senha com criptografia BCRYPT compatível
                $update = $db->prepare("UPDATE users SET password_hash = :hash, role = 'admin', name = :name WHERE email = :email");
                $update->execute([
                    ':hash' => $hash,
                    ':name' => $name,
                    ':email' => $email
                ]);
            } else {
                // Cria o administrador do zero
                $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, 'admin')");
                $insert->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':hash' => $hash
                ]);
            }
        } catch (\Exception $ex) {
            // Ignora silenciosamente para não interromper carregamentos da web
        }
    }

    /**
     * Retorna a instância única da classe Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna a conexão PDO ativa
     */
    public function getConnection() {
        return $this->connection;
    }
}
