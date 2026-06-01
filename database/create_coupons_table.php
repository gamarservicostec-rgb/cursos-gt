<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // 1. Cria a tabela coupons se não existir
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `coupons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) UNIQUE NOT NULL,
            `type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'percentage',
            `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `description` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `usage_count` INT NOT NULL DEFAULT 0,
            `expires_at` DATE NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_coupons_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $db->exec($createTableSQL);

    // 2. A inserção automática de cupons de demonstração foi desativada permanentemente
    // para que cupons excluídos pelo administrador não retornem ao atualizar a página.

} catch (\Exception $e) {
    error_log("Erro de migração da tabela de cupons: " . $e->getMessage());
}
