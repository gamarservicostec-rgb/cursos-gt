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

// 1. Lê a notificação do Mercado Pago (JSON ou Query Params)
$input = json_decode(file_get_contents('php://input'), true);

$paymentId = null;
$action = $input['action'] ?? '';

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
$db = $dbInstance->getConnection();

try {
    // 2. Busca a transação no banco pelo ID do pagamento
    $transStmt = $db->prepare("SELECT id, user_id, course_id, amount, status FROM transactions WHERE payment_id = :payment_id LIMIT 1");
    $transStmt->execute([':payment_id' => $paymentId]);
    $transaction = $transStmt->fetch();

    // Se a transação for do tipo simulação de teste local
    $isMock = (strpos($paymentId, 'MP-MOCK-') !== false);
    $status = 'pending';

    if ($transaction) {
        $userId = $transaction['user_id'];
        $courseId = $transaction['course_id'];
        $currentStatus = $transaction['status'];

        if ($isMock) {
            // Permite simulação via query string: ?mock_status=approved
            $status = $_GET['mock_status'] ?? 'approved';
        } else {
            // Consulta os detalhes reais do pagamento diretamente na API do Mercado Pago
            $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . AppConfig::$MERCADO_PAGO_ACCESS_TOKEN
            ]);
            
            $responseJson = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($responseJson, true);
                $status = $result['status'] ?? 'pending';
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Não foi possível consultar os dados da API Mercado Pago para o ID ' . $paymentId]);
                exit;
            }
        }

        // Se o status mudou, atualiza a transação
        if ($status !== $currentStatus) {
            $db->beginTransaction();

            $updateTransStmt = $db->prepare("UPDATE transactions SET status = :status, updated_at = NOW() WHERE id = :id");
            $updateTransStmt->execute([
                ':status' => $status,
                ':id' => $transaction['id']
            ]);

            // Se o pagamento foi aprovado, libera o acesso do aluno ao curso
            if ($status === 'approved') {
                $enrollStmt = $db->prepare("
                    INSERT INTO enrollments (user_id, course_id, status) 
                    VALUES (:user_id, :course_id, 'active')
                    ON DUPLICATE KEY UPDATE status = 'active'
                ");
                $enrollStmt->execute([
                    ':user_id' => $userId,
                    ':course_id' => $courseId
                ]);

                // Insere log de auditoria
                $auditStmt = $db->prepare("
                    INSERT INTO audit_logs (user_id, action, details, ip_address) 
                    VALUES (:user_id, 'compra_aprovada_webhook', :details, :ip)
                ");
                $auditStmt->execute([
                    ':user_id' => $userId,
                    ':details' => "Matrícula liberada via Webhook para curso {$courseId} (MP ID: {$paymentId})",
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $db->commit();

                // --- DISPARO DE MENSAGENS TRANSACIONAIS (EMAIL & WHATSAPP) ---
                
                // Busca nome e email do aluno
                $userStmt = $db->prepare("SELECT name, email FROM users WHERE id = :id LIMIT 1");
                $userStmt->execute([':id' => $userId]);
                $user = $userStmt->fetch();

                // Busca o nome do curso
                $cStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
                $cStmt->execute([':id' => $courseId]);
                $courseTitle = $cStmt->fetchColumn();

                if ($user) {
                    // 1. Envio de E-mail formatado (Neon Amber Fusion Template)
                    $emailContent = "
                        Olá, <strong>" . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "</strong>!<br><br>
                        Seu pagamento no valor de <strong>R$ " . number_format($transaction['amount'], 2, ',', '.') . "</strong> para o curso <strong>" . htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') . "</strong> foi confirmado com sucesso!<br><br>
                        Sua matrícula já está ativa e seu acesso está 100% liberado.<br>
                        Clique no botão abaixo para acessar o seu painel de controle e iniciar os estudos.
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

                    // 2. Envio de WhatsApp (Simulado/Disparado via Discloud API)
                    $phone = '5511999998888'; // Número de teste (como não há telefone em users)
                    $waMessage = "Olá, *" . $user['name'] . "*! Seu pagamento para o curso '" . $courseTitle . "' foi aprovado com sucesso! 🎉 Seu acesso já está liberado. Acesse a plataforma: " . AppConfig::$APP_URL . "/login.php";
                    
                    $waResult = \Helpers\WhatsAppSender::sendMessage($phone, $waMessage);
                    
                    // Salva log de WhatsApp no banco
                    $waLogStmt = $db->prepare("INSERT INTO whatsapp_logs (phone, message, status) VALUES (:phone, :message, :status)");
                    $waLogStmt->execute([
                        ':phone' => $phone,
                        ':message' => $waMessage,
                        ':status' => $waResult['success'] ? 'success' : 'failed'
                    ]);
                }
            } else {
                $db->commit();
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Status da transação atualizado com sucesso para ' . $status]);
        exit;

    } else {
        // Retorna 200 para que o Mercado Pago não fique reenviando
        http_response_code(200);
        echo json_encode(['message' => 'Notificação recebida, mas transação correspondente não encontrada no banco.']);
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
