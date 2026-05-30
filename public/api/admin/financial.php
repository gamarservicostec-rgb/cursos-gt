<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

use Config\Database;
use Middleware\SecurityHeaders;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Middleware/SecurityHeaders.php';
require_once __DIR__ . '/../../../src/Middleware/AuthMiddleware.php';

// Aplica cabeçalhos de segurança
\Middleware\SecurityHeaders::applyHeaders();

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();
$adminId = $_SESSION['user_id'];

if ($method === 'OPTIONS') {
    exit(0);
}

try {
    if ($method === 'GET') {
        // --- 1. MÉTRIQUES DE BI & KPI ---
        
        // Faturamento Total Acumulado (approved)
        $totalRevStmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'approved'");
        $totalRevenue = (float)$totalRevStmt->fetchColumn();

        // MRR - Receita do Mês Atual
        $thisMonthStart = date('Y-m-01 00:00:00');
        $thisMonthEnd = date('Y-m-t 23:59:59');
        $mrrStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'approved' AND created_at BETWEEN :start AND :end");
        $mrrStmt->execute([':start' => $thisMonthStart, ':end' => $thisMonthEnd]);
        $currentMonthMRR = (float)$mrrStmt->fetchColumn();

        // Receita do Mês Anterior (para calcular taxa de crescimento/queda)
        $lastMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
        $lastMrrStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'approved' AND created_at BETWEEN :start AND :end");
        $lastMrrStmt->execute([':start' => $lastMonthStart, ':end' => $lastMonthEnd]);
        $lastMonthMRR = (float)$lastMrrStmt->fetchColumn();

        // Cálculo da variação percentual
        $mrrChangePercent = 0;
        if ($lastMonthMRR > 0) {
            $mrrChangePercent = (($currentMonthMRR - $lastMonthMRR) / $lastMonthMRR) * 100;
        } elseif ($currentMonthMRR > 0) {
            $mrrChangePercent = 100; // Crescimento inicial de 100%
        }

        // Ticket Médio das Vendas Aprovadas
        $avgStmt = $db->query("SELECT COALESCE(AVG(amount), 0) FROM transactions WHERE status = 'approved'");
        $avgTicket = (float)$avgStmt->fetchColumn();

        // Conversão de Checkout: aprovadas / total iniciadas
        $totalSalesStmt = $db->query("SELECT COUNT(*) FROM transactions");
        $totalSalesCount = (int)$totalSalesStmt->fetchColumn();
        
        $approvedSalesStmt = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'approved'");
        $approvedSalesCount = (int)$approvedSalesStmt->fetchColumn();

        $conversionRate = 0;
        if ($totalSalesCount > 0) {
            $conversionRate = ($approvedSalesCount / $totalSalesCount) * 100;
        }

        // --- 2. DADOS DO GRÁFICO COMPARATIVO DE 6 MESES ---
        $chartMonths = [];
        $chartRevenue = [];
        $chartRefunds = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthOffset = '-' . $i . ' month';
            $monthName = date('M', strtotime($monthOffset));
            $monthStart = date('Y-m-01 00:00:00', strtotime($monthOffset));
            $monthEnd = date('Y-m-t 23:59:59', strtotime($monthOffset));

            // Faturamento aprovado
            $qRev = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'approved' AND created_at BETWEEN :start AND :end");
            $qRev->execute([':start' => $monthStart, ':end' => $monthEnd]);
            $sumRev = (float)$qRev->fetchColumn();

            // Reembolsos
            $qRef = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'refunded' AND created_at BETWEEN :start AND :end");
            $qRef->execute([':start' => $monthStart, ':end' => $monthEnd]);
            $sumRef = (float)$qRef->fetchColumn();

            $chartMonths[] = $monthName;
            $chartRevenue[] = $sumRev;
            $chartRefunds[] = $sumRef;
        }

        // --- 3. DISTRIBUIÇÃO DOS MÉTODOS DE PAGAMENTO ---
        $methodsStmt = $db->query("
            SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE status = 'approved' 
            GROUP BY payment_method
        ");
        $methodsData = $methodsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // --- 4. RANKING DOS CURSOS MAIS VENDIDOS ---
        $rankingStmt = $db->query("
            SELECT c.title, COUNT(t.id) as sales_count, COALESCE(SUM(t.amount), 0) as total_revenue
            FROM transactions t
            JOIN courses c ON t.course_id = c.id
            WHERE t.status = 'approved'
            GROUP BY t.course_id
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $rankingData = $rankingStmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'mrr' => $currentMonthMRR,
                'mrr_change_percent' => round($mrrChangePercent, 1),
                'avg_ticket' => $avgTicket,
                'conversion_rate' => round($conversionRate, 1),
                'total_transactions' => $totalSalesCount,
                'approved_transactions' => $approvedSalesCount
            ],
            'chart' => [
                'labels' => $chartMonths,
                'revenue' => $chartRevenue,
                'refunds' => $chartRefunds
            ],
            'payment_methods' => $methodsData,
            'course_ranking' => $rankingData
        ]);
        exit;

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = trim($input['action'] ?? '');

        if ($action === 'refund') {
            // --- ESTORNO DE TRANSAÇÃO ---
            $transactionId = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$transactionId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da transação inválido para reembolso.']);
                exit;
            }

            // Verifica se a transação existe e está aprovada
            $checkStmt = $db->prepare("SELECT id, status, amount, user_id FROM transactions WHERE id = :id LIMIT 1");
            $checkStmt->execute([':id' => $transactionId]);
            $trans = $checkStmt->fetch();

            if (!$trans) {
                http_response_code(404);
                echo json_encode(['error' => 'Transação não encontrada.']);
                exit;
            }

            if ($trans['status'] !== 'approved') {
                http_response_code(400);
                echo json_encode(['error' => 'Apenas transações com status Aprovada podem ser estornadas.']);
                exit;
            }

            // Altera status para refunded
            $updateStmt = $db->prepare("UPDATE transactions SET status = 'refunded', updated_at = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $transactionId]);

            // Grava na auditoria
            $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'reembolsar_venda', :resource, :details)");
            $logStmt->execute([
                ':admin_id' => $adminId,
                ':resource' => "transactions/{$transactionId}",
                ':details' => "Reembolso/Estorno efetuado no valor de R$ " . number_format($trans['amount'], 2, ',', '.')
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Transação estornada/reembolsada com sucesso! O caixa foi atualizado.'
            ]);
            exit;

        } elseif ($action === 'create') {
            // --- LANÇAMENTO MANUAL DE NOVA TRANSAÇÃO ---
            $userId = filter_var($input['user_id'] ?? 0, FILTER_VALIDATE_INT);
            $courseId = filter_var($input['course_id'] ?? 0, FILTER_VALIDATE_INT);
            $amount = filter_var($input['amount'] ?? 0.0, FILTER_VALIDATE_FLOAT);
            $paymentMethod = trim($input['payment_method'] ?? '');
            $status = trim($input['status'] ?? 'approved');

            if (!$userId || !$courseId || $amount <= 0 || empty($paymentMethod) || empty($status)) {
                http_response_code(400);
                echo json_encode(['error' => 'Todos os campos (Aluno, Curso, Valor, Método e Status) são obrigatórios e devem ser válidos.']);
                exit;
            }

            // Gera um ID de pagamento manual exclusivo
            $paymentId = 'MANUAL-' . strtoupper(uniqid());

            // Insere no banco
            $insertStmt = $db->prepare("
                INSERT INTO transactions (user_id, course_id, payment_id, amount, payment_method, status, created_at)
                VALUES (:user_id, :course_id, :payment_id, :amount, :payment_method, :status, NOW())
            ");
            $insertStmt->execute([
                ':user_id' => $userId,
                ':course_id' => $courseId,
                ':payment_id' => $paymentId,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':status' => $status
            ]);
            $newTransId = $db->lastInsertId();

            // Grava na auditoria
            $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'lancar_venda_manual', :resource, :details)");
            $logStmt->execute([
                ':admin_id' => $adminId,
                ':resource' => "transactions/{$newTransId}",
                ':details' => "Venda manual de R$ " . number_format($amount, 2, ',', '.') . " lançada para Aluno ID {$userId} no Curso ID {$courseId}"
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Venda manual registrada com absoluto sucesso! Caixa e BI atualizados.'
            ]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Ação administrativa inválida ou não informada.']);
            exit;
        }

    } elseif ($method === 'DELETE') {
        // --- EXCLUSÃO DE REGISTRO DE TRANSAÇÃO ---
        $transactionId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
        
        if (!$transactionId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da transação inválido para exclusão.']);
            exit;
        }

        // Deleta
        $delStmt = $db->prepare("DELETE FROM transactions WHERE id = :id");
        $delStmt->execute([':id' => $transactionId]);

        // Grava na auditoria
        $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'excluir_transacao', :resource, :details)");
        $logStmt->execute([
            ':admin_id' => $adminId,
            ':resource' => "transactions/{$transactionId}",
            ':details' => "Registro de transação excluído permanentemente do banco de dados"
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Transação removida de forma definitiva do banco de dados.'
        ]);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno na API financeira: ' . $e->getMessage()]);
    exit;
}
