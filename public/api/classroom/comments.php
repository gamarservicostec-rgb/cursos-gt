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
    // 1. LISTAGEM DE COMENTÁRIOS DA AULA
    $lessonId = filter_var($_GET['lesson_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$lessonId) {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetro lesson_id inválido ou ausente.']);
        exit;
    }

    try {
        // Query de busca incluindo informações de perfil do autor
        $query = "SELECT c.id, c.comment, c.parent_id, c.created_at, u.name as user_name, u.role as user_role, u.avatar_url 
                  FROM lesson_comments c
                  JOIN users u ON c.user_id = u.id
                  WHERE c.lesson_id = :lesson_id
                  ORDER BY c.created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':lesson_id' => $lessonId]);
        $allComments = $stmt->fetchAll();

        // Estruturação dos comentários em árvore (Parent-Replies)
        $parentComments = [];
        $repliesMap = [];

        foreach ($allComments as $c) {
            $formatted = [
                'id' => (int)$c['id'],
                'comment' => htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8'), // Prevenção contra XSS
                'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null,
                'created_at' => $c['created_at'],
                'user' => [
                    'name' => htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8'),
                    'role' => $c['user_role'],
                    'avatar_url' => $c['avatar_url'] ? htmlspecialchars($c['avatar_url'], ENT_QUOTES, 'UTF-8') : null
                ],
                'replies' => []
            ];

            if ($c['parent_id'] === null) {
                $parentComments[$c['id']] = $formatted;
            } else {
                $repliesMap[] = $formatted;
            }
        }

        // Associa respostas aos respectivos pais
        foreach ($repliesMap as $reply) {
            $pId = $reply['parent_id'];
            if (isset($parentComments[$pId])) {
                $parentComments[$pId]['replies'][] = $reply;
            } else {
                // Caso o pai seja uma resposta de outra resposta, agrupamos no nível 2
                // GT Cursos suporta árvore com profundidade principal de 2 níveis para UI limpa
                foreach ($parentComments as &$parent) {
                    foreach ($parent['replies'] as &$subReply) {
                        if ($subReply['id'] == $pId) {
                            $subReply['replies'][] = $reply;
                            break 2;
                        }
                    }
                }
            }
        }

        // Responde com o array indexado numericamente
        echo json_encode(array_values($parentComments));
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro de banco de dados: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Erro interno ao recuperar discussões.']);
        }
        exit;
    }

} elseif ($method === 'POST') {
    // 2. CRIAÇÃO DE UM NOVO COMENTÁRIO
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);
    $commentText = trim($input['comment'] ?? '');
    $parentId = isset($input['parent_id']) ? filter_var($input['parent_id'], FILTER_VALIDATE_INT) : null;

    if (!$lessonId || empty($commentText)) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios (lesson_id, comment) ausentes ou vazios.']);
        exit;
    }

    if (strlen($commentText) > 2000) {
        http_response_code(400);
        echo json_encode(['error' => 'O comentário excede o limite máximo de 2.000 caracteres.']);
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

        // Se houver parent_id, valida se o comentário original existe
        if ($parentId) {
            $parentStmt = $db->prepare("SELECT id FROM lesson_comments WHERE id = :id LIMIT 1");
            $parentStmt->execute([':id' => $parentId]);
            if (!$parentStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Comentário original não encontrado para responder.']);
                exit;
            }
        }

        // Insere o novo comentário no banco de dados
        $insertQuery = "INSERT INTO lesson_comments (user_id, lesson_id, comment, parent_id) 
                        VALUES (:user_id, :lesson_id, :comment, :parent_id)";
        $stmt = $db->prepare($insertQuery);
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId,
            ':comment' => $commentText,
            ':parent_id' => $parentId ? $parentId : null
        ]);

        $commentId = $db->lastInsertId();

        // Busca dados de quem comentou para retornar no payload imediato
        $userStmt = $db->prepare("SELECT name, role, avatar_url FROM users WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $author = $userStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Comentário enviado com sucesso.',
            'data' => [
                'id' => (int)$commentId,
                'comment' => htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8'),
                'parent_id' => $parentId ? (int)$parentId : null,
                'created_at' => date('Y-m-d H:i:s'),
                'user' => [
                    'name' => htmlspecialchars($author['name'], ENT_QUOTES, 'UTF-8'),
                    'role' => $author['role'],
                    'avatar_url' => $author['avatar_url'] ? htmlspecialchars($author['avatar_url'], ENT_QUOTES, 'UTF-8') : null
                ],
                'replies' => []
            ]
        ]);
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        if (AppConfig::$DEV_MODE) {
            echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
        } else {
            echo json_encode(['error' => 'Ocorreu um erro ao registrar seu comentário.']);
        }
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição inválido.']);
    exit;
}
