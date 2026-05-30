<?php
require_once __DIR__ . '/../src/Config/Database.php';

use Config\Database;

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Atualiza a thumbnail do Curso 1 (Masterclass em Segurança de Elite)
    $stmt1 = $db->prepare("
        UPDATE courses 
        SET thumbnail_url = 'https://lh3.googleusercontent.com/aida/ADBb0ugYyV00785rqmTCwFdYvys8iMx-EanneM0RESAMAIrGp-thspn-vuvZoTZ2-NlTgaH7iIcIbyDcm-G5yJFiI8b4Y0uorocfqei2m6VQ5I4TlIhUNi1C9MhWXWmPThcn5bzwez-l-hDaLpo6eI15kP_tQ4Qx5XXjtAYZq7RWCZRNXrENwuyfpFgQ8f0fPE9VVBa6aT4fc3k-tZX4dF3zFhzAy4ZKIL0bfaH4-cKfLFQoo96NW31JZ4rLF4eb' 
        WHERE id = 1
    ");
    $stmt1->execute();

    // Atualiza a thumbnail do Curso 2 (Invasão Hacker e Resposta a Incidentes)
    $stmt2 = $db->prepare("
        UPDATE courses 
        SET thumbnail_url = 'https://lh3.googleusercontent.com/aida-public/AB6AXuB4rUJtEl-pmcUf02iRkZBkv862lA7agbAMSskfoMWswqrznYnpdciCLhUf1s3PUZ3TKAMXHbLM5PGUpOjFOFuzUbPuO-PegqFa6bvTlBoBMqua-rOayafX5VzJF72k1N5_y96I6m7V7S8xjMH6t71v1NF5KVUyxYfx9EIQCkhtcxjj6U_SspQSaTZt8DprNHB1oM6ehdTjHuaMCc7fHPXEgJYx3OnCtFbfRw-7WSj-yfFfzPFLb6d7nXQFAD91xFF-tDr2uI7gSD-Y' 
        WHERE id = 2
    ");
    $stmt2->execute();

    echo "Banco de dados atualizado com as thumbnails do Stitch com sucesso!\n";
} catch (\Exception $e) {
    echo "Erro ao atualizar banco de dados: " . $e->getMessage() . "\n";
}
