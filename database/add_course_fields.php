<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

$logFile = __DIR__ . '/../public/migration_log.txt';
$logData = date('[Y-m-d H:i:s]') . " Iniciando migração...\n";

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    $logData .= "Conexão com o banco de dados OK\n";

    // 1. Verifica as colunas atuais da tabela courses
    $columnsStmt = $db->query("DESCRIBE `courses`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);
    $logData .= "Colunas encontradas em `courses`: " . implode(', ', $columns) . "\n";

    // 2. Adiciona what_learn se não existir
    if (!in_array('what_learn', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `what_learn` TEXT DEFAULT NULL");
        $logData .= "Coluna `what_learn` adicionada\n";
    }

    // 3. Adiciona materials_included se não existir
    if (!in_array('materials_included', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `materials_included` TEXT DEFAULT NULL");
        $logData .= "Coluna `materials_included` adicionada\n";
    }

    // 4. Adiciona access_type se não existir
    if (!in_array('access_type', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `access_type` VARCHAR(255) DEFAULT NULL");
        $logData .= "Coluna `access_type` adicionada\n";
    }

    // 5. Adiciona certificate_info se não existir
    if (!in_array('certificate_info', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `certificate_info` VARCHAR(255) DEFAULT NULL");
        $logData .= "Coluna `certificate_info` adicionada\n";
    }

    // 6. Adiciona bonus (campo EAD: lista de bônus inclusos)
    if (!in_array('bonus', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `bonus` TEXT DEFAULT NULL");
        $logData .= "Coluna `bonus` adicionada\n";
    }

    // 7. Adiciona target_audience (campo EAD: público-alvo do curso)
    if (!in_array('target_audience', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `target_audience` TEXT DEFAULT NULL");
        $logData .= "Coluna `target_audience` adicionada\n";
    }

    // 8. Adiciona is_private (0 = público, 1 = privado/em breve)
    if (!in_array('is_private', $columns)) {
        $db->exec("ALTER TABLE `courses` ADD COLUMN `is_private` TINYINT(1) NOT NULL DEFAULT 0");
        $logData .= "Coluna `is_private` adicionada\n";
    }

    // 6. Verifica as colunas da tabela transactions
    $transStmt = $db->query("DESCRIBE `transactions`");
    $transCols = $transStmt->fetchAll(\PDO::FETCH_COLUMN);
    $logData .= "Colunas encontradas em `transactions`: " . implode(', ', $transCols) . "\n";

    // 7. Adiciona payment_details se não existir
    if (!in_array('payment_details', $transCols)) {
        $db->exec("ALTER TABLE `transactions` ADD COLUMN `payment_details` LONGTEXT DEFAULT NULL");
        $logData .= "Coluna `payment_details` adicionada\n";
    }

    $logData .= "Migração concluída com SUCESSO!\n";

} catch (\Exception $e) {
    $logData .= "ERRO na migração: " . $e->getMessage() . "\n";
    error_log("Erro de migração de colunas adicionais em courses/transactions: " . $e->getMessage());
}

file_put_contents($logFile, $logData, FILE_APPEND);


