<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

use Config\Database;
use Config\AppConfig;
use Middleware\SecurityHeaders;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';

// Inicia sessão sem exigir login (guest checkout)
AppConfig::startSession();

\Middleware\SecurityHeaders::applyHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$courseId       = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);
$paymentMethod  = $input['payment_method'] ?? '';
$paymentMethodId = $input['payment_method_id'] ?? '';
$token          = $input['token'] ?? '';
$installments   = filter_var($input['installments'] ?? 1, FILTER_VALIDATE_INT);
$coupon         = isset($input['coupon']) ? strtoupper(trim($input['coupon'])) : '';
$scheduleTime   = isset($input['schedule_time']) ? trim($input['schedule_time']) : null;

// Dados do visitante (guest checkout)
$guestName     = isset($input['guest_name'])     ? trim($input['guest_name'])     : null;
$guestEmail    = isset($input['guest_email'])    ? strtolower(trim($input['guest_email'])) : null;
$guestPassword = isset($input['guest_password']) ? $input['guest_password']       : null;

if (!$courseId || empty($paymentMethod)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros obrigatórios (course_id, payment_method) inválidos.']);
    exit;
}

$dbInstance = Database::getInstance();
$db         = $dbInstance->getConnection();

// ============================================================
// RESOLUÇÃO DE IDENTIDADE: usuário logado ou visitante?
// ============================================================
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    // Visitante: precisa de dados para criar/localizar conta
    if (empty($guestName) || empty($guestEmail) || empty($guestPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'Preencha seu nome, e-mail e senha para continuar.']);
        exit;
    }

    if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'E-mail inválido. Verifique e tente novamente.']);
        exit;
    }

    if (strlen($guestPassword) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'A senha deve ter no mínimo 6 caracteres.']);
        exit;
    }

    try {
        // Verifica se o e-mail já está cadastrado
        $existingStmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = :email LIMIT 1");
        $existingStmt->execute([':email' => $guestEmail]);
        $existingUser = $existingStmt->fetch();

        if ($existingUser) {
            // E-mail já existe: valida a senha
            if (!password_verify($guestPassword, $existingUser['password_hash'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Este e-mail já possui cadastro. A senha informada está incorreta. Faça login ou use "Esqueci minha senha".']);
                exit;
            }
            $userId = (int)$existingUser['id'];
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_name'] = $existingUser['name'];
            $_SESSION['user_email'] = $guestEmail;
            $_SESSION['user_role'] = 'student';
        } else {
            // E-mail novo: cria a conta automaticamente
            $hashedPass = password_hash($guestPassword, PASSWORD_BCRYPT);
            $createStmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :pass, 'student')");
            $createStmt->execute([
                ':name'  => $guestName,
                ':email' => $guestEmail,
                ':pass'  => $hashedPass
            ]);
            $userId = (int)$db->lastInsertId();
            $_SESSION['user_id']    = $userId;
            $_SESSION['user_name']  = $guestName;
            $_SESSION['user_email'] = $guestEmail;
            $_SESSION['user_role']  = 'student';
        }
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao processar dados do usuário: ' . $e->getMessage()]);
        exit;
    }
}


