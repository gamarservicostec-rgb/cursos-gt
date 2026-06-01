<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

// Executa migrações de banco de dados para templates de certificados
require_once __DIR__ . '/../../database/create_certificate_tables.php';

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca os cursos cadastrados para associar o template de certificado
    $coursesStmt = $db->prepare("SELECT id, title FROM courses ORDER BY title ASC");
    $coursesStmt->execute();
    $courses = $coursesStmt->fetchAll();

    // Busca os alunos (usuários com role 'student')
    $studentsStmt = $db->prepare("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name ASC");
    $studentsStmt->execute();
    $students = $studentsStmt->fetchAll();

} catch (\PDOException $e) {
    die("Erro interno: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Editor de Certificados — Cursos GT</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.cdnfonts.com/css/clash-display" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/satoshi" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@500;700&family=Montserrat:wght@500;700&family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Plus+Jakarta+Sans:wght@500;700&family=Outfit:wght@500;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
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
        .custom-input, .custom-select {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .custom-input:focus, .custom-select:focus {
            border-color: #f2c94c !important;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.15);
            outline: none;
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
        canvas {
            border: 2px dashed rgba(242, 201, 76, 0.2);
            background-color: #111114;
            box-shadow: 0 15px 30px rgba(0,0,0,0.6);
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
            <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="certificates.php">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
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
        <section class="md:col-span-9 space-y-8 pb-16">
            
            <!-- Welcome Header -->
            <div class="glass-card rounded-xl p-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-text-main">Editor Visual de Certificados</h1>
                    <p class="text-text-muted text-[10px] font-bold uppercase tracking-widest mt-1">Desenhe e posicione textos dinâmicos de certificados no canvas</p>
                </div>
                <div class="text-[10px] font-bold text-primary border border-primary/20 bg-primary/5 rounded px-3 py-1.5 uppercase tracking-widest">
                    HTML5 Canvas Engine v1.0
                </div>
            </div>

            <!-- Seção de Certificados Emitidos (Controle Acadêmico) -->
            <div class="glass-card rounded-xl p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-widest font-display flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">workspace_premium</span>
                            Certificados Emitidos (Controle Acadêmico)
                        </h2>
                        <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider mt-1">Gerencie, revogue ou emita manualmente certificados para seus alunos</p>
                    </div>
                    <button onclick="openEmitModal()" class="btn-primary font-bold px-4 py-2.5 rounded text-[10px] uppercase tracking-widest flex items-center gap-1.5 shadow-[0_0_15px_rgba(242,201,76,0.15)] hover:scale-[1.02] active:scale-[0.98] transition-transform">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Emitir Manualmente
                    </button>
                </div>

                <!-- Tabela de Certificados -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-primary uppercase font-bold text-[9px] tracking-widest">
                                <th class="py-3 px-4">Aluno</th>
                                <th class="py-3 px-4">Curso</th>
                                <th class="py-3 px-4">Código Autenticidade</th>
                                <th class="py-3 px-4">Data Emissão</th>
                                <th class="py-3 px-4 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="emittedCertificatesTable" class="divide-y divide-white/5 font-medium text-white/80">
                            <tr>
                                <td colspan="5" class="text-center py-6 text-on-surface-variant text-[10px] font-bold uppercase tracking-widest">Carregando certificados emitidos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Editor Layout: Tools Panel + Canvas Box -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Left Column: Controls (4 cols) -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="glass-card rounded-xl p-6 space-y-6">
                        <h3 class="text-xs font-bold text-white uppercase tracking-widest border-b border-white/5 pb-3">Ferramentas de Design</h3>
                        
                        <!-- Course Association -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest">Vincular ao Curso</label>
                            <select class="custom-select w-full rounded custom-select" id="courseSelect">
                                <option value="">Selecione um Treinamento...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Uploads de Imagens (Fundo, Logo, Assinatura) -->
                        <div class="space-y-3">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest">Imagens do Certificado</label>
                            
                            <div class="grid grid-cols-1 gap-2.5">
                                <!-- Upload Fundo -->
                                <div class="flex items-center gap-2">
                                    <input type="file" id="bgUpload" accept="image/*" class="hidden" onchange="handleImageUpload('background', this.files[0])">
                                    <button class="flex-1 text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center gap-2 text-xs font-semibold" onclick="document.getElementById('bgUpload').click()">
                                        <span class="material-symbols-outlined text-primary text-[18px]">wallpaper</span>
                                        <span>Fundo (Upload)</span>
                                    </button>
                                    <button id="btnDownloadBg" class="p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 text-primary hover:bg-primary/10 transition-colors flex items-center justify-center" onclick="downloadImage('background')" title="Baixar Imagem de Fundo Atual">
                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                    </button>
                                </div>
                                <!-- Upload Logo -->
                                <div class="flex items-center gap-2">
                                    <input type="file" id="logoUpload" accept="image/*" class="hidden" onchange="handleImageUpload('logo', this.files[0])">
                                    <button class="flex-1 text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center gap-2 text-xs font-semibold" onclick="document.getElementById('logoUpload').click()">
                                        <span class="material-symbols-outlined text-primary text-[18px]">brand_awareness</span>
                                        <span>Logo (Upload)</span>
                                    </button>
                                    <button id="btnDownloadLogo" class="p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 text-primary hover:bg-primary/10 transition-colors flex items-center justify-center" onclick="downloadImage('logo')" title="Baixar Logo Institucional Atual">
                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                    </button>
                                </div>
                                <!-- Upload Assinatura -->
                                <div class="flex items-center gap-2">
                                    <input type="file" id="sigUpload" accept="image/*" class="hidden" onchange="handleImageUpload('signature', this.files[0])">
                                    <button class="flex-1 text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center gap-2 text-xs font-semibold" onclick="document.getElementById('sigUpload').click()">
                                        <span class="material-symbols-outlined text-primary text-[18px]">signature</span>
                                        <span>Assinatura (Upload)</span>
                                    </button>
                                    <button id="btnDownloadSig" class="p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 text-primary hover:bg-primary/10 transition-colors flex items-center justify-center" onclick="downloadImage('signature')" title="Baixar Imagem de Assinatura Atual">
                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Adicionar Texto Customizado -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest">Inserir Texto Customizado</label>
                            <div class="flex gap-2">
                                <input type="text" id="customTextInput" placeholder="ex: Carga Horária: 40h" class="custom-input flex-1 rounded px-3 py-2 text-xs">
                                <button class="bg-primary hover:bg-yellow-500 text-black font-bold px-3 py-2 rounded text-xs transition-colors flex items-center" onclick="addCustomTextElement()">
                                    <span class="material-symbols-outlined text-[18px]">add</span>
                                </button>
                            </div>
                        </div>

                        <!-- Certificate Fields Selector -->
                        <div class="space-y-4">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest">Campos Dinâmicos</label>
                            
                            <div class="space-y-2 text-xs font-semibold">
                                <button class="w-full text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center justify-between" onclick="addTextElement('Nome do Aluno', '{student_name}', 200, 180, 24, '#F5F5F7')">
                                    <span>Nome do Aluno</span>
                                    <span class="text-[9px] font-mono text-primary font-bold">{student_name}</span>
                                </button>
                                <button class="w-full text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center justify-between" onclick="addTextElement('Título do Curso', '{course_title}', 200, 240, 20, '#f2c94c')">
                                    <span>Título do Curso</span>
                                    <span class="text-[9px] font-mono text-primary font-bold">{course_title}</span>
                                </button>
                                <button class="w-full text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center justify-between" onclick="addTextElement('Data de Emissão', 'Emitido em {date}', 200, 300, 12, '#8F8F9D')">
                                    <span>Data de Emissão</span>
                                    <span class="text-[9px] font-mono text-primary font-bold">{date}</span>
                                </button>
                                <button class="w-full text-left p-2.5 rounded bg-black/40 border border-white/10 hover:border-primary/40 transition-colors flex items-center justify-between" onclick="addTextElement('Código do QR Code', 'Autenticidade: {code}', 200, 350, 10, '#8F8F9D')">
                                    <span>Código QR Code</span>
                                    <span class="text-[9px] font-mono text-primary font-bold">{code}</span>
                                </button>
                            </div>
                        </div>

                        <div class="h-[1px] bg-white/5"></div>

                        <!-- Active Element Controls (size, color, text value, dimensions) -->
                        <div class="space-y-4" id="elementControls" style="display:none;">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest">Ajuste do Elemento Ativo</label>
                            
                            <!-- Nome / Tipo do Elemento -->
                            <div class="text-[10px] text-white/50 uppercase font-bold tracking-wider" id="activeElementName">Texto Selecionado</div>

                            <!-- Text Controls -->
                            <div id="textControlsSection" class="space-y-4">
                                <!-- Font Family -->
                                <div class="space-y-2">
                                    <label class="block text-[10px] text-white/60">Família da Fonte</label>
                                    <select id="elementFont" class="custom-select w-full rounded" onchange="updateActiveElement('font', this.value)">
                                        <option value="Clash Display">Clash Display</option>
                                        <option value="Satoshi">Satoshi</option>
                                        <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                                        <option value="Outfit">Outfit</option>
                                        <option value="Playfair Display">Playfair Display</option>
                                        <option value="Cinzel">Cinzel</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Alex Brush">Alex Brush (Caligráfica)</option>
                                    </select>
                                </div>
                                <!-- Bold and Italic toggles -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="elementBold" class="rounded border-white/10 bg-black/40 text-primary focus:ring-primary" onchange="updateActiveElement('bold', this.checked)">
                                        <label for="elementBold" class="text-[10px] text-white/60 font-semibold cursor-pointer">Negrito</label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="elementItalic" class="rounded border-white/10 bg-black/40 text-primary focus:ring-primary" onchange="updateActiveElement('italic', this.checked)">
                                        <label for="elementItalic" class="text-[10px] text-white/60 font-semibold cursor-pointer">Itálico</label>
                                    </div>
                                </div>
                                <!-- Font Size -->
                                <div class="space-y-2">
                                    <label class="block text-[10px] text-white/60">Tamanho da Fonte (px)</label>
                                    <input type="number" id="elementSize" class="custom-input w-full rounded" oninput="updateActiveElement('size', this.value)">
                                </div>
                                <!-- Color Picker -->
                                <div class="space-y-2">
                                    <label class="block text-[10px] text-white/60">Cor do Texto (Hex)</label>
                                    <input type="text" id="elementColor" class="custom-input w-full rounded" oninput="updateActiveElement('color', this.value)">
                                </div>
                                <!-- Content Value for Custom Texts -->
                                <div class="space-y-2" id="textValueGroup" style="display:none;">
                                    <label class="block text-[10px] text-white/60">Texto</label>
                                    <input type="text" id="elementValue" class="custom-input w-full rounded" oninput="updateActiveElement('text', this.value)">
                                </div>
                            </div>

                            <!-- Image Controls -->
                            <div id="imageControlsSection" class="space-y-4" style="display:none;">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="block text-[10px] text-white/60">Largura (px)</label>
                                        <input type="number" id="elementWidth" class="custom-input w-full rounded" oninput="updateActiveElement('width', this.value)">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] text-white/60">Altura (px)</label>
                                        <input type="number" id="elementHeight" class="custom-input w-full rounded" oninput="updateActiveElement('height', this.value)">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Button -->
                            <button class="w-full py-2 rounded bg-red-500/10 border border-red-500/20 text-red-400 font-bold text-[10px] uppercase tracking-wider hover:bg-red-500/20 transition-all flex items-center justify-center gap-1" id="deleteElementBtn" onclick="deleteActiveElement()">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                <span>Remover Elemento</span>
                            </button>
                        </div>

                        <!-- Save Template Button -->
                        <button class="w-full btn-primary font-bold py-3.5 rounded text-[10px] uppercase tracking-widest flex items-center justify-center gap-1.5" onclick="saveTemplate()">
                            <span>Salvar Template</span>
                            <span class="material-symbols-outlined text-sm">save</span>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Canvas Editor Workspace (8 cols) -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="glass-card rounded-xl p-6 flex flex-col items-center justify-center">
                        <div class="text-xs font-bold text-text-muted uppercase tracking-widest mb-4">Área de Edição do Canvas (650x450px)</div>
                        
                        <canvas id="certCanvas" width="650" height="450" class="rounded-lg cursor-pointer"></canvas>
                        
                        <p class="text-[10px] text-text-muted leading-relaxed mt-4 text-center max-w-sm">
                            <span class="text-primary font-bold">Instruções:</span> Clique em qualquer campo dinâmico na lateral esquerda para inseri-lo no canvas. Em seguida, clique e arraste o texto no canvas para posicioná-lo.
                        </p>
                    </div>
                </div>

        </section>
    </main>
</div>

<!-- MODAL DE EMISSÃO MANUAL DE CERTIFICADO -->
<div id="emitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-md rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest">Emitir Certificado Manual</h3>
            <button onclick="closeEmitModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="emitForm" onsubmit="emitCertificateManual(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Selecionar Aluno</label>
                    <select id="emitStudentSelect" required class="custom-select w-full rounded">
                        <option value="">Selecione um Aluno...</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'] . ' (' . $s['email'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Selecionar Treinamento</label>
                    <select id="emitCourseSelect" required class="custom-select w-full rounded">
                        <option value="">Selecione um Curso...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeEmitModal()" class="px-4 py-2.5 rounded text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" class="bg-primary px-5 py-2.5 rounded text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)]">Emitir Registro</button>
            </div>
        </form>
    </div>
</div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

    <!-- Canvas dragging engine Script -->
    <script>
        const canvas = document.getElementById('certCanvas');
        const ctx = canvas.getContext('2d');

        let elements = [];
        let selectedIndex = null;
        let isDragging = false;
        let isResizing = false;
        let startX, startY;
        let dragOffsetW, dragOffsetH;

        // Imagens globais
        let bgImage = new Image();
        let logoImage = new Image();
        let sigImage = new Image();
        
        let bgLoaded = false;
        let logoLoaded = false;
        let sigLoaded = false;

        // Redesenha o canvas ao carregar as imagens
        bgImage.onload = () => { bgLoaded = true; drawElements(); };
        logoImage.onload = () => { logoLoaded = true; drawElements(); };
        sigImage.onload = () => { sigLoaded = true; drawElements(); };

        // Background Mock Layout
        function drawBackground() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (bgLoaded && bgImage.src) {
                // Desenha plano de fundo customizado esticado no canvas
                ctx.drawImage(bgImage, 0, 0, canvas.width, canvas.height);
            } else {
                // Fundo escuro premium padrão Obsidian Gold
                ctx.fillStyle = '#0f0f12';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Borda dourada decorativa
                ctx.strokeStyle = '#f1c84b';
                ctx.lineWidth = 4;
                ctx.strokeRect(15, 15, canvas.width - 30, canvas.height - 30);

                ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
                ctx.lineWidth = 1;
                ctx.strokeRect(20, 20, canvas.width - 40, canvas.height - 40);

                // Selo / Badge decorativo
                ctx.strokeStyle = '#f1c84b';
                ctx.fillStyle = 'rgba(241, 200, 75, 0.03)';
                ctx.beginPath();
                ctx.arc(canvas.width - 80, canvas.height - 85, 30, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = '#f1c84b';
                ctx.font = 'bold 8px Satoshi';
                ctx.textAlign = 'center';
                ctx.fillText('GT APPROVED', canvas.width - 80, canvas.height - 82);
            }
        }

        function drawElements() {
            drawBackground();

            elements.forEach((el, index) => {
                if (el.type === 'text') {
                    ctx.fillStyle = el.color;
                    const fontStyle = (el.italic ? 'italic ' : '') + (el.bold ? 'bold ' : 'normal ');
                    ctx.font = `${fontStyle}${el.size}px "${el.font || 'Clash Display'}"`;
                    ctx.textAlign = 'left';
                    ctx.fillText(el.text, el.x, el.y);

                    // Desenha borda se o elemento estiver selecionado
                    if (index === selectedIndex) {
                        ctx.strokeStyle = '#f1c84b';
                        ctx.lineWidth = 1;
                        const textWidth = ctx.measureText(el.text).width;
                        ctx.strokeRect(el.x - 5, el.y - el.size, textWidth + 10, el.size + 8);
                    }
                } else if (el.type === 'image') {
                    const imgObj = el.key === 'logo' ? logoImage : sigImage;
                    const loaded = el.key === 'logo' ? logoLoaded : sigLoaded;

                    if (loaded && imgObj.src) {
                        ctx.drawImage(imgObj, el.x, el.y, el.w, el.h);
                    } else {
                        // Borda temporária se a imagem ainda não carregou
                        ctx.fillStyle = 'rgba(242, 201, 76, 0.05)';
                        ctx.fillRect(el.x, el.y, el.w, el.h);
                        ctx.strokeStyle = 'rgba(242, 201, 76, 0.2)';
                        ctx.lineWidth = 1;
                        ctx.strokeRect(el.x, el.y, el.w, el.h);
                        ctx.fillStyle = '#f2c94c';
                        ctx.font = '9px Satoshi';
                        ctx.textAlign = 'center';
                        ctx.fillText(el.name, el.x + el.w / 2, el.y + el.h / 2 + 3);
                    }

                    // Desenha borda dourada pontilhada e alça de redimensionamento se estiver selecionado
                    if (index === selectedIndex) {
                        ctx.strokeStyle = '#f1c84b';
                        ctx.lineWidth = 1;
                        ctx.setLineDash([4, 4]);
                        ctx.strokeRect(el.x, el.y, el.w, el.h);
                        ctx.setLineDash([]); // Restaura original

                        // Alça de redimensionamento no canto inferior direito
                        ctx.fillStyle = '#f1c84b';
                        ctx.fillRect(el.x + el.w - 6, el.y + el.h - 6, 6, 6);
                    }
                }
            });
        }

        // --- UPLOADS DE IMAGENS COM FILEREADER ---
        function handleImageUpload(type, file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const dataUrl = e.target.result;
                if (type === 'background') {
                    bgImage.src = dataUrl;
                    showToast('Imagem de fundo carregada localmente!', 'success');
                } else if (type === 'logo') {
                    logoImage.src = dataUrl;
                    
                    // Adiciona ou ativa elemento de logo no canvas
                    let logoEl = elements.find(el => el.key === 'logo');
                    if (!logoEl) {
                        elements.push({
                            type: 'image',
                            name: 'Logo da Instituição',
                            key: 'logo',
                            x: 50,
                            y: 50,
                            w: 80,
                            h: 80
                        });
                        selectedIndex = elements.length - 1;
                    } else {
                        selectedIndex = elements.indexOf(logoEl);
                    }
                    showControls(elements[selectedIndex]);
                    drawElements();
                    showToast('Logo carregado no canvas!', 'success');
                } else if (type === 'signature') {
                    sigImage.src = dataUrl;
                    
                    // Adiciona ou ativa elemento de assinatura no canvas
                    let sigEl = elements.find(el => el.key === 'signature');
                    if (!sigEl) {
                        elements.push({
                            type: 'image',
                            name: 'Assinatura do Diretor',
                            key: 'signature',
                            x: 450,
                            y: 350,
                            w: 120,
                            h: 60
                        });
                        selectedIndex = elements.length - 1;
                    } else {
                        selectedIndex = elements.indexOf(sigEl);
                    }
                    showControls(elements[selectedIndex]);
                    drawElements();
                    showToast('Assinatura carregada no canvas!', 'success');
                }
            };
            reader.readAsDataURL(file);
        }

        // --- DOWNLOAD DE IMAGENS DO TEMPLATE ---
        function downloadImage(type) {
            let src = '';
            let filename = '';
            if (type === 'background') {
                src = bgImage.src;
                filename = 'certificado_fundo.png';
            } else if (type === 'logo') {
                src = logoImage.src;
                filename = 'certificado_logo.png';
            } else if (type === 'signature') {
                src = sigImage.src;
                filename = 'certificado_assinatura.png';
            }

            if (!src || src === window.location.href) {
                showToast('Nenhuma imagem de ' + type + ' carregada para download.', 'error');
                return;
            }

            const link = document.createElement('a');
            link.download = filename;
            link.href = src;
            link.click();
            showToast('Download da imagem iniciado!', 'success');
        }

        // Injeta elemento de Texto Livre Customizado
        function addCustomTextElement() {
            const input = document.getElementById('customTextInput');
            const text = input.value.trim();
            if (text === '') {
                showToast('Digite um texto antes de inserir.', 'error');
                return;
            }

            elements.push({
                type: 'text',
                name: 'Texto Customizado',
                text: text,
                x: 150,
                y: 150,
                size: 16,
                color: '#F5F5F7',
                isCustom: true
            });

            selectedIndex = elements.length - 1;
            showControls(elements[selectedIndex]);
            drawElements();
            input.value = '';
            showToast('Texto customizado adicionado!', 'success');
        }

        function addTextElement(name, text, x, y, size, color) {
            // Verifica se o elemento já existe
            const exists = elements.some(el => el.text === text);
            if (exists) return;

            elements.push({ type: 'text', name, text, x, y, size, color });
            selectedIndex = elements.length - 1;
            
            showControls(elements[selectedIndex]);
            drawElements();
        }

        function showControls(el) {
            const controls = document.getElementById('elementControls');
            controls.style.display = 'block';
            
            document.getElementById('activeElementName').innerText = el.name;

            const textSection = document.getElementById('textControlsSection');
            const imageSection = document.getElementById('imageControlsSection');
            const textValGroup = document.getElementById('textValueGroup');
            const deleteBtn = document.getElementById('deleteElementBtn');

            if (el.type === 'text') {
                textSection.style.display = 'block';
                imageSection.style.display = 'none';
                
                document.getElementById('elementSize').value = el.size;
                document.getElementById('elementColor').value = el.color;
                
                // Preenche fonte, negrito e itálico
                document.getElementById('elementFont').value = el.font || 'Clash Display';
                document.getElementById('elementBold').checked = el.bold !== undefined ? el.bold : true;
                document.getElementById('elementItalic').checked = el.italic !== undefined ? el.italic : false;

                if (el.isCustom) {
                    textValGroup.style.display = 'block';
                    document.getElementById('elementValue').value = el.text;
                } else {
                    textValGroup.style.display = 'none';
                }
                
                // Exibe o botão de deletar para todos os textos
                deleteBtn.style.display = 'block';
            } else if (el.type === 'image') {
                textSection.style.display = 'none';
                imageSection.style.display = 'block';
                deleteBtn.style.display = 'block';

                document.getElementById('elementWidth').value = el.w;
                document.getElementById('elementHeight').value = el.h;
            }
        }

        function updateActiveElement(prop, value) {
            if (selectedIndex === null) return;

            const el = elements[selectedIndex];

            if (el.type === 'text') {
                if (prop === 'size') {
                    el.size = parseInt(value) || 12;
                } else if (prop === 'color') {
                    el.color = value;
                } else if (prop === 'text') {
                    el.text = value;
                } else if (prop === 'font') {
                    el.font = value;
                } else if (prop === 'bold') {
                    el.bold = value;
                } else if (prop === 'italic') {
                    el.italic = value;
                }
            } else if (el.type === 'image') {
                if (prop === 'width') {
                    el.w = parseInt(value) || 20;
                } else if (prop === 'height') {
                    el.h = parseInt(value) || 20;
                }
            }

            drawElements();
        }

        function deleteActiveElement() {
            if (selectedIndex === null) return;
            const el = elements[selectedIndex];
            
            if (el.type === 'image') {
                if (el.key === 'logo') { logoImage.src = ''; logoLoaded = false; }
                if (el.key === 'signature') { sigImage.src = ''; sigLoaded = false; }
            }

            elements.splice(selectedIndex, 1);
            selectedIndex = null;
            document.getElementById('elementControls').style.display = 'none';
            drawElements();
            showToast('Elemento removido do canvas.', 'success');
        }

        // --- DRAG AND RESIZE HANDLERS NO CANVAS ---
        canvas.addEventListener('mousedown', function(e) {
            const rect = canvas.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const clickY = e.clientY - rect.top;

            isDragging = false;
            isResizing = false;

            // Se tiver um elemento de imagem selecionado, verifica primeiro se clicou na alça de redimensionamento
            if (selectedIndex !== null) {
                const el = elements[selectedIndex];
                if (el.type === 'image') {
                    const handleX = el.x + el.w;
                    const handleY = el.y + el.h;
                    
                    if (clickX >= handleX - 10 && clickX <= handleX + 5 &&
                        clickY >= handleY - 10 && clickY <= handleY + 5) {
                        isResizing = true;
                        dragOffsetW = el.w - clickX;
                        dragOffsetH = el.h - clickY;
                        return;
                    }
                }
            }

            selectedIndex = null;
            document.getElementById('elementControls').style.display = 'none';

            // Verifica se clicou em algum elemento
            for (let i = elements.length - 1; i >= 0; i--) {
                const el = elements[i];

                if (el.type === 'text') {
                    const fontStyle = (el.italic ? 'italic ' : '') + (el.bold ? 'bold ' : 'normal ');
                    ctx.font = `${fontStyle}${el.size}px "${el.font || 'Clash Display'}"`;
                    const textWidth = ctx.measureText(el.text).width;

                    if (clickX >= el.x - 5 && clickX <= el.x + textWidth + 5 &&
                        clickY >= el.y - el.size && clickY <= el.y + 5) {
                        selectedIndex = i;
                        isDragging = true;
                        startX = clickX - el.x;
                        startY = clickY - el.y;
                        showControls(el);
                        break;
                    }
                } else if (el.type === 'image') {
                    if (clickX >= el.x && clickX <= el.x + el.w &&
                        clickY >= el.y && clickY <= el.y + el.h) {
                        selectedIndex = i;
                        isDragging = true;
                        startX = clickX - el.x;
                        startY = clickY - el.y;
                        showControls(el);
                        break;
                    }
                }
            }

            drawElements();
        });

        canvas.addEventListener('mousemove', function(e) {
            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            if (isResizing && selectedIndex !== null) {
                // Modo redimensionamento (muda largura e altura)
                const el = elements[selectedIndex];
                el.w = Math.max(20, mouseX - el.x);
                el.h = Math.max(20, mouseY - el.y);
                
                document.getElementById('elementWidth').value = el.w;
                document.getElementById('elementHeight').value = el.h;
                
                drawElements();
            } else if (isDragging && selectedIndex !== null) {
                // Modo arrasto (muda posição x e y)
                const el = elements[selectedIndex];
                el.x = mouseX - startX;
                el.y = mouseY - startY;
                
                drawElements();
            }
        });

        canvas.addEventListener('mouseup', function() {
            isDragging = false;
            isResizing = false;
        });

        canvas.addEventListener('mouseleave', function() {
            isDragging = false;
            isResizing = false;
        });

        // --- SELEÇÃO E CARREGAMENTO DE TEMPLATE ---
        document.getElementById('courseSelect').addEventListener('change', function() {
            const courseId = this.value;
            if (courseId) {
                loadTemplate(courseId);
            } else {
                elements = [];
                bgImage.src = ''; bgLoaded = false;
                logoImage.src = ''; logoLoaded = false;
                sigImage.src = ''; sigLoaded = false;
                selectedIndex = null;
                document.getElementById('elementControls').style.display = 'none';
                drawElements();
            }
        });

        async function loadTemplate(courseId) {
            try {
                const response = await fetch(`../api/admin/certificates.php?course_id=${courseId}`);
                if (!response.ok) throw new Error('Falha ao obter configurações de certificado.');
                const res = await response.json();
                
                if (!res.success) throw new Error(res.error || 'Erro inesperado.');

                const t = res.template;
                elements = [];

                if (t.student_name_size > 0) {
                    elements.push({ 
                        type: 'text', 
                        name: 'Nome do Aluno', 
                        text: '{student_name}', 
                        x: t.student_name_x, 
                        y: t.student_name_y, 
                        size: t.student_name_size, 
                        color: t.student_name_color,
                        font: t.student_name_font || 'Clash Display',
                        bold: t.student_name_bold !== undefined ? !!t.student_name_bold : true,
                        italic: t.student_name_italic !== undefined ? !!t.student_name_italic : false
                    });
                }
                
                if (t.course_title_size > 0) {
                    elements.push({ 
                        type: 'text', 
                        name: 'Título do Curso', 
                        text: '{course_title}', 
                        x: t.course_title_x, 
                        y: t.course_title_y, 
                        size: t.course_title_size, 
                        color: t.course_title_color,
                        font: t.course_title_font || 'Clash Display',
                        bold: t.course_title_bold !== undefined ? !!t.course_title_bold : true,
                        italic: t.course_title_italic !== undefined ? !!t.course_title_italic : false
                    });
                }
                
                if (t.date_size > 0) {
                    elements.push({ 
                        type: 'text', 
                        name: 'Data de Emissão', 
                        text: 'Emitido em {date}', 
                        x: t.date_x, 
                        y: t.date_y, 
                        size: t.date_size, 
                        color: t.date_color,
                        font: t.date_font || 'Satoshi',
                        bold: t.date_bold !== undefined ? !!t.date_bold : true,
                        italic: t.date_italic !== undefined ? !!t.date_italic : false
                    });
                }
                
                if (t.code_size > 0) {
                    elements.push({ 
                        type: 'text', 
                        name: 'Código do QR Code', 
                        text: 'Autenticidade: {code}', 
                        x: t.code_x, 
                        y: t.code_y, 
                        size: t.code_size, 
                        color: t.code_color,
                        font: t.code_font || 'Satoshi',
                        bold: t.code_bold !== undefined ? !!t.code_bold : true,
                        italic: t.code_italic !== undefined ? !!t.code_italic : false
                    });
                }

                // Fundo customizado
                if (t.background_url) {
                    bgImage.src = t.background_url;
                } else {
                    bgImage.src = '';
                    bgLoaded = false;
                }

                // Logo customizado
                if (t.logo_url) {
                    logoImage.src = t.logo_url;
                    elements.push({
                        type: 'image',
                        name: 'Logo da Instituição',
                        key: 'logo',
                        x: t.logo_x,
                        y: t.logo_y,
                        w: t.logo_w,
                        h: t.logo_h
                    });
                } else {
                    logoImage.src = '';
                    logoLoaded = false;
                }

                // Assinatura customizada
                if (t.signature_url) {
                    sigImage.src = t.signature_url;
                    elements.push({
                        type: 'image',
                        name: 'Assinatura do Diretor',
                        key: 'signature',
                        x: t.signature_x,
                        y: t.signature_y,
                        w: t.signature_w,
                        h: t.signature_h
                    });
                } else {
                    sigImage.src = '';
                    sigLoaded = false;
                }

                // Texto customizado estático
                if (t.custom_text && t.custom_text_size > 0) {
                    elements.push({
                        type: 'text',
                        name: 'Texto Customizado',
                        text: t.custom_text,
                        x: t.custom_text_x,
                        y: t.custom_text_y,
                        size: t.custom_text_size,
                        color: t.custom_text_color,
                        font: t.custom_text_font || 'Clash Display',
                        bold: t.custom_text_bold !== undefined ? !!t.custom_text_bold : true,
                        italic: t.custom_text_italic !== undefined ? !!t.custom_text_italic : false,
                        isCustom: true
                    });
                }

                selectedIndex = null;
                document.getElementById('elementControls').style.display = 'none';
                drawElements();
                showToast('Template de certificado carregado com sucesso.', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // --- SALVAR TEMPLATE NO BANCO ---
        async function saveTemplate() {
            const courseId = document.getElementById('courseSelect').value;
            if (!courseId) {
                showToast('Por favor, selecione um curso para vincular este template.', 'error');
                return;
            }

            // Mapeia os elementos do canvas para o payload JSON
            const payload = {
                course_id: parseInt(courseId),
                student_name_x: getElementProp('{student_name}', 'x', 100),
                student_name_y: getElementProp('{student_name}', 'y', 180),
                student_name_size: getElementProp('{student_name}', 'size', 0),
                student_name_color: getElementProp('{student_name}', 'color', '#F5F5F7'),
                student_name_font: getElementProp('{student_name}', 'font', 'Clash Display'),
                student_name_bold: getElementProp('{student_name}', 'bold', true) ? 1 : 0,
                student_name_italic: getElementProp('{student_name}', 'italic', false) ? 1 : 0,
                
                course_title_x: getElementProp('{course_title}', 'x', 100),
                course_title_y: getElementProp('{course_title}', 'y', 240),
                course_title_size: getElementProp('{course_title}', 'size', 0),
                course_title_color: getElementProp('{course_title}', 'color', '#f2c94c'),
                course_title_font: getElementProp('{course_title}', 'font', 'Clash Display'),
                course_title_bold: getElementProp('{course_title}', 'bold', true) ? 1 : 0,
                course_title_italic: getElementProp('{course_title}', 'italic', false) ? 1 : 0,
                
                date_x: getElementProp('{date}', 'x', 100),
                date_y: getElementProp('{date}', 'y', 300),
                date_size: getElementProp('{date}', 'size', 0),
                date_color: getElementProp('{date}', 'color', '#8F8F9D'),
                date_font: getElementProp('{date}', 'font', 'Satoshi'),
                date_bold: getElementProp('{date}', 'bold', true) ? 1 : 0,
                date_italic: getElementProp('{date}', 'italic', false) ? 1 : 0,
                
                code_x: getElementProp('{code}', 'x', 100),
                code_y: getElementProp('{code}', 'y', 350),
                code_size: getElementProp('{code}', 'size', 0),
                code_color: getElementProp('{code}', 'color', '#8F8F9D'),
                code_font: getElementProp('{code}', 'font', 'Satoshi'),
                code_bold: getElementProp('{code}', 'bold', true) ? 1 : 0,
                code_italic: getElementProp('{code}', 'italic', false) ? 1 : 0,

                background_url: bgImage.src || null,

                logo_url: logoImage.src || null,
                logo_x: getElementProp('Logo da Instituição', 'x', 50, true),
                logo_y: getElementProp('Logo da Instituição', 'y', 50, true),
                logo_w: getElementProp('Logo da Instituição', 'width', 80, true),
                logo_h: getElementProp('Logo da Instituição', 'height', 80, true),

                signature_url: sigImage.src || null,
                signature_x: getElementProp('Assinatura do Diretor', 'x', 450, true),
                signature_y: getElementProp('Assinatura do Diretor', 'y', 350, true),
                signature_w: getElementProp('Assinatura do Diretor', 'width', 120, true),
                signature_h: getElementProp('Assinatura do Diretor', 'height', 60, true),

                custom_text: getElementProp('Texto Customizado', 'text', null, true),
                custom_text_x: getElementProp('Texto Customizado', 'x', 100, true),
                custom_text_y: getElementProp('Texto Customizado', 'y', 120, true),
                custom_text_size: getElementProp('Texto Customizado', 'size', 0, true),
                custom_text_color: getElementProp('Texto Customizado', 'color', '#F5F5F7', true),
                custom_text_font: getElementProp('Texto Customizado', 'font', 'Clash Display', true),
                custom_text_bold: getElementProp('Texto Customizado', 'bold', true, true) ? 1 : 0,
                custom_text_italic: getElementProp('Texto Customizado', 'italic', false, true) ? 1 : 0
            };

            try {
                const response = await fetch('../api/admin/certificates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await response.json();

                if (!response.ok) throw new Error(res.error || 'Erro ao persistir o template.');

                showToast(res.message, 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Helper para extrair atributos de elemento pelo nome de representação
        function getElementProp(keyOrName, prop, fallbackVal, byName = false) {
            const el = elements.find(item => {
                if (byName) {
                    return item.name === keyOrName;
                } else {
                    return item.text.includes(keyOrName) || keyOrName.includes(item.text);
                }
            });

            if (el) {
                if (prop === 'x') return Math.round(el.x);
                if (prop === 'y') return Math.round(el.y);
                if (prop === 'size') return Math.round(el.size);
                if (prop === 'color') return el.color;
                if (prop === 'font') return el.font || 'Clash Display';
                if (prop === 'bold') return el.bold !== undefined ? el.bold : true;
                if (prop === 'italic') return el.italic !== undefined ? el.italic : false;
                if (prop === 'width') return Math.round(el.w);
                if (prop === 'height') return Math.round(el.h);
                if (prop === 'text') return el.text;
            }
            return fallbackVal;
        }

        // --- HELPER DE NOTIFICAÇÃO TOAST ---
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
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

        // --- FUNÇÕES DE CONTROLE ACADÊMICO DE CERTIFICADOS EMITIDOS (CRUD) ---
        
        // Carrega a listagem via AJAX
        async function loadEmittedCertificates() {
            try {
                const response = await fetch('../api/admin/certificates.php?action=list_emitted');
                if (!response.ok) throw new Error('Não foi possível obter os certificados.');
                const res = await response.json();
                
                if (!res.success) throw new Error(res.error || 'Erro ao carregar lista.');

                const tbody = document.getElementById('emittedCertificatesTable');
                tbody.innerHTML = '';

                if (res.certificates.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-8 text-on-surface-variant text-[10px] font-bold uppercase tracking-widest border border-dashed border-white/5 rounded-lg bg-black/10">Nenhum certificado emitido até o momento.</td>
                        </tr>
                    `;
                    return;
                }

                res.certificates.forEach(c => {
                    const issuedDate = new Date(c.issued_at).toLocaleString('pt-BR');
                    tbody.innerHTML += `
                        <tr class="hover:bg-white/[0.02] transition-colors border-b border-white/5">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-white">${c.student_name}</div>
                                <div class="text-[10px] text-on-surface-variant">${c.student_email}</div>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-xs">${c.course_title}</td>
                            <td class="py-3.5 px-4 font-mono text-primary font-bold flex items-center gap-1.5">
                                <span class="select-all">${c.certificate_code}</span>
                                <button onclick="copyToClipboard('${c.certificate_code}')" class="text-on-surface-variant hover:text-white transition-colors" title="Copiar Código">
                                    <span class="material-symbols-outlined text-xs">content_copy</span>
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono">${issuedDate}</td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="../generate_certificate.php?code=${c.certificate_code}" target="_blank" class="p-1 text-on-surface-variant hover:text-primary transition-colors flex items-center" title="Visualizar Certificado Real">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    </a>
                                    <button onclick="deleteEmittedCertificate(${c.id}, '${c.certificate_code}')" class="p-1 text-on-surface-variant hover:text-red-500 transition-colors flex items-center" title="Revogar / Excluir Certificado">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } catch (err) {
                showToast('Erro ao listar certificados: ' + err.message, 'error');
            }
        }

        // Copiar hash para a área de transferência
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Código copiado com sucesso!', 'success');
            }).catch(() => {
                showToast('Falha ao copiar código.', 'error');
            });
        }

        // Modais de Emissão Manual
        function openEmitModal() {
            document.getElementById('emitForm').reset();
            document.getElementById('emitModal').classList.remove('hidden');
        }

        function closeEmitModal() {
            document.getElementById('emitModal').classList.add('hidden');
        }

        // Submissão da Emissão Manual
        async function emitCertificateManual(e) {
            e.preventDefault();
            const studentId = document.getElementById('emitStudentSelect').value;
            const courseId = document.getElementById('emitCourseSelect').value;

            if (!studentId || !courseId) return;

            try {
                const response = await fetch('../api/admin/certificates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_emitted',
                        user_id: parseInt(studentId),
                        course_id: parseInt(courseId)
                    })
                });
                const res = await response.json();

                if (!response.ok) throw new Error(res.error || 'Erro ao emitir.');

                showToast(res.message, 'success');
                closeEmitModal();
                loadEmittedCertificates();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Revogação de Certificado
        async function deleteEmittedCertificate(id, code) {
            if (!confirm(`Deseja realmente revogar e excluir o certificado ${code}? O aluno perderá acesso a esta credencial imediatamente e esta ação é irreversível.`)) return;

            try {
                const response = await fetch('../api/admin/certificates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_emitted',
                        certificate_id: parseInt(id)
                    })
                });
                const res = await response.json();

                if (!response.ok) throw new Error(res.error || 'Erro ao revogar.');

                showToast(res.message, 'success');
                loadEmittedCertificates();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Adiciona fechamento com clique fora no emitModal
        document.getElementById('emitModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('emitModal')) {
                closeEmitModal();
            }
        });

        // Inicializa o canvas com o plano de fundo e carrega os certificados emitidos
        drawElements();
        loadEmittedCertificates();
    </script>
</body>
</html>
