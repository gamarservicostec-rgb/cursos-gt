<?php
use Config\Database;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

// Roda a migração de cupons para garantir que a tabela exista
require_once __DIR__ . '/../../database/create_coupons_table.php';

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM coupons ORDER BY created_at DESC");
    $stmt->execute();
    $coupons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    die("Erro ao carregar cupons: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Gerenciamento de Cupons</title>
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="coupons.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">redeem</span>
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
        <div class="flex-grow max-w-xl">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" placeholder="Buscar cupom promocional..." type="text">
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
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-white/5 flex justify-between items-center bg-black/20">
                <div>
                    <h3 class="font-title-lg text-title-lg text-white">Central de Cupons 🏷️</h3>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Crie, modifique e acompanhe cupons promocionais integrados ao banco de dados</p>
                </div>
                <button onclick="openAddModal()" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] hover:-translate-y-0.5 transition-all active:translate-y-0 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm font-bold">add</span>
                    Adicionar Cupom
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-on-surface-variant font-label-sm text-[10px] uppercase tracking-[0.2em] bg-white/[0.03]">
                            <th class="px-6 py-4">CÓDIGO</th>
                            <th class="px-6 py-4">TIPO</th>
                            <th class="px-6 py-4">VALOR DESCONTO</th>
                            <th class="px-6 py-4">DESCRIÇÃO</th>
                            <th class="px-6 py-4">UTILIZAÇÕES</th>
                            <th class="px-6 py-4">STATUS</th>
                            <th class="px-6 py-4">EXPIRA EM</th>
                            <th class="px-6 py-4 text-right">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-on-surface-variant font-semibold italic text-xs uppercase tracking-widest">Nenhum cupom cadastrado no banco de dados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $c): ?>
                                <tr class="hover:bg-white/[0.04] transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-primary text-sm">redeem</span>
                                            <span class="font-mono font-bold text-white tracking-widest uppercase text-xs"><?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/90">
                                        <?php echo ($c['type'] === 'fixed') ? 'Fixo (R$)' : 'Porcentagem (%)'; ?>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-primary font-bold">
                                        <?php echo ($c['type'] === 'fixed') ? 'R$ ' . number_format($c['value'], 2, ',', '.') : number_format($c['value'], 0) . '%'; ?>
                                    </td>
                                    <td class="px-6 py-4 font-body-md text-white/70 max-w-xs truncate"><?php echo htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-4 font-body-md text-white/90">
                                        <span class="font-bold font-mono text-xs"><?php echo $c['usage_count']; ?></span> usos
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($c['status'] === 'active'): ?>
                                            <span class="px-3 py-1 rounded-full bg-status-published/10 text-status-published font-bold text-[10px] uppercase tracking-widest border border-status-published/20">ATIVO</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-500 font-bold text-[10px] uppercase tracking-widest border border-red-500/20">INATIVO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-on-surface-variant"><?php echo date('d/m/Y', strtotime($c['expires_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2.5">
                                            <button onclick="editCoupon(<?php echo $c['id']; ?>)" class="p-2 rounded bg-white/5 border border-white/10 hover:border-primary/45 hover:text-primary transition-all flex items-center justify-center" title="Editar Cupom">
                                                <span class="material-symbols-outlined text-[16px]">edit</span>
                                            </button>
                                            <button onclick="deleteCoupon(<?php echo $c['id']; ?>, '<?php echo $c['code']; ?>')" class="p-2 rounded bg-red-500/5 border border-red-500/15 text-red-400 hover:bg-red-500/20 transition-all flex items-center justify-center" title="Excluir Cupom">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL DE CUPOM (ADICIONAR / EDITAR) -->
<div id="couponModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/80 backdrop-blur-md px-4">
    <div class="glass-card w-full max-w-lg rounded-xl overflow-hidden flex flex-col max-h-[85vh] border border-primary/20 shadow-2xl">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center bg-black/40">
            <h3 class="text-xs font-bold text-white uppercase tracking-widest" id="modalTitle">Adicionar Novo Cupom 🏷️</h3>
            <button onclick="closeCouponModal()" class="material-symbols-outlined text-on-surface-variant hover:text-white transition-colors">close</button>
        </div>
        <!-- Modal Body -->
        <form id="couponForm" onsubmit="saveCoupon(event)" class="overflow-y-auto custom-scrollbar px-6 py-5 space-y-4">
            <input type="hidden" id="couponId" name="id">
            
            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Código do Cupom</label>
                <input type="text" id="couponCode" name="code" required placeholder="ex: BLACKFRIDAY50" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white font-mono uppercase focus:border-primary focus:ring-1 focus:ring-primary text-xs">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Tipo de Desconto</label>
                    <select id="couponType" name="type" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        <option value="percentage">Porcentagem (%)</option>
                        <option value="fixed">Fixo (R$)</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Valor</label>
                    <input type="number" step="0.01" id="couponValue" name="value" required placeholder="ex: 50" class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Descrição</label>
                <textarea id="couponDescription" name="description" placeholder="Descreva as condições deste cupom..." class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs h-20 resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Status</label>
                    <select id="couponStatus" name="status" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary">Expira em</label>
                    <input type="date" id="couponExpiresAt" name="expires_at" required class="w-full px-4 py-3 rounded-lg bg-black/40 border border-white/10 text-white focus:border-primary focus:ring-1 focus:ring-primary text-xs" style="color-scheme: dark;">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-white/5 flex justify-end gap-3 bg-black/20 -mx-6 -mb-5 px-6 py-4">
                <button type="button" onclick="closeCouponModal()" class="px-5 py-2.5 rounded-lg border border-white/10 text-white/80 hover:text-white hover:bg-white/5 text-xs font-bold uppercase tracking-wider transition-colors">Cancelar</button>
                <button type="submit" class="bg-primary text-background px-5 py-2.5 rounded-lg font-bold text-xs uppercase tracking-wider shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] transition-all">Salvar Cupom</button>
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

    // --- LÓGICA DE GERENCIAMENTO DE CUPONS (CRUD AJAX) ---
    function openAddModal() {
        document.getElementById('modalTitle').innerText = "Adicionar Novo Cupom 🏷️";
        document.getElementById('couponId').value = "";
        document.getElementById('couponForm').reset();
        document.getElementById('couponExpiresAt').value = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]; // +30 dias padrão
        document.getElementById('couponModal').classList.remove('hidden');
    }

    function closeCouponModal() {
        document.getElementById('couponModal').classList.add('hidden');
    }

    async function editCoupon(id) {
        try {
            const response = await fetch(`../api/admin/coupons.php?id=${id}`);
            if (!response.ok) throw new Error('Erro ao obter detalhes do cupom.');
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro inesperado.');

            const c = res.coupon;
            document.getElementById('modalTitle').innerText = "Editar Cupom 🏷️";
            document.getElementById('couponId').value = c.id;
            document.getElementById('couponCode').value = c.code;
            document.getElementById('couponType').value = c.type;
            document.getElementById('couponValue').value = c.value;
            document.getElementById('couponDescription').value = c.description;
            document.getElementById('couponStatus').value = c.status;
            document.getElementById('couponExpiresAt').value = c.expires_at;

            document.getElementById('couponModal').classList.remove('hidden');
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function saveCoupon(event) {
        event.preventDefault();
        const id = document.getElementById('couponId').value;
        const code = document.getElementById('couponCode').value;
        const type = document.getElementById('couponType').value;
        const value = document.getElementById('couponValue').value;
        const description = document.getElementById('couponDescription').value;
        const status = document.getElementById('couponStatus').value;
        const expires_at = document.getElementById('couponExpiresAt').value;

        const payload = {
            code, type, value: parseFloat(value), description, status, expires_at
        };
        if (id) {
            payload.id = parseInt(id);
        }

        try {
            const response = await fetch('../api/admin/coupons.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao salvar.');

            showToast(res.message, 'success');
            closeCouponModal();
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function deleteCoupon(id, code) {
        if (!confirm(`Deseja realmente excluir permanentemente o cupom ${code}?`)) return;

        try {
            const response = await fetch(`../api/admin/coupons.php?id=${id}`, {
                method: 'DELETE'
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao excluir.');

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
