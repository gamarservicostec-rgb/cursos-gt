<?php
header("Content-Type: application/json");

use Config\Database;
use Config\AppConfig;
use Helpers\EmailSender;
use Helpers\WhatsAppSender;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Helpers/EmailSender.php';
require_once __DIR__ . '/../../../src/Helpers/WhatsAppSender.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

// ============================================================
// VALIDAÇÃO DE ASSINATURA HMAC-SHA256 DO MERCADO PAGO
// Previne que hackers enviem notificações falsas para liberar
// matrículas sem pagar. Esta é a linha de defesa principal.
// ============================================================

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);

$webhookSecret = AppConfig::$MERCADO_PAGO_WEBHOOK_SECRET;
$isMockSecret  = empty($webhookSecret) || $webhookSecret === 'COLE_SEU_SEGREDO_AQUI';
$isMockToken   = strpos(AppConfig::$MERCADO_PAGO_ACCESS_TOKEN, 'mock') !== false;

// Só valida assinatura quando o segredo real estiver configurado
if (!$isMockSecret && !$isMockToken) {
    $xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $dataId     = $input['data']['id'] ?? ($_GET['data_id'] ?? '');

    // Monta a string de assinatura conforme documentação oficial do Mercado Pago
    // Formato: id:[data.id];request-id:[x-request-id];ts:[ts];
    $ts = '';
    $signatureParts = explode(',', $xSignature);
    $receivedHash = '';

    foreach ($signatureParts as $part) {
        $part = trim($part);
        if (str_starts_with($part, 'ts=')) {
            $ts = substr($part, 3);
        } elseif (str_starts_with($part, 'v1=')) {
            $receivedHash = substr($part, 3);
        }
    }

    $signatureTemplate = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
    $expectedHash = hash_hmac('sha256', $signatureTemplate, $webhookSecret);

    if (!hash_equals($expectedHash, $receivedHash)) {
        http_response_code(401);
        echo json_encode(['error' => 'Assinatura inválida. Notificação rejeitada por segurança.']);
        exit;
    }
}

// ============================================================
// PROCESSAMENTO DO PAGAMENTO
// ============================================================

$paymentId = null;
$action    = $input['action'] ?? '';

if (isset($input['data']['id'])) {
    $paymentId = $input['data']['id'];
} elseif (isset($_GET['id'])) {
    $paymentId = $_GET['id'];
} elseif (isset($_GET['data_id'])) {
    $paymentId = $_GET['data_id'];
}

if (!$paymentId) {
    // Retorna 200 OK para o Mercado Pago não reenviar a notificação
    http_response_code(200);
    echo json_encode(['message' => 'Notificação recebida, mas sem ID de pagamento válido para processar.']);
    exit;
}

$dbInstance = Database::getInstance();
$db         = $dbInstance->getConnection();

