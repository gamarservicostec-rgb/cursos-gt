<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Receita total aprovada
    $revStmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE status = 'approved'");
    $revStmt->execute();
    $totalRevenue = (float)$revStmt->fetchColumn();

    // 2. Faturamento deste mês
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

    // 5. Total de cursos cadastrados
    $coursesCountStmt = $db->prepare("SELECT COUNT(*) FROM courses");
    $coursesCountStmt->execute();
    $totalCourses = (int)$coursesCountStmt->fetchColumn();

    // 6. Total de módulos cadastrados
    $modulesCountStmt = $db->prepare("SELECT COUNT(*) FROM modules");
    $modulesCountStmt->execute();
    $totalModules = (int)$modulesCountStmt->fetchColumn();

    // 7. Histórico de 5 transações mais recentes
    $lastSalesStmt = $db->prepare("
        SELECT t.id, t.payment_id, t.amount, t.payment_method, t.status, t.created_at, u.name as student_name, c.title as course_title
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        JOIN courses c ON t.course_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $lastSalesStmt->execute();
    $recentSales = $lastSalesStmt->fetchAll();

    // 8. Histórico de 5 logs de auditoria recentes
    $logsStmt = $db->prepare("
        SELECT a.action, a.details, a.ip_address, a.created_at, u.name as user_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $logsStmt->execute();
    $recentLogs = $logsStmt->fetchAll();

    // 9. Dados gráficos dos últimos 6 meses
    $chartLabels = [];
    $chartData = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthOffset = '-' . $i . ' month';
        $monthName = date('M', strtotime($monthOffset));
        $monthStart = date('Y-m-01 00:00:00', strtotime($monthOffset));
        $monthEnd = date('Y-m-t 23:59:59', strtotime($monthOffset));

        $chartQuery = $db->prepare("SELECT SUM(amount) FROM transactions WHERE status = 'approved' AND created_at BETWEEN :start AND :end");
        $chartQuery->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $sum = (float)$chartQuery->fetchColumn();

        $chartLabels[] = $monthName;
        $chartData[] = $sum;
    }

} catch (\PDOException $e) {
    die("Erro interno ao carregar painel administrativo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "error": "#ffb4ab",
                        "on-primary-container": "#1a1a1a",
                        "on-tertiary-container": "#005f79",
                        "surface-dim": "#0A0A0C",
                        "secondary-container": "#6f00be",
                        "secondary-fixed": "#f0dbff",
                        "on-primary-fixed": "#1a1a1a",
                        "surface-container-highest": "#1c1c1e",
                        "on-primary": "#1a1a1a",
                        "on-tertiary": "#003545",
                        "surface-container": "#111114",
                        "surface-container-low": "#0d0d0f",
                        "status-draft": "#FACC15",
                        "background-deep": "#070708",
                        "background": "#0A0A0C",
                        "on-secondary-fixed-variant": "#6900b3",
                        "surface-container-lowest": "#050505",
                        "outline": "rgba(242, 201, 76, 0.2)",
                        "surface": "#0A0A0C",
                        "tertiary-container": "#7cd9ff",
                        "inverse-primary": "#f2c94c",
                        "tertiary": "#cdefff",
                        "on-secondary-container": "#d6a9ff",
                        "on-secondary-fixed": "#2c0051",
                        "primary": "#f2c94c",
                        "inverse-surface": "#ffffff",
                        "on-error-container": "#ffdad6",
                        "on-surface-variant": "#a1a1a6",
                        "primary-fixed-dim": "#ebc246",
                        "on-secondary": "#ffffff",
                        "surface-bright": "#1c1c1e",
                        "border-subtle": "rgba(242, 201, 76, 0.2)",
                        "on-background": "#ffffff",
                        "on-surface": "#ffffff",
                        "tertiary-fixed": "#bce9ff",
                        "glow-primary": "rgba(242, 201, 76, 0.05)",
                        "tertiary-fixed-dim": "#75d2f8",
                        "surface-tint": "#f2c94c",
                        "on-primary-fixed-variant": "#584400",
                        "on-error": "#690005",
                        "inverse-on-surface": "#000000",
                        "secondary-fixed-dim": "#ddb7ff",
                        "status-published": "#34D399",
                        "secondary": "#ddb7ff",
                        "primary-container": "#f2c94c",
                        "error-container": "#93000a",
                        "on-tertiary-fixed": "#001f29",
                        "on-tertiary-fixed-variant": "#004d63",
                        "surface-container-high": "#161618",
                        "surface-glass": "rgba(10, 10, 12, 0.85)",
                        "surface-variant": "#1c1c1e",
                        "primary-fixed": "#f2c94c",
                        "outline-variant": "rgba(255, 255, 255, 0.1)"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "sidebar-width": "256px",
                        "gutter-grid": "1.5rem",
                        "container-padding": "2rem",
                        "stack-sm": "0.5rem",
                        "stack-md": "1rem",
                        "stack-lg": "2.5rem"
                    },
                    "fontFamily": {
                        "body-lg": ["Plus Jakarta Sans"],
                        "caption": ["Plus Jakarta Sans"],
                        "label-sm": ["Plus Jakarta Sans"],
                        "title-lg": ["Outfit"],
                        "headline-md": ["Outfit"],
                        "display-lg": ["Outfit"],
                        "body-md": ["Plus Jakarta Sans"]
                    },
                    "fontSize": {
                        "body-lg": ["18px", {"lineHeight": "1.5", "fontWeight": "700"}],
                        "caption": ["10px", {"lineHeight": "1.2", "letterSpacing": "0.1em", "fontWeight": "700"}],
                        "label-sm": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "600"}],
                        "title-lg": ["20px", {"lineHeight": "1.4", "fontWeight": "700"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                        "display-lg": ["36px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["14px", {"lineHeight": "1.6", "fontWeight": "500"}]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            background-color: #070708;
            color: #ffffff;
            overflow-x: hidden;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .glass-card {
            background: rgba(10, 10, 12, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(242, 201, 76, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-2px);
            border-color: rgba(242, 201, 76, 0.3);
            box-shadow: 0 12px 40px -12px rgba(0, 0, 0, 0.7);
        }
        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            z-index: -1;
            opacity: 0.1;
        }
        .neon-text-gold {
            background: linear-gradient(135deg, #ffffff 0%, #f2c94c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 15px rgba(242, 201, 76, 0.1);
        }
        .spline-path {
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
            animation: dash 3s ease-in-out forwards;
        }
        @keyframes dash {
            to { stroke-dashoffset: 0; }
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #070708;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(242, 201, 76, 0.1);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(242, 201, 76, 0.2);
        }

        /* Custom Premium Select Dropdown Style (Obsidian Gold) */
        select {
            background-color: #0d0d0f !important;
            color: #ffffff !important;
            border: 1px solid rgba(242, 201, 76, 0.15) !important;
            color-scheme: dark !important;
            outline: none !important;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        select:focus {
            border-color: rgba(242, 201, 76, 0.4) !important;
            box-shadow: 0 0 12px rgba(242, 201, 76, 0.15) !important;
        }
        select option {
            background-color: #0d0d0f !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body class="bg-background-deep font-body-md text-on-background" style="background: linear-gradient(rgba(7, 7, 8, 0.95), rgba(7, 7, 8, 0.98)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&amp;w=2070&amp;auto=format&amp;fit=crop'); background-size: cover; background-attachment: fixed;">

<!-- Atmospheric Glows -->
<div class="glow-orb bg-primary w-[500px] h-[500px] top-[-10%] right-[-5%]"></div>
<div class="glow-orb bg-secondary w-96 h-96 bottom-[10%] left-[-5%]"></div>

<!-- SideNavBar -->
<aside class="w-sidebar-width h-screen fixed left-0 top-0 border-r border-border-subtle backdrop-blur-2xl flex flex-col py-stack-lg z-50 glass-card" style="background: rgba(10, 10, 12, 0.92); height: 100vh;">
    <div class="px-6 mb-8 flex items-center gap-3">
        <img alt="GT Cursos Logo" onerror="this.style.display='none'" class="w-10 h-10 rounded-lg shadow-[0_0_15px_rgba(242,201,76,0.3)] object-contain bg-black p-1" src="../assets/images/logo.png">
        <div>
            <h1 class="font-display-lg text-title-lg font-bold text-primary tracking-tighter leading-none">GT CURSOS</h1>
            <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-[0.2em] opacity-80 mt-1">Admin Terminal</p>
        </div>
    </div>
    <nav class="flex-1 flex flex-col gap-1 px-3 overflow-y-auto custom-scrollbar">
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="index.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">dashboard</span>
            <span class="font-label-sm text-label-sm">Painel</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="courses.php">
            <span class="material-symbols-outlined">school</span>
            <span class="font-label-sm text-label-sm">Cursos</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="categories.php">
            <span class="material-symbols-outlined">category</span>
            <span class="font-label-sm text-label-sm">Categorias</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="students.php">
            <span class="material-symbols-outlined">person</span>
            <span class="font-label-sm text-label-sm">Alunos</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="attendance.php">
            <span class="material-symbols-outlined">how_to_reg</span>
            <span class="font-label-sm text-label-sm">Presença</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="certificates.php">
            <span class="material-symbols-outlined">workspace_premium</span>
            <span class="font-label-sm text-label-sm">Certificados</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="support.php">
            <span class="material-symbols-outlined">support_agent</span>
            <span class="font-label-sm text-label-sm">Suporte & Chamados</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="coupons.php">
            <span class="material-symbols-outlined">redeem</span>
            <span class="font-label-sm text-label-sm">Cupons</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="email_whatsapp.php">
            <span class="material-symbols-outlined">mail</span>
            <span class="font-label-sm text-label-sm">E-mail & WhatsApp</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="financial.php">
            <span class="material-symbols-outlined">payments</span>
            <span class="font-label-sm text-label-sm">Financeiro</span>
        </a>
        
        <div class="mt-auto flex flex-col gap-1 border-t border-white/5 pt-4">
            <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-error/80 hover:text-error hover:bg-error/5 transition-all duration-200" href="../logout.php">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-label-sm text-label-sm">Sair</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Main Wrapper -->
<div class="ml-sidebar-width min-h-screen">
    <!-- TopAppBar -->
    <header class="fixed top-0 right-0 w-[calc(100%-256px)] h-16 z-40 flex justify-between items-center px-container-padding border-b border-white/5 backdrop-blur-xl glass-card" style="background: rgba(10, 10, 12, 0.8);">
        <div class="flex-1 max-w-xl">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" placeholder="Buscar dados, relatórios..." type="text">
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex gap-4">
                <button class="material-symbols-outlined text-on-surface-variant hover:text-white hover:bg-white/10 p-2 rounded-full transition-all">notifications</button>
                <button class="material-symbols-outlined text-on-surface-variant hover:text-white hover:bg-white/10 p-2 rounded-full transition-all">mail</button>
            </div>
            <div class="flex items-center gap-3 pl-6 border-l border-white/10">
                <div class="text-right">
                    <p class="font-body-md text-white font-bold leading-none"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="font-caption text-[10px] text-on-surface-variant uppercase tracking-wider mt-1">Gerente Administrativo</p>
                </div>
                <img alt="User Avatar Admin" onerror="this.style.display='none'" class="w-10 h-10 rounded-full border-2 border-primary/20 object-cover shadow-lg bg-black" src="../assets/images/logo.png">
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-24 px-container-padding pb-stack-lg space-y-stack-lg">
        <!-- Row 1: Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter-grid">
            <!-- Total Users -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-36">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary/10 rounded-lg border border-primary/20">
                        <span class="material-symbols-outlined text-primary">group</span>
                    </div>
                    <span class="text-status-published text-label-sm font-bold flex items-center bg-status-published/10 px-2 py-0.5 rounded-full">+12% <span class="material-symbols-outlined text-[14px] ml-0.5">trending_up</span></span>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">ALUNOS ATIVOS</p>
                    <h2 class="font-display-lg text-display-lg neon-text-gold"><?php echo number_format($activeStudents); ?></h2>
                </div>
            </div>
            <!-- Courses -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-36">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-secondary/10 rounded-lg border border-secondary/20">
                        <span class="material-symbols-outlined text-secondary">school</span>
                    </div>
                    <span class="text-status-published text-label-sm font-bold flex items-center bg-status-published/10 px-2 py-0.5 rounded-full">+5% <span class="material-symbols-outlined text-[14px] ml-0.5">trending_up</span></span>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">CURSOS</p>
                    <h2 class="font-display-lg text-display-lg neon-text-gold"><?php echo number_format($totalCourses); ?></h2>
                </div>
            </div>
            <!-- Modules -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-36">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-tertiary-container/10 rounded-lg border border-tertiary-container/20">
                        <span class="material-symbols-outlined text-tertiary-container">view_module</span>
                    </div>
                    <span class="text-status-draft text-label-sm font-bold flex items-center bg-status-draft/10 px-2 py-0.5 rounded-full">Estável <span class="material-symbols-outlined text-[14px] ml-0.5">horizontal_rule</span></span>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">MÓDULOS</p>
                    <h2 class="font-display-lg text-display-lg neon-text-gold"><?php echo number_format($totalModules); ?></h2>
                </div>
            </div>
            <!-- Revenue -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-36">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary-container/10 rounded-lg border border-primary-container/20">
                        <span class="material-symbols-outlined text-primary-container">payments</span>
                    </div>
                    <span class="text-status-published text-label-sm font-bold flex items-center bg-status-published/10 px-2 py-0.5 rounded-full">+18% <span class="material-symbols-outlined text-[14px] ml-0.5">trending_up</span></span>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">RECEITA TOTAL</p>
                    <h2 class="font-display-lg text-display-lg neon-text-gold">R$ <?php echo number_format($totalRevenue, 0, ',', '.'); ?></h2>
                </div>
            </div>
        </div>

        <!-- Row 2: Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter-grid">
            <!-- Large Revenue Line Chart -->
            <div class="lg:col-span-2 glass-card rounded-xl p-6 relative overflow-hidden">
                <div class="flex justify-between items-center mb-8 relative z-10">
                    <div>
                        <h3 class="font-title-lg text-title-lg text-white">Visão Geral de Receita</h3>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">Desempenho nos últimos 6 meses</p>
                    </div>
                    <select class="bg-white/5 border border-white/10 rounded-lg text-label-sm text-white px-4 py-2 outline-none focus:border-primary transition-all cursor-pointer">
                        <option class="bg-background-deep">Últimos 6 meses</option>
                    </select>
                </div>
                <div class="h-64 relative w-full z-10">
                    <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 800 200">
                        <defs>
                            <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#f2c94c" stop-opacity="0.25"></stop>
                                <stop offset="100%" stop-color="#f2c94c" stop-opacity="0"></stop>
                            </linearGradient>
                        </defs>
                        <!-- Grid Lines -->
                        <line stroke="rgba(255,255,255,0.03)" x1="0" x2="800" y1="0" y2="0"></line>
                        <line stroke="rgba(255,255,255,0.03)" x1="0" x2="800" y1="50" y2="50"></line>
                        <line stroke="rgba(255,255,255,0.03)" x1="0" x2="800" y1="100" y2="100"></line>
                        <line stroke="rgba(255,255,255,0.03)" x1="0" x2="800" y1="150" y2="150"></line>
                        <!-- Area Fill -->
                        <path d="M0,150 Q133,120 266,160 T532,80 T800,40 L800,200 L0,200 Z" fill="url(#chartGradient)"></path>
                        <!-- Line Path -->
                        <path class="spline-path" d="M0,150 Q133,120 266,160 T532,80 T800,40" fill="none" stroke="#f2c94c" stroke-linecap="round" stroke-width="3"></path>
                        <!-- Dots -->
                        <circle class="animate-pulse" cx="266" cy="160" fill="#f2c94c" r="4"></circle>
                        <circle class="animate-pulse" cx="532" cy="80" fill="#f2c94c" r="4"></circle>
                        <circle class="animate-pulse" cx="800" cy="40" fill="#f2c94c" r="4"></circle>
                    </svg>
                    <div class="flex justify-between mt-4 text-[10px] text-on-surface-variant font-bold uppercase tracking-widest px-2">
                        <?php foreach ($chartLabels as $label): ?>
                            <span><?php echo $label; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <!-- Live Feed / Audit Logs -->
            <div class="glass-card rounded-xl p-6 flex flex-col justify-between">
                <div>
                    <h3 class="font-title-lg text-title-lg text-white mb-6">Logs de Segurança</h3>
                    <div class="space-y-4">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="p-3 bg-white/[0.02] border border-white/5 rounded-lg flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-sm mt-0.5">security</span>
                                <div>
                                    <p class="text-xs font-semibold text-white leading-tight"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-[10px] text-on-surface-variant mt-1 leading-normal"><?php echo htmlspecialchars($log['details'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-[9px] text-primary mt-1 font-mono"><?php echo $log['ip_address']; ?> • <?php echo date('H:i', strtotime($log['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Sales Management -->
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-white/5 flex justify-between items-center">
                <h3 class="font-title-lg text-title-lg text-white">Transações e Vendas Recentes</h3>
                <a href="courses.php" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] hover:-translate-y-0.5 transition-all active:translate-y-0">Grade de Cursos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.2em] bg-white/[0.03]">
                            <th class="px-6 py-4">ALUNO / CURSO</th>
                            <th class="px-6 py-4">MÉTODO</th>
                            <th class="px-6 py-4">VALOR</th>
                            <th class="px-6 py-4">STATUS</th>
                            <th class="px-6 py-4 text-right">DATA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!empty($recentSales)): ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr class="hover:bg-white/[0.04] transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-primary">person</span>
                                            </div>
                                            <div>
                                                <p class="font-body-md font-bold text-white"><?php echo htmlspecialchars($sale['student_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="font-caption text-caption text-on-surface-variant uppercase tracking-wider"><?php echo htmlspecialchars($sale['course_title'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/90 uppercase tracking-widest text-[11px]"><?php echo $sale['payment_method']; ?></td>
                                    <td class="px-6 py-4 font-body-md text-primary font-bold">R$ <?php echo number_format($sale['amount'], 2, ',', '.'); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($sale['status'] === 'approved'): ?>
                                            <span class="px-3 py-1 rounded-full bg-status-published/10 text-status-published font-bold text-[10px] uppercase tracking-widest border border-status-published/20">APROVADO</span>
                                        <?php elseif ($sale['status'] === 'pending'): ?>
                                            <span class="px-3 py-1 rounded-full bg-status-draft/10 text-status-draft font-bold text-[10px] uppercase tracking-widest border border-status-draft/20">PENDENTE</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-500 font-bold text-[10px] uppercase tracking-widest border border-red-500/20">CANCELADO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs text-on-surface-variant"><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-muted">Nenhuma venda registrada no sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Micro-interaction Script -->
<script>
    document.querySelectorAll('.glass-card').forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });
</script>
</body>
</html>
