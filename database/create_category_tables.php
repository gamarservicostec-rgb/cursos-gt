<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    echo "<body style='background-color: #060608; color: #F5F5F7; font-family: sans-serif; padding: 30px;'>";
    echo "<div style='max-width: 700px; margin: 0 auto; background: #0E0E12; padding: 25px; border: 1px solid rgba(242,201,76,0.15); border-radius: 8px;'>";
    echo "<h2 style='color: #F2C94C; border-b: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;'>⚡ Migração: Módulo de Categorias</h2>";

    // 1. Cria a tabela categories
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
    echo "<p style='color: #00E676;'>✓ Tabela 'categories' criada ou verificada com sucesso.</p>";

    // 2. Insere categorias sementes se a tabela estiver vazia
    $countStmt = $db->query("SELECT COUNT(*) FROM `categories`");
    if ($countStmt->fetchColumn() == 0) {
        $insertSeeds = "
            INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES 
            ('Segurança de Elite', 'seguranca-de-elite', 1),
            ('Tecnologia & Dev', 'tecnologia-dev', 2),
            ('Negócios & Marketing', 'negocios-marketing', 3)
        ";
        $db->exec($insertSeeds);
        echo "<p style='color: #00E676;'>✓ Categorias iniciais (Segurança, Tecnologia, Negócios) semeadas com sucesso.</p>";
    }

    // 3. Adiciona a coluna category_id na tabela courses se ela não existir
    $columnsStmt = $db->query("DESCRIBE `courses`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

    if (!in_array('category_id', $columns)) {
        // Cria a coluna e o relacionamento
        $alterCourses = "
            ALTER TABLE `courses` 
            ADD COLUMN `category_id` INT DEFAULT NULL,
            ADD CONSTRAINT `fk_courses_category` 
            FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
            ON DELETE SET NULL;
        ";
        $db->exec($alterCourses);
        echo "<p style='color: #00E676;'>✓ Coluna 'category_id' e relacionamento FOREIGN KEY adicionados à tabela 'courses'.</p>";

        // Vincula temporariamente os cursos de semente aos IDs padrões
        // Curso 1 (Segurança de Elite) => Categoria 1 (Segurança de Elite)
        // Curso 2 (Cibersegurança) => Categoria 2 (Tecnologia & Dev)
        $db->exec("UPDATE `courses` SET `category_id` = 1 WHERE `id` = 1");
        $db->exec("UPDATE `courses` SET `category_id` = 2 WHERE `id` = 2");
        echo "<p style='color: #00E676;'>✓ Cursos de exemplo vinculados às respectivas categorias de forma automática.</p>";
    } else {
        echo "<p style='color: #8F8F9D;'>• Tabela 'courses' já possui o relacionamento com categorias.</p>";
    }

    echo "<h3 style='color: #00E676; margin-top: 20px;'>✓ Processo concluído com 100% de sucesso!</h3>";
    echo "<p><a href='../public/admin/index.php' style='color: #F2C94C; text-decoration: none; font-weight: bold;'>Voltar para a Dashboard Administrativa</a></p>";
    echo "</div>";
    echo "</body>";

} catch (\Exception $e) {
    echo "<p style='color: #FF1744; font-weight: bold;'>✗ Erro crítico na migração: " . $e->getMessage() . "</p>";
    echo "</div>";
    echo "</body>";
}