try {
    // Busca a transação no banco pelo ID do pagamento
    $transStmt = $db->prepare("SELECT id, user_id, course_id, amount, status FROM transactions WHERE payment_id = :payment_id LIMIT 1");
    $transStmt->execute([':payment_id' => $paymentId]);
    $transaction = $transStmt->fetch();

    // Verifica se é simulação de teste local
    $isMockPayment = (strpos($paymentId, 'MP-MOCK-') !== false);
    $status        = 'pending';

    if ($transaction) {
        $userId        = $transaction['user_id'];
        $courseId      = $transaction['course_id'];
        $currentStatus = $transaction['status'];

        if ($isMockPayment) {
            // Simulação: aceita status via query string para testes
            $status = $_GET['mock_status'] ?? 'approved';
        } else {
            // Consulta o status real do pagamento direto na API do Mercado Pago
            $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . AppConfig::$MERCADO_PAGO_ACCESS_TOKEN
            ]);

            $responseJson = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($responseJson, true);
                $status = $result['status'] ?? 'pending';
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Não foi possível consultar a API Mercado Pago para o ID ' . $paymentId]);
                exit;
            }
        }

        // Só processa se o status mudou para evitar operações duplicadas
        if ($status !== $currentStatus) {
            $db->beginTransaction();

            // Atualiza o status da transação
            $updateTransStmt = $db->prepare("UPDATE transactions SET status = :status, updated_at = NOW() WHERE id = :id");
            $updateTransStmt->execute([':status' => $status, ':id' => $transaction['id']]);

            // Se foi APROVADO: libera acesso do aluno ao curso
            if ($status === 'approved') {

                // Busca o horário agendado que o aluno escolheu no checkout (para cursos híbridos)
                $scheduleStmt = $db->prepare("SELECT schedule_time FROM enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
                $scheduleStmt->execute([':uid' => $userId, ':cid' => $courseId]);
                $existingEnroll = $scheduleStmt->fetch();
                $scheduleTime = $existingEnroll['schedule_time'] ?? null;

                // Cria ou reativa a matrícula do aluno
                $enrollStmt = $db->prepare("
                    INSERT INTO enrollments (user_id, course_id, schedule_time, status) 
                    VALUES (:user_id, :course_id, :schedule_time, 'active')
                    ON DUPLICATE KEY UPDATE status = 'active'
                ");
                $enrollStmt->execute([
                    ':user_id'       => $userId,
                    ':course_id'     => $courseId,
                    ':schedule_time' => $scheduleTime
                ]);

                // Grava log de auditoria
                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (user_id, action, details, ip_address) 
                    VALUES (:user_id, 'compra_aprovada_webhook', :details, :ip)
                ");
                $auditStmt->execute([
                    ':user_id' => $userId,
                    ':details' => "Matrícula liberada via Webhook para curso {$courseId} (MP ID: {$paymentId})",
                    ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $db->commit();

                // -------------------------------------------------------
                // DISPARO AUTOMÁTICO: E-MAIL + WHATSAPP
                // -------------------------------------------------------
                $userStmt = $db->prepare("SELECT name, email FROM users WHERE id = :id LIMIT 1");
                $userStmt->execute([':id' => $userId]);
                $user = $userStmt->fetch();

                $cStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
                $cStmt->execute([':id' => $courseId]);
                $courseTitle = $cStmt->fetchColumn();

                if ($user) {
                    // E-mail de confirmação
                    $emailContent = "
                        Olá, <strong>" . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "</strong>!<br><br>
                        Seu pagamento de <strong>R$ " . number_format($transaction['amount'], 2, ',', '.') . "</strong> 
                        para o curso <strong>" . htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') . "</strong> 
                        foi confirmado com sucesso!<br><br>
                        Sua matrícula está ativa e seu acesso liberado imediatamente.<br>
                        Clique abaixo para iniciar seus estudos.
                    ";
                    
                    $bodyHtml = \Helpers\EmailSender::getTemplateHtml(
                        "Matrícula Confirmada!",
                        $emailContent,
                        "Acessar Minha Conta",
                        AppConfig::$APP_URL . "/login.php"
                    );

                    \Helpers\EmailSender::send(
                        $user['email'],
                        "Confirmação de Matrícula - GT Cursos",
                        $bodyHtml
                    );

                    // WhatsApp via Discloud
                    $phone     = '5511999998888'; // Substituir pelo telefone real do aluno quando disponível
                    $waMessage = "Olá, *" . $user['name'] . "*! Seu pagamento para o curso '" . $courseTitle . "' foi aprovado! 🎉 Seu acesso está liberado. Acesse: " . AppConfig::$APP_URL . "/login.php";
                    
                    $waResult = \Helpers\WhatsAppSender::sendMessage($phone, $waMessage);
                    
                    // Salva log do WhatsApp
                    $waLogStmt = $db->prepare("INSERT INTO whatsapp_logs (phone, message, status) VALUES (:phone, :message, :status)");
                    $waLogStmt->execute([
                        ':phone'   => $phone,
                        ':message' => $waMessage,
                        ':status'  => $waResult['success'] ? 'success' : 'failed'
                    ]);
                }

            } else {
                $db->commit();
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Status da transação atualizado para: ' . $status]);
        exit;

    } else {
        // Transação não encontrada — retorna 200 para MP parar de reenviar
        http_response_code(200);
        echo json_encode(['message' => 'Notificação recebida, mas transação não encontrada no banco.']);
        exit;
    }

} catch (\PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    exit;
}
