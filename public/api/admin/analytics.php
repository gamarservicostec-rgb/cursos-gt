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

// Inicia sessão e requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use GET.']);
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Receita total aprovada
    $revStmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE status = 'approved'");
    $revStmt->execute();
    $totalRevenue = (float)$revStmt->fetchColumn();

    // 2. Faturamento deste mês (MRR Fictício de Matrículas no Mês)
    $thisMonthStart = date('Y-m-01 00:00:00');
    $mrrStmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE status = 'approved' AND created_at >= :start");
    $mrrStmt->execute([':start' => $thisMonthStart]);
    $monthlyRevenue = (float)$mrrStmt->fetchColumn();

    // 3. Quantidade de transações aprovadas
    $transCountStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE status = 'approved'");
    $transCountStmt->execute();
    $totalApprovedSales = (int)$transCountStmt->fetchColumn();

    // 4. Quantidade de alunos ativos cadastrados
    $studentsStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $studentsStmt->execute();
    $activeStudents = (int)$studentsStmt->fetchColumn();

    // 5. Quantidade de cursos na grade
    $coursesStmt = $db->prepare("SELECT COUNT(*) FROM courses");
    $coursesStmt->execute();
    $totalCourses = (int)$coursesStmt->fetchColumn();

    // 6. Últimas 5 transações registradas
    $lastSalesStmt = $db->prepare("
        SELECT t.id, t.payment_id, t.amount, t.payment_method, t.status, t.created_at, u.name as student_name, c.title as course_title
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        JOIN courses c ON t.course_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $lastSalesStmt->execute();
    $sales = $lastSalesStmt->fetchAll();

    $formattedSales = array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'payment_id' => $s['payment_id'],
            'amount' => (float)$s['amount'],
            'payment_method' => $s['payment_method'],
            'status' => $s['status'],
            'created_at' => $s['created_at'],
            'student_name' => htmlspecialchars($s['student_name'], ENT_QUOTES, 'UTF-8'),
            'course_title' => htmlspecialchars($s['course_title'], ENT_QUOTES, 'UTF-8')
        ];
    }, $sales);

    // 7. Últimos 5 logs de auditoria de segurança
    $logsStmt = $db->prepare("
        SELECT a.action, a.details, a.ip_address, a.created_at, u.name as user_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $logsStmt->execute();
    $logs = $logsStmt->fetchAll();

    $formattedLogs = array_map(function($l) {
        return [
            'action' => $l['action'],
            'details' => $l['details'] ? htmlspecialchars($l['details'], ENT_QUOTES, 'UTF-8') : null,
            'ip_address' => $l['ip_address'],
            'created_at' => $l['created_at'],
            'user_name' => $l['user_name'] ? htmlspecialchars($l['user_name'], ENT_QUOTES, 'UTF-8') : 'Visitante'
        ];
    }, $logs);

    // 8. Gráfico de vendas dos últimos 6 meses (Simulação estruturada)
    $chartData = [
        'labels' => [],
        'data' => []
    ];

    for ($i = 5; $i >= 0; $i--) {
        $monthOffset = '-' . $i . ' month';
        $monthName = date('M', strtotime($monthOffset));
        $monthStart = date('Y-m-01 00:00:00', strtotime($monthOffset));
        $monthEnd = date('Y-m-t 23:59:59', strtotime($monthOffset));

        $chartQuery = $db->prepare("SELECT SUM(amount) FROM transactions WHERE status = 'approved' AND created_at BETWEEN :start AND :end");
        $chartQuery->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $sum = (float)$chartQuery->fetchColumn();

        $chartData['labels'][] = $monthName;
        $chartData['data'][] = $sum;
    }

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'total_approved_sales' => $totalApprovedSales,
            'active_students' => $activeStudents,
            'total_courses' => $totalCourses
        ],
        'chart_data' => $chartData,
        'recent_sales' => $formattedSales,
        'recent_logs' => $formattedLogs
    ]);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    if (AppConfig::$DEV_MODE) {
        echo json_encode(['error' => 'Erro interno de servidor: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Erro de banco de dados ao carregar dados do dashboard administrativo.']);
    }
    exit;
}
