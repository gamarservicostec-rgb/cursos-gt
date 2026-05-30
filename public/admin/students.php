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

// Garante a coluna phone na tabela users (migração dinâmica autogerenciada)
try {
    $columnsStmt = $db->query("DESCRIBE `users`");
    $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);
    if (!in_array('phone', $columns)) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`");
    }
} catch (\Exception $e) {
    error_log("Erro de migração de users: " . $e->getMessage());
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Busca os estudantes cadastrados com opção de busca
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT id, name, email, phone, xp, level, current_streak, created_at 
            FROM users 
            WHERE role = 'student' AND (name LIKE :search OR email LIKE :search)
            ORDER BY name ASC
        ");
        $stmt->execute([':search' => "%{$search}%"]);
    } else {
        $stmt = $db->prepare("
            SELECT id, name, email, phone, xp, level, current_streak, created_at 
            FROM users 
            WHERE role = 'student' 
            ORDER BY name ASC
        ");
        $stmt->execute();
    }
    $students = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Erro ao carregar alunos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Gerenciamento de Alunos</title>
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
        
        /* Custom Premium Select Dropdown Style (Obsidian Gold) */
        select, select option {
            background-color: #111114 !important;
            color: #ffffff !important;
            color-scheme: dark !important;
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="students.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
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
        <form class="flex-1 max-w-xl" method="GET" action="students.php">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar aluno por nome ou e-mail..." type="text">
            </div>
        </form>
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
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-white/5 flex justify-between items-center bg-black/20">
                <div>
                    <h3 class="font-title-lg text-title-lg text-white">Alunos Registrados 👤</h3>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Central de suporte e gestão de dados cadastrais e métricas gamificadas (XP/Streak)</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.2em] bg-white/[0.03]">
                            <th class="px-6 py-4">ALUNO</th>
                            <th class="px-6 py-4">E-MAIL</th>
                            <th class="px-6 py-4">NÍVEL / XP</th>
                            <th class="px-6 py-4">STREAK DIÁRIO</th>
                            <th class="px-6 py-4">CADASTRO</th>
                            <th class="px-6 py-4 text-right">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-white/[0.04] transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-primary">person</span>
                                            </div>
                                            <div>
                                                <p class="font-body-md font-bold text-white"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="font-caption text-caption text-on-surface-variant uppercase tracking-wider">ID: #<?php echo $student['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/90"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded bg-primary/20 text-primary font-bold text-[10px]">Nível <?php echo $student['level']; ?></span>
                                            <span class="text-xs text-text-muted font-mono"><?php echo number_format($student['xp']); ?> XP</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/90">
                                        <div class="flex items-center gap-1 text-primary">
                                            <span class="material-symbols-outlined fill-1 text-sm text-status-draft">local_fire_department</span>
                                            <span class="font-bold text-xs"><?php echo $student['current_streak']; ?> dias</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-on-surface-variant"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2.5">
                                            <button onclick="viewStudent(<?php echo $student['id']; ?>)" class="p-2 rounded bg-white/5 border border-white/10 hover:border-primary/45 hover:text-primary transition-all flex items-center justify-center" title="Visualizar Perfil Completo">
                                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                            </button>
                                            <button onclick="editStudent(<?php echo $student['id']; ?>)" class="p-2 rounded bg-white/5 border border-white/10 hover:border-primary/45 hover:text-primary transition-all flex items-center justify-center" title="Editar Perfil / Gamificação">
                                                <span class="material-symbols-outlined text-[16px]">edit</span>
                                            </button>
                                            <button onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?>')" class="p-2 rounded bg-red-500/5 border border-red-500/15 text-red-400 hover:bg-red-500/20 transition-all flex items-center justify-center" title="Excluir Aluno">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-on-surface-variant font-semibold italic text-xs uppercase tracking-widest">Nenhum aluno encontrado no sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL VISUALIZAR DETALHES DO ALUNO -->
<div id="viewModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/80 backdrop-blur-md px-4">
    <div class="glass-card w-full max-w-2xl rounded-xl overflow-hidden flex flex-col max-h-[85vh] border border-primary/20 shadow-2xl">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center bg-black/40">
            <h3 class="text-xs font-bold text-white uppercase tracking-widest">Dossiê Completo do Aluno 🎖️</h3>
            <button onclick="closeViewModal()" class="material-symbols-outlined text-on-surface-variant hover:text-white transition-colors">close</button>
        </div>
        
        <div class="overflow-y-auto custom-scrollbar px-6 py-6 space-y-6">
            <!-- Header do Perfil -->
            <div class="flex items-center gap-4 border-b border-white/5 pb-5">
                <div class="w-14 h-14 rounded-xl bg-primary/10 border-2 border-primary/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-[32px]">person</span>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight" id="viewName">-</h2>
                    <p class="text-xs text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">mail</span>
                        <span id="viewEmail">-</span>
                    </p>
                    <p class="text-xs text-on-surface-variant flex items-center gap-1 mt-0.5">
                        <span class="material-symbols-outlined text-xs font-bold">chat</span>
                        <span id="viewPhone">-</span>
                    </p>
                </div>
            </div>

            <!-- Dados Acadêmicos & Gamificação -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-black/40 rounded-lg border border-white/5 p-4 text-center">
                    <p class="text-[9px] font-bold text-primary uppercase tracking-widest mb-1">Pontuação XP</p>
                    <p class="font-mono text-lg font-bold text-white" id="viewXp">0</p>
                </div>
                <div class="bg-black/40 rounded-lg border border-white/5 p-4 text-center">
                    <p class="text-[9px] font-bold text-primary uppercase tracking-widest mb-1">Nível LMS</p>
                    <p class="font-mono text-lg font-bold text-white" id="viewLevel">1</p>
                </div>
                <div class="bg-black/40 rounded-lg border border-white/5 p-4 text-center">
                    <p class="text-[9px] font-bold text-primary uppercase tracking-widest mb-1">Streak Diário</p>
                    <p class="font-mono text-lg font-bold text-white" id="viewStreak">0 dias</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Coluna: Matrículas -->
                <div class="space-y-3">
                    <h4 class="text-[10px] font-bold text-white uppercase tracking-widest border-b border-white/5 pb-2">Matrículas Ativas 📚</h4>
                    <div id="viewCoursesList" class="space-y-2 text-xs">
                        <!-- Carregado via JS -->
                    </div>
                </div>
                <!-- Coluna: Conquistas -->
                <div class="space-y-3">
                    <h4 class="text-[10px] font-bold text-white uppercase tracking-widest border-b border-white/5 pb-2">Medalhas & Conquistas 🏆</h4>
                    <div id="viewAchievementsList" class="space-y-2 text-xs">
                        <!-- Carregado via JS -->
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-black/40 px-6 py-4 border-t border-white/5 flex justify-end">
            <button onclick="closeViewModal()" class="px-5 py-2.5 rounded-lg bg-primary text-background font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition-all">Fechar Dossiê</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PERFIL / GAMIFICAÇÃO DO ALUNO -->
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/80 backdrop-blur-md px-4">
    <div class="glass-card w-full max-w-lg rounded-xl overflow-hidden flex flex-col max-h-[85vh] border border-primary/20 shadow-2xl">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center bg-black/40">
            <h3 class="text-xs font-bold text-white uppercase tracking-widest">Editar Perfil / Gamificação 🔧</h3>
            <button onclick="closeEditModal()" class="material-symbols-outlined text-on-surface-variant hover:text-white transition-colors">close</button>
        </div>
        
        <form id="editForm" onsubmit="saveStudent(event)" class="overflow-y-auto custom-scrollbar px-6 py-5 space-y-4">
            <input type="hidden" id="editId" name="id">

            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Nome Completo</label>
                <input type="text" id="editName" name="name" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
            </div>

            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Endereço de E-mail</label>
                <input type="email" id="editEmail" name="email" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
            </div>

            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Número WhatsApp (com DDD)</label>
                <input type="text" id="editPhone" name="phone" placeholder="ex: 11999998888" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">XP</label>
                    <input type="number" id="editXp" name="xp" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                </div>
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Nível</label>
                    <input type="number" id="editLevel" name="level" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                </div>
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Streak (dias)</label>
                    <input type="number" id="editStreak" name="streak" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-white/5 flex justify-end gap-3 bg-black/20 -mx-6 -mb-5 px-6 py-4">
                <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-lg border border-white/10 text-white/80 hover:text-white hover:bg-white/5 text-xs font-bold uppercase tracking-wider transition-colors">Cancelar</button>
                <button type="submit" class="bg-primary text-background px-5 py-2.5 rounded-lg font-bold text-xs uppercase tracking-wider shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] transition-all">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

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

    // --- LÓGICA DE GESTÃO DE ESTUDANTES (CRUD AJAX) ---
    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    async function viewStudent(id) {
        try {
            const response = await fetch(`../api/admin/students.php?id=${id}`);
            if (!response.ok) throw new Error('Não foi possível obter os dados do estudante.');
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao carregar.');

            const s = res.student;
            document.getElementById('viewName').innerText = s.name;
            document.getElementById('viewEmail').innerText = s.email;
            document.getElementById('viewPhone').innerText = s.phone || 'Sem telefone cadastrado';
            document.getElementById('viewXp').innerText = s.xp.toLocaleString();
            document.getElementById('viewLevel').innerText = s.level;
            document.getElementById('viewStreak').innerText = s.current_streak + ' dias';

            // Monta lista de matrículas
            const coursesContainer = document.getElementById('viewCoursesList');
            coursesContainer.innerHTML = '';
            if (res.courses.length === 0) {
                coursesContainer.innerHTML = '<p class="text-on-surface-variant italic">Sem matrículas ativas.</p>';
            } else {
                res.courses.forEach(c => {
                    coursesContainer.innerHTML += `
                        <div class="p-2 rounded bg-white/[0.02] border border-white/5 flex items-center justify-between">
                            <span class="font-bold text-white">${c.title}</span>
                            <span class="px-2 py-0.5 rounded text-[8px] font-bold bg-primary/10 text-primary uppercase border border-primary/20">${c.status === 'active' ? 'Matriculado' : 'Concluído'}</span>
                        </div>
                    `;
                });
            }

            // Monta lista de conquistas
            const achContainer = document.getElementById('viewAchievementsList');
            achContainer.innerHTML = '';
            if (res.achievements.length === 0) {
                achContainer.innerHTML = '<p class="text-on-surface-variant italic">Nenhuma medalha conquistada.</p>';
            } else {
                res.achievements.forEach(a => {
                    achContainer.innerHTML += `
                        <div class="p-2 rounded bg-emerald-500/[0.02] border border-emerald-500/10 flex flex-col gap-0.5">
                            <span class="font-bold text-emerald-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs fill-1">military_tech</span>
                                ${a.title}
                            </span>
                            <span class="text-[10px] text-text-muted">${a.description}</span>
                        </div>
                    `;
                });
            }

            document.getElementById('viewModal').classList.remove('hidden');
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function editStudent(id) {
        try {
            const response = await fetch(`../api/admin/students.php?id=${id}`);
            if (!response.ok) throw new Error('Não foi possível obter dados para edição.');
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao carregar.');

            const s = res.student;
            document.getElementById('editId').value = s.id;
            document.getElementById('editName').value = s.name;
            document.getElementById('editEmail').value = s.email;
            document.getElementById('editPhone').value = s.phone || '';
            document.getElementById('editXp').value = s.xp;
            document.getElementById('editLevel').value = s.level;
            document.getElementById('editStreak').value = s.current_streak;

            document.getElementById('editModal').classList.remove('hidden');
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function saveStudent(event) {
        event.preventDefault();
        const id = document.getElementById('editId').value;
        const name = document.getElementById('editName').value;
        const email = document.getElementById('editEmail').value;
        const phone = document.getElementById('editPhone').value;
        const xp = document.getElementById('editXp').value;
        const level = document.getElementById('editLevel').value;
        const streak = document.getElementById('editStreak').value;

        const payload = {
            id: parseInt(id),
            name, email, phone,
            xp: parseInt(xp),
            level: parseInt(level),
            streak: parseInt(streak)
        };

        try {
            const response = await fetch('../api/admin/students.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao salvar alterações.');

            showToast(res.message, 'success');
            closeEditModal();
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function deleteStudent(id, name) {
        if (!confirm(`Tem certeza absoluta de que deseja excluir permanentemente o aluno ${name}? Todas as suas matrículas, presenças e registros de gamificação serão deletados do sistema!`)) return;

        try {
            const response = await fetch(`../api/admin/students.php?id=${id}`, {
                method: 'DELETE'
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao excluir aluno.');

            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            showToast(err.message, 'error');
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
