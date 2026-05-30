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

$name = trim($input['name'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';

// Validação básica de entradas
if (empty($name) || strlen($name) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'O nome inserido é muito curto (mínimo de 3 caracteres).']);
    exit;
}
if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'O endereço de e-mail inserido é inválido.']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'A senha inserida é fraca (mínimo de 6 caracteres).']);
    exit;
}

// Conecta ao banco de dados MySQL
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Verifica se e-mail já existe
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Este endereço de e-mail já está sendo utilizado por outra conta.']);
        exit;
    }

    // Criptografa senha com Bcrypt
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insere o aluno no banco de dados
    $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, 'student')");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => $passwordHash
    ]);
    
    $userId = $db->lastInsertId();

    // Renova o ID de sessão para mitigar ataques de Session Fixation
    session_regenerate_id(true);

    // Inicia sessão automática pós-cadastro
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'student';

    // Registra log de auditoria
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, 'cadastro_sucesso', :ip)");
    $logStmt->execute([
        ':user_id' => $userId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Conta criada com sucesso.',
        'redirect' => 'dashboard/index.php'
    ]);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Ocorreu um erro interno de banco de dados.']);
    }
    exit;
}
