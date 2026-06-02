<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

echo "<h1>Testando Migração e Colunas do Banco de Dados</h1>";

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    
    echo "<p>Conexão com o banco estabelecida com sucesso!</p>";
    
    // Testar DESCRIBE courses
    $columnsStmt = $db->query("DESCRIBE `courses`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "<h2>Colunas atuais da tabela `courses`:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<h2>Tentando executar a migração diretamente...</h2>";
    
    // Executar os comandos de ALTER TABLE individualmente para capturar o erro exato
    $fields = [
        'what_learn' => "ALTER TABLE `courses` ADD COLUMN `what_learn` TEXT DEFAULT NULL",
        'materials_included' => "ALTER TABLE `courses` ADD COLUMN `materials_included` TEXT DEFAULT NULL",
        'access_type' => "ALTER TABLE `courses` ADD COLUMN `access_type` VARCHAR(255) DEFAULT NULL",
        'certificate_info' => "ALTER TABLE `courses` ADD COLUMN `certificate_info` VARCHAR(255) DEFAULT NULL",
        'bonus' => "ALTER TABLE `courses` ADD COLUMN `bonus` TEXT DEFAULT NULL",
        'target_audience' => "ALTER TABLE `courses` ADD COLUMN `target_audience` TEXT DEFAULT NULL",
        'is_private' => "ALTER TABLE `courses` ADD COLUMN `is_private` TINYINT(1) NOT NULL DEFAULT 0"
    ];
    
    $existingCols = array_column($columns, 'Field');
    
    foreach ($fields as $colName => $sql) {
        if (!in_array($colName, $existingCols)) {
            echo "<p>Tentando adicionar coluna `$colName`...</p>";
            try {
                $db->exec($sql);
                echo "<p style='color: green;'>Coluna `$colName` adicionada com sucesso!</p>";
            } catch (\Exception $ex) {
                echo "<p style='color: red;'>Erro ao adicionar coluna `$colName`: " . $ex->getMessage() . "</p>";
            }
        } else {
            echo "<p>Coluna `$colName` já existe.</p>";
        }
    }
    
} catch (\Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Erro Geral: " . $e->getMessage() . "</p>";
}
