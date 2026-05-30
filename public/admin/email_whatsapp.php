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
    // 1. Busca campanhas de e-mail disparadas
    $emailStmt = $db->prepare("SELECT id, title, subject, status, sent_count, created_at FROM email_campaigns ORDER BY created_at DESC LIMIT 10");
    $emailStmt->execute();
    $emails = $emailStmt->fetchAll();

    // 2. Busca logs do WhatsApp disparados
    $waStmt = $db->prepare("SELECT id, phone, message, status, error_message, sent_at FROM whatsapp_logs ORDER BY sent_at DESC LIMIT 10");
    $waStmt->execute();
    $waLogs = $waStmt->fetchAll();

    // 3. Contadores rápidos
    $emailCountStmt = $db->prepare("SELECT SUM(sent_count) FROM email_campaigns");
    $emailCountStmt->execute();
    $totalEmailsSent = (int)$emailCountStmt->fetchColumn();

    $waSuccessStmt = $db->prepare("SELECT COUNT(*) FROM whatsapp_logs WHERE status = 'success'");
    $waSuccessStmt->execute();
    $waSuccess = (int)$waSuccessStmt->fetchColumn();

    $waFailedStmt = $db->prepare("SELECT COUNT(*) FROM whatsapp_logs WHERE status = 'failed'");
    $waFailedStmt->execute();
    $waFailed = (int)$waFailedStmt->fetchColumn();

} catch (\PDOException $e) {
    $emails = [];
    $waLogs = [];
    $totalEmailsSent = 0;
    $waSuccess = 0;
    $waFailed = 0;
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - E-mail & WhatsApp</title>
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="email_whatsapp.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">mail</span>
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
        <div class="flex-grow max-w-xl">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" placeholder="Buscar automações..." type="text">
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter-grid">
            <!-- Total Emails -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-primary/10 rounded-lg border border-primary/20">
                        <span class="material-symbols-outlined text-primary">mail</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">TOTAL DE E-MAILS ENVIADOS</p>
                    <h2 class="font-display-lg text-headline-md neon-text-gold"><?php echo number_format($totalEmailsSent); ?> disparos</h2>
                </div>
            </div>
            <!-- WhatsApp Success -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-status-published/10 rounded-lg border border-status-published/20">
                        <span class="material-symbols-outlined text-status-published">check_circle</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">WHATSAPP ENTREGUES</p>
                    <h2 class="font-display-lg text-headline-md text-status-published"><?php echo number_format($waSuccess); ?> entregues</h2>
                </div>
            </div>
            <!-- WhatsApp Failed -->
            <div class="glass-card rounded-xl p-5 flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <div class="p-2 bg-red-500/10 rounded-lg border border-red-500/20">
                        <span class="material-symbols-outlined text-red-500">error</span>
                    </div>
                </div>
                <div>
                    <p class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.15em] mb-1">WHATSAPP FALHAS</p>
                    <h2 class="font-display-lg text-headline-md text-red-500"><?php echo number_format($waFailed); ?> falhas</h2>
                </div>
            </div>
        </div>

        <!-- NEW: Dispach Message Form Section (Obsidian Gold Premium) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter-grid">
            <!-- Form E-mail Campaign -->
            <div class="glass-card rounded-xl p-6 space-y-4">
                <h3 class="font-title-lg text-title-lg text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-xl">send_and_archive</span>
                    Disparar Campanha de E-mail
                </h3>
                <form onsubmit="sendCampaign(event, 'email')" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Público Alvo</label>
                            <select id="emailSegment" onchange="toggleEmailRecipient(this.value)" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                                <option value="all">Todos os Alunos Registrados</option>
                                <option value="individual">Destinatário Único</option>
                            </select>
                        </div>
                        <div class="space-y-1 hidden" id="emailIndividualGroup">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">E-mail do Aluno</label>
                            <input type="email" id="emailTarget" placeholder="aluno@exemplo.com" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Título da Campanha</label>
                            <input type="text" id="emailTitle" required placeholder="ex: Alerta de Aula ao Vivo" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Assunto do E-mail</label>
                            <input type="text" id="emailSubject" required placeholder="ex: Não perca a mentoria de hoje à noite!" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Mensagem (Corpo HTML do E-mail)</label>
                        <textarea id="emailMessage" required placeholder="Digite o conteúdo da mensagem... (Dica: Use {nome} para personalizar o envio)" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs h-28 resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-primary py-3 rounded-lg text-background font-bold text-xs uppercase tracking-widest flex items-center justify-center gap-1.5 shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] transition-all">
                        <span>Disparar Campanha de E-mail</span>
                        <span class="material-symbols-outlined text-sm">mail_outline</span>
                    </button>
                </form>
            </div>

            <!-- Form WhatsApp Dispatch -->
            <div class="glass-card rounded-xl p-6 space-y-4">
                <h3 class="font-title-lg text-title-lg text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-xl">cell_tower</span>
                    Enviar Alerta WhatsApp
                </h3>
                <form onsubmit="sendCampaign(event, 'whatsapp')" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Público Alvo</label>
                            <select id="waSegment" onchange="toggleWaRecipient(this.value)" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                                <option value="individual">Destinatário Único</option>
                                <option value="all">Enviar para Todos (Alunos Registrados)</option>
                            </select>
                        </div>
                        <div class="space-y-1" id="waIndividualGroup">
                            <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Número WhatsApp (com DDD)</label>
                            <input type="text" id="waTarget" placeholder="ex: 11999998888" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Mensagem</label>
                        <textarea id="waMessage" required placeholder="Escreva o texto da notificação instantânea..." class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs h-44 resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-primary py-3 rounded-lg text-background font-bold text-xs uppercase tracking-widest flex items-center justify-center gap-1.5 shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] transition-all">
                        <span>Enviar Mensagem WhatsApp</span>
                        <span class="material-symbols-outlined text-sm">chat</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter-grid">
            <!-- Left: Emails Dispatch -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="font-title-lg text-title-lg text-white mb-4">Campanhas de E-mail Recentes</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-on-surface-variant font-label-sm text-[9px] uppercase tracking-wider bg-white/[0.02]">
                                <th class="px-4 py-3">TÍTULO</th>
                                <th class="px-4 py-3">ASSUNTO</th>
                                <th class="px-4 py-3">DISPAROS</th>
                                <th class="px-4 py-3">STATUS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (!empty($emails)): ?>
                                <?php foreach ($emails as $email): ?>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 font-bold text-white"><?php echo htmlspecialchars($email['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 text-white/70 max-w-[150px] truncate"><?php echo htmlspecialchars($email['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 font-bold"><?php echo $email['sent_count']; ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded bg-status-published/10 text-status-published text-[9px] border border-status-published/20 uppercase font-bold">DISPARADO</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-text-muted">Nenhuma campanha de e-mail registrada.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: WhatsApp Logs -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="font-title-lg text-title-lg text-white mb-4">Logs de Disparos WhatsApp</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-on-surface-variant font-label-sm text-[9px] uppercase tracking-wider bg-white/[0.02]">
                                <th class="px-4 py-3">TELEFONE</th>
                                <th class="px-4 py-3">MENSAGEM</th>
                                <th class="px-4 py-3">STATUS</th>
                                <th class="px-4 py-3">DATA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (!empty($waLogs)): ?>
                                <?php foreach ($waLogs as $log): ?>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 font-bold text-white font-mono"><?php echo htmlspecialchars($log['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 text-white/70 max-w-[150px] truncate"><?php echo htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($log['status'] === 'success'): ?>
                                                <span class="px-2 py-0.5 rounded bg-status-published/10 text-status-published text-[9px] border border-status-published/20 font-bold uppercase">SUCESSO</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded bg-red-500/10 text-red-500 text-[9px] border border-red-500/20 font-bold uppercase">FALHA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-on-surface-variant text-[10px]"><?php echo date('d/m H:i', strtotime($log['sent_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-text-muted">Nenhum log de WhatsApp registrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

<!-- Micro-interaction & Dispatch Script -->
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

    // Alternar campos de destinatário individual ou massa
    function toggleEmailRecipient(val) {
        const group = document.getElementById('emailIndividualGroup');
        if (val === 'individual') {
            group.classList.remove('hidden');
            document.getElementById('emailTarget').required = true;
        } else {
            group.classList.add('hidden');
            document.getElementById('emailTarget').required = false;
        }
    }

    function toggleWaRecipient(val) {
        const group = document.getElementById('waIndividualGroup');
        if (val === 'individual') {
            group.classList.remove('hidden');
            document.getElementById('waTarget').required = true;
        } else {
            group.classList.add('hidden');
            document.getElementById('waTarget').required = false;
        }
    }

    // --- REQUISIÇÃO AJAX DE DISPARO ---
    async function sendCampaign(event, channel) {
        event.preventDefault();
        
        let payload = { channel };

        if (channel === 'email') {
            const segment = document.getElementById('emailSegment').value;
            const target_email = document.getElementById('emailTarget').value;
            const title = document.getElementById('emailTitle').value;
            const subject = document.getElementById('emailSubject').value;
            const message = document.getElementById('emailMessage').value;

            payload.segment = segment;
            payload.target_email = target_email;
            payload.title = title;
            payload.subject = subject;
            payload.message = message;
        } else if (channel === 'whatsapp') {
            const segment = document.getElementById('waSegment').value;
            const target_phone = document.getElementById('waTarget').value;
            const message = document.getElementById('waMessage').value;

            payload.segment = segment;
            payload.target_phone = target_phone;
            payload.message = message;
        }

        // Feedback visual de carregamento
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span>Processando Disparos...</span> <span class="material-symbols-outlined animate-spin text-sm">sync</span>`;

        try {
            const response = await fetch('../api/admin/email_whatsapp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao efetuar o disparo das mensagens.');

            showToast(res.message, 'success');
            // Limpa o form de mensagem
            if (channel === 'email') {
                document.getElementById('emailMessage').value = '';
            } else {
                document.getElementById('waMessage').value = '';
            }
            // Recarrega em 1.5s para ver os logs atualizados
            setTimeout(() => window.location.reload(), 1500);

        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
        }
    }

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
