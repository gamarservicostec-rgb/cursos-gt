<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

use Config\Database;
use Config\AppConfig;
use Middleware\SecurityHeaders;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';

// Aplica cabeçalhos de segurança HTTP
\Middleware\SecurityHeaders::applyHeaders();

// Permite apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não permitido. Use POST.']);
    exit;
}

// Inicia sessão segura
AppConfig::startSession();

// Lê corpo da requisição (JSON ou Form Data)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';
$csrfToken = $input['csrf_token'] ?? '';

// Validação básica de entradas
if (!$email || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'E-mail ou senha inválidos.']);
    exit;
}

// Conecta ao banco de dados MySQL
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca usuário pelo e-mail
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, xp, level FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Renova o ID de sessão para mitigar ataques de Session Fixation
        session_regenerate_id(true);

        // Registra dados cruciais na sessão do PHP
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        // Registra log de sucesso da auditoria
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)");
        $logStmt->execute([
            ':user_id' => $user['id'],
            ':action' => 'login_sucesso',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Autenticação realizada com sucesso.',
            'redirect' => ($user['role'] === 'admin') ? 'admin/index.php' : 'dashboard/index.php',
            'user' => [
                'name' => $user['name'],
                'role' => $user['role'],
                'xp' => $user['xp'],
                'level' => $user['level']
            ]
        ]);
        exit;
    } else {
        // Log de falha de login para auditoria (IP suspeito)
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (NULL, :action, :ip)");
        $logStmt->execute([
            ':action' => 'login_falha_email_' . substr($input['email'] ?? '', 0, 30),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        http_response_code(401);
        echo json_encode(['error' => 'E-mail ou senha incorretos.']);
        exit;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Ocorreu um erro interno de banco de dados.']);
    }
    exit;
}
