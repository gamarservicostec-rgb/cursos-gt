<?php
use Config\Database;
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
    // 1. Busca transações para a tabela (Últimas 100)
    $transQuery = $db->prepare("
        SELECT t.id, t.payment_id, t.amount, t.payment_method, t.status, t.created_at, u.name as student_name, c.title as course_title, u.id as student_id
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        JOIN courses c ON t.course_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 100
    ");
    $transQuery->execute();
    $transactions = $transQuery->fetchAll(\PDO::FETCH_ASSOC);

    // 2. Busca estudantes para o Modal de Lançamento Manual
    $studQuery = $db->prepare("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name ASC");
    $studQuery->execute();
    $studentsList = $studQuery->fetchAll(\PDO::FETCH_ASSOC);

    // 3. Busca cursos para o Modal de Lançamento Manual
    $coursesQuery = $db->prepare("SELECT id, title, price FROM courses ORDER BY title ASC");
    $coursesQuery->execute();
    $coursesList = $coursesQuery->fetchAll(\PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Erro ao carregar dados financeiros: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Gestão Financeira & BI</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="index.php">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-label-sm text-label-sm">Painel</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="courses.php">
            <span class="material-symbols-outlined">school</span>
            <span class="font-label-sm text-label-sm">Cursos</span>
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="financial.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
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
        <div class="flex-grow max-w-xl">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" id="tableSearch" placeholder="Buscar no histórico de transações..." type="text" onkeyup="filterTransactions()">
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
                    <p class="font-caption text-[10px] text-on-surface-variant uppercase tracking-wider mt-1">Diretor Financeiro</p>
                </div>
                <img alt="User Avatar Admin" onerror="this.style.display='none'" class="w-10 h-10 rounded-full border-2 border-primary/20 object-cover shadow-lg bg-black" src="../assets/images/logo.png">
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-24 px-container-padding pb-stack-lg space-y-stack-lg">
        
        <!-- ROW 1: KPIs de Faturamento e Vendas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter-grid">
            <!-- Total Revenue -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary/10 rounded-lg border border-primary/20">
                        <span class="material-symbols-outlined text-primary">account_balance_wallet</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">RECEITA TOTAL ACUMULADA</p>
                    <h2 class="font-display-lg text-headline-md neon-text-gold" id="kpiTotalRevenue">R$ 0,00</h2>
                </div>
            </div>

            <!-- MRR (Receita do Mês) -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary/10 rounded-lg border border-primary/20">
                        <span class="material-symbols-outlined text-primary">calendar_month</span>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded" id="mrrTrendBadge">--% vs ant</span>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">FATURAMENTO (MÊS VIGENTE)</p>
                    <h2 class="font-display-lg text-headline-md text-white" id="kpiMRR">R$ 0,00</h2>
                </div>
            </div>

            <!-- Ticket Médio -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary/10 rounded-lg border border-primary/20">
                        <span class="material-symbols-outlined text-primary">analytics</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">TICKET MÉDIO (APROVADO)</p>
                    <h2 class="font-display-lg text-headline-md text-white" id="kpiAvgTicket">R$ 0,00</h2>
                </div>
            </div>

            <!-- Conversion Rate -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-status-published/10 rounded-lg border border-status-published/20">
                        <span class="material-symbols-outlined text-status-published">ads_click</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">CONVERSÃO DE CHECKOUT</p>
                    <h2 class="font-display-lg text-headline-md text-status-published" id="kpiConversionRate">0.0%</h2>
                </div>
            </div>
        </div>

        <!-- ROW 2: BI GRÁFICOS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter-grid">
            <!-- Gráfico Comparativo 6 Meses -->
            <div class="glass-card rounded-xl p-6 lg:col-span-2 space-y-4">
                <h3 class="font-title-lg text-title-lg text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">query_stats</span>
                    Gráfico Comparativo de Vendas & Estornos (Últimos 6 Meses)
                </h3>
                <div class="h-64 relative">
                    <canvas id="mrrChart"></canvas>
                </div>
            </div>

            <!-- Gráfico Distribuição de Pagamento & Ranking -->
            <div class="glass-card rounded-xl p-6 space-y-4">
                <h3 class="font-title-lg text-title-lg text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">pie_chart</span>
                    Métodos de Pagamento (Approved)
                </h3>
                <div class="h-60 relative flex items-center justify-center">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- ROW 3: Ranking e Ações Rápidas -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter-grid">
            <!-- Top Cursos (Ranking) -->
            <div class="glass-card rounded-xl p-6 lg:col-span-2">
                <h3 class="font-title-lg text-title-lg text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary font-bold">trophy</span>
                    Cursos com Maior Faturamento 🏆
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-on-surface-variant font-label-sm text-[9px] uppercase tracking-wider bg-white/[0.02]">
                                <th class="px-4 py-3">CURSO</th>
                                <th class="px-4 py-3">Nº DE VENDAS</th>
                                <th class="px-4 py-3 text-right">FATURAMENTO TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5" id="courseRankingTable">
                            <!-- Inserido dinamicamente via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Lançamento Manual / Ações de Controle -->
            <div class="glass-card rounded-xl p-6 flex flex-col justify-between">
                <div>
                    <h3 class="font-title-lg text-title-lg text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                        Controle Financeiro Direto
                    </h3>
                    <p class="text-xs text-on-surface-variant leading-relaxed">
                        Lançamentos manuais criam registros instantâneos no banco de dados para conciliações bancárias extras ou venda de pacotes fora do gateway Mercado Pago.
                    </p>
                </div>
                <div class="space-y-3 mt-6">
                    <button onclick="openCreateModal()" class="w-full bg-primary py-3 rounded-lg text-background font-bold text-xs uppercase tracking-widest flex items-center justify-center gap-1.5 shadow-[0_0_20px_rgba(242,201,76,0.15)] hover:shadow-[0_0_30px_rgba(242,201,76,0.35)] transition-all">
                        <span>Lançar Venda Manual</span>
                        <span class="material-symbols-outlined text-sm">add_circle</span>
                    </button>
                    <button onclick="exportToCSV()" class="w-full bg-white/5 border border-white/10 py-3 rounded-lg text-white font-bold text-xs uppercase tracking-widest flex items-center justify-center gap-1.5 hover:bg-white/10 transition-all">
                        <span>Exportar Relatório CSV</span>
                        <span class="material-symbols-outlined text-sm">download</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ROW 4: HISTÓRICO DE TRANSAÇÕES COMPLETO COM FILTROS -->
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-white/5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-black/20">
                <div>
                    <h3 class="font-title-lg text-title-lg text-white">Histórico de Transações do Sistema 📊</h3>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Controle total de status, reembolsos, auditoria e conciliação em lote</p>
                </div>
                
                <!-- Filtros Interativos -->
                <div class="flex flex-wrap items-center gap-3 text-xs w-full md:w-auto">
                    <div class="flex items-center gap-2">
                        <span class="text-on-surface-variant font-bold text-[9px] uppercase tracking-wider">Período:</span>
                        <input type="date" id="filterStartDate" class="bg-black/40 border border-white/10 rounded px-2 py-1 text-white text-xs focus:ring-1 focus:ring-primary focus:border-primary" onchange="filterTransactions()">
                        <span class="text-on-surface-variant">a</span>
                        <input type="date" id="filterEndDate" class="bg-black/40 border border-white/10 rounded px-2 py-1 text-white text-xs focus:ring-1 focus:ring-primary focus:border-primary" onchange="filterTransactions()">
                    </div>
                    <div>
                        <select id="filterStatus" class="bg-black/40 border border-white/10 rounded px-3 py-1 text-white text-xs focus:ring-1 focus:ring-primary focus:border-primary" onchange="filterTransactions()">
                            <option value="">Todos os Status</option>
                            <option value="approved">Aprovada</option>
                            <option value="pending">Pendente</option>
                            <option value="refunded">Reembolsada</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="transactionsTable">
                    <thead>
                        <tr class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.2em] bg-white/[0.03]">
                            <th class="px-6 py-4">ALUNO</th>
                            <th class="px-6 py-4">CURSO</th>
                            <th class="px-6 py-4">MÉTODO</th>
                            <th class="px-6 py-4">GATEWAY ID</th>
                            <th class="px-6 py-4">VALOR</th>
                            <th class="px-6 py-4">STATUS</th>
                            <th class="px-6 py-4">DATA</th>
                            <th class="px-6 py-4 text-right">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="hover:bg-white/[0.03] transition-colors transaction-row" 
                                    data-student="<?php echo htmlspecialchars($t['student_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-course="<?php echo htmlspecialchars($t['course_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-payment-id="<?php echo htmlspecialchars($t['payment_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-method="<?php echo $t['payment_method']; ?>"
                                    data-status="<?php echo $t['status']; ?>"
                                    data-date="<?php echo date('Y-m-d', strtotime($t['created_at'])); ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-xs font-bold">
                                                <?php echo substr($t['student_name'], 0, 2); ?>
                                            </div>
                                            <div>
                                                <p class="font-body-md font-bold text-white leading-tight"><?php echo htmlspecialchars($t['student_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="font-caption text-[8px] text-on-surface-variant uppercase tracking-wider mt-0.5">Aluno ID: #<?php echo $t['student_id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/90 max-w-[200px] truncate"><?php echo htmlspecialchars($t['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($t['payment_method'] === 'pix'): ?>
                                            <span class="px-2 py-0.5 rounded bg-teal-500/10 text-teal-400 text-[9px] border border-teal-500/20 font-bold uppercase">PIX</span>
                                        <?php elseif ($t['payment_method'] === 'credit_card'): ?>
                                            <span class="px-2 py-0.5 rounded bg-violet-500/10 text-violet-400 text-[9px] border border-violet-500/20 font-bold uppercase">CARTÃO</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[9px] border border-amber-500/20 font-bold uppercase">BOLETO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-[10px] text-white/70"><?php echo htmlspecialchars($t['payment_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-4 font-mono text-xs font-bold text-white">R$ <?php echo number_format($t['amount'], 2, ',', '.'); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($t['status'] === 'approved'): ?>
                                            <span class="px-2 py-0.5 rounded bg-status-published/10 text-status-published text-[9px] border border-status-published/20 font-bold uppercase">APROVADA</span>
                                        <?php elseif ($t['status'] === 'pending'): ?>
                                            <span class="px-2 py-0.5 rounded bg-status-draft/10 text-status-draft text-[9px] border border-status-draft/20 font-bold uppercase">PENDENTE</span>
                                        <?php elseif ($t['status'] === 'refunded'): ?>
                                            <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 text-[9px] border border-blue-500/20 font-bold uppercase">REEMBOLSADA</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded bg-red-500/10 text-red-400 text-[9px] border border-red-500/20 font-bold uppercase">CANCELADA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-on-surface-variant"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2.5">
                                            <?php if ($t['status'] === 'approved'): ?>
                                                <button onclick="refundTransaction(<?php echo $t['id']; ?>, <?php echo $t['amount']; ?>)" class="px-2 py-1 rounded bg-blue-500/10 text-blue-400 hover:bg-blue-500/25 border border-blue-500/20 text-[10px] font-bold transition-all" title="Estornar/Reembolsar Venda">
                                                    ESTORNAR
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteTransaction(<?php echo $t['id']; ?>)" class="p-1.5 rounded bg-red-500/5 border border-red-500/15 text-red-400 hover:bg-red-500/20 transition-all flex items-center justify-center" title="Excluir Transação">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noTransactionsRow">
                                <td colspan="8" class="px-6 py-12 text-center text-on-surface-variant font-semibold italic text-xs uppercase tracking-widest">Nenhuma transação encontrada no sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL: LANÇAR VENDA MANUAL -->
<div id="createModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/80 backdrop-blur-md px-4">
    <div class="glass-card w-full max-w-lg rounded-xl overflow-hidden flex flex-col max-h-[85vh] border border-primary/20 shadow-2xl">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center bg-black/40">
            <h3 class="text-xs font-bold text-white uppercase tracking-widest">Lançar Nova Venda Manual 💰</h3>
            <button onclick="closeCreateModal()" class="material-symbols-outlined text-on-surface-variant hover:text-white transition-colors">close</button>
        </div>
        
        <form id="createForm" onsubmit="saveManualTransaction(event)" class="overflow-y-auto custom-scrollbar px-6 py-5 space-y-4">
            
            <!-- Seleção do Aluno -->
            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Selecione o Aluno</label>
                <select id="fieldStudent" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                    <option value="">-- Escolha um Aluno Registrado --</option>
                    <?php foreach ($studentsList as $stud): ?>
                        <option value="<?php echo $stud['id']; ?>"><?php echo htmlspecialchars($stud['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($stud['email'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Seleção do Curso -->
            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Selecione o Curso comprado</label>
                <select id="fieldCourse" required onchange="setCoursePrice(this)" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                    <option value="">-- Escolha um Curso --</option>
                    <?php foreach ($coursesList as $c): ?>
                        <option value="<?php echo $c['id']; ?>" data-price="<?php echo $c['price']; ?>"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?> (R$ <?php echo number_format($c['price'], 2, ',', '.'); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Valor da Venda -->
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Valor da Venda (R$)</label>
                    <input type="number" step="0.01" min="0.01" id="fieldAmount" required placeholder="0.00" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                </div>

                <!-- Método de Pagamento -->
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Método de Lançamento</label>
                    <select id="fieldMethod" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        <option value="pix">PIX Direto</option>
                        <option value="credit_card">Cartão de Crédito</option>
                        <option value="boleto">Boleto Pago</option>
                    </select>
                </div>
            </div>

            <!-- Status da Transação -->
            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Status Inicial da Venda</label>
                <select id="fieldStatus" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                    <option value="approved">Aprovada (Adiciona ao faturamento na hora)</option>
                    <option value="pending">Pendente (Aguardando acerto de caixa)</option>
                </select>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-white/5 flex justify-end gap-3 bg-black/20 -mx-6 -mb-5 px-6 py-4">
                <button type="button" onclick="closeCreateModal()" class="px-5 py-2.5 rounded-lg border border-white/10 text-white/80 hover:text-white hover:bg-white/5 text-xs font-bold uppercase tracking-wider transition-colors">Cancelar</button>
                <button type="submit" class="bg-primary text-background px-5 py-2.5 rounded-lg font-bold text-xs uppercase tracking-wider shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] transition-all">Lançar Transação</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

<!-- LÓGICA JAVASCRIPT EM TEMPO REAL & BI CHARTING -->
<script>
    // Configurações Globais dos Gráficos
    Chart.defaults.color = '#a1a1a6';
    Chart.defaults.font.family = 'Plus Jakarta Sans';

    let mrrChartInstance = null;
    let paymentChartInstance = null;

    // Ao iniciar a página
    document.addEventListener("DOMContentLoaded", () => {
        loadFinancialData();
    });

    // --- CARREGAMENTO ASSÍNCRONO DOS DADOS DE BI ---
    async function loadFinancialData() {
        try {
            const response = await fetch('../api/admin/financial.php');
            if (!response.ok) throw new Error('Falha ao obter dados financeiros da API.');
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao carregar dados.');

            // 1. Atualizar KPIs do Topo
            document.getElementById('kpiTotalRevenue').innerText = 'R$ ' + res.kpis.total_revenue.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            document.getElementById('kpiMRR').innerText = 'R$ ' + res.kpis.mrr.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            document.getElementById('kpiAvgTicket').innerText = 'R$ ' + res.kpis.avg_ticket.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            document.getElementById('kpiConversionRate').innerText = res.kpis.conversion_rate.toFixed(1) + '%';

            // MRR Trend Badge (Crescimento/Queda)
            const trendBadge = document.getElementById('mrrTrendBadge');
            const percent = res.kpis.mrr_change_percent;
            if (percent > 0) {
                trendBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                trendBadge.innerText = `+${percent}% vs mês ant`;
            } else if (percent < 0) {
                trendBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-red-500/10 text-red-400 border border-red-500/20';
                trendBadge.innerText = `${percent}% vs mês ant`;
            } else {
                trendBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-white/5 text-white/50 border border-white/10';
                trendBadge.innerText = `0% vs mês ant`;
            }

            // 2. Gráfico 6 Meses (Comparativo Vendas vs Reembolsos)
            renderMRRChart(res.chart);

            // 3. Gráfico de Pizza de Métodos de Pagamento
            renderPaymentChart(res.payment_methods);

            // 4. Ranking de Cursos
            renderCourseRanking(res.course_ranking);

        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- RENDERIZADORES DE GRÁFICOS (CHART.JS) ---
    function renderMRRChart(chartData) {
        const ctx = document.getElementById('mrrChart').getContext('2d');
        if (mrrChartInstance) mrrChartInstance.destroy();

        mrrChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Faturamento Aprovado (R$)',
                        data: chartData.revenue,
                        backgroundColor: '#f2c94c',
                        borderRadius: 4,
                        maxBarThickness: 32
                    },
                    {
                        label: 'Reembolsos / Estornos (R$)',
                        data: chartData.refunds,
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        maxBarThickness: 32
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 20 }
                    }
                }
            }
        });
    }

    function renderPaymentChart(methods) {
        const ctx = document.getElementById('paymentChart').getContext('2d');
        if (paymentChartInstance) paymentChartInstance.destroy();

        const labels = [];
        const data = [];
        const colors = [];

        methods.forEach(m => {
            if (m.payment_method === 'pix') {
                labels.push('PIX');
                colors.push('#14b8a6'); // Teal
            } else if (m.payment_method === 'credit_card') {
                labels.push('Cartão de Crédito');
                colors.push('#8b5cf6'); // Violet
            } else {
                labels.push('Boleto Bancário');
                colors.push('#f59e0b'); // Amber
            }
            data.push(m.count);
        });

        if (methods.length === 0) {
            labels.push('Sem Dados');
            data.push(1);
            colors.push('rgba(255,255,255,0.05)');
        }

        paymentChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 1,
                    borderColor: '#0a0a0c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 8, padding: 12 }
                    }
                }
            }
        });
    }

    function renderCourseRanking(ranking) {
        const container = document.getElementById('courseRankingTable');
        container.innerHTML = '';

        if (ranking.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-on-surface-variant italic">Nenhum curso vendido até o momento.</td>
                </tr>
            `;
            return;
        }

        ranking.forEach((item, idx) => {
            const place = idx + 1;
            const placeIcon = place === 1 ? '🥇' : (place === 2 ? '🥈' : (place === 3 ? '🥉' : `#${place}`));
            
            container.innerHTML += `
                <tr class="hover:bg-white/[0.01]">
                    <td class="px-4 py-3 font-bold text-white flex items-center gap-2">
                        <span class="text-sm">${placeIcon}</span>
                        <span>${item.title}</span>
                    </td>
                    <td class="px-4 py-3 font-bold text-white/70">${item.sales_count} vendas</td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-primary">R$ ${parseFloat(item.total_revenue).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                </tr>
            `;
        });
    }

    // --- CONTROLE DE EXIBIÇÃO DE MODAL ---
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('createForm').reset();
    }

    // Seta automaticamente o preço do curso selecionado no modal
    function setCoursePrice(select) {
        const option = select.options[select.selectedIndex];
        const price = option.getAttribute('data-price');
        if (price) {
            document.getElementById('fieldAmount').value = parseFloat(price).toFixed(2);
        } else {
            document.getElementById('fieldAmount').value = '';
        }
    }

    // --- SALVAR LANÇAMENTO MANUAL (AJAX) ---
    async function saveManualTransaction(event) {
        event.preventDefault();
        
        const payload = {
            action: 'create',
            user_id: parseInt(document.getElementById('fieldStudent').value),
            course_id: parseInt(document.getElementById('fieldCourse').value),
            amount: parseFloat(document.getElementById('fieldAmount').value),
            payment_method: document.getElementById('fieldMethod').value,
            status: document.getElementById('fieldStatus').value
        };

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span>Lançando...</span> <span class="material-symbols-outlined animate-spin text-sm">sync</span>`;

        try {
            const response = await fetch('../api/admin/financial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao lançar transação.');

            showToast(res.message, 'success');
            closeCreateModal();
            setTimeout(() => window.location.reload(), 1200);

        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
        }
    }

    // --- EFETUAR ESTORNO/REEMBOLSO (AJAX) ---
    async function refundTransaction(id, amount) {
        if (!confirm(`Deseja realmente estornar permanentemente esta transação de R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}? O status será alterado para Reembolsada e a receita acumulada será reajustada.`)) return;

        try {
            const response = await fetch('../api/admin/financial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'refund', id: id })
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao processar estorno.');

            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- DELETAR REGISTRO DE TRANSAÇÃO (AJAX) ---
    async function deleteTransaction(id) {
        if (!confirm("Tem certeza absoluta de que deseja excluir permanentemente o registro desta transação do banco de dados? Esta ação é irreversível e removerá todos os vestígios contábeis da venda no sistema!")) return;

        try {
            const response = await fetch(`../api/admin/financial.php?id=${id}`, {
                method: 'DELETE'
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao remover transação.');

            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- FILTRO DINÂMICO DE TABELA (JS LOCAL RAPIDÍSSIMO) ---
    function filterTransactions() {
        const query = document.getElementById('tableSearch').value.toLowerCase();
        const statusVal = document.getElementById('filterStatus').value;
        const startVal = document.getElementById('filterStartDate').value;
        const endVal = document.getElementById('filterEndDate').value;

        const rows = document.querySelectorAll('.transaction-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const student = row.getAttribute('data-student').toLowerCase();
            const course = row.getAttribute('data-course').toLowerCase();
            const payId = row.getAttribute('data-payment-id').toLowerCase();
            const status = row.getAttribute('data-status');
            const date = row.getAttribute('data-date');

            const matchesSearch = student.includes(query) || course.includes(query) || payId.includes(query);
            const matchesStatus = !statusVal || status === statusVal;
            
            let matchesDate = true;
            if (startVal && date < startVal) matchesDate = false;
            if (endVal && date > endVal) matchesDate = false;

            if (matchesSearch && matchesStatus && matchesDate) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        // Toggle do aviso de sem registros
        const noTransRow = document.getElementById('noTransactionsRow');
        if (noTransRow) {
            if (visibleCount === 0) {
                noTransRow.classList.remove('hidden');
            } else {
                noTransRow.classList.add('hidden');
            }
        }
    }

    // --- EXPORTAÇÃO CSV DE RELATÓRIO CONTÁBIL ---
    function exportToCSV() {
        const rows = document.querySelectorAll('.transaction-row:not(.hidden)');
        if (rows.length === 0) {
            showToast("Nenhuma transação filtrada para exportar.", "error");
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8,ID_Venda,Aluno,Curso,Metodo_Pagamento,Valor,Gateway_ID,Status,Data_Lancamento\n";

        rows.forEach(row => {
            const student = row.getAttribute('data-student');
            const course = row.getAttribute('data-course');
            const payId = row.getAttribute('data-payment-id');
            const method = row.getAttribute('data-method').toUpperCase();
            const status = row.getAttribute('data-status').toUpperCase();
            const date = row.getAttribute('data-date');
            
            // Busca o valor diretamente da coluna da tabela correspondente
            const amountText = row.querySelector('td:nth-child(5)').innerText.replace('R$ ', '').replace('.', '').replace(',', '.');
            
            csvContent += `"${payId}","${student}","${course}","${method}","${amountText}","${payId}","${status}","${date}"\n`;
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `relatorio-financeiro-gt-${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showToast("Relatório financeiro exportado com sucesso!", "success");
    }

    // --- TOAST NOTIFICATIONS ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const bgClass = type === 'success' ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-red-500/10 border-red-500/20 text-red-400';
        const icon = type === 'success' ? 'check_circle' : 'error';

        const toast = document.createElement('div');
        toast.className = `glass-card border rounded-lg px-4 py-3 flex items-center gap-3 text-xs font-semibold shadow-xl transition-all duration-300 transform translate-y-2 opacity-0 ${bgClass}`;
        toast.innerHTML = `
            <span class="material-symbols-outlined text-[18px]">${icon}</span>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        }, 50);

        setTimeout(() => {
            toast.classList.add('translate-y-2', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>
</body>
</html>
