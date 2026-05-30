<?php
/**
 * Script de migração e seeds autônomos para tabelas de Quizzes e Gamificação.
 * Executado de forma transparente nas requisições do painel administrativo.
 */

namespace Database;

use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // 1. Criação da Tabela de Quizzes
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

    // 2. Criação da Tabela de Questões
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

    // 3. Criação da Tabela de Opções
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

    // 4. Criação da Tabela de Tentativas de Prova
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

    // 5. Criação da Tabela de Conquistas / Medalhas
    $db->exec("
        CREATE TABLE IF NOT EXISTS `achievements` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `title` VARCHAR(100) NOT NULL,
          `description` VARCHAR(255) NOT NULL,
          `icon_url` VARCHAR(255) DEFAULT NULL,
          `xp_bonus` INT DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Criação da Tabela de Conquistas Desbloqueadas
    $db->exec("
        CREATE TABLE IF NOT EXISTS `user_achievements` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `achievement_id` INT NOT NULL,
          `unlocked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE,
          UNIQUE KEY `uq_user_achievement` (`user_id`, `achievement_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Sementes Iniciais para Medalhas (achievements)
    $achievCount = $db->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
    if ($achievCount == 0) {
        $db->exec("
            INSERT INTO `achievements` (`id`, `title`, `description`, `icon_url`, `xp_bonus`) VALUES
            (1, 'Primeiro Passo', 'Concluiu a primeira aula na plataforma GT Cursos.', 'badge_first_lesson', 100),
            (2, 'Sobrevivente', 'Alcançou um streak de 7 dias de atividades consecutivas.', 'badge_streak_7', 250),
            (3, 'Cérebro de Elite', 'Foi aprovado em qualquer prova com pontuação perfeita de 100%.', 'badge_perfect_quiz', 500),
            (4, 'Operador Híbrido', 'Completou com sucesso todas as presenças físicas em cursos presenciais.', 'badge_attendance_pro', 600);
        ");
    }

    // 8. Sementes Iniciais para Quizzes (Curso 1 - Masterclass em Segurança de Elite)
    $quizCount = $db->query("SELECT COUNT(*) FROM quizzes WHERE course_id = 1")->fetchColumn();
    if ($quizCount == 0) {
        // Verifica se o curso 1 existe antes de inserir
        $course1Exists = $db->query("SELECT COUNT(*) FROM courses WHERE id = 1")->fetchColumn();
        if ($course1Exists) {
            // Insere o Quiz
            $db->exec("
                INSERT INTO `quizzes` (`id`, `course_id`, `module_id`, `title`, `min_score`) VALUES
                (1, 1, 1, 'Avaliação de Elite: Protocolos Operacionais', 70);
            ");

            // Pergunta 1
            $db->exec("
                INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `type`) VALUES
                (1, 1, 'Qual o principal objetivo da Análise de Risco Perimetral em segurança de alta complexidade?', 'unica_escolha');
            ");
            $db->exec("
                INSERT INTO `question_options` (`question_id`, `option_text`, `is_correct`) VALUES
                (1, 'Identificar ameaças e vulnerabilidades antes que elas acessem o perímetro protegido.', 1),
                (1, 'Apenas registrar a entrada de visitantes no portal principal.', 0),
                (1, 'Instalar alarmes de baixo custo para economizar recursos.', 0),
                (1, 'Limitar o acesso físico apenas a diretores da instituição.', 0);
            ");

            // Pergunta 2
            $db->exec("
                INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `type`) VALUES
                (2, 1, 'Quais dispositivos são considerados mais eficientes para vigilância tática ativa em perímetros escuros?', 'unica_escolha');
            ");
            $db->exec("
                INSERT INTO `question_options` (`question_id`, `option_text`, `is_correct`) VALUES
                (2, 'Sensores termails acoplados a câmeras com inteligência artificial.', 1),
                (2, 'Câmeras analógicas comuns de baixa resolução.', 0),
                (2, 'Apenas holofotes manuais ativados por operadores.', 0),
                (2, 'Sensores de movimento magnéticos simples.', 0);
            ");

            // Pergunta 3
            $db->exec("
                INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `type`) VALUES
                (3, 1, 'Qual protocolo deve ser acionado imediatamente após a detecção de uma intrusão física confirmada?', 'unica_escolha');
            ");
            $db->exec("
                INSERT INTO `question_options` (`question_id`, `option_text`, `is_correct`) VALUES
                (3, 'Contenção periférica tática e isolamento do setor comprometido.', 1),
                (3, 'Envio de e-mail informativo para a diretoria.', 0),
                (3, 'Desligamento geral de toda a energia elétrica do local.', 0),
                (3, 'Ignorar o evento se o alarme secundário não disparar.', 0);
            ");
        }
    }

} catch (\PDOException $e) {
    // Falha silenciosa ou log nos erros de produção
    error_log("Erro de migração de quizzes: " . $e->getMessage());
}
