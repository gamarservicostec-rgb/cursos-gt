<?php
header("Content-Type: application/json");

use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../../../src/Config/Database.php';

// Inicia sessão
AppConfig::startSession();

// Requer que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado. Por favor, faça login.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$paymentId = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';

if (empty($paymentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do pagamento não informado.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca a transação no banco de dados local
    $transStmt = $db->prepare("SELECT id, course_id, amount, status FROM transactions WHERE payment_id = :payment_id AND user_id = :user_id LIMIT 1");
    $transStmt->execute([
        ':payment_id' => $paymentId,
        ':user_id' => $userId
    ]);
    $transaction = $transStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Transação não localizada para este usuário.']);
        exit;
    }

    $status = $transaction['status'];

    // 2. Se ainda estiver pendente no banco local, fazemos consulta de redundância na API do Mercado Pago
    $isMockPayment = (strpos($paymentId, 'MP-MOCK-') !== false);
    
    if ($status === 'pending' && !$isMockPayment) {
        // Consulta em tempo real na API do Mercado Pago para ver se o status já foi atualizado
        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . urlencode($paymentId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . AppConfig::$MERCADO_PAGO_ACCESS_TOKEN
        ]);

        $responseJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($responseJson, true);
            $mpStatus = $result['status'] ?? 'pending';

            if ($mpStatus === 'approved') {
                // Pagamento aprovado no Mercado Pago! Atualizamos o banco de dados local de imediato
                $db->beginTransaction();

                // Atualiza status da transação
                $updateStmt = $db->prepare("UPDATE transactions SET status = 'approved', updated_at = NOW() WHERE id = :id");
                $updateStmt->execute([':id' => $transaction['id']]);

                // Busca o horário agendado temporário salvo em enrollments (se houver)
                $scheduleStmt = $db->prepare("SELECT schedule_time FROM enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
                $scheduleStmt->execute([
                    ':uid' => $userId,
                    ':cid' => $transaction['course_id']
                ]);
                $existingEnroll = $scheduleStmt->fetch();
                $scheduleTime = $existingEnroll['schedule_time'] ?? null;

                // Cria ou reativa a matrícula como ativa
                $enrollStmt = $db->prepare("
                    INSERT INTO enrollments (user_id, course_id, schedule_time, status) 
                    VALUES (:user_id, :course_id, :schedule_time, 'active')
                    ON DUPLICATE KEY UPDATE status = 'active'
                ");
                $enrollStmt->execute([
                    ':user_id' => $userId,
                    ':course_id' => $transaction['course_id'],
                    ':schedule_time' => $scheduleTime
                ]);

                // Log de auditoria
                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (user_id, action, details, ip_address) 
                    VALUES (:user_id, 'compra_aprovada_polling', :details, :ip)
                ");
                $auditStmt->execute([
                    ':user_id' => $userId,
                    ':details' => "Matrícula liberada via Polling/Redundância para curso {$transaction['course_id']} (MP ID: {$paymentId})",
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $db->commit();
                $status = 'approved';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
    exit;

} catch (\PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno ao consultar pagamento: ' . $e->getMessage()]);
    exit;
}
