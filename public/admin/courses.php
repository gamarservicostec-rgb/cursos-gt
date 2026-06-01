<?php
use Config\Database;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

// Executa migrações de banco de dados para quizzes e gamificação
require_once __DIR__ . '/../../database/create_quiz_tables.php';

$adminName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Grade de Cursos</title>
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
        .input-glass {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #F5F5F7 !important;
            transition: all 0.3s ease;
        }
        .input-glass:focus {
            border-color: #f2c94c !important;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.15);
            outline: none;
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="courses.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">school</span>
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
        <!-- Header Operations & Course List Grid -->
        <div id="catalogView" class="space-y-6">
            <div class="glass-card rounded-xl p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white font-display">Gerenciamento de Catálogo</h1>
                    <p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mt-1">Crie, edite e configure a grade de disciplinas dos treinamentos</p>
                </div>
                <button onclick="openCreateCoursePanel()" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_30px_rgba(242,201,76,0.4)] hover:-translate-y-0.5 transition-all active:translate-y-0 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Novo Curso
                </button>
            </div>

            <!-- List of Courses -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6" id="coursesGrid">
                <div class="col-span-2 text-center py-12 text-on-surface-variant text-xs font-bold uppercase tracking-wider">
                    Carregando grade de cursos...
                </div>
            </div>
        </div>

        <!-- CONSTRUTOR DE CURSO UNIFICADO & AMPLO (De Duas Colunas - Oculto por padrão) -->
        <div class="hidden space-y-6" id="courseEditPanel">
            <!-- Top Navigation & Return -->
            <div class="glass-card rounded-xl p-4 flex items-center justify-between">
                <button onclick="closeCourseEditPanel()" class="flex items-center gap-2 text-xs font-bold text-primary hover:text-white transition-colors uppercase tracking-widest">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Voltar ao Catálogo de Cursos
                </button>
                <div class="text-right">
                    <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest block">Ambiente de Construção</span>
                    <span class="text-xs font-bold text-white uppercase tracking-wider" id="activeEditingCourseTitle">Novo Curso</span>
                </div>
            </div>

            <!-- Two Column Main Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Coluna da Esquerda (Identidade do Curso — 4 cols) -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="glass-card rounded-xl p-6" style="border-color: rgba(242, 201, 76, 0.25);">
                        <div class="border-b border-white/5 pb-4 mb-4">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-widest" id="coursePanelHeader">Identidade do Treinamento</h3>
                        </div>
                        
                        <form id="courseForm" onsubmit="saveCourse(event)" class="space-y-4">
                            <input type="hidden" id="courseIdField">
                            
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Título do Curso</label>
                                <input type="text" id="courseTitleField" required placeholder="ex: Masterclass em Segurança de Elite" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Descrição Curta</label>
                                <textarea id="courseDescField" required rows="4" placeholder="Descreva os objetivos principais..." class="w-full px-4 py-3 rounded-lg input-glass text-xs resize-none"></textarea>
                            </div>

                             <div>
                                 <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">URL da Thumbnail (Imagem do Curso)</label>
                                 <input type="text" id="courseThumbnailField" placeholder="https://exemplo.com/imagem.jpg ou assets/images/uploads/imagem.jpg" class="w-full px-4 py-3 rounded-lg input-glass text-xs" oninput="updateThumbnailPreview(this.value)">
                                 
                                 <!-- Botão de Upload com ícone de Download (conforme pedido) -->
                                 <div class="flex items-center gap-2 mt-2">
                                     <button type="button" onclick="document.getElementById('courseThumbnailUpload').click()" class="flex items-center justify-center rounded border border-primary/40 text-primary text-[10px] font-bold px-4 py-2.5 uppercase tracking-wider hover:bg-primary hover:text-background-deep transition-all duration-300">
                                         <span class="material-symbols-outlined mr-1.5 text-xs">download</span>
                                         Upload de Imagem (Download)
                                     </button>
                                     <span id="uploadStatusText" class="text-[10px] text-on-surface-variant italic"></span>
                                 </div>
                                 <input type="file" id="courseThumbnailUpload" accept="image/*" class="hidden" onchange="uploadThumbnailFile(this)">
                                 
                                 <!-- Preview amplo panorâmico -->
                                 <div class="mt-3 relative aspect-video rounded-lg overflow-hidden border border-white/10 bg-black/40 flex items-center justify-center">
                                     <img id="courseThumbnailPreview" src="" alt="Preview da Thumbnail" class="absolute inset-0 w-full h-full object-cover hidden" onload="this.classList.remove('hidden'); document.getElementById('courseThumbnailFallback').classList.add('hidden');" onerror="this.classList.add('hidden'); document.getElementById('courseThumbnailFallback').classList.remove('hidden');">
                                     <div id="courseThumbnailFallback" class="flex flex-col items-center justify-center text-on-surface-variant opacity-60">
                                         <span class="material-symbols-outlined text-3xl">image</span>
                                         <span class="text-[9px] font-bold uppercase tracking-wider mt-1">Pré-visualização do Card</span>
                                     </div>
                                 </div>
                             </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Preço (R$)</label>
                                    <input type="number" step="0.01" id="coursePriceField" required placeholder="1290.00" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Modalidade</label>
                                    <select id="courseTypeField" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                        <option value="hybrid">Híbrido</option>
                                        <option value="ead">EAD</option>
                                        <option value="presencial">Presencial</option>
                                    </select>
                                </div>
                            </div>

                                       <div>
                                 <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Categoria</label>
                                 <select id="courseCategoryField" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                     <option value="">Sem Categoria</option>
                                 </select>
                             </div>

                             <div>
                                 <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Status</label>
                                 <select id="courseStatusField" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                     <option value="active">Ativo (Publicado)</option>
                                     <option value="inactive">Rascunho (Inativo)</option>
                                 </select>
                             </div>

                             <!-- Campos Premium de Modalidade Híbrida -->
                             <div id="hybridFields" class="hidden space-y-4 border-t border-white/5 pt-4 mt-2">
                                 <div class="grid grid-cols-2 gap-4">
                                     <div>
                                         <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Duração (Dias)</label>
                                         <input type="number" id="courseDurationField" placeholder="ex: 10" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                     </div>
                                     <div>
                                         <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Semana Operacional</label>
                                         <select id="courseWeekdaysField" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                             <option value="1">Segunda a Sexta (Dias Úteis)</option>
                                             <option value="0">Todos os Dias (Inclusivo FDS)</option>
                                         </select>
                                     </div>
                                 </div>
                                 <div>
                                     <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Horários Disponíveis (Separados por vírgula)</label>
                                     <input type="text" id="courseAvailableHoursField" placeholder="ex: 08:00, 10:00, 14:00, 19:00" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                                 </div>
                             </div>
                         </div>

                            <div class="pt-4 border-t border-white/5 flex gap-3">
                                <button type="submit" class="w-full bg-primary py-3 rounded-lg text-on-primary font-bold text-xs uppercase tracking-wider shadow-[0_0_20px_rgba(242,201,76,0.2)] hover:shadow-[0_0_35px_rgba(242,201,76,0.35)] transition-all">Salvar Registro</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Coluna da Direita (Engenharia Pedagógica & Avaliações — 8 cols) -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Painel de Bloqueio se novo curso -->
                    <div id="pedagogicLockPanel" class="glass-card rounded-xl p-10 text-center space-y-4">
                        <span class="material-symbols-outlined text-5xl text-primary animate-pulse">lock</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider font-display">Grade Pedagógica Bloqueada</h3>
                        <p class="text-xs text-on-surface-variant max-w-md mx-auto leading-relaxed">
                            Para configurar os módulos, adicionar aulas digitais e criar a avaliação técnica final (Quiz), salve as informações básicas de identidade do curso na coluna ao lado.
                        </p>
                    </div>

                    <!-- Grade Curricular & Quiz (Exibidos apenas quando editando) -->
                    <div id="pedagogicContentPanel" class="space-y-6 hidden">
                        <!-- Curriculum Section -->
                        <div class="glass-card rounded-xl p-6" id="curriculumSection">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/5 pb-6 mb-6">
                                <div>
                                    <span class="text-[10px] font-bold text-primary uppercase tracking-widest">Currículo e Aulas</span>
                                    <h2 class="text-lg font-bold text-white mt-0.5 font-display" id="curriculumTitle">Nome do Curso Selecionado</h2>
                                </div>
                                <div class="flex gap-3">
                                    <button onclick="openModuleModal()" class="border border-primary/20 bg-primary/5 text-primary flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-primary/10 transition-all">
                                        <span class="material-symbols-outlined text-[18px]">tab</span>
                                        Novo Módulo
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4" id="curriculumAccordion">
                                <!-- Módulos carregados via AJAX -->
                            </div>
                        </div>

                        <!-- Quiz Section -->
                        <div class="glass-card rounded-xl p-6" id="quizSection">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/5 pb-6 mb-6">
                                <div>
                                    <span class="text-[10px] font-bold text-primary uppercase tracking-widest text-glow">Configurações Pedagógicas</span>
                                    <h2 class="text-lg font-bold text-white mt-0.5 font-display">Avaliação Técnica Final</h2>
                                </div>
                                <div class="flex gap-3" id="quizHeaderActions">
                                    <!-- Ações dinâmicas de acordo com o status do Quiz -->
                                </div>
                            </div>

                            <div id="quizContent" class="space-y-6">
                                <!-- Carregado via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- MODULE CREATION/EDITION MODAL -->
