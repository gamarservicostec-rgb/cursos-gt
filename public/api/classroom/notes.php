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

// Inicia sessão e requer autenticação do aluno
\Middleware\AuthMiddleware::requireStudent();

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

if ($method === 'GET') {
    // 1. RECUPERAR ANOTAÇÕES DO ALUNO NA AULA
    $lessonId = filter_var($_GET['lesson_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$lessonId) {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetro lesson_id inválido ou ausente.']);
        exit;
    }

    try {
        // Ordena por video_timestamp para aparecer em ordem cronológica de reprodução do vídeo
        $query = "SELECT id, notes_text, video_timestamp, created_at, updated_at 
                  FROM user_notes 
                  WHERE user_id = :user_id AND lesson_id = :lesson_id
                  ORDER BY video_timestamp ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId
        ]);
        $notes = $stmt->fetchAll();

        // Formata os retornos escapando saídas contra XSS
        $formattedNotes = array_map(function($note) {
            return [
                'id' => (int)$note['id'],
                'notes_text' => htmlspecialchars($note['notes_text'], ENT_QUOTES, 'UTF-8'),
                'video_timestamp' => (int)$note['video_timestamp'],
                'created_at' => $note['created_at'],
                'updated_at' => $note['updated_at']
            ];
        }, $notes);

        echo json_encode($formattedNotes);
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro interno de BD: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Erro interno de servidor ao recuperar anotações.']);
        }
        exit;
    }

} elseif ($method === 'POST') {
    // 2. SALVAR OU ATUALIZAR UMA ANOTAÇÃO
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);
    $notesText = trim($input['notes_text'] ?? '');
    $videoTimestamp = filter_var($input['video_timestamp'] ?? 0, FILTER_VALIDATE_INT);
    $noteId = isset($input['note_id']) ? filter_var($input['note_id'], FILTER_VALIDATE_INT) : null;

    if (!$lessonId || empty($notesText)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados da anotação incompletos ou vazios.']);
        exit;
    }

    if (strlen($notesText) > 4000) {
        http_response_code(400);
        echo json_encode(['error' => 'A anotação excede o limite máximo de 4.000 caracteres.']);
        exit;
    }

    try {
        // Valida se a lição existe
        $lessonStmt = $db->prepare("SELECT id FROM lessons WHERE id = :id LIMIT 1");
        $lessonStmt->execute([':id' => $lessonId]);
        if (!$lessonStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Aula inválida ou não encontrada.']);
            exit;
        }

        if ($noteId) {
            // Atualiza anotação existente
            $updateQuery = "UPDATE user_notes 
                            SET notes_text = :notes_text, video_timestamp = :video_timestamp 
                            WHERE id = :id AND user_id = :user_id";
            $stmt = $db->prepare($updateQuery);
            $stmt->execute([
                ':notes_text' => $notesText,
                ':video_timestamp' => $videoTimestamp,
                ':id' => $noteId,
                ':user_id' => $userId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Anotação atualizada com sucesso.',
                'data' => [
                    'id' => $noteId,
                    'notes_text' => htmlspecialchars($notesText, ENT_QUOTES, 'UTF-8'),
                    'video_timestamp' => $videoTimestamp,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        } else {
            // Insere nova anotação
            $insertQuery = "INSERT INTO user_notes (user_id, lesson_id, notes_text, video_timestamp) 
                            VALUES (:user_id, :lesson_id, :notes_text, :video_timestamp)";
            $stmt = $db->prepare($insertQuery);
            $stmt->execute([
                ':user_id' => $userId,
                ':lesson_id' => $lessonId,
                ':notes_text' => $notesText,
                ':video_timestamp' => $videoTimestamp
            ]);

            $newId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Anotação salva com sucesso.',
                'data' => [
                    'id' => (int)$newId,
                    'notes_text' => htmlspecialchars($notesText, ENT_QUOTES, 'UTF-8'),
                    'video_timestamp' => $videoTimestamp,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Ocorreu um erro ao salvar sua anotação.']);
        }
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição inválido.']);
    exit;
}
