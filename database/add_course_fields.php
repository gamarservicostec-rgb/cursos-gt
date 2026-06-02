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

    // 4. Adiciona access_type se não existir
    if (!in_array('access_type', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `access_type` VARCHAR(255) DEFAULT NULL");
    }

    // 5. Adiciona certificate_info se não existir
    if (!in_array('certificate_info', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `certificate_info` VARCHAR(255) DEFAULT NULL");
    }

    // 6. Adiciona bonus (campo EAD: lista de bônus inclusos)
    if (!in_array('bonus', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `bonus` TEXT DEFAULT NULL");
    }

    // 6. Verifica as colunas da tabela transactions
    $transStmt = $db->query("DESCRIBE `transactions`");
    $transCols = $transStmt->fetchAll(\PDO::FETCH_COLUMN);

    // 7. Adiciona payment_details se não existir
    if (!in_array('payment_details', $transCols)) {
        $db->exec("ALTER TABLE `transactions` ADD COLUMN `payment_details` LONGTEXT DEFAULT NULL");
    }

} catch (\Exception $e) {
    error_log("Erro de migração de colunas adicionais em courses/transactions: " . $e->getMessage());
}

