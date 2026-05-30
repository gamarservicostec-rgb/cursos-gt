-- ====================================================
-- ESTRUTURA COMPLETA E EXPANDIDA DO BANCO DE DADOS — GT CURSOS
-- Neon Amber Fusion - Híbrido EAD + Presencial
-- Compatible with MySQL 8.0+ / phpMyAdmin / cPanel HostGator
-- ====================================================

-- CREATE DATABASE IF NOT EXISTS `gt_cursos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `gt_cursos`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------
-- 1. TABELA DE USUÁRIOS (users)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student') DEFAULT 'student',
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `xp` INT DEFAULT 0,
  `level` INT DEFAULT 1,
  `current_streak` INT DEFAULT 0,
  `last_activity` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_xp` (`xp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 2. TABELA DE CURSOS (courses)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `thumbnail_url` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('ead', 'presencial', 'hybrid') DEFAULT 'hybrid',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_courses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 3. TABELA DE MÓDULOS (modules)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  INDEX `idx_modules_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 4. TABELA DE MATÉRIAS (subjects)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  INDEX `idx_subjects_module` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 5. TABELA DE AULAS (lessons)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `lessons`;
CREATE TABLE `lessons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `video_provider` VARCHAR(50) DEFAULT 'bunny',
  `video_url` VARCHAR(255) DEFAULT NULL,
  `duration` INT DEFAULT 0, -- em segundos
  `attachment_url` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  INDEX `idx_lessons_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 6. TABELA DE MATRÍCULAS (enrollments)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `status` ENUM('active', 'completed', 'suspended') DEFAULT 'active',
  `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_course` (`user_id`, `course_id`),
  INDEX `idx_enrollments_user` (`user_id`),
  INDEX `idx_enrollments_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 7. PROGRESSO DE AULAS EAD (lesson_progress)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `lesson_progress`;
CREATE TABLE `lesson_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `lesson_id` INT NOT NULL,
  `watched_duration` INT DEFAULT 0,
  `completed` TINYINT(1) DEFAULT 0,
  `completed_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_lesson_progress` (`user_id`, `lesson_id`),
  INDEX `idx_progress_user` (`user_id`),
  INDEX `idx_progress_lesson` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 8. PRESENÇA FÍSICA AULAS PRESENCIAIS (physical_attendance)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `physical_attendance`;
CREATE TABLE `physical_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `attended` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_course_date` (`user_id`, `course_id`, `date`),
  INDEX `idx_attendance_user` (`user_id`),
  INDEX `idx_attendance_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 9. QUIZZES E PROVAS (quizzes)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
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

-- ----------------------------------------------------
-- 10. QUESTÕES DAS PROVAS (questions)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `type` ENUM('dissertativa', 'unica_escolha', 'multipla_escolha') DEFAULT 'unica_escolha',
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  INDEX `idx_questions_quiz` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 11. OPÇÕES DE QUESTÕES (question_options)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `question_options`;
CREATE TABLE `question_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `option_text` TEXT NOT NULL,
  `is_correct` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  INDEX `idx_options_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 12. TENTATIVAS DE PROVA (quiz_attempts)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `quiz_attempts`;
CREATE TABLE `quiz_attempts` (
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

-- ----------------------------------------------------
-- 13. CERTIFICADOS EMITIDOS (certificates)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
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

-- ----------------------------------------------------
-- 14. TRANSAÇÕES FINANCEIRAS (transactions)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `payment_id` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('pix', 'credit_card', 'boleto') NOT NULL,
  `status` ENUM('pending', 'approved', 'refunded', 'cancelled') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  INDEX `idx_transactions_user` (`user_id`),
  INDEX `idx_transactions_status` (`status`),
  INDEX `idx_transactions_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 15. TICKETS DE SUPORTE (support_tickets)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_tickets_user` (`user_id`),
  INDEX `idx_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 16. MENSAGENS DE SUPORTE (support_messages)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `support_messages`;
CREATE TABLE `support_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_messages_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 17. ANOTAÇÕES DE AULA (user_notes)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `user_notes`;
CREATE TABLE `user_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `lesson_id` INT NOT NULL,
  `notes_text` TEXT NOT NULL,
  `video_timestamp` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  INDEX `idx_notes_user_lesson` (`user_id`, `lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 18. COMENTÁRIOS DE AULA (lesson_comments)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `lesson_comments`;
CREATE TABLE `lesson_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `lesson_id` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `lesson_comments` (`id`) ON DELETE CASCADE,
  INDEX `idx_comments_lesson` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 19. NOTIFICAÇÕES INTERNAS (notifications)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'achievement') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user` (`user_id`),
  INDEX `idx_notifications_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 20. TABELA DE CONQUISTAS / MEDALHAS (achievements)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `achievements`;
CREATE TABLE `achievements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `icon_url` VARCHAR(255) DEFAULT NULL,
  `xp_bonus` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 21. RELACIONAMENTO DE CONQUISTAS DE ALUNOS (user_achievements)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `user_achievements`;
CREATE TABLE `user_achievements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `achievement_id` INT NOT NULL,
  `unlocked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_achievement` (`user_id`, `achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 22. CAMPANHAS DE E-MAIL MARKETING (email_campaigns)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `email_campaigns`;
CREATE TABLE `email_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` TEXT NOT NULL,
  `status` ENUM('draft', 'sending', 'sent') DEFAULT 'draft',
  `sent_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 23. LOGS DE DISPAROS DE WHATSAPP (whatsapp_logs)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('success', 'failed') DEFAULT 'success',
  `error_message` TEXT DEFAULT NULL,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 24. LOGS DE AUDITORIA CRÍTICOS (audit_logs)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 25. ATIVIDADE ADMINISTRATIVA (admin_activity)
-- ----------------------------------------------------
DROP TABLE IF EXISTS `admin_activity`;
CREATE TABLE `admin_activity` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` VARCHAR(150) NOT NULL,
  `affected_resource` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_admin_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- INSERÇÃO DE DADOS DE EXEMPLO (SEED DATA)
-- ----------------------------------------------------

-- Usuários iniciais (admin e aluno teste)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `xp`, `level`) VALUES
('Diretoria GT', 'admin@cursosgt.com.br', '$2y$10$wE99wXbJkYyVb0Q06oV8P.UaTj5B.4M/b8Xh57Q1P8a9Zg2xG1KzK', 'admin', 0, 1),
('Gabriel Teixeira', 'gabriel@exemplo.com', '$2y$10$wE99wXbJkYyVb0Q06oV8P.UaTj5B.4M/b8Xh57Q1P8a9Zg2xG1KzK', 'student', 1250, 5);

-- Cursos de exemplo
INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail_url`, `type`, `price`, `status`) VALUES
(1, 'Masterclass em Segurança de Elite', 'Aprenda protocolos de proteção avançada física, digital e de perímetros de segurança estratégica de alto nível com profissionais de elite.', NULL, 'hybrid', 1290.00, 'active'),
(2, 'Invasão Hacker e Resposta a Incidentes', 'Treinamento completo em cibersegurança tática, auditoria hacker ofensiva, análise de vulnerabilidades e resposta a crises digitais.', NULL, 'ead', 890.00, 'active');

-- Módulos
INSERT INTO `modules` (`id`, `course_id`, `title`, `description`, `sort_order`) VALUES
(1, 1, 'Protocolos de Segurança Avançados', 'Aprenda os fundamentos da análise de vulnerabilidade física e gestão estrutural de riscos.', 1),
(2, 1, 'Tecnologia de Reconhecimento e IA', 'Dispositivos de escuta, sensores termais e monitoramento moderno.', 2);

-- Matérias
INSERT INTO `subjects` (`id`, `module_id`, `title`, `sort_order`) VALUES
(1, 1, 'Módulo 1: Segurança Operacional', 1),
(2, 2, 'Módulo 2: Sensores de Inteligência', 2);

-- Aulas
INSERT INTO `lessons` (`id`, `subject_id`, `title`, `description`, `video_provider`, `video_url`, `duration`, `sort_order`) VALUES
(1, 1, '1. Análise de Risco Perimetral', 'Nesta aula introdutória ao Módulo 1, exploramos os fundamentos da análise de risco perimetral em ambientes de alta complexidade.', 'bunny', '7750362074564546823', 765, 1),
(2, 1, '2. Equipamentos de Vigilância e Defesa', 'Detalhes técnicos de implantação de redes táticas de vigilância ativa.', 'bunny', '8004146441738874927', 500, 2);

-- Matrícula ativa do Gabriel no Curso 1
INSERT INTO `enrollments` (`user_id`, `course_id`, `status`) VALUES
(2, 1, 'active');

-- Conquistas / Medalhas seed
INSERT INTO `achievements` (`id`, `title`, `description`, `icon_url`, `xp_bonus`) VALUES
(1, 'Primeiro Passo', 'Concluiu a primeira aula na plataforma GT Cursos.', 'badge_first_lesson', 100),
(2, 'Sobrevivente', 'Alcançou um streak de 7 dias de atividades consecutivas.', 'badge_streak_7', 250),
(3, 'Cérebro de Elite', 'Foi aprovado em qualquer prova com pontuação perfeita de 100%.', 'badge_perfect_quiz', 500),
(4, 'Operador Híbrido', 'Completou com sucesso todas as presenças físicas em cursos presenciais.', 'badge_attendance_pro', 600);

SET FOREIGN_KEY_CHECKS = 1;
