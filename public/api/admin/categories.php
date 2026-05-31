<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// Helper para gerar Slugs limpos
function generateSlug($string) {
    $string = preg_replace('~[^\pL\d]+~u', '-', $string);
    $string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);
    $string = preg_replace('~[^-\w]+~', '', $string);
    $string = trim($string, '-');
    $string = preg_replace('~-+~', '-', $string);
    $string = strtolower($string);
    return empty($string) ? 'n-a' : $string;
}

try {
    if ($method === 'GET') {
        // Listar categorias
        $stmt = $db->prepare("SELECT id, name, slug, sort_order, created_at FROM categories ORDER BY sort_order ASC, name ASC");
        $stmt->execute();
        $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }

    if ($method === 'POST') {
        // Criar ou Editar categoria
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $id = isset($input['id']) ? (int)$input['id'] : null;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome da categoria é obrigatório.']);
            exit;
        }

        $slug = generateSlug($name);

        if ($id > 0) {
            // Atualização
            $stmt = $db->prepare("UPDATE categories SET name = :name, slug = :slug, sort_order = :sort_order WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':sort_order' => $sortOrder,
                ':id' => $id
            ]);
            echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!', 'category' => ['id' => $id, 'name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder]]);
        } else {
            // Inserção
            $stmt = $db->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (:name, :slug, :sort_order)");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':sort_order' => $sortOrder
            ]);
            $newId = (int)$db->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!', 'category' => ['id' => $newId, 'name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder]]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        // Excluir categoria
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            // Tenta pegar do body JSON
            $input = json_decode(file_get_contents('php://input'), true);
            $id = isset($input['id']) ? (int)$input['id'] : 0;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da categoria inválido para exclusão.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
        exit;
    }

    // Método não suportado
    http_response_code(405);
    echo json_encode(['error' => 'Método não suportado.']);
    exit;

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno na API de categorias: ' . $e->getMessage()]);
    exit;
}
