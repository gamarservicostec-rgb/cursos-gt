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
    // 1. Busca os TOP 10 alunos por XP
    $query = "SELECT id, name, role, xp, level, avatar_url 
              FROM users 
              WHERE role = 'student'
              ORDER BY xp DESC, name ASC 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $topUsers = $stmt->fetchAll();

    $leaderboard = [];
    $rank = 1;
    $userInTopTen = false;
    $userRank = null;

    foreach ($topUsers as $u) {
        $isMe = ($u['id'] == $userId);
        if ($isMe) {
            $userInTopTen = true;
            $userRank = $rank;
        }

        $leaderboard[] = [
            'rank' => $rank,
            'name' => htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'),
            'xp' => (int)$u['xp'],
            'level' => (int)$u['level'],
            'avatar_url' => $u['avatar_url'] ? htmlspecialchars($u['avatar_url'], ENT_QUOTES, 'UTF-8') : null,
            'is_me' => $isMe
        ];
        $rank++;
    }

    // 2. Se o usuário logado não estiver no Top 10, calcula a posição dele de forma dinâmica
    if (!$userInTopTen) {
        // Conta quantos alunos possuem mais XP do que ele
        $myXpStmt = $db->prepare("SELECT xp, name, level, avatar_url FROM users WHERE id = :id LIMIT 1");
        $myXpStmt->execute([':id' => $userId]);
        $me = $myXpStmt->fetch();

        if ($me) {
            $myXp = $me['xp'];
            
            $rankStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND (xp > :xp OR (xp = :xp AND name < :name))");
            $rankStmt->execute([
                ':xp' => $myXp,
                ':name' => $me['name']
            ]);
            $higherXpCount = (int)$rankStmt->fetchColumn();
            
            $userRank = $higherXpCount + 1;

            $myStats = [
                'rank' => $userRank,
                'name' => htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8'),
                'xp' => (int)$myXp,
                'level' => (int)$me['level'],
                'avatar_url' => $me['avatar_url'] ? htmlspecialchars($me['avatar_url'], ENT_QUOTES, 'UTF-8') : null,
                'is_me' => true
            ];
        }
    } else {
        // Se já está no top 10, busca suas informações para consistência no retorno
        $myStatsStmt = $db->prepare("SELECT xp, level, name, avatar_url FROM users WHERE id = :id LIMIT 1");
        $myStatsStmt->execute([':id' => $userId]);
        $me = $myStatsStmt->fetch();

        $myStats = [
            'rank' => $userRank,
            'name' => htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8'),
            'xp' => (int)$me['xp'],
            'level' => (int)$me['level'],
            'avatar_url' => $me['avatar_url'] ? htmlspecialchars($me['avatar_url'], ENT_QUOTES, 'UTF-8') : null,
            'is_me' => true
        ];
    }

    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard,
        'my_position' => $myStats
    ]);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Ocorreu um erro ao carregar o ranking global de alunos.']);
    }
    exit;
}