try {
    // 1. Verifica se o curso existe e busca o valor (preço)
    $courseStmt = $db->prepare("SELECT id, title, price FROM courses WHERE id = :id AND status = 'active' LIMIT 1");
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Curso não encontrado ou inativo.']);
        exit;
    }

    $amount = (float)$course['price'];
    
    // Aplicação dinâmica e segura de cupons promocionais a partir do banco de dados
    $discount = 0.00;
    if (!empty($coupon)) {
        $couponStmt = $db->prepare("SELECT * FROM coupons WHERE code = :code AND status = 'active' AND expires_at >= CURDATE() LIMIT 1");
        $couponStmt->execute([':code' => $coupon]);
        $c = $couponStmt->fetch(\PDO::FETCH_ASSOC);

        if ($c) {
            if ($c['type'] === 'fixed') {
                $discount = (float)$c['value'];
            } else {
                // Porcentagem
                $discount = $amount * ((float)$c['value'] / 100);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'O cupom de desconto informado é inválido ou já expirou.']);
            exit;
        }
    }
    $amount = max(0.00, $amount - $discount);

    $paymentId = '';
    $status = 'pending';
    $qrCode = null;
    $qrCodeBase64 = null;
    $barCode = null;

    // Detecta se estamos rodando em ambiente mock/desenvolvimento
    $isMock = (strpos(AppConfig::$MERCADO_PAGO_ACCESS_TOKEN, 'mock') !== false);

    if ($isMock) {
        // --- SIMULAÇÃO DE PAGAMENTO DE ALTO NÍVEL ---
        $paymentId = 'MP-MOCK-' . rand(10000000, 99999999);
        
        if ($paymentMethod === 'credit_card' || $paymentMethod === 'pix') {
            $status = 'approved';
        } else {
            $status = 'pending'; // Boleto pendente
        }

        if ($paymentMethod === 'pix') {
            $qrCode = "00020101021226870014br.gov.bcb.pix2565pix.mercado-pago.com.br/qr/v2/mock-pix-gt-cursos-payment-key-992211";
            $qrCodeBase64 = "iVBORw0KGgoAAAANSUhEUgAAAQAAAAEAAQMAAAB6lhacAAAAA1UEWHRM... (Mock Base64 Pix QR Code)";
        } elseif ($paymentMethod === 'boleto') {
            $barCode = "34191.79001 01043.513184 91020.150008 7 999900000" . round($amount * 100);
        }

    } else {
        // --- INTEGRAÇÃO REAL COM A API DO MERCADO PAGO ---
        $payload = [
            'transaction_amount' => $amount,
            'description' => 'Matrícula curso GT Cursos: ' . $course['title'],
            'payment_method_id' => $paymentMethodId,
            'installments' => $installments,
            'payer' => [
                'email' => $_SESSION['user_email'],
                'first_name' => $_SESSION['user_name']
            ]
        ];

        if ($paymentMethod === 'credit_card') {
            $payload['token'] = $token;
            $payload['installments'] = $installments;
        }

        // Requisição cURL segura para a API do Mercado Pago
        $ch = curl_init('https://api.mercadopago.com/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . AppConfig::$MERCADO_PAGO_ACCESS_TOKEN,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('mp_', true)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $responseJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($responseJson, true);
            $paymentId = $result['id'] ?? '';
            $status = $result['status'] ?? 'pending'; // 'approved', 'in_process', 'rejected', 'pending'
            
            if ($paymentMethod === 'pix') {
                $qrCode = $result['point_of_interaction']['transaction_data']['qr_code'] ?? null;
                $qrCodeBase64 = $result['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
            } elseif ($paymentMethod === 'boleto') {
                $barCode = $result['barcode']['content'] ?? null;
            }
        } else {
            // Em caso de erro na API do Mercado Pago, responde com erro amigável
            http_response_code(400);
            $errData = json_decode($responseJson, true);
            $errMsg = $errData['message'] ?? 'Erro no processador de pagamentos.';
            echo json_encode(['error' => 'Falha ao processar pagamento com o Mercado Pago: ' . $errMsg]);
            exit;
        }
    }

    $paymentDetailsJson = null;
    if ($paymentMethod === 'pix') {
        $paymentDetailsJson = json_encode([
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64
        ]);
    } elseif ($paymentMethod === 'boleto') {
        $paymentDetailsJson = json_encode([
            'barcode' => $barCode,
            'ticket_url' => $result['transaction_details']['external_resource_url'] ?? null
        ]);
    }

    // 2. Grava a transação financeira no banco de dados MySQL
    $db->beginTransaction();

    $transStmt = $db->prepare("
        INSERT INTO transactions (user_id, course_id, payment_id, amount, payment_method, status, payment_details) 
        VALUES (:user_id, :course_id, :payment_id, :amount, :payment_method, :status, :payment_details)
    ");
    $transStmt->execute([
        ':user_id' => $userId,
        ':course_id' => $courseId,
        ':payment_id' => $paymentId,
        ':amount' => $amount,
        ':payment_method' => $paymentMethod,
        ':status' => $status,
        ':payment_details' => $paymentDetailsJson
    ]);

    // 3. Se o pagamento foi aprovado de imediato (Cartão aprovado ou simulação Pix/Cartão)
    if ($status === 'approved') {
        // Cria a matrícula ativa do aluno
        $enrollStmt = $db->prepare("
            INSERT INTO enrollments (user_id, course_id, schedule_time, status) 
            VALUES (:user_id, :course_id, :schedule_time, 'active')
            ON DUPLICATE KEY UPDATE status = 'active', schedule_time = VALUES(schedule_time)
        ");
        $enrollStmt->execute([
            ':user_id' => $userId,
            ':course_id' => $courseId,
            ':schedule_time' => $scheduleTime
        ]);

        // Cria log de auditoria
        $auditStmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address) 
            VALUES (:user_id, 'compra_aprovada', :details, :ip)
        ");
        $auditStmt->execute([
            ':user_id' => $userId,
            ':details' => "Acesso liberado ao curso {$courseId} (MP ID: {$paymentId})",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pagamento iniciado/concluído com sucesso.',
        'data' => [
            'payment_id' => $paymentId,
            'status' => $status, // 'approved' ou 'pending'
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'barcode' => $barCode,
            'redirect' => ($status === 'approved') ? 'confirmation.php?status=success&payment_id=' . $paymentId : 'confirmation.php?status=pending&payment_id=' . $paymentId
        ]
    ]);
    exit;

} catch (\PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno SQL: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Erro de banco de dados ao processar o seu pedido.']);
    }
    exit;
}
