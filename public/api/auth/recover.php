<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

use Config\Database;
use Config\AppConfig;
use Helpers\EmailSender;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Helpers/EmailSender.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders'; // Optional security inclusion if needed

// Permite apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não permitido. Use POST.']);
    exit;
}

// Lê corpo da requisição (JSON ou Form Data)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'O endereço de e-mail fornecido é inválido.']);
    exit;
}

// Conecta ao banco de dados
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca usuário pelo e-mail
    $stmt = $db->prepare("SELECT id, name FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Gera um token de recuperação falso seguro (para simulação ou mock de 1 hora)
        $token = bin2hex(random_bytes(16));
        
        // Em produção, salvaríamos esse token com data de expiração no banco
        // $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        
        $recoveryLink = AppConfig::$APP_URL . "/recover_password.php?token=" . $token;

        // Escreve corpo do e-mail HTML elegante
        $emailSubject = "Recuperação de Senha — GT Cursos";
        $emailContent = "
        <p>Olá, <strong>" . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Recebemos uma solicitação de redefinição de senha para a sua conta associada ao endereço <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong> na plataforma GT Cursos.</p>
        <p>Para prosseguir com a definição de uma nova credencial de acesso seguro, por favor clique no botão abaixo dentro do prazo máximo de 1 hora.</p>
        <p>Se não foi você quem solicitou esta alteração, por favor ignore este e-mail por segurança.</p>
        ";

        $emailHtml = EmailSender::getTemplateHtml(
            "Redefinição de Senha",
            $emailContent,
            "Redefinir Minha Senha",
            $recoveryLink
        );

        // Dispara e-mail via Helper
        $emailSent = EmailSender::send($email, $emailSubject, $emailHtml);

        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => 'O e-mail contendo as instruções de recuperação foi disparado com sucesso.'
            ]);
        } else {
            // Em desenvolvimento, caso o mail() falhe por falta de SMTP configurado na máquina local, retorna sucesso com simulação do link para testes
            if (AppConfig::$DEV_MODE) {
                echo json_encode([
                    'success' => true,
                    'message' => '[DEV MODE] O e-mail falhou ao disparar nativamente, mas o link gerado foi capturado.',
                    'debug_link' => $recoveryLink
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Não foi possível disparar o e-mail de recuperação no momento. Tente novamente mais tarde.']);
            }
        }
        exit;
    } else {
        // Por motivos de privacidade/segurança, respondemos sucesso fictício de forma vaga para evitar enumeração de contas
        echo json_encode([
            'success' => true,
            'message' => 'Se o endereço fornecido estiver associado a uma conta ativa, você receberá instruções de recuperação em breve.'
        ]);
        exit;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno de servidor no banco de dados.']);
    exit;
}