<div id="moduleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-md rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest" id="moduleModalHeader">Adicionar Novo Módulo</h3>
            <button onclick="closeModuleModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="moduleForm" onsubmit="saveModule(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <input type="hidden" id="moduleIdField">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Nome do Módulo</label>
                    <input type="text" id="moduleTitleField" required placeholder="ex: Fundamentos Táticos" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Descrição do Módulo</label>
                    <textarea id="moduleDescField" rows="2" class="w-full px-4 py-3 rounded-lg input-glass text-xs resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Ordem de Exibição (Sort)</label>
                    <input type="number" id="moduleSortField" value="1" required class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeModuleModal()" class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="moduleSubmitBtn" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)]">Criar Módulo</button>
            </div>
        </form>
    </div>
</div>

<!-- SUBJECT CREATION/EDITION MODAL -->
<div id="subjectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-md rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest" id="subjectModalHeader">Adicionar Nova Matéria</h3>
            <button onclick="closeSubjectModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="subjectForm" onsubmit="saveSubject(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <input type="hidden" id="subjectModuleIdField">
                <input type="hidden" id="subjectIdField">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Nome da Matéria</label>
                    <input type="text" id="subjectTitleField" required placeholder="ex: Redes Avançadas e Firewall" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Ordem de Exibição (Sort)</label>
                    <input type="number" id="subjectSortField" value="1" required class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeSubjectModal()" class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="subjectSubmitBtn" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242,201,76,0.2)]">Criar Matéria</button>
            </div>
        </form>
    </div>
</div>

<!-- LESSON CREATION/EDITION MODAL -->
<div id="lessonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-lg rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest" id="lessonModalHeader">Adicionar Nova Aula</h3>
            <button onclick="closeLessonModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="lessonForm" onsubmit="saveLesson(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <input type="hidden" id="lessonSubjectIdField">
                <input type="hidden" id="lessonIdField">
                
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Título da Aula</label>
                    <input type="text" id="lessonTitleField" required placeholder="ex: 1. Introdução à Engenharia Social" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Descrição / Resumo</label>
                    <textarea id="lessonDescField" rows="2" class="w-full px-4 py-3 rounded-lg input-glass text-xs resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Vídeo URL (ID do Player Bunny.net)</label>
                    <input type="text" id="lessonVideoField" required placeholder="ex: 7750362074564546823" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Link do Material de Apoio (PDF / Excel / Slides)</label>
                    <input type="url" id="lessonAttachmentField" placeholder="ex: https://exemplo.com/material.pdf" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Duração (Segundos)</label>
                        <input type="number" id="lessonDurationField" value="600" required class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Ordem (Sort)</label>
                        <input type="number" id="lessonSortField" value="1" required class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeLessonModal()" class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="lessonSubmitBtn" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242, 201, 76, 0.2)]">Publicar Aula</button>
            </div>
        </form>
    </div>
</div>

<!-- QUIZ CREATION/EDITION MODAL -->
<div id="quizModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-md rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest" id="quizModalHeader">Configurar Avaliação Técnica</h3>
            <button onclick="closeQuizModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="quizForm" onsubmit="saveQuiz(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <input type="hidden" id="quizIdField">
                
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Título da Avaliação</label>
                    <input type="text" id="quizTitleField" required placeholder="ex: Avaliação Final Técnica - Módulo Operacional" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Nota Mínima de Corte (%)</label>
                    <input type="number" id="quizMinScoreField" value="70" min="0" max="100" required class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                    <p class="text-[9px] text-on-surface-variant uppercase mt-1">O aluno precisará atingir este aproveitamento para destravar o certificado.</p>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeQuizModal()" class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="quizSubmitBtn" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242, 201, 76, 0.2)]">Salvar Avaliação</button>
            </div>
        </form>
    </div>
</div>

<!-- QUESTION CREATION/EDITION MODAL -->
<div id="questionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card w-full max-w-lg rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]" style="border-color: rgba(242, 201, 76, 0.3);">
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-bold text-white uppercase tracking-widest" id="questionModalHeader">Adicionar Nova Pergunta</h3>
            <button onclick="closeQuestionModal()" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="questionForm" onsubmit="saveQuestion(event)" class="flex flex-col flex-1 overflow-hidden mb-0">
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 custom-scrollbar">
                <input type="hidden" id="questionIdField">
                
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Pergunta / Enunciado Técnico</label>
                    <textarea id="questionTextField" required rows="3" placeholder="Escreva a questão detalhada para a prova..." class="w-full px-4 py-3 rounded-lg input-glass text-xs resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-primary mb-2">Opções de Resposta e Correta</label>
                    <p class="text-[9px] text-on-surface-variant uppercase mb-3">Selecione o rádio para marcar a opção correta.</p>
                    
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_opt" value="0" checked class="focus:ring-primary h-4 w-4 text-primary bg-black border-border-color focus:outline-none">
                            <input type="text" id="opt0" placeholder="Opção A" required class="flex-grow px-4 py-2.5 rounded-lg input-glass text-xs">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_opt" value="1" class="focus:ring-primary h-4 w-4 text-primary bg-black border-border-color focus:outline-none">
                            <input type="text" id="opt1" placeholder="Opção B" required class="flex-grow px-4 py-2.5 rounded-lg input-glass text-xs">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_opt" value="2" class="focus:ring-primary h-4 w-4 text-primary bg-black border-border-color focus:outline-none">
                            <input type="text" id="opt2" placeholder="Opção C" required class="flex-grow px-4 py-2.5 rounded-lg input-glass text-xs">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_opt" value="3" class="focus:ring-primary h-4 w-4 text-primary bg-black border-border-color focus:outline-none">
                            <input type="text" id="opt3" placeholder="Opção D" required class="flex-grow px-4 py-2.5 rounded-lg input-glass text-xs">
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-end gap-3 border-t border-white/5 flex-shrink-0">
                <button type="button" onclick="closeQuestionModal()" class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="questionSubmitBtn" class="bg-primary px-5 py-2.5 rounded-lg text-on-primary font-bold text-label-sm shadow-[0_0_20px_rgba(242, 201, 76, 0.2)]">Confirmar Pergunta</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

<script>
    let currentCourseId = null;
    let categoriesList = [];
 
    // 1. CARREGAR CURSOS AO ABRIR A TELA
    document.addEventListener('DOMContentLoaded', () => {
        loadCourses();
        loadCategoriesSelect();
        
        // Listener para modalidade híbrida
        const typeSelect = document.getElementById('courseTypeField');
        if (typeSelect) {
            typeSelect.addEventListener('change', toggleHybridFields);
        }
    });

    function toggleHybridFields() {
        const type = document.getElementById('courseTypeField').value;
        const hybridFields = document.getElementById('hybridFields');
        if (type === 'hybrid') {
            hybridFields.classList.remove('hidden');
        } else {
            hybridFields.classList.add('hidden');
        }
    }

    async function loadCategoriesSelect() {
        try {
            const response = await fetch('../api/admin/categories.php');
            const data = await response.json();
            if (data.success) {
                categoriesList = data.categories;
                const select = document.getElementById('courseCategoryField');
                select.innerHTML = '<option value="">Sem Categoria</option>' + categoriesList.map(cat => `
                    <option value="${cat.id}">${cat.name}</option>
                `).join('');
            }
        } catch (err) {
            console.error('Erro ao carregar categorias para o select:', err);
        }
    }

    async function loadCourses() {
        try {
            const response = await fetch('../api/admin/courses.php');
            if (!response.ok) throw new Error('Falha ao obter catálogo.');
            const courses = await response.json();
            
            const grid = document.getElementById('coursesGrid');
            grid.innerHTML = '';

            if (courses.length === 0) {
                grid.innerHTML = `<div class="col-span-2 text-center py-12 text-on-surface-variant text-xs font-bold uppercase tracking-wider border border-dashed border-white/10 rounded-xl">Nenhum treinamento cadastrado.</div>`;
                return;
            }

            courses.forEach(c => {
                const priceFormatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(c.price);
                const typeLabel = c.type === 'hybrid' ? 'Híbrido' : (c.type === 'ead' ? 'Online EAD' : 'Presencial');
                const statusClass = c.status === 'active' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-white/5 text-on-surface-variant border-white/10';
                const statusLabel = c.status === 'active' ? 'Ativo' : 'Rascunho';
                let thumbnail = c.thumbnail_url ? c.thumbnail_url : 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=1470&auto=format&fit=crop';
                if (thumbnail && !thumbnail.startsWith('http://') && !thumbnail.startsWith('https://') && !thumbnail.startsWith('/')) {
                    thumbnail = '../' + thumbnail;
                }

                grid.innerHTML += `
                    <div class="glass-card rounded-xl overflow-hidden flex flex-col justify-between hover:border-primary/20 transition-all cursor-pointer group" onclick="selectCourse(${c.id})">
                        <!-- Imagem com Aspect Ratio de Cinema e Overlay degradê -->
                        <div class="relative w-full aspect-video overflow-hidden border-b border-white/5">
                            <img src="${thumbnail}" alt="${c.title}" class="w-full h-full object-cover scale-100 group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-background-deep via-transparent to-transparent"></div>
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span class="text-[9px] font-bold border rounded-full px-2 py-0.5 uppercase tracking-wider backdrop-blur-md ${statusClass}">
                                    ${statusLabel}
                                </span>
                                <span class="text-[9px] font-bold text-primary bg-black/60 backdrop-blur-md border border-primary/10 rounded-full px-2 py-0.5 uppercase tracking-wider">
                                    ${c.category_name || 'Sem Categoria'}
                                </span>
                            </div>
                            <div class="absolute top-4 right-4">
                                <span class="text-[9px] font-bold text-primary bg-black/60 backdrop-blur-md border border-primary/10 rounded-full px-2 py-0.5 uppercase tracking-wider">
                                    ${typeLabel}
                                </span>
                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-white group-hover:text-primary transition-colors font-display line-clamp-1">${c.title}</h3>
                                <p class="text-xs text-on-surface-variant mt-2 line-clamp-2">${c.description}</p>
                            </div>
                            
                            <div class="border-t border-white/5 pt-4 mt-6 flex items-center justify-between">
                                <span class="text-xs font-bold text-primary font-display">${priceFormatted}</span>
                                <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                                    <button onclick="editCourse(${c.id}, '${c.title}', '${c.description.replace(/'/g, "\\'")}', ${c.price}, '${c.type}', '${c.status}', '${c.thumbnail_url || ''}')" class="p-1.5 hover:text-primary transition-colors" title="Editar Informações">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button onclick="deleteCourse(${c.id})" class="p-1.5 hover:text-error hover:text-red-500 transition-colors" title="Excluir Curso">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        } catch (err) {
            showToast('Erro ao carregar cursos: ' + err.message, 'error');
        }
    }

    // 2. SELECIONAR CURSO E CARREGAR GRADE DE DISCIPLINAS
    async function selectCourse(courseId) {
        currentCourseId = courseId;
        
        // Exibe o painel de construção de duas colunas e esconde a listagem geral
        document.getElementById('catalogView').classList.add('hidden');
        document.getElementById('courseEditPanel').classList.remove('hidden');
        
        // Libera a engenharia pedagógica e esconde o painel de bloqueio
        document.getElementById('pedagogicContentPanel').classList.remove('hidden');
        document.getElementById('pedagogicLockPanel').classList.add('hidden');
        
        const accordion = document.getElementById('curriculumAccordion');
        accordion.innerHTML = `<div class="text-center py-8 text-on-surface-variant text-xs font-bold uppercase tracking-wider">Carregando currículo...</div>`;
        
        try {
            const response = await fetch(`../api/admin/courses.php?course_id=${courseId}`);
            if (!response.ok) throw new Error('Não foi possível obter a grade.');
            const res = await response.json();
            
            if (!res.success) throw new Error(res.error || 'Erro inesperado.');
            
            const course = res.course;
            
            // Preenche a coluna de Identidade do Curso (esquerda)
            document.getElementById('courseIdField').value = course.id;
            document.getElementById('courseTitleField').value = course.title;
            document.getElementById('courseDescField').value = course.description;
            document.getElementById('coursePriceField').value = course.price;
            document.getElementById('courseTypeField').value = course.type;
            document.getElementById('courseCategoryField').value = course.category_id || '';
            document.getElementById('courseStatusField').value = course.status;
            document.getElementById('courseThumbnailField').value = course.thumbnail_url || '';
            
            // Preenche dados do modelo híbrido
            document.getElementById('courseDurationField').value = course.duration_days || '';
            document.getElementById('courseWeekdaysField').value = course.weekdays_only !== undefined ? course.weekdays_only : '1';
            document.getElementById('courseAvailableHoursField').value = course.available_hours || '';
            toggleHybridFields();

            updateThumbnailPreview(course.thumbnail_url || '');
            
            document.getElementById('coursePanelHeader').innerText = 'Editar Registro de Treinamento';
            document.getElementById('activeEditingCourseTitle').innerText = course.title;
            document.getElementById('curriculumTitle').innerText = course.title;
            
            accordion.innerHTML = '';
            
            if (course.curriculum.length === 0) {
                accordion.innerHTML = `<div class="text-center py-8 text-on-surface-variant text-xs font-bold uppercase tracking-wider border border-dashed border-white/10 rounded-lg bg-black/10">Este curso não possui nenhum módulo. Crie um novo módulo para começar!</div>`;
            } else {
                course.curriculum.forEach(m => {
                    let subjectsHtml = '';
                    
                    if (!m.subjects || m.subjects.length === 0) {
                        subjectsHtml = `<div class="text-center py-4 border border-dashed border-white/5 rounded-lg bg-black/10 text-on-surface-variant text-xs italic">Nenhuma matéria cadastrada neste módulo. Adicione uma matéria para organizar as aulas!</div>`;
                    } else {
                        m.subjects.forEach(s => {
                            let lessonsHtml = '';
                            const lessons = s.lessons || [];
                            
                            if (lessons.length === 0) {
                                lessonsHtml = `<p class="text-[11px] text-on-surface-variant py-3 italic text-center border border-dashed border-white/5 rounded-lg bg-black/5">Nenhuma aula cadastrada nesta matéria.</p>`;
                            } else {
                                lessonsHtml = `
                                    <div class="space-y-2 pl-4 border-l border-white/5 mt-2">
                                        ${lessons.map(l => `
                                            <div class="flex items-center justify-between p-2.5 bg-black/20 border border-white/5 rounded-lg text-xs hover:border-primary/10 group/lesson-item">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-primary text-[18px]">play_circle</span>
                                                    <span class="font-medium text-white">${l.title}</span>
                                                    <span class="text-[10px] text-on-surface-variant">(${Math.round(l.duration/60)} min)</span>
                                                    ${l.attachment_url ? `<span class="material-symbols-outlined text-emerald-400 text-[14px]" title="Possui material complementar (Anexo)">description</span>` : ''}
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <span class="font-mono text-[9px] text-on-surface-variant hidden md:inline">ID: ${l.video_url}</span>
                                                    <div class="flex items-center gap-1">
                                                        <button onclick="editLesson(${l.id}, ${s.id}, '${l.title.replace(/'/g, "\\'")}', '${(l.description || '').replace(/'/g, "\\'")}', '${l.video_url}', ${l.duration}, ${l.sort_order}, '${l.attachment_url || ''}')" class="p-1 text-on-surface-variant hover:text-primary transition-colors" title="Editar Aula">
                                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                                        </button>
                                                        <button onclick="deleteLesson(${l.id})" class="p-1 text-on-surface-variant hover:text-red-500 transition-colors" title="Excluir Aula">
                                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                `;
                            }
                            
                            subjectsHtml += `
                                <div class="p-3 bg-black/20 border border-white/5 rounded-lg space-y-2 mt-3">
                                    <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                        <div class="flex-1 flex items-center gap-2 cursor-pointer select-none" onclick="toggleCollapse('subject-body-${s.id}', 'subject-icon-${s.id}')">
                                            <span class="material-symbols-outlined text-primary text-[18px] transition-transform duration-300 mr-1" id="subject-icon-${s.id}">expand_less</span>
                                            <span class="material-symbols-outlined text-primary text-[16px]">topic</span>
                                            <span class="text-xs font-bold text-white uppercase tracking-wider">${s.title}</span>
                                            <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                                                <button onclick="editSubject(${s.id}, ${m.id}, '${s.title.replace(/'/g, "\\'")}', ${s.sort_order || 1})" class="p-1 text-on-surface-variant hover:text-primary transition-colors" title="Editar Matéria">
                                                    <span class="material-symbols-outlined text-[12px]">edit</span>
                                                </button>
                                                <button onclick="deleteSubject(${s.id})" class="p-1 text-on-surface-variant hover:text-red-500 transition-colors" title="Excluir Matéria">
                                                    <span class="material-symbols-outlined text-[12px]">delete</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button onclick="openLessonModal(${s.id})" class="border border-primary/20 bg-primary/5 hover:bg-primary/10 text-primary flex items-center gap-1.5 px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-wider transition-all">
                                            <span class="material-symbols-outlined text-[14px]">add</span>
                                            Adicionar Aula
                                        </button>
                                    </div>
                                    <div id="subject-body-${s.id}" class="transition-all duration-300">
                                        ${lessonsHtml}
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    accordion.innerHTML += `
                        <div class="glass-card rounded-lg p-4 bg-black/30 border border-white/5">
                            <div class="flex items-center justify-between pb-3 border-b border-white/5">
                                <div class="flex-1 flex items-center gap-3 cursor-pointer select-none" onclick="toggleCollapse('module-body-${m.id}', 'module-icon-${m.id}')">
                                    <span class="material-symbols-outlined text-primary text-[20px] transition-transform duration-300" id="module-icon-${m.id}">expand_less</span>
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <h4 class="text-xs font-bold uppercase text-white tracking-wider font-display">${m.title}</h4>
                                            <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                                                <button onclick="editModule(${m.id}, '${m.title.replace(/'/g, "\\'")}', '${(m.description || '').replace(/'/g, "\\'")}', ${m.sort_order})" class="p-1 text-on-surface-variant hover:text-primary transition-colors" title="Editar Módulo">
                                                    <span class="material-symbols-outlined text-[14px]">edit</span>
                                                </button>
                                                <button onclick="deleteModule(${m.id})" class="p-1 text-on-surface-variant hover:text-red-500 transition-colors" title="Excluir Módulo">
                                                    <span class="material-symbols-outlined text-[14px]">delete</span>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-[10px] text-on-surface-variant mt-0.5">${m.description || 'Sem descrição cadastrada.'}</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openSubjectModal(${m.id})" class="border border-primary/20 bg-primary/5 hover:bg-primary/10 text-primary flex items-center gap-1.5 px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-wider transition-all">
                                        <span class="material-symbols-outlined text-[14px]">add_box</span>
                                        Nova Matéria
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3 transition-all duration-300" id="module-body-${m.id}">
                                <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest block mb-1">Matérias e Disciplinas:</span>
                                ${subjectsHtml}
                            </div>
                        </div>
                    `;
                });
            }
            
            // Carrega dinamicamente a avaliação técnica associada ao curso
            loadCourseQuiz(courseId);
            
        } catch (err) {
            showToast('Erro ao carregar currículo: ' + err.message, 'error');
        }
    }
    
    // Auxiliar de Preview de Thumbnail
    function updateThumbnailPreview(url) {
        const preview = document.getElementById('courseThumbnailPreview');
        const fallback = document.getElementById('courseThumbnailFallback');
        if (url && url.trim() !== '') {
            let finalUrl = url;
            if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('/')) {
                finalUrl = '../' + url;
            }
            preview.src = finalUrl;
            preview.classList.remove('hidden');
            fallback.classList.add('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            fallback.classList.remove('hidden');
        }
    }

    // Função para Fazer Upload do arquivo de Thumbnail (Download)
    function uploadThumbnailFile(input) {
        if (!input.files || input.files.length === 0) return;
        
        const file = input.files[0];
        const statusText = document.getElementById('uploadStatusText');
        
        statusText.innerText = 'Enviando...';
        statusText.classList.remove('text-red-400');
        statusText.classList.add('text-on-surface-variant');
        
        const formData = new FormData();
        formData.append('file', file);
        
        fetch('../api/admin/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Falha no upload.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Preenche o input URL com o caminho do arquivo
                const fullUrl = data.url;
                document.getElementById('courseThumbnailField').value = fullUrl;
                updateThumbnailPreview(fullUrl);
                statusText.innerText = 'Concluído!';
                showToast('Imagem carregada com sucesso!', 'success');
            } else {
                throw new Error(data.error || 'Erro desconhecido.');
            }
        })
        .catch(err => {
            statusText.innerText = 'Erro no envio.';
            statusText.classList.remove('text-on-surface-variant');
            statusText.classList.add('text-red-400');
            showToast(err.message, 'error');
        });
    }

    // 3. OPERAÇÕES DE CURSO (CRUD)
    function openCreateCoursePanel() {
        // Limpa o formulário de identidade do curso
        document.getElementById('courseForm').reset();
        document.getElementById('courseIdField').value = '';
        updateThumbnailPreview('');
        
        // Altera cabeçalhos
        document.getElementById('coursePanelHeader').innerText = 'Cadastrar Novo Treinamento';
        document.getElementById('activeEditingCourseTitle').innerText = 'Novo Curso';
        
        toggleHybridFields();

        // Bloqueia coluna direita (módulos, aulas, quizzes)
        document.getElementById('pedagogicLockPanel').classList.remove('hidden');
        document.getElementById('pedagogicContentPanel').classList.add('hidden');
        
        // Exibe o painel amplo e esconde a listagem geral
        document.getElementById('catalogView').classList.add('hidden');
        document.getElementById('courseEditPanel').classList.remove('hidden');
        
        currentCourseId = null;
    }
    
    function closeCourseEditPanel() {
        // Oculta o painel de edição e exibe a listagem geral
        document.getElementById('courseEditPanel').classList.add('hidden');
        document.getElementById('catalogView').classList.remove('hidden');
        currentCourseId = null;
        loadCourses();
    }
    
    function editCourse(id, title, desc, price, type, status, thumbnailUrl) {
        // Atalho inteligente: redireciona para a visualização unificada que carrega tudo atualizado via API
        selectCourse(id);
    }
    
    async function saveCourse(e) {
        e.preventDefault();
        const id = document.getElementById('courseIdField').value;
        const payload = {
            action: id ? 'update_course' : 'create_course',
            course_id: id ? parseInt(id) : undefined,
            title: document.getElementById('courseTitleField').value,
            description: document.getElementById('courseDescField').value,
            price: parseFloat(document.getElementById('coursePriceField').value),
            type: document.getElementById('courseTypeField').value,
            category_id: document.getElementById('courseCategoryField').value ? parseInt(document.getElementById('courseCategoryField').value) : null,
            status: document.getElementById('courseStatusField').value,
            thumbnail_url: document.getElementById('courseThumbnailField').value,
            duration_days: document.getElementById('courseDurationField').value ? parseInt(document.getElementById('courseDurationField').value) : null,
            weekdays_only: parseInt(document.getElementById('courseWeekdaysField').value),
            available_hours: document.getElementById('courseAvailableHoursField').value
        };
        
        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            
            if (!response.ok) throw new Error(res.error || 'Erro de processamento.');
            
            showToast(res.message, 'success');
            
            // Se for um novo curso, captura o ID retornado e ativa o painel pedagógico na direita
            if (!id && res.course_id) {
                const newCourseId = res.course_id;
                document.getElementById('courseIdField').value = newCourseId;
                currentCourseId = newCourseId;
                
                // Destrava a coluna pedagógica
                document.getElementById('pedagogicContentPanel').classList.remove('hidden');
                document.getElementById('pedagogicLockPanel').classList.add('hidden');
                
                document.getElementById('coursePanelHeader').innerText = 'Editar Registro de Treinamento';
                document.getElementById('activeEditingCourseTitle').innerText = payload.title;
                document.getElementById('curriculumTitle').innerText = payload.title;
                
                selectCourse(newCourseId);
            } else {
                // Se for atualização, atualiza os títulos visuais na hora
                document.getElementById('activeEditingCourseTitle').innerText = payload.title;
                document.getElementById('curriculumTitle').innerText = payload.title;
            }
            
            // Recarrega listagem geral em background
            loadCourses();
            
        } catch (err) {
            showToast(err.message, 'error');
        }
    }
    
    async function deleteCourse(id) {
        if (!confirm('Deseja realmente excluir este curso e toda a sua grade? Esta ação é irreversível.')) return;
        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_course', course_id: id })
            });
            const res = await response.json();
            
            if (!response.ok) throw new Error(res.error || 'Erro ao deletar.');
            
            showToast(res.message, 'success');
            if (currentCourseId === id) {
                closeCourseEditPanel();
            } else {
                loadCourses();
            }
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // 4. OPERAÇÕES DE MÓDULO (CRUD COMPLETO)
    function openModuleModal() {
        document.getElementById('moduleForm').reset();
        document.getElementById('moduleIdField').value = '';
        document.getElementById('moduleModalHeader').innerText = 'Adicionar Novo Módulo';
        document.getElementById('moduleSubmitBtn').innerText = 'Criar Módulo';
        document.getElementById('moduleModal').classList.remove('hidden');
    }

    function closeModuleModal() {
        document.getElementById('moduleModal').classList.add('hidden');
    }

    function editModule(id, title, desc, sortOrder) {
        document.getElementById('moduleForm').reset();
        document.getElementById('moduleIdField').value = id;
        document.getElementById('moduleTitleField').value = title;
        document.getElementById('moduleDescField').value = desc || '';
        document.getElementById('moduleSortField').value = sortOrder || 1;
        document.getElementById('moduleModalHeader').innerText = 'Editar Detalhes do Módulo';
        document.getElementById('moduleSubmitBtn').innerText = 'Atualizar Módulo';
        document.getElementById('moduleModal').classList.remove('hidden');
    }

    async function deleteModule(moduleId) {
        if (!confirm('Deseja realmente excluir este módulo e todas as suas aulas associadas? Esta ação é irreversível.')) return;
        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_module', module_id: moduleId })
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao deletar módulo.');

            showToast(res.message, 'success');
            if (currentCourseId) selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function saveModule(e) {
        e.preventDefault();
        if (!currentCourseId) return;

        const id = document.getElementById('moduleIdField').value;
        const payload = {
            action: id ? 'update_module' : 'create_module',
            module_id: id ? parseInt(id) : undefined,
            course_id: currentCourseId,
            title: document.getElementById('moduleTitleField').value,
            description: document.getElementById('moduleDescField').value,
            sort_order: parseInt(document.getElementById('moduleSortField').value)
        };

        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            
            if (!response.ok) throw new Error(res.error || 'Erro ao salvar módulo.');

            showToast(res.message, 'success');
            closeModuleModal();
            selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // 4.5. OPERAÇÕES DE MATÉRIA (CRUD COMPLETO)
    function openSubjectModal(moduleId) {
        document.getElementById('subjectForm').reset();
        document.getElementById('subjectModuleIdField').value = moduleId;
        document.getElementById('subjectIdField').value = '';
        document.getElementById('subjectModalHeader').innerText = 'Adicionar Nova Matéria';
        document.getElementById('subjectSubmitBtn').innerText = 'Criar Matéria';
        document.getElementById('subjectModal').classList.remove('hidden');
    }

    function closeSubjectModal() {
        document.getElementById('subjectModal').classList.add('hidden');
    }

    function editSubject(id, moduleId, title, sortOrder) {
        document.getElementById('subjectForm').reset();
        document.getElementById('subjectIdField').value = id;
        document.getElementById('subjectModuleIdField').value = moduleId;
        document.getElementById('subjectTitleField').value = title;
        document.getElementById('subjectSortField').value = sortOrder || 1;
        document.getElementById('subjectModalHeader').innerText = 'Editar Detalhes da Matéria';
        document.getElementById('subjectSubmitBtn').innerText = 'Atualizar Matéria';
        document.getElementById('subjectModal').classList.remove('hidden');
    }

    async function deleteSubject(subjectId) {
        if (!confirm('Deseja realmente excluir esta matéria e todas as suas aulas associadas? Esta ação é irreversível.')) return;
        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_subject', subject_id: subjectId })
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao deletar matéria.');

            showToast(res.message, 'success');
            if (currentCourseId) selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function saveSubject(e) {
        e.preventDefault();
        if (!currentCourseId) return;

        const id = document.getElementById('subjectIdField').value;
        const moduleId = document.getElementById('subjectModuleIdField').value;
        const payload = {
            action: id ? 'update_subject' : 'create_subject',
            subject_id: id ? parseInt(id) : undefined,
            module_id: parseInt(moduleId),
            title: document.getElementById('subjectTitleField').value,
            sort_order: parseInt(document.getElementById('subjectSortField').value)
        };

        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            
            if (!response.ok) throw new Error(res.error || 'Erro ao salvar matéria.');

            showToast(res.message, 'success');
            closeSubjectModal();
            selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // 5. OPERAÇÕES DE AULA (CRUD COMPLETO)
    function openLessonModal(subjectId) {
        document.getElementById('lessonForm').reset();
        document.getElementById('lessonSubjectIdField').value = subjectId;
        document.getElementById('lessonIdField').value = '';
        document.getElementById('lessonModalHeader').innerText = 'Adicionar Nova Aula';
        document.getElementById('lessonSubmitBtn').innerText = 'Publicar Aula';
        document.getElementById('lessonModal').classList.remove('hidden');
    }

    document.getElementById('lessonModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('lessonModal')) {
            closeLessonModal();
        }
    });

    function closeLessonModal() {
        document.getElementById('lessonModal').classList.add('hidden');
    }

    function editLesson(id, subjectId, title, desc, videoUrl, duration, sortOrder, attachmentUrl) {
        document.getElementById('lessonForm').reset();
        document.getElementById('lessonIdField').value = id;
        document.getElementById('lessonSubjectIdField').value = subjectId;
        document.getElementById('lessonTitleField').value = title;
        document.getElementById('lessonDescField').value = desc || '';
        document.getElementById('lessonVideoField').value = videoUrl;
        document.getElementById('lessonDurationField').value = duration;
        document.getElementById('lessonSortField').value = sortOrder || 1;
        document.getElementById('lessonAttachmentField').value = attachmentUrl || '';
        document.getElementById('lessonModalHeader').innerText = 'Editar Detalhes da Aula';
        document.getElementById('lessonSubmitBtn').innerText = 'Atualizar Aula';
        document.getElementById('lessonModal').classList.remove('hidden');
    }

    async function deleteLesson(lessonId) {
        if (!confirm('Deseja realmente excluir esta aula? Esta ação é irreversível.')) return;
        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_lesson', lesson_id: lessonId })
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Erro ao deletar aula.');

            showToast(res.message, 'success');
            if (currentCourseId) selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function saveLesson(e) {
        e.preventDefault();
        const id = document.getElementById('lessonIdField').value;
        const subjectId = document.getElementById('lessonSubjectIdField').value;
        
        const payload = {
            action: id ? 'update_lesson' : 'create_lesson',
            lesson_id: id ? parseInt(id) : undefined,
            subject_id: subjectId ? parseInt(subjectId) : undefined,
            title: document.getElementById('lessonTitleField').value,
            description: document.getElementById('lessonDescField').value,
            video_url: document.getElementById('lessonVideoField').value,
            duration: parseInt(document.getElementById('lessonDurationField').value),
            sort_order: parseInt(document.getElementById('lessonSortField').value),
            attachment_url: document.getElementById('lessonAttachmentField').value
        };

        try {
            const response = await fetch('../api/admin/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            
            if (!response.ok) throw new Error(res.error || 'Erro ao salvar aula.');

            showToast(res.message, 'success');
            closeLessonModal();
            if (currentCourseId) selectCourse(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // 6. HELPER DE NOTIFICAÇÃO TOAST
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

    // ==========================================
    // 7. OPERAÇÕES DE AVALIAÇÃO TÉCNICA (QUIZZES & QUESTÕES)
    // ==========================================

    let currentQuiz = null;

    // Carrega o quiz e questões associados ao curso selecionado
    async function loadCourseQuiz(courseId) {
        const quizSec = document.getElementById('quizSection');
        const quizContent = document.getElementById('quizContent');
        const quizHeaderActions = document.getElementById('quizHeaderActions');

        quizSec.classList.remove('hidden');
        quizContent.innerHTML = `<div class="text-center py-6 text-on-surface-variant text-xs font-bold uppercase tracking-wider">Buscando avaliação técnica...</div>`;
        quizHeaderActions.innerHTML = '';

        try {
            const response = await fetch(`../api/admin/quizzes.php?course_id=${courseId}`);
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao carregar dados do Quiz.');

            const quiz = res.quiz;
            currentQuiz = quiz;

            if (!quiz) {
                // Não há quiz para o curso
                quizHeaderActions.innerHTML = `
                    <button onclick="openQuizModal()" class="bg-primary px-4 py-2 rounded-lg text-on-primary font-bold text-xs uppercase tracking-wider shadow-[0_0_15px_rgba(242,201,76,0.15)] flex items-center gap-1.5 hover:shadow-[0_0_20px_rgba(242,201,76,0.3)] transition-all">
                        <span class="material-symbols-outlined text-[16px]">add_task</span>
                        Configurar Avaliação
                    </button>
                `;

                quizContent.innerHTML = `
                    <div class="text-center py-10 border border-dashed border-white/10 rounded-xl bg-black/20">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">assignment_late</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider font-display">Sem Avaliação Cadastrada</h3>
                        <p class="text-xs text-on-surface-variant mt-1.5 max-w-sm mx-auto leading-relaxed">
                            Este treinamento não possui uma prova final vinculada. Adicione uma prova técnica com perguntas de múltipla escolha para validar a emissão de certificados.
                        </p>
                    </div>
                `;
                return;
            }

            // Exibe botões do header
            quizHeaderActions.innerHTML = `
                <button onclick="openQuizModal(currentQuiz)" class="border border-white/10 hover:border-primary/40 bg-white/5 text-white flex items-center gap-1.5 px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-wider transition-all">
                    <span class="material-symbols-outlined text-[14px]">edit</span>
                    Editar Configurações
                </button>
                <button onclick="deleteQuiz(${quiz.id})" class="border border-red-500/20 hover:border-red-500/50 bg-red-500/5 text-red-400 flex items-center gap-1.5 px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-wider transition-all">
                    <span class="material-symbols-outlined text-[14px]">delete</span>
                    Remover Avaliação
                </button>
                <button onclick="openQuestionModal()" class="bg-primary px-4 py-1.5 rounded text-on-primary font-bold text-[10px] uppercase tracking-wider shadow-[0_0_15px_rgba(242,201,76,0.15)] flex items-center gap-1.5 hover:shadow-[0_0_20px_rgba(242,201,76,0.3)] transition-all">
                    <span class="material-symbols-outlined text-[14px]">add</span>
                    Adicionar Pergunta
                </button>
            `;

            // Renderiza detalhes gerais e as questões
            let questionsHtml = '';
            
            if (!quiz.questions || quiz.questions.length === 0) {
                questionsHtml = `
                    <div class="text-center py-8 border border-dashed border-white/5 rounded-xl bg-black/10 text-on-surface-variant text-xs italic">
                        Esta avaliação não possui nenhuma pergunta tática. Cadastre sua primeira questão para habilitar a prova!
                    </div>
                `;
            } else {
                questionsHtml = `
                    <div class="space-y-4">
                        <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest block">Banco de Perguntas da Prova:</span>
                        ${quiz.questions.map((q, idx) => `
                            <div class="p-4 bg-black/30 border border-white/5 rounded-xl space-y-3 hover:border-primary/10 transition-all">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <span class="w-5 h-5 rounded bg-primary/10 border border-primary/20 flex items-center justify-center font-display text-[10px] font-bold text-primary flex-shrink-0 mt-0.5">${idx + 1}</span>
                                        <p class="text-xs font-bold text-white leading-relaxed">${q.text}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button onclick='editQuestion(${JSON.stringify(q).replace(/'/g, "&apos;")})' class="p-1 text-on-surface-variant hover:text-primary transition-colors" title="Editar Pergunta">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                        </button>
                                        <button onclick="deleteQuestion(${q.id}, ${quiz.id})" class="p-1 text-on-surface-variant hover:text-red-500 transition-colors" title="Excluir Pergunta">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-8">
                                    ${q.options.map((o, optIdx) => {
                                        const isCorrect = (optIdx === q.correct_idx);
                                        const borderClass = isCorrect ? 'border-emerald-500/20 bg-emerald-500/5 text-emerald-400 font-bold' : 'border-white/5 bg-black/20 text-on-surface-variant';
                                        const checkIcon = isCorrect ? '<span class="material-symbols-outlined text-[12px] text-emerald-400 fill-1">check_circle</span>' : '';
                                        return `
                                            <div class="px-3 py-1.5 border rounded-lg text-[10px] flex items-center justify-between gap-2 ${borderClass}">
                                                <span>${o.text}</span>
                                                ${checkIcon}
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            quizContent.innerHTML = `
                <div class="p-4 bg-primary/5 border border-primary/10 rounded-lg flex items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-lg">verified</span>
                        <span class="text-white font-semibold">Título da Prova: <strong class="text-primary">${quiz.title}</strong></span>
                    </div>
                    <span class="text-[10px] font-bold text-primary border border-primary/20 bg-primary/5 rounded px-2.5 py-1 uppercase tracking-widest">
                        Nota de Corte: ${quiz.min_score}%
                    </span>
                </div>
                ${questionsHtml}
            `;

        } catch (err) {
            quizContent.innerHTML = `<div class="text-center py-6 text-red-400 text-xs font-bold uppercase">Erro: ${err.message}</div>`;
        }
    }

    // --- MODAL DE CONFIGURAÇÃO DE QUIZ ---
    function openQuizModal(quiz = null) {
        document.getElementById('quizForm').reset();
        document.getElementById('quizIdField').value = quiz ? quiz.id : '';
        document.getElementById('quizTitleField').value = quiz ? quiz.title : '';
        document.getElementById('quizMinScoreField').value = quiz ? quiz.min_score : '70';
        document.getElementById('quizModalHeader').innerText = quiz ? 'Editar Avaliação Técnica' : 'Configurar Avaliação Técnica';
        document.getElementById('quizModal').classList.remove('hidden');
    }

    function closeQuizModal() {
        document.getElementById('quizModal').classList.add('hidden');
    }

    async function saveQuiz(e) {
        e.preventDefault();
        if (!currentCourseId) return;

        const id = document.getElementById('quizIdField').value;
        const payload = {
            action: 'save_quiz',
            course_id: currentCourseId,
            quiz_id: id ? parseInt(id) : undefined,
            title: document.getElementById('quizTitleField').value,
            min_score: parseInt(document.getElementById('quizMinScoreField').value)
        };

        try {
            const response = await fetch('../api/admin/quizzes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao processar requisição.');

            showToast(res.message, 'success');
            closeQuizModal();
            loadCourseQuiz(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function deleteQuiz(quizId) {
        if (!confirm('Deseja realmente remover esta avaliação técnica e todas as suas perguntas? Essa ação desabilitará o requisito de prova para o certificado deste curso.')) return;

        try {
            const response = await fetch('../api/admin/quizzes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_quiz', quiz_id: quizId })
            });
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao remover avaliação.');

            showToast(res.message, 'success');
            loadCourseQuiz(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- MODAL DE QUESTÕES ---
    function openQuestionModal() {
        document.getElementById('questionForm').reset();
        document.getElementById('questionIdField').value = '';
        document.getElementById('questionModalHeader').innerText = 'Adicionar Nova Pergunta';
        document.getElementById('questionModal').classList.remove('hidden');
    }

    function closeQuestionModal() {
        document.getElementById('questionModal').classList.add('hidden');
    }

    function editQuestion(q) {
        document.getElementById('questionForm').reset();
        document.getElementById('questionIdField').value = q.id;
        document.getElementById('questionTextField').value = q.text;
        
        if (q.options && q.options.length >= 4) {
            document.getElementById('opt0').value = q.options[0].text;
            document.getElementById('opt1').value = q.options[1].text;
            document.getElementById('opt2').value = q.options[2].text;
            document.getElementById('opt3').value = q.options[3].text;
        }

        // Seleciona o rádio correspondente
        const radios = document.getElementsByName('correct_opt');
        if (radios && radios[q.correct_idx]) {
            radios[q.correct_idx].checked = true;
        }

        document.getElementById('questionModalHeader').innerText = 'Editar Pergunta Tática';
        document.getElementById('questionModal').classList.remove('hidden');
    }

    async function saveQuestion(e) {
        e.preventDefault();
        if (!currentQuiz) return;

        const id = document.getElementById('questionIdField').value;
        const radios = document.getElementsByName('correct_opt');
        let correctIdx = 0;
        for (let i = 0; i < radios.length; i++) {
            if (radios[i].checked) {
                correctIdx = i;
                break;
            }
        }

        const payload = {
            action: 'save_question',
            quiz_id: currentQuiz.id,
            question_id: id ? parseInt(id) : undefined,
            question_text: document.getElementById('questionTextField').value,
            options: [
                document.getElementById('opt0').value,
                document.getElementById('opt1').value,
                document.getElementById('opt2').value,
                document.getElementById('opt3').value
            ],
            correct_idx: correctIdx
        };

        try {
            const response = await fetch('../api/admin/quizzes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao salvar pergunta.');

            showToast(res.message, 'success');
            closeQuestionModal();
            loadCourseQuiz(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function deleteQuestion(questionId, quizId) {
        if (!confirm('Deseja realmente remover esta questão da prova?')) return;

        try {
            const response = await fetch('../api/admin/quizzes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_question', question_id: questionId, quiz_id: quizId })
            });
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao remover pergunta.');

            showToast(res.message, 'success');
            loadCourseQuiz(currentCourseId);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function toggleCollapse(bodyId, iconId) {
        const body = document.getElementById(bodyId);
        const icon = document.getElementById(iconId);
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            icon.innerText = 'expand_less';
        } else {
            body.classList.add('hidden');
            icon.innerText = 'expand_more';
        }
    }

    // Adiciona listeners para cliques fora dos modais para fechá-los
    document.getElementById('moduleModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('moduleModal')) {
            closeModuleModal();
        }
    });

    document.getElementById('subjectModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('subjectModal')) {
            closeSubjectModal();
        }
    });

    document.getElementById('lessonModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('lessonModal')) {
            closeLessonModal();
        }
    });

    document.getElementById('quizModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('quizModal')) {
            closeQuizModal();
        }
    });

    document.getElementById('questionModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('questionModal')) {
            closeQuestionModal();
        }
    });
</script>
</body>
</html>
