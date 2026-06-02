<?php
header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");

use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Config/AppConfig.php';

// Inicia sessão
AppConfig::startSession();

// Requer que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão não encontrada. Faça login novamente.']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$paymentId = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';

if (empty($paymentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do pagamento não informado.']);
    exit;
}

$dbInstance = Database::getInstance();
$db         = $dbInstance->getConnection();

// Statuses que indicam pagamento aprovado no Mercado Pago
$APPROVED_STATUSES = ['approved', 'success', 'paid'];

try {
    // 1. Busca a transação no banco de dados local
    $transStmt = $db->prepare("
        SELECT id, course_id, amount, status 
        FROM transactions 
        WHERE payment_id = :payment_id AND user_id = :user_id 
        LIMIT 1
    ");
    $transStmt->execute([
        ':payment_id' => $paymentId,
        ':user_id'    => $userId
    ]);
    $transaction = $transStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transação não localizada para este usuário.']);
        exit;
    }

    $localStatus = $transaction['status'];

    // 2. Se já está aprovado localmente, retorna aprovado imediatamente (sem consultar MP)
    if (in_array($localStatus, $APPROVED_STATUSES)) {
        echo json_encode([
            'success'      => true,
            'status'       => 'approved',
            'redirect_url' => 'confirmation.php?payment_id=' . urlencode($paymentId) . '&t=' . time()
        ]);
        exit;
    }

    // 3. Se ainda está pendente, consulta a API do Mercado Pago em tempo real
    $isMockPayment = (strpos($paymentId, 'MP-MOCK-') !== false);
    $mpStatus      = $localStatus; // valor padrão = status local

    if (!$isMockPayment) {
        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . urlencode($paymentId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . AppConfig::$MERCADO_PAGO_ACCESS_TOKEN
        ]);

        $responseJson = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && !empty($responseJson)) {
            $result   = json_decode($responseJson, true);
            $mpStatus = $result['status'] ?? $localStatus;
        } else {
            // Se a API do MP falhar, não interrompemos — retornamos o status local
            error_log("[GT check_payment] cURL falhou (HTTP {$httpCode}): {$curlError}");
        }
    }

    // 4. Mercado Pago confirmou aprovação — atualiza o banco e libera a matrícula
    if (in_array($mpStatus, $APPROVED_STATUSES) && !in_array($localStatus, $APPROVED_STATUSES)) {
        $db->beginTransaction();

        // Atualiza status da transação
        $updateStmt = $db->prepare("UPDATE transactions SET status = 'approved', updated_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $transaction['id']]);

        // Busca horário agendado salvo em enrollments (cursos híbridos)
        $scheduleStmt = $db->prepare("SELECT schedule_time FROM enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
        $scheduleStmt->execute([':uid' => $userId, ':cid' => $transaction['course_id']]);
        $existingEnroll = $scheduleStmt->fetch(\PDO::FETCH_ASSOC);
        $scheduleTime   = $existingEnroll['schedule_time'] ?? null;

        // Cria ou reativa a matrícula como ativa
        $enrollStmt = $db->prepare("
            INSERT INTO enrollments (user_id, course_id, schedule_time, status)
            VALUES (:user_id, :course_id, :schedule_time, 'active')
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $enrollStmt->execute([
            ':user_id'       => $userId,
            ':course_id'     => $transaction['course_id'],
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
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $db->commit();

        echo json_encode([
            'success'      => true,
            'status'       => 'approved',
            'redirect_url' => 'confirmation.php?payment_id=' . urlencode($paymentId) . '&t=' . time()
        ]);
        exit;
    }

    // 5. Ainda pendente — retorna status atual
    echo json_encode([
        'success' => true,
        'status'  => $mpStatus // 'pending', 'in_process', etc.
    ]);
    exit;

} catch (\PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[GT check_payment] PDOException: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao consultar pagamento.']);
    exit;
}
