<?php
use Config\Database;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'];

// Gera token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$errorMessage = "";
$successMessage = "";

// PROCESSAMENTO DE FORMULÁRIO POST (Mutabilidade do Suporte)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die("Requisição inválida (falha no token CSRF).");
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'send_reply') {
            $ticketId = filter_var($_POST['ticket_id'] ?? 0, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');

            if (!$ticketId || empty($message)) {
                $errorMessage = "Preencha a mensagem de resposta.";
            } else {
                // Inserir mensagem de resposta do Admin
                $insertMsg = $db->prepare("INSERT INTO support_messages (ticket_id, user_id, message) VALUES (:ticket_id, :user_id, :message)");
                $insertMsg->execute([
                    ':ticket_id' => $ticketId,
                    ':user_id' => $adminId,
                    ':message' => $message
                ]);

                // Atualizar status do ticket para "em andamento" se estiver como "aberto"
                $updateTicket = $db->prepare("UPDATE support_tickets SET status = 'in_progress' WHERE id = :id AND status = 'open'");
                $updateTicket->execute([':id' => $ticketId]);

                // Gravar na auditoria administrativa
                $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'responder_suporte', :resource, :details)");
                $logStmt->execute([
                    ':admin_id' => $adminId,
                    ':resource' => "support_tickets/{$ticketId}",
                    ':details' => "Resposta enviada no ticket ID {$ticketId}"
                ]);

                header("Location: support.php?ticket_id=" . $ticketId . "&success=1");
                exit;
            }
        } elseif ($action === 'resolve_ticket') {
            $ticketId = filter_var($_POST['ticket_id'] ?? 0, FILTER_VALIDATE_INT);

            if ($ticketId) {
                // Atualizar status para resolvido
                $updateStatus = $db->prepare("UPDATE support_tickets SET status = 'resolved' WHERE id = :id");
                $updateStatus->execute([':id' => $ticketId]);

                // Gravar na auditoria
                $logStmt = $db->prepare("INSERT INTO admin_activity (admin_id, action, affected_resource, details) VALUES (:admin_id, 'resolver_suporte', :resource, :details)");
                $logStmt->execute([
                    ':admin_id' => $adminId,
                    ':resource' => "support_tickets/{$ticketId}",
                    ':details' => "Ticket ID {$ticketId} marcado como resolvido"
                ]);

                header("Location: support.php?ticket_id=" . $ticketId . "&success=2");
                exit;
            }
        }
    } catch (\PDOException $e) {
        $errorMessage = "Erro no banco de dados: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $successMessage = "Mensagem enviada com sucesso.";
    if ($_GET['success'] == 2) $successMessage = "Ticket marcado como resolvido.";
}

