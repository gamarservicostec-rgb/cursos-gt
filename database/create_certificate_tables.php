<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // 1. Cria a tabela certificate_templates se não existir com todas as colunas
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `certificate_templates` (
            `course_id` INT NOT NULL PRIMARY KEY,
            `student_name_x` INT NOT NULL DEFAULT 100,
            `student_name_y` INT NOT NULL DEFAULT 180,
            `student_name_size` INT NOT NULL DEFAULT 26,
            `student_name_color` VARCHAR(7) NOT NULL DEFAULT '#F5F5F7',
            `course_title_x` INT NOT NULL DEFAULT 100,
            `course_title_y` INT NOT NULL DEFAULT 240,
            `course_title_size` INT NOT NULL DEFAULT 20,
            `course_title_color` VARCHAR(7) NOT NULL DEFAULT '#f2c94c',
            `date_x` INT NOT NULL DEFAULT 100,
            `date_y` INT NOT NULL DEFAULT 300,
            `date_size` INT NOT NULL DEFAULT 12,
            `date_color` VARCHAR(7) NOT NULL DEFAULT '#8F8F9D',
            `code_x` INT NOT NULL DEFAULT 100,
            `code_y` INT NOT NULL DEFAULT 350,
            `code_size` INT NOT NULL DEFAULT 10,
            `code_color` VARCHAR(7) NOT NULL DEFAULT '#8F8F9D',
            `background_url` LONGTEXT DEFAULT NULL,
            `logo_url` LONGTEXT DEFAULT NULL,
            `logo_x` INT NOT NULL DEFAULT 50,
            `logo_y` INT NOT NULL DEFAULT 50,
            `logo_w` INT NOT NULL DEFAULT 80,
            `logo_h` INT NOT NULL DEFAULT 80,
            `signature_url` LONGTEXT DEFAULT NULL,
            `signature_x` INT NOT NULL DEFAULT 450,
            `signature_y` INT NOT NULL DEFAULT 350,
            `signature_w` INT NOT NULL DEFAULT 120,
            `signature_h` INT NOT NULL DEFAULT 60,
            `custom_text` VARCHAR(255) DEFAULT NULL,
            `custom_text_x` INT NOT NULL DEFAULT 100,
            `custom_text_y` INT NOT NULL DEFAULT 120,
            `custom_text_size` INT NOT NULL DEFAULT 16,
            `custom_text_color` VARCHAR(7) NOT NULL DEFAULT '#F5F5F7',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $db->exec($createTableSQL);

    // 1.5. Rotina de verificação estrutural (migrações dinâmicas para bases já existentes)
    $columnsStmt = $db->query("DESCRIBE `certificate_templates`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

    // Altera background_url para LONGTEXT caso ainda seja VARCHAR
    $db->exec("ALTER TABLE `certificate_templates` MODIFY COLUMN `background_url` LONGTEXT DEFAULT NULL");

    $newCols = [
        'student_name_x' => "INT NOT NULL DEFAULT 100",
        'student_name_y' => "INT NOT NULL DEFAULT 180",
        'student_name_size' => "INT NOT NULL DEFAULT 26",
        'student_name_color' => "VARCHAR(7) NOT NULL DEFAULT '#F5F5F7'",
        'student_name_font' => "VARCHAR(50) NOT NULL DEFAULT 'Clash Display'",
        'student_name_bold' => "TINYINT(1) NOT NULL DEFAULT 1",
        'student_name_italic' => "TINYINT(1) NOT NULL DEFAULT 0",
        
        'course_title_x' => "INT NOT NULL DEFAULT 100",
        'course_title_y' => "INT NOT NULL DEFAULT 240",
        'course_title_size' => "INT NOT NULL DEFAULT 20",
        'course_title_color' => "VARCHAR(7) NOT NULL DEFAULT '#f2c94c'",
        'course_title_font' => "VARCHAR(50) NOT NULL DEFAULT 'Clash Display'",
        'course_title_bold' => "TINYINT(1) NOT NULL DEFAULT 1",
        'course_title_italic' => "TINYINT(1) NOT NULL DEFAULT 0",
        
        'date_x' => "INT NOT NULL DEFAULT 100",
        'date_y' => "INT NOT NULL DEFAULT 300",
        'date_size' => "INT NOT NULL DEFAULT 12",
        'date_color' => "VARCHAR(7) NOT NULL DEFAULT '#8F8F9D'",
        'date_font' => "VARCHAR(50) NOT NULL DEFAULT 'Satoshi'",
        'date_bold' => "TINYINT(1) NOT NULL DEFAULT 1",
        'date_italic' => "TINYINT(1) NOT NULL DEFAULT 0",
        
        'code_x' => "INT NOT NULL DEFAULT 100",
        'code_y' => "INT NOT NULL DEFAULT 350",
        'code_size' => "INT NOT NULL DEFAULT 10",
        'code_color' => "VARCHAR(7) NOT NULL DEFAULT '#8F8F9D'",
        'code_font' => "VARCHAR(50) NOT NULL DEFAULT 'Satoshi'",
        'code_bold' => "TINYINT(1) NOT NULL DEFAULT 1",
        'code_italic' => "TINYINT(1) NOT NULL DEFAULT 0",

        'logo_url' => "LONGTEXT DEFAULT NULL",
        'logo_x' => "INT NOT NULL DEFAULT 50",
        'logo_y' => "INT NOT NULL DEFAULT 50",
        'logo_w' => "INT NOT NULL DEFAULT 80",
        'logo_h' => "INT NOT NULL DEFAULT 80",

        'signature_url' => "LONGTEXT DEFAULT NULL",
        'signature_x' => "INT NOT NULL DEFAULT 450",
        'signature_y' => "INT NOT NULL DEFAULT 350",
        'signature_w' => "INT NOT NULL DEFAULT 120",
        'signature_h' => "INT NOT NULL DEFAULT 60",

        'custom_text' => "VARCHAR(255) DEFAULT NULL",
        'custom_text_x' => "INT NOT NULL DEFAULT 100",
        'custom_text_y' => "INT NOT NULL DEFAULT 120",
        'custom_text_size' => "INT NOT NULL DEFAULT 16",
        'custom_text_color' => "VARCHAR(7) NOT NULL DEFAULT '#F5F5F7'",
        'custom_text_font' => "VARCHAR(50) NOT NULL DEFAULT 'Clash Display'",
        'custom_text_bold' => "TINYINT(1) NOT NULL DEFAULT 1",
        'custom_text_italic' => "TINYINT(1) NOT NULL DEFAULT 0"
    ];

    foreach ($newCols as $colName => $colDef) {
        if (!in_array($colName, $columns)) {
            $db->exec("ALTER TABLE `certificate_templates` ADD COLUMN `{$colName}` {$colDef}");
        }
    }

    // 2. Insere templates padrões para os cursos existentes se não existirem
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM certificate_templates WHERE course_id = :id");
    
    // Curso 1
    $checkStmt->execute([':id' => 1]);
    if ($checkStmt->fetchColumn() == 0) {
        $insertQuery = "
            INSERT INTO `certificate_templates` 
            (course_id, student_name_x, student_name_y, student_name_size, student_name_color, 
             course_title_x, course_title_y, course_title_size, course_title_color, 
             date_x, date_y, date_size, date_color, 
             code_x, code_y, code_size, code_color) 
            VALUES (1, 100, 180, 26, '#F5F5F7', 100, 240, 20, '#f2c94c', 100, 300, 12, '#8F8F9D', 100, 350, 10, '#8F8F9D')
        ";
        $db->exec($insertQuery);
    }

    // Curso 2
    $checkStmt->execute([':id' => 2]);
    if ($checkStmt->fetchColumn() == 0) {
        $insertQuery = "
            INSERT INTO `certificate_templates` 
            (course_id, student_name_x, student_name_y, student_name_size, student_name_color, 
             course_title_x, course_title_y, course_title_size, course_title_color, 
             date_x, date_y, date_size, date_color, 
             code_x, code_y, code_size, code_color) 
            VALUES (2, 100, 180, 26, '#F5F5F7', 100, 240, 20, '#f2c94c', 100, 300, 12, '#8F8F9D', 100, 350, 10, '#8F8F9D')
        ";
        $db->exec($insertQuery);
    }

} catch (\Exception $e) {
    // Falha silenciosa ou log para auditoria de erros
    error_log("Erro de migração de certificados: " . $e->getMessage());
}
