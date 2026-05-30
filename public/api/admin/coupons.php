<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");

use Config\Database;
use Middleware\SecurityHeaders;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';
require_once __DIR__ . '/../../../src/Middleware/AuthMiddleware.php';

// Aplica cabeçalhos de segurança
\Middleware\SecurityHeaders::applyHeaders();

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

if ($method === 'GET') {
    // 1. LEITURA (GET)
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    try {
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM coupons WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $coupon = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($coupon) {
                echo json_encode(['success' => true, 'coupon' => $coupon]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Cupom não encontrado.']);
            }
        } else {
            $stmt = $db->prepare("SELECT * FROM coupons ORDER BY created_at DESC");
            $stmt->execute();
            $coupons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'coupons' => $coupons]);
        }
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao buscar cupons: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'POST') {
    // 2. CRIAÇÃO OU ATUALIZAÇÃO (POST)
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id = isset($input['id']) ? filter_var($input['id'], FILTER_VALIDATE_INT) : null;
    $code = strtoupper(trim($input['code'] ?? ''));
    $type = trim($input['type'] ?? 'percentage');
    $value = filter_var($input['value'] ?? 0, FILTER_VALIDATE_FLOAT);
    $description = trim($input['description'] ?? '');
    $status = trim($input['status'] ?? 'active');
    $expiresAt = trim($input['expires_at'] ?? '');

    if (empty($code) || !$value || empty($expiresAt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Preencha o código do cupom, desconto e data de expiração.']);
        exit;
    }

    if ($type !== 'fixed' && $type !== 'percentage') {
        $type = 'percentage';
    }
    if ($status !== 'active' && $status !== 'inactive') {
        $status = 'active';
    }

    try {
        if ($id) {
            // Edição
            $stmt = $db->prepare("
                UPDATE coupons 
                SET code = :code, type = :type, value = :value, description = :description, status = :status, expires_at = :expires_at 
                WHERE id = :id
            ");
            $stmt->execute([
                ':code' => $code,
                ':type' => $type,
                ':value' => $value,
                ':description' => $description,
                ':status' => $status,
                ':expires_at' => $expiresAt,
                ':id' => $id
            ]);

            // Auditoria
            $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'editar_cupom', :resource, :details)");
            $logStmt->execute([
                ':admin' => $_SESSION['user_id'],
                ':resource' => "coupons/{$id}",
                ':details' => "Cupom ID {$id} ({$code}) atualizado pelo administrador"
            ]);

            echo json_encode(['success' => true, 'message' => 'Cupom atualizado com sucesso no banco de dados.']);
        } else {
            // Criação - Verifica duplicado
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM coupons WHERE code = :code");
            $checkStmt->execute([':code' => $code]);
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Código de cupom já cadastrado no sistema.']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO coupons (code, type, value, description, status, expires_at) 
                VALUES (:code, :type, :value, :description, :status, :expires_at)
            ");
            $stmt->execute([
                ':code' => $code,
                ':type' => $type,
                ':value' => $value,
                ':description' => $description,
                ':status' => $status,
                ':expires_at' => $expiresAt
            ]);
            $newId = $db->lastInsertId();

            // Auditoria
            $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'criar_cupom', :resource, :details)");
            $logStmt->execute([
                ':admin' => $_SESSION['user_id'],
                ':resource' => "coupons/{$newId}",
                ':details' => "Cupom {$code} cadastrado no valor de {$value}"
            ]);

            echo json_encode(['success' => true, 'message' => 'Novo cupom cadastrado com sucesso.']);
        }
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao persistir cupom: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'DELETE') {
    // 3. EXCLUSÃO (DELETE)
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do cupom inválido para exclusão.']);
        exit;
    }

    try {
        // Busca código para auditoria
        $codeStmt = $db->prepare("SELECT code FROM coupons WHERE id = :id LIMIT 1");
        $codeStmt->execute([':id' => $id]);
        $code = $codeStmt->fetchColumn();

        if (!$code) {
            http_response_code(404);
            echo json_encode(['error' => 'Cupom não localizado para exclusão.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM coupons WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'excluir_cupom', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "coupons/{$id}",
            ':details' => "Cupom ID {$id} ({$code}) removido do sistema pelo administrador"
        ]);

        echo json_encode(['success' => true, 'message' => 'Cupom excluído com sucesso do banco de dados.']);
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao deletar cupom: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não suportado.']);
    exit;
}
