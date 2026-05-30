<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método de requisição não permitido. Use POST.']);
    exit;
}

// Lê o corpo da requisição (JSON ou POST)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);
$watchedDuration = filter_var($input['watched_duration'] ?? 0, FILTER_VALIDATE_INT);
$completedInput = filter_var($input['completed'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$lessonId || $watchedDuration < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados de progresso incompletos ou inválidos.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Verifica se a aula de fato existe
    $lessonStmt = $db->prepare("SELECT id, duration FROM lessons WHERE id = :id LIMIT 1");
    $lessonStmt->execute([':id' => $lessonId]);
    $lesson = $lessonStmt->fetch();

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Aula não encontrada.']);
        exit;
    }

    $db->beginTransaction();

    // 2. Busca progresso existente do usuário nesta aula
    $progStmt = $db->prepare("SELECT id, completed FROM lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id LIMIT 1");
    $progStmt->execute([
        ':user_id' => $userId,
        ':lesson_id' => $lessonId
    ]);
    $existingProgress = $progStmt->fetch();

    $alreadyCompleted = $existingProgress ? (bool)$existingProgress['completed'] : false;
    $shouldMarkCompleted = $completedInput || ($watchedDuration >= $lesson['duration'] && $lesson['duration'] > 0);

    // Se assistiu mais que a duração total, limita ao teto
    if ($lesson['duration'] > 0 && $watchedDuration > $lesson['duration']) {
        $watchedDuration = $lesson['duration'];
    }

    $completedAt = null;
    $completedValue = 0;

    if ($alreadyCompleted) {
        $completedValue = 1;
    } elseif ($shouldMarkCompleted) {
        $completedValue = 1;
        $completedAt = date('Y-m-d H:i:s');
    }

    // 3. Salva ou atualiza a tabela `lesson_progress`
    if ($existingProgress) {
        $updateStmt = $db->prepare("UPDATE lesson_progress SET watched_duration = :watched_duration, completed = :completed, completed_at = COALESCE(completed_at, :completed_at) WHERE id = :id");
        $updateStmt->execute([
            ':watched_duration' => $watchedDuration,
            ':completed' => $completedValue,
            ':completed_at' => $completedAt,
            ':id' => $existingProgress['id']
        ]);
    } else {
        $insertStmt = $db->prepare("INSERT INTO lesson_progress (user_id, lesson_id, watched_duration, completed, completed_at) VALUES (:user_id, :lesson_id, :watched_duration, :completed, :completed_at)");
        $insertStmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId,
            ':watched_duration' => $watchedDuration,
            ':completed' => $completedValue,
            ':completed_at' => $completedAt
        ]);
    }

    $xpAwarded = 0;
    $levelUp = false;
    $newLevel = 1;
    $newXp = 0;
    $unlockedFirstLessonBadge = false;

    // 4. Se a aula acabou de ser concluída, processa a gamificação!
    if ($completedValue === 1 && !$alreadyCompleted) {
        // Aluno ganha 50 XP padrão por aula concluída
        $xpAwarded += 50;

        // Busca dados de XP/Level atuais do usuário
        $userStmt = $db->prepare("SELECT xp, level FROM users WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch();

        $currentXp = $user['xp'] ?? 0;
        $currentLevel = $user['level'] ?? 1;

        // Verifica se é a PRIMEIRA AULA concluída do aluno (Achievement 1: Primeiro Passo)
        $countStmt = $db->prepare("SELECT COUNT(*) FROM lesson_progress WHERE user_id = :user_id AND completed = 1");
        $countStmt->execute([':user_id' => $userId]);
        $completedCount = (int)$countStmt->fetchColumn();

        if ($completedCount === 1) {
            // Verifica se já não desbloqueou
            $achieveCheck = $db->prepare("SELECT id FROM user_achievements WHERE user_id = :user_id AND achievement_id = 1");
            $achieveCheck->execute([':user_id' => $userId]);
            
            if (!$achieveCheck->fetch()) {
                // Insere conquista
                $achieveInsert = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (:user_id, 1)");
                $achieveInsert->execute([':user_id' => $userId]);

                // Bônus de 100 XP
                $xpAwarded += 100;
                $unlockedFirstLessonBadge = true;

                // Notificação de Conquista
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Conquista Desbloqueada! 🎖️', 'Você desbloqueou a medalha: Primeiro Passo!', 'achievement')");
                $notifStmt->execute([':user_id' => $userId]);
            }
        }

        // Calcula novos XP e Nível
        $newXp = $currentXp + $xpAwarded;
        $newLevel = floor($newXp / 1000) + 1; // 1000 XP por Nível
        if ($newLevel > $currentLevel) {
            $levelUp = true;
            // Notificação de Level Up
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Subiu de Nível! 🏆', :msg, 'achievement')");
            $notifStmt->execute([
                ':user_id' => $userId,
                ':msg' => "Parabéns! Você alcançou o Nível {$newLevel} na GT Cursos. Continue avançando!"
            ]);
        }

        // Atualiza o perfil do usuário
        $updateUserStmt = $db->prepare("UPDATE users SET xp = :xp, level = :level, last_activity = NOW() WHERE id = :id");
        $updateUserStmt->execute([
            ':xp' => $newXp,
            ':level' => $newLevel,
            ':id' => $userId
        ]);
    } else {
        // Apenas atualiza a última atividade
        $updateUserStmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
        $updateUserStmt->execute([':id' => $userId]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Progresso atualizado com sucesso.',
        'data' => [
            'watched_duration' => $watchedDuration,
            'completed' => ($completedValue === 1),
            'xp_awarded' => $xpAwarded,
            'level_up' => $levelUp,
            'new_level' => $levelUp ? $newLevel : null,
            'achievement_unlocked' => $unlockedFirstLessonBadge
        ]
    ]);
    exit;

} catch (\PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Ocorreu um erro ao salvar o progresso da aula.']);
    }
    exit;
}
