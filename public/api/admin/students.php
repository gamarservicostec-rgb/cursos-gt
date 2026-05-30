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
    // 1. LEITURA DE DETALHES DO ESTUDANTE (GET)
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do estudante inválido ou ausente.']);
        exit;
    }

    try {
        // Busca dados do aluno
        $stmt = $db->prepare("SELECT id, name, email, phone, xp, level, current_streak, created_at FROM users WHERE id = :id AND role = 'student' LIMIT 1");
        $stmt->execute([':id' => $id]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$student) {
            http_response_code(404);
            echo json_encode(['error' => 'Estudante não encontrado no sistema.']);
            exit;
        }

        // Busca conquistas desbloqueadas do aluno
        $achStmt = $db->prepare("
            SELECT a.title, a.description, ua.unlocked_at 
            FROM user_achievements ua
            JOIN achievements a ON ua.achievement_id = a.id
            WHERE ua.user_id = :id
            ORDER BY ua.unlocked_at DESC
        ");
        $achStmt->execute([':id' => $id]);
        $achievements = $achStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Busca matrículas ativas do aluno
        $coursesStmt = $db->prepare("
            SELECT c.title, e.status, e.enrolled_at 
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.user_id = :id
        ");
        $coursesStmt->execute([':id' => $id]);
        $courses = $coursesStmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'student' => $student,
            'achievements' => $achievements,
            'courses' => $courses
        ]);
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno ao obter estudante: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'POST') {
    // 2. ATUALIZAÇÃO CADASTRAL E DE GAMIFICAÇÃO DO ALUNO (POST)
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id = isset($input['id']) ? filter_var($input['id'], FILTER_VALIDATE_INT) : null;
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $xp = filter_var($input['xp'] ?? 0, FILTER_VALIDATE_INT);
    $level = filter_var($input['level'] ?? 1, FILTER_VALIDATE_INT);
    $streak = filter_var($input['streak'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id || empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados cadastrais obrigatórios ausentes.']);
        exit;
    }

    try {
        // Verifica duplicidade de e-mail em outros cadastros
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id");
        $checkStmt->execute([':email' => $email, ':id' => $id]);
        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Este endereço de e-mail já está em uso por outro usuário.']);
            exit;
        }

        // Executa atualização no banco
        $stmt = $db->prepare("
            UPDATE users 
            SET name = :name, email = :email, phone = :phone, xp = :xp, level = :level, current_streak = :streak 
            WHERE id = :id AND role = 'student'
        ");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => !empty($phone) ? $phone : null,
            ':xp' => $xp,
            ':level' => $level,
            ':streak' => $streak,
            ':id' => $id
        ]);

        // Grava auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'editar_estudante', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "students/{$id}",
            ':details' => "Perfil do aluno ID {$id} ({$name}) atualizado (WhatsApp: {$phone}, XP: {$xp}, Nível: {$level}, Streak: {$streak})"
        ]);

        echo json_encode(['success' => true, 'message' => 'Perfil do estudante atualizado com sucesso.']);
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno ao salvar estudante: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'DELETE') {
    // 3. EXCLUSÃO (DELETE)
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do estudante inválido para exclusão.']);
        exit;
    }

    try {
        // Recupera nome para auditoria
        $nameStmt = $db->prepare("SELECT name FROM users WHERE id = :id AND role = 'student' LIMIT 1");
        $nameStmt->execute([':id' => $id]);
        $name = $nameStmt->fetchColumn();

        if (!$name) {
            http_response_code(404);
            echo json_encode(['error' => 'Estudante não localizado para exclusão.']);
            exit;
        }

        // Deleta
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
        $stmt->execute([':id' => $id]);

        // Grava auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'excluir_estudante', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "students/{$id}",
            ':details' => "Aluno ID {$id} ({$name}) excluído permanentemente do sistema"
        ]);

        echo json_encode(['success' => true, 'message' => 'Aluno excluído permanentemente do banco de dados.']);
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno de banco ao excluir aluno: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não suportado.']);
    exit;
}
