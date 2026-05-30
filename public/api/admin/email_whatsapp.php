<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

use Config\Database;
use Middleware\SecurityHeaders;
use Middleware\AuthMiddleware;
use Helpers\EmailSender;
use Helpers\WhatsAppSender;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';
require_once __DIR__ . '/../../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../src/Helpers/EmailSender.php';
require_once __DIR__ . '/../../../src/Helpers/WhatsAppSender.php';

// Aplica cabeçalhos de segurança
\Middleware\SecurityHeaders::applyHeaders();

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não suportado.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$channel = trim($input['channel'] ?? '');
$segment = trim($input['segment'] ?? 'individual');
$targetEmail = trim($input['target_email'] ?? '');
$targetPhone = trim($input['target_phone'] ?? '');
$title = trim($input['title'] ?? 'GT Alerta');
$subject = trim($input['subject'] ?? 'Notificação GT Cursos');
$message = trim($input['message'] ?? '');

if (empty($channel) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Canal de disparo e mensagem são obrigatórios.']);
    exit;
}

try {
    if ($channel === 'email') {
        // --- 1. DISPAROS DE E-MAIL ---
        $emailsToSend = [];

        if ($segment === 'all') {
            // Busca todos os estudantes cadastrados
            $stmt = $db->query("SELECT email, name FROM users WHERE role = 'student'");
            $emailsToSend = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            if (empty($targetEmail)) {
                http_response_code(400);
                echo json_encode(['error' => 'Endereço de e-mail destinatário é obrigatório para envio individual.']);
                exit;
            }
            $emailsToSend[] = ['email' => $targetEmail, 'name' => 'Aluno'];
        }

        if (empty($emailsToSend)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum aluno cadastrado no sistema para receber o e-mail em massa.']);
            exit;
        }

        $sentCount = 0;
        $bodyHtmlTemplate = EmailSender::getTemplateHtml($title, $message);

        foreach ($emailsToSend as $recipient) {
            $personalBody = str_replace('{nome}', $recipient['name'], $bodyHtmlTemplate);
            $success = EmailSender::send($recipient['email'], $subject, $personalBody);
            if ($success) {
                $sentCount++;
            }
        }

        // Registra a campanha disparada no banco de dados
        $campStmt = $db->prepare("INSERT INTO email_campaigns (title, subject, body_html, status, sent_count) VALUES (:title, :subject, :body, 'sent', :sent_count)");
        $campStmt->execute([
            ':title' => $title,
            ':subject' => $subject,
            ':body' => $message,
            ':sent_count' => $sentCount
        ]);
        $campId = $db->lastInsertId();

        // Grava auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'disparar_email', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "email_campaigns/{$campId}",
            ':details' => "Campanha de e-mail '{$title}' disparada com sucesso para {$sentCount} alunos"
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Campanha disparada com sucesso! E-mails entregues: {$sentCount}."
        ]);
        exit;

    } elseif ($channel === 'whatsapp') {
        // --- 2. DISPAROS DE WHATSAPP ---
        $phonesToSend = [];

        if ($segment === 'all') {
            // Busca todos os estudantes com número de celular válido cadastrado
            $stmt = $db->query("SELECT phone, name FROM users WHERE role = 'student' AND phone IS NOT NULL AND phone <> ''");
            $phonesToSend = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            if (empty($targetPhone)) {
                http_response_code(400);
                echo json_encode(['error' => 'Número de telefone do destinatário é obrigatório.']);
                exit;
            }
            $phonesToSend[] = ['phone' => $targetPhone, 'name' => 'Aluno'];
        }

        if (empty($phonesToSend)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum aluno com WhatsApp/telefone cadastrado para envio massivo.']);
            exit;
        }

        $sentSuccess = 0;
        $sentFailed = 0;

        foreach ($phonesToSend as $recipient) {
            // Dispara via WhatsAppSender
            $res = WhatsAppSender::sendMessage($recipient['phone'], $message);

            if ($res['success']) {
                $sentSuccess++;
                // Registra log de sucesso
                $logStmt = $db->prepare("INSERT INTO whatsapp_logs (phone, message, status) VALUES (:phone, :message, 'success')");
                $logStmt->execute([
                    ':phone' => $recipient['phone'],
                    ':message' => $message
                ]);
            } else {
                $sentFailed++;
                $err = $res['message'] ?? ($res['response']['error'] ?? 'Erro desconhecido');
                // Registra log de falha
                $logStmt = $db->prepare("INSERT INTO whatsapp_logs (phone, message, status, error_message) VALUES (:phone, :message, 'failed', :err)");
                $logStmt->execute([
                    ':phone' => $recipient['phone'],
                    ':message' => $message,
                    ':err' => $err
                ]);
            }
        }

        // Grava auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'disparar_whatsapp', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "whatsapp_logs",
            ':details' => "Disparo de WhatsApp efetuado (Sucessos: {$sentSuccess}, Falhas: {$sentFailed})"
        ]);

        if ($sentSuccess > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Mensagem de WhatsApp disparada com sucesso! Log gravado na central."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => "Falha ao enviar mensagem de WhatsApp. Verifique os logs do painel."
            ]);
        }
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Canal de comunicação inválido.']);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno ao processar disparos: ' . $e->getMessage()]);
    exit;
}
