<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

use Config\Database;
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
    // 1. LEITURA (GET) - OBTER TEMPLATE DE UM CURSO
    $courseId = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;

    if (!$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do curso não informado.']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM certificate_templates WHERE course_id = :course_id LIMIT 1");
        $stmt->execute([':course_id' => $courseId]);
        $template = $stmt->fetch();

        if ($template) {
            echo json_encode([
                'success' => true,
                'exists' => true,
                'template' => [
                    'course_id' => (int)$template['course_id'],
                    'student_name_x' => (int)$template['student_name_x'],
                    'student_name_y' => (int)$template['student_name_y'],
                    'student_name_size' => (int)$template['student_name_size'],
                    'student_name_color' => $template['student_name_color'],
                    'student_name_font' => $template['student_name_font'] ?? 'Clash Display',
                    'student_name_bold' => (int)($template['student_name_bold'] ?? 1),
                    'student_name_italic' => (int)($template['student_name_italic'] ?? 0),
                    
                    'course_title_x' => (int)$template['course_title_x'],
                    'course_title_y' => (int)$template['course_title_y'],
                    'course_title_size' => (int)$template['course_title_size'],
                    'course_title_color' => $template['course_title_color'],
                    'course_title_font' => $template['course_title_font'] ?? 'Clash Display',
                    'course_title_bold' => (int)($template['course_title_bold'] ?? 1),
                    'course_title_italic' => (int)($template['course_title_italic'] ?? 0),
                    
                    'date_x' => (int)$template['date_x'],
                    'date_y' => (int)$template['date_y'],
                    'date_size' => (int)$template['date_size'],
                    'date_color' => $template['date_color'],
                    'date_font' => $template['date_font'] ?? 'Satoshi',
                    'date_bold' => (int)($template['date_bold'] ?? 1),
                    'date_italic' => (int)($template['date_italic'] ?? 0),
                    
                    'code_x' => (int)$template['code_x'],
                    'code_y' => (int)$template['code_y'],
                    'code_size' => (int)$template['code_size'],
                    'code_color' => $template['code_color'],
                    'code_font' => $template['code_font'] ?? 'Satoshi',
                    'code_bold' => (int)($template['code_bold'] ?? 1),
                    'code_italic' => (int)($template['code_italic'] ?? 0),
                    
                    'background_url' => $template['background_url'],
                    'logo_url' => $template['logo_url'],
                    'logo_x' => (int)$template['logo_x'],
                    'logo_y' => (int)$template['logo_y'],
                    'logo_w' => (int)$template['logo_w'],
                    'logo_h' => (int)$template['logo_h'],
                    'signature_url' => $template['signature_url'],
                    'signature_x' => (int)$template['signature_x'],
                    'signature_y' => (int)$template['signature_y'],
                    'signature_w' => (int)$template['signature_w'],
                    'signature_h' => (int)$template['signature_h'],
                    
                    'custom_text' => $template['custom_text'],
                    'custom_text_x' => (int)$template['custom_text_x'],
                    'custom_text_y' => (int)$template['custom_text_y'],
                    'custom_text_size' => (int)$template['custom_text_size'],
                    'custom_text_color' => $template['custom_text_color'],
                    'custom_text_font' => $template['custom_text_font'] ?? 'Clash Display',
                    'custom_text_bold' => (int)($template['custom_text_bold'] ?? 1),
                    'custom_text_italic' => (int)($template['custom_text_italic'] ?? 0)
                ]
            ]);
            exit;
        } else {
            // Retorna padrões equilibrados caso não possua registro no banco
            echo json_encode([
                'success' => true,
                'exists' => false,
                'template' => [
                    'course_id' => $courseId,
                    'student_name_x' => 100,
                    'student_name_y' => 180,
                    'student_name_size' => 26,
                    'student_name_color' => '#F5F5F7',
                    'student_name_font' => 'Clash Display',
                    'student_name_bold' => 1,
                    'student_name_italic' => 0,
                    
                    'course_title_x' => 100,
                    'course_title_y' => 240,
                    'course_title_size' => 20,
                    'course_title_color' => '#f2c94c',
                    'course_title_font' => 'Clash Display',
                    'course_title_bold' => 1,
                    'course_title_italic' => 0,
                    
                    'date_x' => 100,
                    'date_y' => 300,
                    'date_size' => 12,
                    'date_color' => '#8F8F9D',
                    'date_font' => 'Satoshi',
                    'date_bold' => 1,
                    'date_italic' => 0,
                    
                    'code_x' => 100,
                    'code_y' => 350,
                    'code_size' => 10,
                    'code_color' => '#8F8F9D',
                    'code_font' => 'Satoshi',
                    'code_bold' => 1,
                    'code_italic' => 0,
                    
                    'background_url' => null,
                    'logo_url' => null,
                    'logo_x' => 50,
                    'logo_y' => 50,
                    'logo_w' => 80,
                    'logo_h' => 80,
                    'signature_url' => null,
                    'signature_x' => 450,
                    'signature_y' => 350,
                    'signature_w' => 120,
                    'signature_h' => 60,
                    
                    'custom_text' => 'CERTIFICADO DE ELITE',
                    'custom_text_x' => 325,
                    'custom_text_y' => 60,
                    'custom_text_size' => 16,
                    'custom_text_color' => '#F5F5F7',
                    'custom_text_font' => 'Clash Display',
                    'custom_text_bold' => 1,
                    'custom_text_italic' => 0
                ]
            ]);
            exit;
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno de banco de dados: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'POST') {
    // 2. ESCRITA (POST) - SALVAR OU ATUALIZAR TEMPLATE
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $courseId = filter_var($input['course_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'Curso inválido ou ID do curso ausente.']);
        exit;
    }

    // Filtra e valida os campos
    $sNameX = filter_var($input['student_name_x'] ?? 100, FILTER_VALIDATE_INT);
    $sNameY = filter_var($input['student_name_y'] ?? 180, FILTER_VALIDATE_INT);
    $sNameSize = filter_var($input['student_name_size'] ?? 26, FILTER_VALIDATE_INT);
    $sNameColor = trim($input['student_name_color'] ?? '#F5F5F7');
    $sNameFont = trim($input['student_name_font'] ?? 'Clash Display');
    $sNameBold = filter_var($input['student_name_bold'] ?? 1, FILTER_VALIDATE_INT);
    $sNameItalic = filter_var($input['student_name_italic'] ?? 0, FILTER_VALIDATE_INT);

    $cTitleX = filter_var($input['course_title_x'] ?? 100, FILTER_VALIDATE_INT);
    $cTitleY = filter_var($input['course_title_y'] ?? 240, FILTER_VALIDATE_INT);
    $cTitleSize = filter_var($input['course_title_size'] ?? 20, FILTER_VALIDATE_INT);
    $cTitleColor = trim($input['course_title_color'] ?? '#f2c94c');
    $cTitleFont = trim($input['course_title_font'] ?? 'Clash Display');
    $cTitleBold = filter_var($input['course_title_bold'] ?? 1, FILTER_VALIDATE_INT);
    $cTitleItalic = filter_var($input['course_title_italic'] ?? 0, FILTER_VALIDATE_INT);

    $dateX = filter_var($input['date_x'] ?? 100, FILTER_VALIDATE_INT);
    $dateY = filter_var($input['date_y'] ?? 300, FILTER_VALIDATE_INT);
    $dateSize = filter_var($input['date_size'] ?? 12, FILTER_VALIDATE_INT);
    $dateColor = trim($input['date_color'] ?? '#8F8F9D');
    $dateFont = trim($input['date_font'] ?? 'Satoshi');
    $dateBold = filter_var($input['date_bold'] ?? 1, FILTER_VALIDATE_INT);
    $dateItalic = filter_var($input['date_italic'] ?? 0, FILTER_VALIDATE_INT);

    $codeX = filter_var($input['code_x'] ?? 100, FILTER_VALIDATE_INT);
    $codeY = filter_var($input['code_y'] ?? 350, FILTER_VALIDATE_INT);
    $codeSize = filter_var($input['code_size'] ?? 10, FILTER_VALIDATE_INT);
    $codeColor = trim($input['code_color'] ?? '#8F8F9D');
    $codeFont = trim($input['code_font'] ?? 'Satoshi');
    $codeBold = filter_var($input['code_bold'] ?? 1, FILTER_VALIDATE_INT);
    $codeItalic = filter_var($input['code_italic'] ?? 0, FILTER_VALIDATE_INT);

    $backgroundUrl = trim($input['background_url'] ?? '');
    
    // Novas Colunas
    $logoUrl = trim($input['logo_url'] ?? '');
    $logoX = filter_var($input['logo_x'] ?? 50, FILTER_VALIDATE_INT);
    $logoY = filter_var($input['logo_y'] ?? 50, FILTER_VALIDATE_INT);
    $logoW = filter_var($input['logo_w'] ?? 80, FILTER_VALIDATE_INT);
    $logoH = filter_var($input['logo_h'] ?? 80, FILTER_VALIDATE_INT);

    $sigUrl = trim($input['signature_url'] ?? '');
    $sigX = filter_var($input['signature_x'] ?? 450, FILTER_VALIDATE_INT);
    $sigY = filter_var($input['signature_y'] ?? 350, FILTER_VALIDATE_INT);
    $sigW = filter_var($input['signature_w'] ?? 120, FILTER_VALIDATE_INT);
    $sigH = filter_var($input['signature_h'] ?? 60, FILTER_VALIDATE_INT);

    $customText = trim($input['custom_text'] ?? '');
    $customTextX = filter_var($input['custom_text_x'] ?? 100, FILTER_VALIDATE_INT);
    $customTextY = filter_var($input['custom_text_y'] ?? 120, FILTER_VALIDATE_INT);
    $customTextSize = filter_var($input['custom_text_size'] ?? 16, FILTER_VALIDATE_INT);
    $customTextColor = trim($input['custom_text_color'] ?? '#F5F5F7');
    $customTextFont = trim($input['custom_text_font'] ?? 'Clash Display');
    $customTextBold = filter_var($input['custom_text_bold'] ?? 1, FILTER_VALIDATE_INT);
    $customTextItalic = filter_var($input['custom_text_italic'] ?? 0, FILTER_VALIDATE_INT);

    try {
        // Gravação via INSERT ON DUPLICATE KEY UPDATE
        $saveSQL = "
            INSERT INTO `certificate_templates` 
            (course_id, student_name_x, student_name_y, student_name_size, student_name_color,
             course_title_x, course_title_y, course_title_size, course_title_color,
             date_x, date_y, date_size, date_color,
             code_x, code_y, code_size, code_color, background_url,
             logo_url, logo_x, logo_y, logo_w, logo_h,
             signature_url, signature_x, signature_y, signature_w, signature_h,
             custom_text, custom_text_x, custom_text_y, custom_text_size, custom_text_color,
             student_name_font, student_name_bold, student_name_italic,
             course_title_font, course_title_bold, course_title_italic,
             date_font, date_bold, date_italic,
             code_font, code_bold, code_italic,
             custom_text_font, custom_text_bold, custom_text_italic)
            VALUES 
            (:course_id, :s_x, :s_y, :s_size, :s_color,
             :c_x, :c_y, :c_size, :c_color,
             :d_x, :d_y, :d_size, :d_color,
             :cd_x, :cd_y, :cd_size, :cd_color, :bg_url,
             :logo_url, :logo_x, :logo_y, :logo_w, :logo_h,
             :sig_url, :sig_x, :sig_y, :sig_w, :sig_h,
             :cust_text, :cust_x, :cust_y, :cust_size, :cust_color,
             :s_font, :s_bold, :s_italic,
             :c_font, :c_bold, :c_italic,
             :d_font, :d_bold, :d_italic,
             :cd_font, :cd_bold, :cd_italic,
             :cust_font, :cust_bold, :cust_italic)
            ON DUPLICATE KEY UPDATE
             student_name_x = VALUES(student_name_x),
             student_name_y = VALUES(student_name_y),
             student_name_size = VALUES(student_name_size),
             student_name_color = VALUES(student_name_color),
             course_title_x = VALUES(course_title_x),
             course_title_y = VALUES(course_title_y),
             course_title_size = VALUES(course_title_size),
             course_title_color = VALUES(course_title_color),
             date_x = VALUES(date_x),
             date_y = VALUES(date_y),
             date_size = VALUES(date_size),
             date_color = VALUES(date_color),
             code_x = VALUES(code_x),
             code_y = VALUES(code_y),
             code_size = VALUES(code_size),
             code_color = VALUES(code_color),
             background_url = VALUES(background_url),
             logo_url = VALUES(logo_url),
             logo_x = VALUES(logo_x),
             logo_y = VALUES(logo_y),
             logo_w = VALUES(logo_w),
             logo_h = VALUES(logo_h),
             signature_url = VALUES(signature_url),
             signature_x = VALUES(signature_x),
             signature_y = VALUES(signature_y),
             signature_w = VALUES(signature_w),
             signature_h = VALUES(signature_h),
             custom_text = VALUES(custom_text),
             custom_text_x = VALUES(custom_text_x),
             custom_text_y = VALUES(custom_text_y),
             custom_text_size = VALUES(custom_text_size),
             custom_text_color = VALUES(custom_text_color),
             student_name_font = VALUES(student_name_font),
             student_name_bold = VALUES(student_name_bold),
             student_name_italic = VALUES(student_name_italic),
             course_title_font = VALUES(course_title_font),
             course_title_bold = VALUES(course_title_bold),
             course_title_italic = VALUES(course_title_italic),
             date_font = VALUES(date_font),
             date_bold = VALUES(date_bold),
             date_italic = VALUES(date_italic),
             code_font = VALUES(code_font),
             code_bold = VALUES(code_bold),
             code_italic = VALUES(code_italic),
             custom_text_font = VALUES(custom_text_font),
             custom_text_bold = VALUES(custom_text_bold),
             custom_text_italic = VALUES(custom_text_italic)
        ";

        $stmt = $db->prepare($saveSQL);
        $stmt->execute([
            ':course_id' => $courseId,
            ':s_x' => $sNameX,
            ':s_y' => $sNameY,
            ':s_size' => $sNameSize,
            ':s_color' => $sNameColor,
            ':c_x' => $cTitleX,
            ':c_y' => $cTitleY,
            ':c_size' => $cTitleSize,
            ':c_color' => $cTitleColor,
            ':d_x' => $dateX,
            ':d_y' => $dateY,
            ':d_size' => $dateSize,
            ':d_color' => $dateColor,
            ':cd_x' => $codeX,
            ':cd_y' => $codeY,
            ':cd_size' => $codeSize,
            ':cd_color' => $codeColor,
            ':bg_url' => !empty($backgroundUrl) ? $backgroundUrl : null,
            ':logo_url' => !empty($logoUrl) ? $logoUrl : null,
            ':logo_x' => $logoX,
            ':logo_y' => $logoY,
            ':logo_w' => $logoW,
            ':logo_h' => $logoH,
            ':sig_url' => !empty($sigUrl) ? $sigUrl : null,
            ':sig_x' => $sigX,
            ':sig_y' => $sigY,
            ':sig_w' => $sigW,
            ':sig_h' => $sigH,
            ':cust_text' => !empty($customText) ? $customText : null,
            ':cust_x' => $customTextX,
            ':cust_y' => $customTextY,
            ':cust_size' => $customTextSize,
            ':cust_color' => $customTextColor,
            ':s_font' => $sNameFont,
            ':s_bold' => $sNameBold,
            ':s_italic' => $sNameItalic,
            ':c_font' => $cTitleFont,
            ':c_bold' => $cTitleBold,
            ':c_italic' => $cTitleItalic,
            ':d_font' => $dateFont,
            ':d_bold' => $dateBold,
            ':d_italic' => $dateItalic,
            ':cd_font' => $codeFont,
            ':cd_bold' => $codeBold,
            ':cd_italic' => $codeItalic,
            ':cust_font' => $customTextFont,
            ':cust_bold' => $customTextBold,
            ':cust_italic' => $customTextItalic
        ]);

        // Grava log de atividade
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin, 'salvar_template_certificado', :resource, :details)");
        $logStmt->execute([
            ':admin' => $_SESSION['user_id'],
            ':resource' => "certificates/templates/{$courseId}",
            ':details' => "Template de certificado salvo/atualizado para o curso ID {$courseId} com suporte estendido"
        ]);

        echo json_encode(['success' => true, 'message' => 'Template de certificado salvo com sucesso no banco de dados.']);
        exit;

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno ao salvar template no banco: ' . $e->getMessage()]);
        exit;
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não suportado.']);
    exit;
}
