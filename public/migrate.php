<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Config/AppConfig.php';

// Inicia sessão segura
AppConfig::startSession();

// Layout Obsidian Gold
echo "<!DOCTYPE html>
<html class='dark' lang='pt-BR'>
<head>
    <meta charset='utf-8'>
    <title>Terminal de Migração Tática — Cursos GT</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&family=Plus+Jakarta+Sans:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #060608;
            color: #F5F5F7;
        }
        h1, h2 {
            font-family: 'Clash Display', sans-serif;
        }
        .glass-card {
            background: rgba(14, 14, 18, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 201, 76, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);
        }
    </style>
</head>
<body class='min-h-screen flex items-center justify-center p-6 bg-cover bg-center' style=\"background-image: linear-gradient(rgba(6, 6, 8, 0.96), rgba(6, 6, 8, 0.98)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop');\">
    <div class='w-full max-w-2xl glass-card rounded-2xl p-8 md:p-10'>
        <div class='border-b border-white/5 pb-6 mb-6 flex items-center gap-4'>
            <div class='h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary'>
                <span class='material-symbols-outlined text-2xl text-yellow-500 font-bold'>terminal</span>
            </div>
            <div>
                <h1 class='text-2xl font-bold uppercase tracking-wider text-white'>Terminal de Sincronização</h1>
                <p class='text-xs text-slate-400 uppercase font-semibold tracking-widest mt-1'>Módulos Pedagógicos e Estruturais GT Cursos</p>
            </div>
        </div>

        <div class='space-y-4 text-sm leading-relaxed mb-8' id='migrationOutput'>";

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // 1. Executa a migração de categorias
    echo "<div class='p-4 bg-white/[0.02] border border-white/5 rounded-xl space-y-2'>";
    echo "<h3 class='text-xs font-bold text-yellow-500 uppercase tracking-wider flex items-center gap-2'>⚡ Sincronizando Tabela de Categorias...</h3>";
    
    // Tabela categories
    $createCategoriesTable = "
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) UNIQUE NOT NULL,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($createCategoriesTable);
    echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Tabela 'categories' criada ou verificada com sucesso.</p>";

    // Inserção das sementes
    $countStmt = $db->query("SELECT COUNT(*) FROM `categories`");
    if ($countStmt->fetchColumn() == 0) {
        $insertSeeds = "
            INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES 
            ('Segurança de Elite', 'seguranca-de-elite', 1),
            ('Tecnologia & Dev', 'tecnologia-dev', 2),
            ('Negócios & Marketing', 'negocios-marketing', 3)
        ";
        $db->exec($insertSeeds);
        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Categorias padrão (Segurança de Elite, Tecnologia, Negócios) importadas.</p>";
    }

    // Coluna category_id na tabela de cursos
    $columnsStmt = $db->query("DESCRIBE `courses`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

    if (!in_array('category_id', $columns)) {
        $alterCourses = "
            ALTER TABLE `courses` 
            ADD COLUMN `category_id` INT DEFAULT NULL,
            ADD CONSTRAINT `fk_courses_category` 
            FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
            ON DELETE SET NULL;
        ";
        $db->exec($alterCourses);
        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Coluna 'category_id' e relacionamento FOREIGN KEY adicionados à tabela 'courses'.</p>";

        // Vincula cursos iniciais aos IDs criados
        $db->exec("UPDATE `courses` SET `category_id` = 1 WHERE `id` = 1");
        $db->exec("UPDATE `courses` SET `category_id` = 2 WHERE `id` = 2");
        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Cursos de exemplo vinculados automaticamente.</p>";
    } else {
        echo "<p class='text-xs text-slate-400 ml-4'>• Relacionamento 'category_id' em 'courses' já existe.</p>";
    }
    echo "</div>";

    // 2. Executa a migração de quizzes/gamificação
    echo "<div class='p-4 bg-white/[0.02] border border-white/5 rounded-xl space-y-2 mt-4'>";
    echo "<h3 class='text-xs font-bold text-yellow-500 uppercase tracking-wider flex items-center gap-2'>⚡ Sincronizando Sistema de Avaliações Técnicas...</h3>";
    
    // Tabela quizzes
    $createQuizzesTable = "
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
    ";
    $db->exec($createQuizzesTable);
    
    // Tabela questions
    $createQuestionsTable = "
        CREATE TABLE IF NOT EXISTS `questions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `quiz_id` INT NOT NULL,
            `question_text` TEXT NOT NULL,
            `type` ENUM('dissertativa', 'unica_escolha', 'multipla_escolha') DEFAULT 'unica_escolha',
            FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
            INDEX `idx_questions_quiz` (`quiz_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($createQuestionsTable);

    // Tabela question_options
    $createOptionsTable = "
        CREATE TABLE IF NOT EXISTS `question_options` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `question_id` INT NOT NULL,
            `option_text` TEXT NOT NULL,
            `is_correct` TINYINT(1) DEFAULT 0,
            FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
            INDEX `idx_options_question` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($createOptionsTable);

    // Tabela quiz_attempts
    $createAttemptsTable = "
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
    ";
    $db->exec($createAttemptsTable);

    echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Tabelas de Quizzes, Perguntas, Opções e Histórico de Tentativas sincronizadas.</p>";
    echo "</div>";

    // 3. Executa a migração de certificados
    echo "<div class='p-4 bg-white/[0.02] border border-white/5 rounded-xl space-y-2 mt-4'>";
    echo "<h3 class='text-xs font-bold text-yellow-500 uppercase tracking-wider flex items-center gap-2'>⚡ Sincronizando Sistema de Certificação HD...</h3>";
    
    // Tabela certificate_templates
    $createTemplatesTable = "
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
    ";
    $db->exec($createTemplatesTable);

    // Tabela certificates
    $createCertificatesTable = "
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
    ";
    $db->exec($createCertificatesTable);

    echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Tabelas de Modelos e Códigos de Verificação de Certificados preparadas.</p>";
    echo "</div>";

    // Gravar log de auditoria técnica
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, 'sincronizacao_banco_completa', 'Migração de tabelas de categorias, quizzes e certificados disparada com sucesso', :ip)");
    $logStmt->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

    echo "<h2 class='text-lg font-bold text-emerald-400 uppercase mt-8 border-t border-white/5 pt-6 flex items-center gap-2'>
            <span class='w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping'></span>
            Sincronização Concluída com 100% de Sucesso!
          </h2>
          <p class='text-xs text-slate-400 mt-2'>Toda a infraestrutura relacional está pronta e em perfeito funcionamento.</p>";

} catch (\Exception $e) {
    echo "<div class='p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl space-y-2 mt-4'>";
    echo "<h3 class='text-xs font-bold uppercase tracking-wider flex items-center gap-2'>✗ Erro Crítico de Banco de Dados</h3>";
    echo "<p class='text-xs font-medium ml-4'>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</div>
        
        <div class='flex justify-end gap-4 border-t border-white/5 pt-6'>
            <a href='admin/index.php' class='px-6 py-3 rounded-lg bg-primary text-deep-obsidian font-bold text-xs uppercase tracking-widest shadow-[0_0_20px_rgba(241,200,75,0.2)] hover:bg-yellow-500 transition-all'>Entrar no Painel</a>
            <a href='index.php' class='px-6 py-3 rounded-lg border border-white/10 text-slate-300 font-bold text-xs uppercase tracking-widest hover:bg-white/5 transition-all'>Ir para a Home</a>
        </div>
    </div>
</body>
</html>";
