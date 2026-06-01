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
    // 1. LEITURA (GET) - LISTAR TODOS OU BUSCAR ESTRUTURA DE UM CURSO
    $courseId = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;

    try {
        if ($courseId) {
            // Retorna a árvore completa (Pensando no Editor de Grade/Syllabus do Admin)
            $courseQuery = "SELECT id, title, description, price, type, status, thumbnail_url, category_id, duration_days, weekdays_only, available_hours, what_learn, materials_included, access_type, certificate_info FROM courses WHERE id = :id LIMIT 1";
            $cStmt = $db->prepare($courseQuery);
            $cStmt->execute([':id' => $courseId]);
            $course = $cStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$course) {
                http_response_code(404);
                echo json_encode(['error' => 'Curso não encontrado.']);
                exit;
            }

            // Busca módulos do curso
            $modQuery = "SELECT id, title, description, sort_order FROM modules WHERE course_id = :course_id ORDER BY sort_order ASC";
            $mStmt = $db->prepare($modQuery);
            $mStmt->execute([':course_id' => $courseId]);
            $modules = $mStmt->fetchAll();

            $curriculum = [];
            foreach ($modules as $m) {
                // Busca matérias de cada módulo
                $subQuery = "SELECT id, title, sort_order FROM subjects WHERE module_id = :module_id ORDER BY sort_order ASC";
                $sStmt = $db->prepare($subQuery);
                $sStmt->execute([':module_id' => $m['id']]);
                $subjects = $sStmt->fetchAll();

                $formattedSubjects = [];
                foreach ($subjects as $s) {
                    // Busca aulas de cada matéria
                    $lessQuery = "SELECT id, title, description, video_provider, video_url, duration, attachment_url, sort_order 
                                  FROM lessons 
                                  WHERE subject_id = :subject_id 
                                  ORDER BY sort_order ASC";
                    $lStmt = $db->prepare($lessQuery);
                    $lStmt->execute([':subject_id' => $s['id']]);
                    $lessons = $lStmt->fetchAll();

                    $formattedSubjects[] = [
                        'id' => (int)$s['id'],
                        'title' => htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'),
                        'sort_order' => (int)$s['sort_order'],
                        'lessons' => array_map(function($l) {
                            return [
                                'id' => (int)$l['id'],
                                'title' => htmlspecialchars($l['title'], ENT_QUOTES, 'UTF-8'),
                                'description' => $l['description'] ? htmlspecialchars($l['description'], ENT_QUOTES, 'UTF-8') : null,
                                'video_provider' => $l['video_provider'],
                                'video_url' => $l['video_url'],
                                'duration' => (int)$l['duration'],
                                'attachment_url' => $l['attachment_url'],
                                'sort_order' => (int)$l['sort_order']
                            ];
                        }, $lessons)
                    ];
                }

                $curriculum[] = [
                    'id' => (int)$m['id'],
                    'title' => htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8'),
                    'description' => $m['description'] ? htmlspecialchars($m['description'], ENT_QUOTES, 'UTF-8') : null,
                    'sort_order' => (int)$m['sort_order'],
                    'subjects' => $formattedSubjects
                ];
            }

            echo json_encode([
                'success' => true,
                'course' => [
                    'id' => (int)$course['id'],
                    'title' => htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8'),
                    'price' => (float)$course['price'],
                    'type' => $course['type'],
                    'status' => $course['status'],
                    'thumbnail_url' => $course['thumbnail_url'],
                    'category_id' => $course['category_id'] ? (int)$course['category_id'] : null,
                    'duration_days' => $course['duration_days'] ? (int)$course['duration_days'] : null,
                    'weekdays_only' => $course['weekdays_only'] !== null ? (int)$course['weekdays_only'] : 1,
                    'available_hours' => $course['available_hours'] ? htmlspecialchars($course['available_hours'], ENT_QUOTES, 'UTF-8') : null,
                    'curriculum' => $curriculum
                ]
            ]);
            exit;

        } else {
            // Listagem geral de cursos para o Painel do Admin
            $stmt = $db->prepare("SELECT c.id, c.title, c.description, c.price, c.type, c.status, c.thumbnail_url, c.category_id, c.created_at, cat.name as category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id ORDER BY c.created_at DESC");
            $stmt->execute();
            $courses = $stmt->fetchAll();

            $formatted = array_map(function($c) {
                return [
                    'id' => (int)$c['id'],
                    'title' => htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8'),
                    'price' => (float)$c['price'],
                    'type' => $c['type'],
                    'status' => $c['status'],
                    'thumbnail_url' => $c['thumbnail_url'],
                    'category_id' => $c['category_id'] ? (int)$c['category_id'] : null,
                    'category_name' => $c['category_name'] ? htmlspecialchars($c['category_name'], ENT_QUOTES, 'UTF-8') : 'Sem Categoria',
                    'created_at' => $c['created_at']
                ];
            }, $courses);

            echo json_encode($formatted);
            exit;
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro SQL: ' . $e->getMessage()]);
        exit;
    }

} elseif ($method === 'POST') {
    // 2. ESCRITA/MUTABILIDADE (POST) - CRUD VIA AÇÕES
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $action = $input['action'] ?? '';

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ação de catálogo de cursos não informada (parâmetro action ausente).']);
        exit;
    }

    try {
        switch ($action) {
            case 'create_course':
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $price = filter_var($input['price'] ?? 0.00, FILTER_VALIDATE_FLOAT);
                $type = $input['type'] ?? 'hybrid'; // 'ead', 'presencial', 'hybrid'
                $status = $input['status'] ?? 'active';
                $thumbnailUrl = trim($input['thumbnail_url'] ?? '');
                $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : null;
                
                // Propriedades do modelo híbrido
                $durationDays = isset($input['duration_days']) && $input['duration_days'] !== '' ? (int)$input['duration_days'] : null;
                $weekdaysOnly = isset($input['weekdays_only']) ? (int)$input['weekdays_only'] : 1;
                $availableHours = isset($input['available_hours']) ? trim($input['available_hours']) : null;
                
                // Novos campos táticos
                $whatLearn = isset($input['what_learn']) ? trim($input['what_learn']) : null;
                $materialsIncluded = isset($input['materials_included']) ? trim($input['materials_included']) : null;
                $accessType = isset($input['access_type']) ? trim($input['access_type']) : null;
                $certificateInfo = isset($input['certificate_info']) ? trim($input['certificate_info']) : null;

                if (empty($title) || empty($description) || $price < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de curso inválidos ou incompletos.']);
                    exit;
                }

                $stmt = $db->prepare("INSERT INTO courses (title, description, price, type, status, thumbnail_url, category_id, duration_days, weekdays_only, available_hours, what_learn, materials_included, access_type, certificate_info) VALUES (:title, :description, :price, :type, :status, :thumbnail_url, :category_id, :duration_days, :weekdays_only, :available_hours, :what_learn, :materials_included, :access_type, :certificate_info)");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':price' => $price,
                    ':type' => $type,
                    ':status' => $status,
                    ':thumbnail_url' => $thumbnailUrl,
                    ':category_id' => $categoryId,
                    ':duration_days' => $durationDays,
                    ':weekdays_only' => $weekdaysOnly,
                    ':available_hours' => $availableHours,
                    ':what_learn' => $whatLearn,
                    ':materials_included' => $materialsIncluded,
                    ':access_type' => $accessType,
                    ':certificate_info' => $certificateInfo
                ]);

                $newId = $db->lastInsertId();

                // Registra na auditoria administrativa
                $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'criar_curso', :resource, :details)");
                $logStmt->execute([
                    ':admin_id' => $_SESSION['user_id'],
                    ':resource' => "courses/{$newId}",
                    ':details' => "Novo curso registrado: {$title}"
                ]);

                echo json_encode(['success' => true, 'message' => 'Curso criado com sucesso.', 'course_id' => (int)$newId]);
                exit;

            case 'update_course':
                $courseId = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $price = filter_var($input['price'] ?? 0.00, FILTER_VALIDATE_FLOAT);
                $type = $input['type'] ?? 'hybrid';
                $status = $input['status'] ?? 'active';
                $thumbnailUrl = trim($input['thumbnail_url'] ?? '');
                $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : null;
                
                // Propriedades do modelo híbrido
                $durationDays = isset($input['duration_days']) && $input['duration_days'] !== '' ? (int)$input['duration_days'] : null;
                $weekdaysOnly = isset($input['weekdays_only']) ? (int)$input['weekdays_only'] : 1;
                $availableHours = isset($input['available_hours']) ? trim($input['available_hours']) : null;
                
                // Novos campos táticos
                $whatLearn = isset($input['what_learn']) ? trim($input['what_learn']) : null;
                $materialsIncluded = isset($input['materials_included']) ? trim($input['materials_included']) : null;
                $accessType = isset($input['access_type']) ? trim($input['access_type']) : null;
                $certificateInfo = isset($input['certificate_info']) ? trim($input['certificate_info']) : null;

                if (!$courseId || empty($title) || empty($description) || $price < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de atualização inválidos ou incompletos.']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE courses SET title = :title, description = :description, price = :price, type = :type, status = :status, thumbnail_url = :thumbnail_url, category_id = :category_id, duration_days = :duration_days, weekdays_only = :weekdays_only, available_hours = :available_hours, what_learn = :what_learn, materials_included = :materials_included, access_type = :access_type, certificate_info = :certificate_info WHERE id = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':price' => $price,
                    ':type' => $type,
                    ':status' => $status,
                    ':thumbnail_url' => $thumbnailUrl,
                    ':category_id' => $categoryId,
                    ':duration_days' => $durationDays,
                    ':weekdays_only' => $weekdaysOnly,
                    ':available_hours' => $availableHours,
                    ':what_learn' => $whatLearn,
                    ':materials_included' => $materialsIncluded,
                    ':access_type' => $accessType,
                    ':certificate_info' => $certificateInfo,
                    ':id' => $courseId
                ]);

                // Registra na auditoria
                $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'editar_curso', :resource, :details)");
                $logStmt->execute([
                    ':admin_id' => $_SESSION['user_id'],
                    ':resource' => "courses/{$courseId}",
                    ':details' => "Curso editado: {$title}"
                ]);

                echo json_encode(['success' => true, 'message' => 'Curso atualizado com sucesso.']);
                exit;

            case 'delete_course':
                $courseId = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);

                if (!$courseId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do curso inválido para exclusão.']);
                    exit;
                }

                $stmt = $db->prepare("DELETE FROM courses WHERE id = :id");
                $stmt->execute([':id' => $courseId]);

                // Registra na auditoria
                $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'excluir_curso', :resource, :details)");
                $logStmt->execute([
                    ':admin_id' => $_SESSION['user_id'],
                    ':resource' => "courses/{$courseId}",
                    ':details' => "Curso ID {$courseId} excluído do sistema"
                ]);

                echo json_encode(['success' => true, 'message' => 'Curso excluído com sucesso do catálogo.']);
                exit;

            case 'create_module':
                $courseId = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);

                if (!$courseId || empty($title)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de módulo inválidos ou incompletos.']);
                    exit;
                }

                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("INSERT INTO modules (course_id, title, description, sort_order) VALUES (:course_id, :title, :description, :sort_order)");
                    $stmt->execute([
                        ':course_id' => $courseId,
                        ':title' => $title,
                        ':description' => $description,
                        ':sort_order' => $sortOrder
                    ]);

                    $moduleId = $db->lastInsertId();

                    // Cria automaticamente a matéria (subject) padrão para este módulo
                    $subStmt = $db->prepare("INSERT INTO subjects (module_id, title, sort_order) VALUES (:module_id, :title, 1)");
                    $subStmt->execute([
                        ':module_id' => $moduleId,
                        ':title' => "Geral: " . $title
                    ]);
                    $subjectId = $db->lastInsertId();

                    $db->commit();

                    echo json_encode([
                        'success' => true, 
                        'message' => 'Módulo e matéria padrão adicionados com sucesso.', 
                        'module_id' => (int)$moduleId,
                        'subject_id' => (int)$subjectId
                    ]);
                    exit;
                } catch (\PDOException $e) {
                    $db->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao criar módulo e matéria: ' . $e->getMessage()]);
                    exit;
                }

            case 'update_module':
                $moduleId = filter_var($input['module_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);

                if (!$moduleId || empty($title)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de módulo inválidos ou incompletos para atualização.']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE modules SET title = :title, description = :description, sort_order = :sort_order WHERE id = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':sort_order' => $sortOrder,
                    ':id' => $moduleId
                ]);

                echo json_encode(['success' => true, 'message' => 'Módulo atualizado com sucesso.']);
                exit;

            case 'delete_module':
                $moduleId = filter_var($input['module_id'] ?? 0, FILTER_VALIDATE_INT);

                if (!$moduleId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do módulo inválido para exclusão.']);
                    exit;
                }

                $stmt = $db->prepare("DELETE FROM modules WHERE id = :id");
                $stmt->execute([':id' => $moduleId]);

                echo json_encode(['success' => true, 'message' => 'Módulo excluído com sucesso do curso.']);
                exit;

            case 'create_subject':
                $moduleId = filter_var($input['module_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $sortOrder = filter_var($input['sort_order'] ?? 1, FILTER_VALIDATE_INT);

                if (!$moduleId || empty($title)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de matéria inválidos ou incompletos.']);
                    exit;
                }

                $stmt = $db->prepare("INSERT INTO subjects (module_id, title, sort_order) VALUES (:module_id, :title, :sort_order)");
                $stmt->execute([
                    ':module_id' => $moduleId,
                    ':title' => $title,
                    ':sort_order' => $sortOrder
                ]);

                echo json_encode(['success' => true, 'message' => 'Matéria adicionada com sucesso.', 'subject_id' => (int)$db->lastInsertId()]);
                exit;

            case 'update_subject':
                $subjectId = filter_var($input['subject_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $sortOrder = filter_var($input['sort_order'] ?? 1, FILTER_VALIDATE_INT);

                if (!$subjectId || empty($title)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de matéria inválidos ou incompletos para atualização.']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE subjects SET title = :title, sort_order = :sort_order WHERE id = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':sort_order' => $sortOrder,
                    ':id' => $subjectId
                ]);

                echo json_encode(['success' => true, 'message' => 'Matéria atualizada com sucesso.']);
                exit;

            case 'delete_subject':
                $subjectId = filter_var($input['subject_id'] ?? 0, FILTER_VALIDATE_INT);

                if (!$subjectId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID da matéria inválido para exclusão.']);
                    exit;
                }

                $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
                $stmt->execute([':id' => $subjectId]);

                echo json_encode(['success' => true, 'message' => 'Matéria excluída com sucesso do módulo.']);
                exit;

            case 'create_lesson':
                $subjectId = filter_var($input['subject_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $videoUrl = trim($input['video_url'] ?? '');
                $duration = filter_var($input['duration'] ?? 0, FILTER_VALIDATE_INT);
                $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
                $attachmentUrl = trim($input['attachment_url'] ?? '');

                if (!$subjectId || empty($title) || empty($videoUrl)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de aula inválidos ou incompletos.']);
                    exit;
                }

                $stmt = $db->prepare("INSERT INTO lessons (subject_id, title, description, video_provider, video_url, duration, sort_order, attachment_url) 
                                      VALUES (:subject_id, :title, :description, 'bunny', :video_url, :duration, :sort_order, :attachment_url)");
                $stmt->execute([
                    ':subject_id' => $subjectId,
                    ':title' => $title,
                    ':description' => $description,
                    ':video_url' => $videoUrl,
                    ':duration' => $duration,
                    ':sort_order' => $sortOrder,
                    ':attachment_url' => $attachmentUrl
                ]);

                echo json_encode(['success' => true, 'message' => 'Aula cadastrada com sucesso na matéria.', 'lesson_id' => (int)$db->lastInsertId()]);
                exit;

            case 'update_lesson':
                $lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                $videoUrl = trim($input['video_url'] ?? '');
                $duration = filter_var($input['duration'] ?? 0, FILTER_VALIDATE_INT);
                $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
                $attachmentUrl = trim($input['attachment_url'] ?? '');

                if (!$lessonId || empty($title) || empty($videoUrl)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados de atualização de aula inválidos ou incompletos.']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE lessons 
                                       SET title = :title, description = :description, video_url = :video_url, duration = :duration, sort_order = :sort_order, attachment_url = :attachment_url 
                                       WHERE id = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':video_url' => $videoUrl,
                    ':duration' => $duration,
                    ':sort_order' => $sortOrder,
                    ':attachment_url' => $attachmentUrl,
                    ':id' => $lessonId
                ]);

                echo json_encode(['success' => true, 'message' => 'Aula atualizada com sucesso.']);
                exit;

            case 'delete_lesson':
                $lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);

                if (!$lessonId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID da aula inválido para exclusão.']);
                    exit;
                }

                $stmt = $db->prepare("DELETE FROM lessons WHERE id = :id");
                $stmt->execute([':id' => $lessonId]);

                echo json_encode(['success' => true, 'message' => 'Aula excluída com sucesso.']);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Ação de catálogo solicitada inválida.']);
                exit;
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno de processamento SQL: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição inválido.']);
    exit;
}
