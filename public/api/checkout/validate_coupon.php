<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Config/AppConfig.php';

// Inicia sessão sem exigir login (visitantes podem aplicar cupons)
AppConfig::startSession();

$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
$courseId = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : 0;

if (empty($code) || !$courseId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Código de cupom ou ID do curso inválido.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca o curso para pegar o preço original
    $courseStmt = $db->prepare("SELECT price FROM courses WHERE id = :id AND status = 'active' LIMIT 1");
    $courseStmt->execute([':id' => $courseId]);
    $price = $courseStmt->fetchColumn();

    if ($price === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Treinamento não encontrado ou inativo.']);
        exit;
    }

    $price = (float)$price;

    // 2. Busca o cupom no banco de dados (deve estar ativo e não ter expirado)
    $couponStmt = $db->prepare("SELECT * FROM coupons WHERE code = :code AND status = 'active' AND expires_at >= CURDATE() LIMIT 1");
    $couponStmt->execute([':code' => $code]);
    $c = $couponStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$c) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cupom inválido ou expirado.']);
        exit;
    }

    $discount = 0.00;
    if ($c['type'] === 'fixed') {
        $discount = (float)$c['value'];
    } else {
        // Porcentagem
        $discount = $price * ((float)$c['value'] / 100);
    }

    echo json_encode([
        'success' => true,
        'code' => $c['code'],
        'type' => $c['type'],
        'value' => (float)$c['value'],
        'discount' => $discount,
        'final_price' => max(0.00, $price - $discount)
    ]);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao validar cupom: ' . $e->getMessage()]);
    exit;
}
