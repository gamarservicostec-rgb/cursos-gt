<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não suportado.']);
    exit;
}

// Verifica se o arquivo foi enviado sem erros
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado ou erro no upload.']);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileTmp = $file['tmp_name'];
$fileSize = $file['size'];

// Validação de Tamanho Seguro (Máximo 5MB)
$maxSize = 5 * 1024 * 1024;
if ($fileSize > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'O arquivo é muito grande. O limite máximo é de 5MB.']);
    exit;
}

// Validação de Extensão e MIME Type de Imagem
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$fileMime = mime_content_type($fileTmp);
if (!in_array($fileMime, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de arquivo inválido. Apenas imagens JPEG, PNG, WEBP e GIF são permitidas.']);
    exit;
}

// Higienização do nome e extensão
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
if (!in_array(strtolower($ext), $allowedExts)) {
    http_response_code(400);
    echo json_encode(['error' => 'Extensão de arquivo não permitida.']);
    exit;
}

// Define o diretório de destino e garante que exista
$uploadDir = __DIR__ . '/../../assets/images/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Gera um nome único e seguro para a imagem
$newFileName = 'course_thumb_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
$destPath = $uploadDir . $newFileName;

// Move o arquivo temporário para o destino final
if (move_uploaded_file($fileTmp, $destPath)) {
    // Retorna a URL relativa do asset para salvamento no banco de dados
    $relativeUrl = 'assets/images/uploads/' . $newFileName;
    echo json_encode([
        'success' => true,
        'message' => 'Imagem carregada com sucesso!',
        'url' => $relativeUrl
    ]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao salvar a imagem no diretório de destino.']);
    exit;
}
