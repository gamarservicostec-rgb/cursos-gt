<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não permitido. Use GET.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca dados gerais de XP, nível e streak do usuário
    $userStmt = $db->prepare("SELECT xp, level, current_streak, last_activity, name, email FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuário não encontrado.']);
        exit;
    }

    // Algoritmo de streak (ofensiva diária):
    // Se a última atividade foi hoje ou ontem, o streak é mantido.
    // Se a última atividade foi há mais de 1 dia, zera o streak.
    $currentStreak = (int)$user['current_streak'];
    if ($user['last_activity']) {
        $lastActDate = new DateTime($user['last_activity']);
        $todayDate = new DateTime();
        
        $interval = $todayDate->diff($lastActDate);
        $daysDiff = $interval->days;

        if ($daysDiff > 1) {
            // Zera o streak no banco de dados e localmente
            $currentStreak = 0;
            $resetStreakStmt = $db->prepare("UPDATE users SET current_streak = 0 WHERE id = :id");
            $resetStreakStmt->execute([':id' => $userId]);
        }
    }

    // 2. Busca o curso ativo do aluno (matrícula ativa)
    $enrollStmt = $db->prepare("SELECT course_id FROM enrollments WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $enrollStmt->execute([':user_id' => $userId]);
    $enrollment = $enrollStmt->fetch();

    $courseProgress = [
        'has_active_course' => false,
        'course_id' => null,
        'course_title' => null,
        'completed_lessons' => 0,
        'total_lessons' => 0,
        'percentage' => 0
    ];

    if ($enrollment) {
        $courseId = $enrollment['course_id'];
        
        // Busca título do curso
        $cStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
        $cStmt->execute([':id' => $courseId]);
        $courseTitle = $cStmt->fetchColumn();

        // Conta total de aulas no curso
        $totalStmt = $db->prepare("
            SELECT COUNT(l.id) 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            WHERE m.course_id = :course_id
        ");
        $totalStmt->execute([':course_id' => $courseId]);
        $totalLessons = (int)$totalStmt->fetchColumn();

        // Conta aulas concluídas pelo aluno no curso
        $compStmt = $db->prepare("
            SELECT COUNT(lp.id) 
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            WHERE lp.user_id = :user_id AND m.course_id = :course_id AND lp.completed = 1
        ");
        $compStmt->execute([
            ':user_id' => $userId,
            ':course_id' => $courseId
        ]);
        $completedLessons = (int)$compStmt->fetchColumn();

        $percentage = ($totalLessons > 0) ? round(($completedLessons / $totalLessons) * 100) : 0;

        $courseProgress = [
            'has_active_course' => true,
            'course_id' => (int)$courseId,
            'course_title' => htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'),
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'percentage' => (int)$percentage
        ];
    }

    // 3. Busca conquistas/medalhas desbloqueadas pelo aluno
    $achieveStmt = $db->prepare("
        SELECT a.id, a.title, a.description, a.icon_url, a.xp_bonus, ua.unlocked_at 
        FROM user_achievements ua
        JOIN achievements a ON ua.achievement_id = a.id
        WHERE ua.user_id = :user_id
        ORDER BY ua.unlocked_at DESC
    ");
    $achieveStmt->execute([':user_id' => $userId]);
    $unlockedAchievements = $achieveStmt->fetchAll();

    $achievementsList = array_map(function($a) {
        return [
            'id' => (int)$a['id'],
            'title' => htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars($a['description'], ENT_QUOTES, 'UTF-8'),
            'icon_url' => $a['icon_url'] ? htmlspecialchars($a['icon_url'], ENT_QUOTES, 'UTF-8') : null,
            'xp_bonus' => (int)$a['xp_bonus'],
            'unlocked_at' => $a['unlocked_at']
        ];
    }, $unlockedAchievements);

    // 4. Progresso de nível (Ex: Nível 1 vai de 0 a 999 XP. Progresso de Nível 1 = (XP atual / 1000) * 100)
    $xpInCurrentLevel = $user['xp'] % 1000;
    $levelProgressPercentage = round(($xpInCurrentLevel / 1000) * 100);

    echo json_encode([
        'success' => true,
        'user' => [
            'name' => htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'),
            'xp' => (int)$user['xp'],
            'level' => (int)$user['level'],
            'level_progress_percentage' => (int)$levelProgressPercentage,
            'xp_needed_next_level' => 1000 - $xpInCurrentLevel,
            'streak' => $currentStreak
        ],
        'course_progress' => $courseProgress,
        'achievements' => $achievementsList
    ]);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Erro de banco de dados ao buscar dados de engajamento.']);
    }
    exit;
}
