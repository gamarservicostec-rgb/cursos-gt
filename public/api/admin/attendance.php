<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

use Config\Database;
use Config\AppConfig;
use Middleware\SecurityHeaders;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';
require_once __DIR__ . '/../../../src/Middleware/AuthMiddleware.php';

// Aplica cabeçalhos de segurança HTTP
\Middleware\SecurityHeaders::applyHeaders();

// Inicia sessão e requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

if ($method === 'GET') {
    // 1. RECUPERAR LISTA DE ALUNOS E SUAS PRESENÇAS EM UMA DATA ESPECÍFICA
    $courseId = filter_var($_GET['course_id'] ?? 0, FILTER_VALIDATE_INT);
    $date = $_GET['date'] ?? date('Y-m-d'); // Data padrão: hoje

    // Valida o formato da data (AAAA-MM-DD)
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$courseId || !$d || $d->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetros course_id ou date inválidos ou ausentes.']);
        exit;
    }

    try {
        // Query de busca combinando matrículas ativas e a presença opcional naquela data
        $query = "SELECT u.id as student_id, u.name as student_name, u.email as student_email, COALESCE(pa.attended, 0) as attended
                  FROM enrollments e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN physical_attendance pa ON pa.user_id = u.id AND pa.course_id = e.course_id AND pa.date = :date
                  WHERE e.course_id = :course_id AND e.status = 'active'
                  ORDER BY u.name ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':course_id' => $courseId,
            ':date' => $date
        ]);
        $list = $stmt->fetchAll();

        $formattedList = array_map(function($student) {
            return [
                'student_id' => (int)$student['student_id'],
                'student_name' => htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'),
                'student_email' => htmlspecialchars($student['student_email'], ENT_QUOTES, 'UTF-8'),
                'attended' => (bool)$student['attended']
            ];
        }, $list);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'course_id' => $courseId,
            'students' => $formattedList
        ]);
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Erro de banco de dados ao buscar lista de presença.']);
        }
        exit;
    }

} elseif ($method === 'POST') {
    // 2. GRAVAR OU ATUALIZAR PRESENÇA DO ALUNO
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $studentId = filter_var($input['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $courseId = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);
    $date = $input['date'] ?? date('Y-m-d');
    $attended = filter_var($input['attended'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$studentId || !$courseId || !$d || $d->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados de chamada física inválidos ou ausentes.']);
        exit;
    }

    try {
        // Valida se o aluno está realmente matriculado no curso
        $checkStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = :user_id AND course_id = :course_id AND status = 'active' LIMIT 1");
        $checkStmt->execute([
            ':user_id' => $studentId,
            ':course_id' => $courseId
        ]);
        
        if (!$checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'O aluno especificado não possui matrícula ativa neste curso.']);
            exit;
        }

        // Insere ou atualiza o registro de presença física
        $query = "INSERT INTO physical_attendance (user_id, course_id, date, attended) 
                  VALUES (:user_id, :course_id, :date, :attended) 
                  ON DUPLICATE KEY UPDATE attended = VALUES(attended)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $studentId,
            ':course_id' => $courseId,
            ':date' => $date,
            ':attended' => $attended
        ]);

        // Grava log na auditoria administrativa
        $logStmt = $db->prepare("
            INSERT INTO admin_activity (admin_id, action, affected_resource, details) 
            VALUES (:admin_id, 'registrar_presenca', :resource, :details)
        ");
        $statusStr = ($attended === 1) ? 'Presença' : 'Falta';
        $logStmt->execute([
            ':admin_id' => $_SESSION['user_id'],
            ':resource' => "physical_attendance/user_{$studentId}_course_{$courseId}",
            ':details' => "Presença física atualizada para '{$statusStr}' na data {$date}"
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Chamada física atualizada com sucesso.',
            'data' => [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'date' => $date,
                'attended' => ($attended === 1)
            ]
        ]);
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro SQL: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Erro de banco de dados ao salvar a chamada de presença.']);
        }
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição inválido.']);
    exit;
}
