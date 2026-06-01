<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // 1. Verifica as colunas atuais da tabela courses
    $columnsStmt = $db->query("DESCRIBE `courses`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

    // 2. Adiciona what_learn se não existir
    if (!in_array('what_learn', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `what_learn` TEXT DEFAULT NULL");
    }

    // 3. Adiciona materials_included se não existir
    if (!in_array('materials_included', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `materials_included` TEXT DEFAULT NULL");
    }

} catch (\Exception $e) {
    error_log("Erro de migração de colunas adicionais em courses: " . $e->getMessage());
}