// RECUPERAÇÃO DE DADOS (Tickets, Mensagens e Logs de Auditoria)
try {
    // 1. Listar todos os tickets
    $ticketsStmt = $db->prepare("
        SELECT st.id, st.subject, st.status, st.created_at, u.name as student_name, u.email as student_email 
        FROM support_tickets st 
        JOIN users u ON st.user_id = u.id 
        ORDER BY st.created_at DESC
    ");
    $ticketsStmt->execute();
    $tickets = $ticketsStmt->fetchAll();

    // 2. Ticket selecionado para chat
    $activeTicket = null;
    $chatMessages = [];
    $activeTicketId = isset($_GET['ticket_id']) ? filter_var($_GET['ticket_id'], FILTER_VALIDATE_INT) : null;

    if ($activeTicketId) {
        // Busca cabeçalho do ticket
        $ticketStmt = $db->prepare("
            SELECT st.id, st.subject, st.status, st.created_at, u.name as student_name, u.email as student_email 
            FROM support_tickets st 
            JOIN users u ON st.user_id = u.id 
            WHERE st.id = :id
            LIMIT 1
        ");
        $ticketStmt->execute([':id' => $activeTicketId]);
        $activeTicket = $ticketStmt->fetch();

        if ($activeTicket) {
            // Busca mensagens do chat
            $msgsStmt = $db->prepare("
                SELECT sm.id, sm.message, sm.created_at, sm.user_id, u.name as sender_name, u.role as sender_role 
                FROM support_messages sm
                JOIN users u ON sm.user_id = u.id
                WHERE sm.ticket_id = :ticket_id
                ORDER BY sm.created_at ASC
            ");
            $msgsStmt->execute([':ticket_id' => $activeTicketId]);
            $chatMessages = $msgsStmt->fetchAll();
        }
    }

    // 3. Listar logs de auditoria técnica (Últimos 15)
    $logsStmt = $db->prepare("
        SELECT al.id, al.action, al.details, al.ip_address, al.created_at, u.name as user_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 15
    ");
    $logsStmt->execute();
    $auditLogs = $logsStmt->fetchAll();

} catch (\PDOException $e) {
    die("Erro interno ao carregar suporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Suporte & Logs — Cursos GT</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.cdnfonts.com/css/clash-display" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/satoshi" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
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
        .btn-primary {
            background-color: #f2c94c;
            color: #070708;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #FFD700;
            box-shadow: 0 0 20px rgba(242, 201, 76, 0.35);
        }
        .input-glass {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transition: all 0.3s ease;
        }
        .input-glass:focus {
            background: rgba(255, 255, 255, 0.04);
            border-color: #f2c94c;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.15);
            outline: none;
        }
        .status-pill {
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-open {
            background-color: rgba(255, 59, 48, 0.1);
            color: #FF3B30;
            border: 1px solid rgba(255, 59, 48, 0.2);
        }
        .status-progress {
            background-color: rgba(242, 201, 76, 0.1);
            color: #f2c94c;
            border: 1px solid rgba(242, 201, 76, 0.2);
        }
        .status-resolved {
            background-color: rgba(52, 199, 89, 0.1);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.2);
        }
        .status-closed {
            background-color: rgba(255, 255, 255, 0.05);
            color: #8F8F9D;
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="support.php">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">support_agent</span>
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
        <section class="md:col-span-9 space-y-8">
            
            <!-- Alert Messages -->
            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-semibold px-4 py-3 rounded-lg flex items-center gap-2 shadow-lg">
                    <span class="material-symbols-outlined">error</span>
                    <span><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($successMessage)): ?>
                <div class="bg-primary/10 border border-primary/20 text-primary text-xs font-semibold px-4 py-3 rounded-lg flex items-center gap-2 shadow-lg">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Interactive Support Chat Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Ticket List Column (5 cols) -->
                <div class="lg:col-span-5 glass-card rounded-xl p-6 flex flex-col h-[500px]">
                    <h3 class="text-xs font-bold text-text-main uppercase tracking-widest mb-4">Chamados Disponíveis 📨</h3>
                    
                    <div class="flex-grow overflow-y-auto space-y-3 pr-1">
                        <?php if (empty($tickets)): ?>
                            <p class="text-xs text-text-muted text-center py-12">Nenhum ticket aberto.</p>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <a href="support.php?ticket_id=<?php echo $t['id']; ?>" class="block p-3.5 bg-black/40 border rounded-lg transition-all hover:border-primary/25 <?php echo ($activeTicketId === $t['id']) ? 'border-primary/45 bg-primary/[0.02]' : 'border-border-color'; ?>">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="status-pill <?php 
                                            echo ($t['status'] === 'open') ? 'status-open' : (($t['status'] === 'in_progress') ? 'status-progress' : (($t['status'] === 'resolved') ? 'status-resolved' : 'status-closed')); 
                                        ?>">
                                            <?php 
                                                echo ($t['status'] === 'open') ? 'Aberto' : (($t['status'] === 'in_progress') ? 'Em Progresso' : (($t['status'] === 'resolved') ? 'Resolvido' : 'Fechado')); 
                                            ?>
                                        </span>
                                        <span class="text-[9px] text-text-muted font-bold"><?php echo date('d/m H:i', strtotime($t['created_at'])); ?></span>
                                    </div>
                                    <h4 class="text-xs font-bold text-text-main leading-tight line-clamp-1"><?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="text-[9px] text-text-muted font-bold uppercase tracking-wider mt-1.5">Autor: <?php echo htmlspecialchars($t['student_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Box Area Column (7 cols) -->
                <div class="lg:col-span-7 glass-card rounded-xl p-6 flex flex-col h-[500px]">
                    <?php if (!$activeTicket): ?>
                        <div class="flex-grow flex flex-col items-center justify-center text-center text-text-muted text-xs font-bold uppercase tracking-wider">
                            <span class="material-symbols-outlined text-[48px] text-border-color mb-3">forum</span>
                            Selecione um chamado da lista para iniciar o atendimento.
                        </div>
                    <?php else: ?>
                        <!-- Chat Header -->
                        <div class="border-b border-border-color pb-4 mb-4 flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-text-main uppercase tracking-wider leading-tight"><?php echo htmlspecialchars($activeTicket['subject'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <span class="text-[9px] text-text-muted font-semibold">Conversando com: <?php echo htmlspecialchars($activeTicket['student_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $activeTicket['student_email']; ?>)</span>
                            </div>
                            
                            <?php if ($activeTicket['status'] !== 'resolved' && $activeTicket['status'] !== 'closed'): ?>
                                <form method="POST" action="support.php" onsubmit="return confirm('Deseja realmente marcar este chamado como resolvido?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="resolve_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $activeTicket['id']; ?>">
                                    <button type="submit" class="border border-green-500/20 bg-green-500/5 text-green-400 hover:bg-green-500/10 flex items-center gap-1.5 px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-wider transition-all">
                                        <span class="material-symbols-outlined text-[14px]">done</span>
                                        Resolver
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="status-pill status-resolved">Resolvido</span>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Messages Scroller -->
                        <div class="flex-grow overflow-y-auto space-y-4 pr-1 mb-4 flex flex-col" id="chatMessageScroller">
                            <?php foreach ($chatMessages as $msg): ?>
                                <?php 
                                    $isAdmin = ($msg['sender_role'] === 'admin');
                                    $alignment = $isAdmin ? 'self-end bg-primary/[0.04] border-primary/20 text-right' : 'self-start bg-black/40 border-border-color';
                                    $senderLabel = $isAdmin ? 'Você' : htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="max-w-[85%] rounded-lg p-3 border text-xs leading-relaxed <?php echo $alignment; ?>">
                                    <div class="text-[9px] font-bold uppercase tracking-widest text-text-muted mb-1">
                                        <?php echo $senderLabel; ?> • <span class="font-normal text-[8px]"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                    <p class="text-text-main font-medium whitespace-pre-line text-left"><?php echo htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Chat Reply Form -->
                        <?php if ($activeTicket['status'] !== 'resolved' && $activeTicket['status'] !== 'closed'): ?>
                            <form method="POST" action="support.php" class="flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="send_reply">
                                <input type="hidden" name="ticket_id" value="<?php echo $activeTicket['id']; ?>">
                                <input type="text" name="message" required placeholder="Digite sua mensagem de resposta..." autocomplete="off" class="flex-grow px-4 py-3 rounded-lg input-glass text-xs">
                                <button type="submit" class="btn-primary flex items-center justify-center px-5 rounded-lg">
                                    <span class="material-symbols-outlined text-[20px]">send</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="p-3 bg-white/5 border border-border-color rounded-lg text-center text-[10px] font-bold uppercase tracking-wider text-text-muted">
                                Este chamado foi concluído.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Real-Time Terminal Audit Log (12 cols) -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-xs font-bold text-text-main uppercase tracking-widest mb-4">Auditoria e Segurança em Tempo Real (Terminal) 🖥️</h3>
                
                <div class="bg-black/60 border border-border-color rounded-lg p-4 font-mono text-[11px] leading-relaxed max-h-[300px] overflow-y-auto space-y-2 text-text-muted">
                    <?php if (empty($auditLogs)): ?>
                        <div class="text-center py-6">Nenhum log de segurança gerado.</div>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <?php 
                                // Formata tag de gravidade/ação com cores baseadas na criticidade
                                $actionName = strtolower($log['action']);
                                $actionColor = "text-primary";
                                if (strpos($actionName, 'excluir') !== false || strpos($actionName, 'fail') !== false || strpos($actionName, 'bloqueio') !== false) {
                                    $actionColor = "text-red-500 font-bold";
                                } elseif (strpos($actionName, 'login') !== false || strpos($actionName, 'autenticacao') !== false) {
                                    $actionColor = "text-green-400";
                                }
                            ?>
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-1 border-b border-white/[0.02] pb-1.5 last:border-b-0 last:pb-0">
                                <div>
                                    <span class="text-text-muted">[<?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>]</span>
                                    <span class="<?php echo $actionColor; ?>"> [<?php echo strtoupper(htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8')); ?>] </span>
                                    <span class="text-text-main font-medium"><?php echo htmlspecialchars($log['details'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="text-[9px] sm:text-[10px] text-text-muted text-right flex sm:block justify-between font-bold uppercase sm:font-normal">
                                    <span>IP: <?php echo $log['ip_address']; ?></span>
                                    <span class="sm:ml-2">Operador: <?php echo htmlspecialchars($log['user_name'] ?? 'Sistema', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </main>
</div>

    <script>
        // Mantém a barra de rolagem do chat sempre na última mensagem
        document.addEventListener('DOMContentLoaded', () => {
            const scroller = document.getElementById('chatMessageScroller');
            if (scroller) {
                scroller.scrollTop = scroller.scrollHeight;
            }
        });
    </script>
</body>
</html>
