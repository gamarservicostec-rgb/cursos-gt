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

    // 2. Insere cupons padrões para demonstração se a tabela estiver vazia
    $countStmt = $db->query("SELECT COUNT(*) FROM `coupons`");
    if ($countStmt->fetchColumn() == 0) {
        $insertQuery = "
            INSERT INTO `coupons` (`code`, `type`, `value`, `description`, `status`, `usage_count`, `expires_at`) VALUES 
            ('PROMO500', 'fixed', 500.00, 'Desconto fixo de 500 reais em qualquer masterclass', 'active', 18, '2026-12-31'),
            ('ELITE30', 'percentage', 30.00, '30% de desconto tático para novos alunos de elite', 'active', 42, '2026-08-30'),
            ('BLACKFRIDAY', 'percentage', 50.00, 'Metade do preço em toda a plataforma GT Cursos', 'inactive', 120, '2025-11-30')
        ";
        $db->exec($insertQuery);
    }

} catch (\Exception $e) {
    error_log("Erro de migração da tabela de cupons: " . $e->getMessage());
}
