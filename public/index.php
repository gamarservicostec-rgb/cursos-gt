<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/Database.php';

// Inicia sessão
AppConfig::startSession();

// Conecta ao banco de dados e busca cursos e categorias ativos
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca os cursos ativos e junta com os dados de categoria correspondentes
    $stmt = $db->prepare("
        SELECT c.id, c.title, c.description, c.thumbnail_url, c.type, c.price, c.category_id, cat.slug as category_slug, cat.name as category_name
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'active'
        ORDER BY c.id ASC
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll();

    // Busca todas as categorias ordenadas por sort_order
    $catStmt = $db->prepare("SELECT id, name, slug FROM categories ORDER BY sort_order ASC, name ASC");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
} catch (\PDOException $e) {
    $courses = [];
    $categories = [];
}

// Lógica de Detecção Inteligente e Fallback de Arquivos de Banner
$banner1 = 'assets/images/banner1.png';
$banner2 = 'assets/images/banner2.png';

$possiblePaths1 = [
    'assets/images/banner1.png',
    'assets/images/banner1.jpg',
    'assets/imagens/banner1.png',
    'assets/imagens/banner1.jpg',
    'assets/images/banner1.PNG',
    'assets/images/banner1.JPG',
    'assets/imagens/banner1.PNG',
    'assets/imagens/banner1.JPG'
];

$possiblePaths2 = [
    'assets/images/banner2.png',
    'assets/images/banner2.jpg',
    'assets/imagens/banner2.png',
    'assets/imagens/banner2.jpg',
    'assets/images/banner2.PNG',
    'assets/images/banner2.JPG',
    'assets/imagens/banner2.PNG',
    'assets/imagens/banner2.JPG'
];

foreach ($possiblePaths1 as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
        $banner1 = $path;
        break;
    }
}

foreach ($possiblePaths2 as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
        $banner2 = $path;
        break;
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="description" content="GT Cursos - Plataforma de Educação de Elite. Treinamento híbrido avançado, segurança operacional, tecnologia e desenvolvimento estratégico.">
    <title>GT Cursos — Educação Híbrida de Elite</title>
    <!-- Favicon Real -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!-- Fonts -->
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@600;700&f[]=satoshi@400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#F2C94C",
                        "secondary": "#D4AF37",
                        "gold-light": "#FFE082",
                        "background-dark": "#060608",
                        "surface-dark": "#0E0E12",
                        "surface": "rgba(22, 22, 28, 0.7)",
                        "text-main": "#F5F5F7",
                        "muted": "#8F8F9D",
                        "border-color": "rgba(242, 201, 76, 0.08)",
                        "success": "#00E676"
                    },
                    fontFamily: {
                        "heading": ["Clash Display", "sans-serif"],
                        "body": ["Satoshi", "sans-serif"]
                    },
                    boxShadow: {
                        "glow": "0 0 25px rgba(242, 201, 76, 0.15)",
                        "glow-strong": "0 0 40px rgba(242, 201, 76, 0.3)"
                    }
                },
            },
        }
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #060608;
        }
        ::-webkit-scrollbar-thumb {
            background: #1A1A22;
            border-radius: 4px;
            border: 1px solid rgba(242, 201, 76, 0.1);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #F2C94C;
        }
        .glass-panel {
            background-color: rgba(14, 14, 18, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 201, 76, 0.06);
        }
        .text-glow {
            text-shadow: 0 0 20px rgba(242, 201, 76, 0.4);
        }
        .bg-grid-pattern {
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(242, 201, 76, 0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(242, 201, 76, 0.02) 1px, transparent 1px);
        }

        /* Animações de Entrada e Saída (Scroll Reveal) */
        .reveal {
            opacity: 0;
            transform: translateY(40px) scale(0.97);
            transition: all 0.9s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform, opacity;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Delays Dinâmicos de Animação para Efeito Cascata */
        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }
        .delay-400 { transition-delay: 400ms; }

        /* Hover Premium nos Cards de Cursos */
        .course-card {
            transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1) !important;
            border: 1px solid rgba(242, 201, 76, 0.04) !important;
            position: relative;
            overflow: hidden;
        }
        .course-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: rgba(242, 201, 76, 0.25) !important;
            box-shadow: 0 25px 50px -12px rgba(242, 201, 76, 0.08), 0 0 30px rgba(242, 201, 76, 0.04) !important;
        }
        /* Efeito de Reflexo de Brilho Dinâmico (Hover Light Sweep) */
        .course-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(242, 201, 76, 0.08), transparent);
            transform: skewX(-25deg);
            transition: 0.8s ease-in-out;
            pointer-events: none;
            z-index: 2;
        }
        .course-card:hover::after {
            left: 130%;
        }

        /* Hover nos Cards de Diferenciais */
        .diff-card {
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .diff-card:hover {
            transform: translateY(-6px);
            border-color: rgba(242, 201, 76, 0.2) !important;
            box-shadow: 0 15px 30px rgba(242, 201, 76, 0.05);
        }
    </style>
</head>
<body class="bg-background-dark text-text-main font-body antialiased overflow-x-hidden bg-grid-pattern">
    
    <!-- 1. Header (Navegação) -->
    <header class="w-full border-b border-solid border-border-color px-6 md:px-16 py-4 glass-panel sticky top-0 z-50 transition-all duration-300">
        <div class="max-w-[1440px] mx-auto flex items-center justify-between relative">
            <div class="flex items-center gap-3 text-text-main">
                <img src="assets/images/logo.png" alt="Logo GT Cursos" class="h-10 w-auto object-contain">
                <span class="font-heading text-2xl font-bold tracking-wider uppercase hidden sm:inline">
                    GT <span class="text-primary text-glow">CURSOS</span>
                </span>
            </div>
            
            <nav class="hidden md:flex absolute left-1/2 -translate-x-1/2 items-center gap-8 text-sm font-semibold tracking-wider uppercase">
                <a href="#inicio" class="hover:text-primary transition-colors">Início</a>
                <a href="#diferenciais" class="hover:text-primary transition-colors">Diferenciais</a>
                <a href="#catalogo" class="hover:text-primary transition-colors">Cursos</a>
                <a href="#metodo" class="hover:text-primary transition-colors">Metodologia</a>
                <a href="#faq" class="hover:text-primary transition-colors">FAQ</a>
            </nav>

            <div class="flex items-center gap-6">
                <!-- Botão do WhatsApp -->
                <a href="https://wa.me/5511946721741" target="_blank" class="flex items-center justify-center rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-2.5 hover:bg-emerald-500 hover:text-background-dark transition-all duration-300 hover:shadow-[0_0_15px_rgba(16,185,129,0.3)]" title="Falar no WhatsApp">
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.665.989 3.3 1.513 5.367 1.515 5.53.003 10.028-4.492 10.03-10.025.002-2.68-1.038-5.197-2.93-7.091c-1.892-1.893-4.41-2.931-7.096-2.933-5.534 0-10.03 4.493-10.032 10.027-.001 1.777.464 3.506 1.345 5.037L1.86 21.19l4.787-1.25zM17.51 15.01c-.267-.134-1.579-.78-1.821-.865-.243-.086-.42-.13-.596.134-.176.265-.682.865-.837 1.04-.155.174-.31.195-.577.062-.267-.134-1.13-.416-2.152-1.327-.794-.708-1.33-1.582-1.487-1.85-.158-.266-.017-.41.116-.543.12-.12.267-.31.4-.467.135-.156.18-.26.27-.435.09-.175.045-.325-.022-.46-.067-.134-.596-1.436-.816-1.968-.215-.518-.432-.448-.596-.456-.153-.008-.33-.009-.507-.009-.176 0-.464.067-.707.325-.243.258-.928.907-.928 2.212 0 1.304.949 2.563 1.08 2.731.133.17 1.868 2.853 4.525 4.001.633.273 1.127.436 1.513.559.636.202 1.214.174 1.671.106.509-.076 1.579-.646 1.8-.1237.221-.592.221-1.1.155-1.185-.066-.086-.243-.13-.51-.264z"/>
                    </svg>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center gap-4">
                        <a href="<?php echo ($_SESSION['user_role'] === 'admin') ? 'admin/index.php' : 'dashboard/index.php'; ?>" class="flex items-center justify-center rounded border border-primary text-primary text-xs font-bold px-5 py-2.5 uppercase tracking-widest hover:bg-primary hover:text-background-dark transition-all duration-300 hover:shadow-glow">
                            Meu Painel
                        </a>
                        <a href="logout.php" class="text-xs font-bold text-muted hover:text-white uppercase tracking-widest transition-colors hidden sm:inline">
                            Sair
                        </a>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-4">
                        <a href="login.php" class="text-xs font-bold text-text-main hover:text-primary uppercase tracking-widest transition-colors">
                            Entrar
                        </a>
                        <a href="register.php" class="flex items-center justify-center rounded bg-primary text-background-dark text-xs font-bold px-5 py-2.5 uppercase tracking-widest hover:bg-gold-light hover:shadow-glow transition-all duration-300">
                            Matricular-se
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 2. Hero Carousel (Banners rotativos) -->
    <section class="relative w-full overflow-hidden bg-black border-b border-solid border-border-color z-10">
        <!-- Container de Slides -->
        <div class="relative w-full aspect-[1774/887] overflow-hidden">
            <!-- Slide 1 -->
            <div class="absolute inset-0 w-full h-full opacity-100 transition-opacity duration-1000 ease-in-out z-10" id="slide-0">
                <img src="<?php echo $banner1; ?>" alt="Treinamento Técnico GT Cursos - Banner 1" class="w-full h-full object-cover object-top">
                <div class="absolute inset-0 bg-gradient-to-t from-background-dark/95 via-transparent to-transparent"></div>
            </div>
            <!-- Slide 2 -->
            <div class="absolute inset-0 w-full h-full opacity-0 transition-opacity duration-1000 ease-in-out z-0" id="slide-1">
                <img src="<?php echo $banner2; ?>" alt="Capacitação Profissional GT Cursos - Banner 2" class="w-full h-full object-cover object-top">
                <div class="absolute inset-0 bg-gradient-to-t from-background-dark/95 via-transparent to-transparent"></div>
            </div>

            <!-- Setas Laterais de Navegação -->
            <button onclick="prevSlide()" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-10 md:w-12 h-10 md:h-12 rounded-full bg-black/60 border border-white/10 flex items-center justify-center text-white hover:border-primary hover:text-primary transition-all duration-300">
                <span class="material-symbols-outlined text-[20px] md:text-[24px]">chevron_left</span>
            </button>
            <button onclick="nextSlide()" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-10 md:w-12 h-10 md:h-12 rounded-full bg-black/60 border border-white/10 flex items-center justify-center text-white hover:border-primary hover:text-primary transition-all duration-300">
                <span class="material-symbols-outlined text-[20px] md:text-[24px]">chevron_right</span>
            </button>

            <!-- Indicadores (Bullets) -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex gap-2.5">
                <button onclick="goToSlide(0)" class="w-2.5 h-2.5 rounded-full bg-primary transition-all duration-300" id="bullet-0" aria-label="Slide 1"></button>
                <button onclick="goToSlide(1)" class="w-2.5 h-2.5 rounded-full bg-white/30 hover:bg-white/60 transition-all duration-300" id="bullet-1" aria-label="Slide 2"></button>
            </div>
        </div>
    </section>

    <!-- 3. Hero Section (Seção 1 - Conexão GT Serv Tec) -->
    <section id="inicio" class="relative min-h-[90vh] flex items-center justify-center px-6 md:px-16 py-12 md:py-24 overflow-hidden border-b border-border-color">
        <!-- Glows de Fundo -->
        <div class="absolute top-1/4 left-1/4 -translate-x-1/2 -translate-y-1/2 w-[350px] md:w-[600px] h-[350px] md:h-[600px] rounded-full bg-primary/5 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-1/4 right-1/4 translate-x-1/2 translate-y-1/2 w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-secondary/5 blur-[100px] pointer-events-none"></div>

        <div class="max-w-[1440px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
            <!-- Texto Esquerda -->
            <div class="lg:col-span-7 flex flex-col items-start text-left gap-6 reveal">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold uppercase tracking-widest">
                    <span class="w-2 h-2 rounded-full bg-success animate-ping"></span>
                    Matrículas Abertas • Canal de Talentos
                </div>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-heading font-bold leading-[1.05] uppercase tracking-tight">
                    Sua Carreira Técnica <br>
                    <span class="text-primary text-glow">na GT Serv Tec</span>
                </h1>
                <p class="text-muted text-base md:text-xl font-normal leading-relaxed max-w-xl">
                    Formamos e preparamos os futuros técnicos da nossa empresa. Capacitação de alto nível em segurança eletrônica, automação comercial, infraestrutura de redes e suporte de hardware. Estude conosco e prepare-se para ingressar no time da GT Serv Tec!
                </p>
                <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mt-2">
                    <a href="#catalogo" class="flex items-center justify-center rounded h-14 px-8 bg-primary text-background-dark text-sm font-bold tracking-[0.05em] uppercase hover:bg-gold-light hover:shadow-glow-strong transition-all duration-300">
                        Ver Cursos
                    </a>
                    <a href="#metodo" class="flex items-center justify-center rounded h-14 px-8 border border-white/10 hover:border-primary/50 text-text-main text-sm font-bold tracking-[0.05em] uppercase hover:bg-white/5 transition-all duration-300">
                        Nossa Metodologia
                    </a>
                </div>
                <!-- Mini Estatísticas Hero -->
                <div class="grid grid-cols-3 gap-6 md:gap-10 border-t border-white/5 pt-8 mt-4 w-full">
                    <div>
                        <h4 class="text-xl md:text-2xl font-heading font-bold text-primary uppercase">Suporte</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">WhatsApp Ativo</p>
                    </div>
                    <div>
                        <h4 class="text-xl md:text-2xl font-heading font-bold text-primary uppercase">Prática</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">Equipamentos Reais</p>
                    </div>
                    <div>
                        <h4 class="text-xl md:text-2xl font-heading font-bold text-primary uppercase">Diploma</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">QR Code Oficial</p>
                    </div>
                </div>
            </div>

            <!-- Card/Imagem Direita (Hero) -->
            <div class="lg:col-span-5 relative reveal delay-200">
                <div class="relative w-full aspect-[4/5] rounded-2xl overflow-hidden border border-primary/20 shadow-glow bg-cover bg-center group" style="background-image: linear-gradient(to top, rgba(6, 6, 8, 0.9) 0%, rgba(6, 6, 8, 0.3) 50%, rgba(6, 6, 8, 0) 100%), url('assets/images/hero_processo_seletivo.png');">
                    <!-- Overlay de Scanner Tático -->
                    <div class="absolute inset-0 border border-primary/10 rounded-2xl pointer-events-none"></div>
                    <div class="absolute top-4 left-4 inline-flex items-center gap-1.5 px-3 py-1 rounded bg-black/80 backdrop-blur border border-white/10 text-[10px] text-primary uppercase font-bold tracking-widest">
                        <span class="material-symbols-outlined text-[12px] animate-pulse">radar</span> PROCESSO SELETIVO
                    </div>
                    <div class="absolute bottom-6 left-6 right-6 flex flex-col gap-2 z-10">
                        <span class="text-primary text-xs uppercase font-bold tracking-widest font-heading">Formação em Destaque</span>
                        <h3 class="text-text-main text-2xl font-heading font-bold uppercase leading-tight">Instalador de Sistemas de Segurança</h3>
                        <p class="text-muted text-xs font-normal">Capacitação avançada em CFTV, cabeamento estruturado, centrais de alarme e automação prática.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Diferenciais (Seção 2) -->
    <section id="diferenciais" class="px-6 md:px-16 py-20 bg-surface-dark/40 border-b border-border-color relative">
        <div class="max-w-[1440px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16 reveal">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">POR QUE ESCOLHER A GT?</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">OS PILARES DO NOSSO TREINAMENTO</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Desenvolvemos uma estrutura de ensino voltada para a prática absoluta, com tecnologias que impulsionam o seu potential de ação.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Diferencial 1 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col gap-4 reveal delay-100">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">school</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">ENSINO HÍBRIDO PRÁTICO</h3>
                    <p class="text-muted text-sm leading-relaxed">Combine o estudo teórico online avançado com treinamentos presenciais práticos em equipamentos reais de instalação.</p>
                </div>
                
                <!-- Diferencial 2 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col gap-4 reveal delay-200">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">workspace_premium</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">CERTIFICAÇÃO TÉCNICA</h3>
                    <p class="text-muted text-sm leading-relaxed">Emita certificados profissionais dinâmicos com autenticação pública por QR Code, válidos para o mercado de segurança.</p>
                </div>

                <!-- Diferencial 3 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col gap-4 reveal delay-300">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">groups</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">BANCO DE TALENTOS</h3>
                    <p class="text-muted text-sm leading-relaxed">Os alunos destacados nas aulas práticas e exames teóricos entram em prioridade de recrutamento na própria GT Serv Tec.</p>
                </div>

                <!-- Diferencial 4 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col gap-4 reveal delay-400">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">support_agent</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">SUPORTE E CHATS</h3>
                    <p class="text-muted text-sm leading-relaxed">Tire suas dúvidas técnicas via chat de suporte direto integrado na plataforma ou atendimento no WhatsApp.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Catálogo de Cursos (Seção 3) -->
    <section id="catalogo" class="px-6 md:px-16 py-20 border-b border-border-color">
        <div class="max-w-[1440px] mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12 reveal">
                <div class="flex flex-col gap-4">
                    <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">NOSSOS TREINAMENTOS</span>
                    <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">PROGRAMAS DE FORMAÇÃO DISPONÍVEIS</h2>
                </div>
                <p class="text-muted text-base max-w-md">Escolha a sua especialização. Faça a sua matrícula online com opções facilitadas em PIX e Cartão com liberação imediata.</p>
            </div>

            <!-- Abas de Filtros de Categorias (Obsidian Gold) -->
            <?php if (!empty($categories)): ?>
                <div class="flex flex-wrap items-center justify-center gap-3 mb-10 border-b border-white/5 pb-8 reveal delay-100">
                    <button onclick="filterCategory('all', this)" class="px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider bg-primary text-background-dark shadow-glow-strong hover:bg-gold-light hover:shadow-glow transition-all duration-300 transform active:scale-95 tab-btn active-tab">
                        Todos
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button onclick="filterCategory('<?php echo $cat['slug']; ?>', this)" class="px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider bg-white/5 text-muted hover:text-white hover:bg-white/10 transition-all duration-300 transform active:scale-95 border border-white/5 tab-btn">
                            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Grid de Cursos Dinâmicos -->
            <?php
            $coursesCount = !empty($courses) ? count($courses) : 0;
            $containerClass = "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 justify-center";
            $cardClass = "w-full";

            if ($coursesCount === 1) {
                $containerClass = "flex justify-center";
                $cardClass = "w-full max-w-[380px]";
            } elseif ($coursesCount === 2) {
                $containerClass = "flex flex-wrap justify-center gap-8";
                $cardClass = "w-full md:w-[calc(50%-16px)] max-w-[380px]";
            } elseif ($coursesCount === 3) {
                $containerClass = "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 justify-center";
                $cardClass = "w-full";
            } elseif ($coursesCount === 4) {
                $containerClass = "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 max-w-[800px] mx-auto gap-8 justify-center";
                $cardClass = "w-full";
            } elseif ($coursesCount === 5) {
                $containerClass = "flex flex-wrap justify-center gap-8";
                $cardClass = "w-full md:w-[calc(50%-16px)] lg:w-[calc(33.333%-22px)] max-w-[380px]";
            } elseif ($coursesCount === 6) {
                $containerClass = "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 justify-center";
                $cardClass = "w-full";
            }
            ?>
            <div class="<?php echo $containerClass; ?>" id="coursesGridContainer">
                <?php if (!empty($courses)): ?>
                    <?php $courseIndex = 0; ?>
                    <?php foreach ($courses as $course): ?>
                        <?php 
                            $delayClass = 'delay-' . (($courseIndex % 3) * 100); 
                            $courseIndex++;
                        ?>
                        <div data-category-slug="<?php echo htmlspecialchars($course['category_slug'] ?? 'unassigned', ENT_QUOTES, 'UTF-8'); ?>" class="course-card glass-panel rounded-xl overflow-hidden flex flex-col justify-between group hover:border-primary/20 hover:shadow-glow transition-all duration-500 transform ease-in-out opacity-100 scale-100 reveal <?php echo $delayClass; ?> <?php echo $cardClass; ?>">
                            <!-- Thumbnail Area -->
                            <div class="relative aspect-video w-full overflow-hidden bg-cover bg-center border-b border-white/5" style="background-image: url('<?php echo $course['thumbnail_url'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOZxoS-KaOHz2AQP_l-4pCnAOU55dDkFGrPU1UWvoYfvguKBjWVSTpGWkrosgpc5tAulMSWltO9FEY_pPGWgXIfJSk3nDGa5Sln93zKm49t0cfx3Rt41EpQmF0oZA7nVtIAsObChnhjSwTCqnSr2bGJfedSqdorO8A6LPiwU6Bzh57MN4fFHkKkFqbp5n1YBlJoOQrhpxl6yUFhz_gymvmJPCHnFCBE487_7b-yyGcSpGHu_NNTksWusxyIRG87m9YbpHDk5klzSnG'; ?>');">
                                <div class="absolute inset-0 bg-gradient-to-t from-background-dark/95 to-transparent opacity-60"></div>
                                
                                <!-- Tag de Categoria (Canto Superior Esquerdo) -->
                                <?php if (!empty($course['category_name'])): ?>
                                    <div class="absolute top-4 left-4 bg-black/75 backdrop-blur-md border border-primary/10 text-primary text-[9px] font-bold px-2.5 py-1 rounded shadow-md uppercase tracking-wider font-heading z-10">
                                        <?php echo htmlspecialchars($course['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="absolute top-4 left-4 bg-black/75 backdrop-blur-md border border-white/10 text-muted text-[9px] font-bold px-2.5 py-1 rounded shadow-md uppercase tracking-wider font-heading z-10">
                                        Sem Categoria
                                    </div>
                                <?php endif; ?>

                                <!-- Tag de Tipo (Canto Superior Direito) -->
                                <div class="absolute top-4 right-4 bg-primary text-background-dark text-[9px] font-bold px-2.5 py-1 rounded shadow-glow uppercase tracking-wider font-heading z-10">
                                    <?php echo strtoupper($course['type']); ?>
                                </div>
                            </div>
                            
                            <!-- Info Area -->
                            <div class="p-6 flex-grow flex flex-col gap-3 justify-between">
                                <div>
                                    <h3 class="text-xl font-heading font-bold uppercase tracking-wide group-hover:text-primary transition-colors line-clamp-1">
                                        <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <p class="text-muted text-sm leading-relaxed mt-2 line-clamp-3">
                                        <?php echo htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </div>
                                
                                <div class="flex items-center justify-between border-t border-white/5 pt-4 mt-3">
                                    <div class="flex flex-col">
                                        <span class="text-muted text-[10px] uppercase font-bold tracking-wider">Investimento</span>
                                        <span class="text-primary font-heading text-lg font-bold">R$ <?php echo number_format($course['price'], 2, ',', '.'); ?></span>
                                    </div>
                                    <a href="course_details.php?id=<?php echo $course['id']; ?>" class="flex items-center justify-center rounded bg-primary/10 border border-primary/20 text-primary hover:bg-primary hover:text-background-dark text-xs font-bold px-4 py-2.5 uppercase tracking-widest hover:shadow-glow transition-all duration-300">
                                        Ver Detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-20 text-center glass-panel rounded-xl flex flex-col items-center justify-center gap-3">
                        <span class="material-symbols-outlined text-muted text-5xl">inventory_2</span>
                        <p class="text-muted text-base uppercase tracking-wider font-semibold">Nenhum treinamento ativo no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 5. Metodologia Híbrida (Seção 4) -->
    <section id="metodo" class="px-6 md:px-16 py-20 bg-surface-dark/40 border-b border-border-color relative">
        <div class="max-w-[1440px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16 reveal">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">COMO FUNCIONA</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">O CAMINHO DA EXCELÊNCIA OPERACIONAL</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Entenda a nossa metodologia de alta performance, projetada para consolidar o aprendizado em nível profissional.</p>
            </div>

            <!-- Steps Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 relative">
                <!-- Step 1 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10 reveal delay-100">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        01
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Estudo Teórico Online (EAD)</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Acesse vídeo-aulas profissionais em Bunny.net Stream, faça anotações integradas, tire dúvidas nos fóruns e conclua os módulos em nossa interface Obsidian Gold.</p>
                </div>

                <!-- Step 2 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10 reveal delay-200">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        02
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Treinamentos e Provas</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Realize exames táticos de múltipla escolha e valide sua presença física nas aulas presenciais práticas, ganhando pontuação XP e medalhas.</p>
                </div>

                <!-- Step 3 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10 reveal delay-300">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        03
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Certificação Autêntica</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Gere e baixe seu certificado de conclusão com posicionamento milimétrico gráfico de assinatura, logo e autenticação pública por QR Code.</p>
                </div>
            </div>
        </div>
    </section>


    <!-- 7. Depoimentos de Elite (Seção 6) -->
    <section class="px-6 md:px-16 py-20 bg-surface-dark/40 border-b border-border-color">
        <div class="max-w-[1440px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16 reveal">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">DEPOIMENTOS</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">O QUE DIZEM OS NOSSOS OPERADORES</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Confira a avaliação de profissionais que passaram pelo nosso treinamento de alto impacto e transformaram suas carreiras.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Depoimento 1 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col justify-between gap-6 reveal delay-100">
                    <div class="flex flex-col gap-4">
                        <div class="flex text-primary gap-1">
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-muted text-sm leading-relaxed italic">"O treinamento híbrido de segurança da GT é fora da curva. A riqueza de detalhes teóricos combinados com a prática de perímetros me deu total segurança operacional de elite."</p>
                    </div>
                    <div class="flex items-center gap-4 border-t border-white/5 pt-4">
                        <div class="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary font-heading">
                            MS
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-text-main font-bold">Marcos Silva</span>
                            <span class="text-[10px] text-muted uppercase tracking-wider">Operador de Segurança Privada</span>
                        </div>
                    </div>
                </div>

                <!-- Depoimento 2 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col justify-between gap-6 reveal delay-200">
                    <div class="flex flex-col gap-4">
                        <div class="flex text-primary gap-1">
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-muted text-sm leading-relaxed italic">"A plataforma Obsidian Gold é rápida, elegante e a gamificação realmente te instiga a devorar as aulas. Emitir o certificado na hora pelo subdomínio foi super simples!"</p>
                    </div>
                    <div class="flex items-center gap-4 border-t border-white/5 pt-4">
                        <div class="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary font-heading">
                            RC
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-text-main font-bold">Rodrigo Costa</span>
                            <span class="text-[10px] text-muted uppercase tracking-wider">Supervisor de Monitoramento</span>
                        </div>
                    </div>
                </div>

                <!-- Depoimento 3 -->
                <div class="glass-panel diff-card p-8 rounded-xl flex flex-col justify-between gap-6 reveal delay-300">
                    <div class="flex flex-col gap-4">
                        <div class="flex text-primary gap-1">
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <p class="text-muted text-sm leading-relaxed italic">"Suporte via WhatsApp espetacular. Dispararam todas as notificações da minha turma e as presenças presenciais foram integradas perfeitamente no meu painel!"</p>
                    </div>
                    <div class="flex items-center gap-4 border-t border-white/5 pt-4">
                        <div class="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary font-heading">
                            AN
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-text-main font-bold">Ana Nogueira</span>
                            <span class="text-[10px] text-muted uppercase tracking-wider">Aluna de Gestão Corporativa</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. FAQ Accordion (Seção 7) -->
    <section id="faq" class="px-6 md:px-16 py-20 border-b border-border-color">
        <div class="max-w-[800px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16 reveal">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">DÚVIDAS FREQUENTES</span>
                <h2 class="text-3xl md:text-4xl font-heading font-bold uppercase tracking-tight text-center">PERGUNTAS FREQUENTES</h2>
            </div>

            <div class="flex flex-col gap-4">
                <!-- FAQ Item 1 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300 reveal delay-100">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Como funciona a parceria da GT Cursos com a GT Serv Tec?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            A GT Cursos foi idealizada para preparar e capacitar técnicos de elite para atender
                             diretamente as demandas da GT Serv Tec. Preparamos você nas 
                             instalações e configurações do dia a dia para que você possa 
                             participar do nosso banco de talentos prioritário e integrar 
                             nossa equipe de profissionais em campo.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300 reveal delay-200">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Como funciona a parte prática presencial nos treinamentos híbridos?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Os alunos que optarem por fazer parte de nossas turmas na modalidade híbrida
                            vão assistir às aulas online. Porém, nas dependências de nossa escola, esses
                            alunos serão observados quanto ao compromisso, presença e aplicação nas aulas, 
                            para futuramente fazerem parte do grupo de prioritários para possíveis contratações.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300 reveal delay-300">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Os certificados possuem validação oficial?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Sim! Cada certificado emitido pela GT Cursos possui um código alfa-numérico único e um QR Code impresso. Qualquer empresa ou cliente pode validar a autenticidade e validade da sua certificação apontando a câmera do celular para o QR Code ou consultando a nossa página oficial de verificação.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300 reveal delay-400">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Quais são as formas de pagamento para as matrículas?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            As matrículas são integradas de forma 100% segura através do Mercado Pago. Você pode pagar via PIX (com liberação imediata das aulas), Cartão de Crédito em até 12 parcelas (com juros calculados na simulação) ou Boleto Bancário convencional.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. Footer (Rodapé) -->
    <footer class="w-full px-6 md:px-16 py-12 glass-panel relative z-10">
        <div class="max-w-[1440px] mx-auto flex flex-col md:flex-row items-center justify-between gap-6 border-b border-white/5 pb-8 mb-8">
            <div class="flex items-center gap-3 text-text-main">
                <img src="assets/images/logo.png" alt="Logo GT Cursos" class="h-10 w-auto object-contain">
                <span class="font-heading text-2xl font-bold tracking-wider uppercase">
                    GT <span class="text-primary text-glow">CURSOS</span>
                </span>
            </div>

            <p class="text-muted text-xs text-center md:text-right">
                © 2026 GT Cursos. Todos os direitos reservados. CNPJ: XX.XXX.XXX/0001-XX. <br>
                Plataforma de Alta Capacidade e Treinamentos de Elite.
            </p>
        </div>

        <div class="max-w-[1440px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-muted">
            <div class="flex gap-6">
                <a href="javascript:void(0)" onclick="openModal('terms')" class="hover:text-primary transition-colors">Termos de Serviço</a>
                <a href="javascript:void(0)" onclick="openModal('privacy')" class="hover:text-primary transition-colors">Política de Privacidade</a>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-success"></span>
                <span>Conexão Segura SSL HostGator</span>
            </div>
        </div>
    </footer>

    <!-- FAQ Accordion & Category Filter JS Script -->
    <script>
        function toggleFaq(button) {
            const content = button.nextElementSibling;
            const icon = button.querySelector('.material-symbols-outlined');
            
            if (content.style.maxHeight && content.style.maxHeight !== '0px') {
                content.style.maxHeight = '0px';
                icon.style.transform = 'rotate(0deg)';
                button.parentElement.classList.remove('border-primary/20');
            } else {
                // Fecha todos os outros primeiro
                document.querySelectorAll('#faq .max-h-0').forEach(c => {
                    c.style.maxHeight = '0px';
                    c.previousElementSibling.querySelector('.material-symbols-outlined').style.transform = 'rotate(0deg)';
                    c.parentElement.classList.remove('border-primary/20');
                });
                
                content.style.maxHeight = content.scrollHeight + 'px';
                icon.style.transform = 'rotate(180deg)';
                button.parentElement.classList.add('border-primary/20');
            }
        }

        // Função Premium de Filtragem Dinâmica e Animada de Categorias (Obsidian Gold)
        function filterCategory(categorySlug, button) {
            // 1. Atualiza o estado visual das abas
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('bg-primary', 'text-background-dark', 'shadow-glow-strong', 'active-tab', 'hover:bg-gold-light');
                btn.classList.add('bg-white/5', 'text-muted', 'border-white/5');
            });
            
            button.classList.remove('bg-white/5', 'text-muted', 'border-white/5');
            button.classList.add('bg-primary', 'text-background-dark', 'shadow-glow-strong', 'active-tab', 'hover:bg-gold-light');

            // 2. Filtra os cards com efeitos de fade-in e scale
            const cards = document.querySelectorAll('.course-card');
            
            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category-slug');
                const isMatch = (categorySlug === 'all' || cardCategory === categorySlug);
                
                if (isMatch) {
                    // Destrava display flex antes de iniciar a transição
                    card.style.display = 'flex';
                    
                    // Dá um pequeno timeout para o navegador recalcular os estilos e disparar a animação
                    setTimeout(() => {
                        card.classList.remove('opacity-0', 'scale-95');
                        card.classList.add('opacity-100', 'scale-100');
                    }, 50);
                } else {
                    // Dispara animação de saída (fade out + encolhimento leve)
                    card.classList.remove('opacity-100', 'scale-100');
                    card.classList.add('opacity-0', 'scale-95');
                    
                    // Oculta da tela após a transição completar (500ms)
                    setTimeout(() => {
                        if (card.classList.contains('opacity-0')) {
                            card.style.display = 'none';
                        }
                    }, 500);
                }
            });
        }

        // Funções para controle dos modais legais
        function openModal(modalId) {
            document.getElementById(modalId + 'Modal').classList.remove('hidden');
        }
        function closeModal(modalId) {
            document.getElementById(modalId + 'Modal').classList.add('hidden');
        }
        window.addEventListener('click', (e) => {
            const terms = document.getElementById('termsModal');
            const privacy = document.getElementById('privacyModal');
            if (e.target === terms) closeModal('terms');
            if (e.target === privacy) closeModal('privacy');
        });

        // Lógica do Hero Carousel Rotativo (Transições de Esmaecer / Fade)
        let currentSlideIndex = 0;
        const totalSlides = 2;
        let carouselInterval = setInterval(nextSlide, 3500);

        function showSlide(index) {
            currentSlideIndex = (index + totalSlides) % totalSlides;
            
            for (let i = 0; i < totalSlides; i++) {
                const slide = document.getElementById(`slide-${i}`);
                const bullet = document.getElementById(`bullet-${i}`);
                if (slide && bullet) {
                    if (i === currentSlideIndex) {
                        slide.classList.remove('opacity-0', 'z-0');
                        slide.classList.add('opacity-100', 'z-10');
                        bullet.classList.remove('bg-white/30', 'hover:bg-white/60');
                        bullet.classList.add('bg-primary');
                    } else {
                        slide.classList.remove('opacity-100', 'z-10');
                        slide.classList.add('opacity-0', 'z-0');
                        bullet.classList.remove('bg-primary');
                        bullet.classList.add('bg-white/30', 'hover:bg-white/60');
                    }
                }
            }
        }

        function nextSlide() {
            showSlide(currentSlideIndex + 1);
        }

        function prevSlide() {
            showSlide(currentSlideIndex - 1);
        }

        function goToSlide(index) {
            clearInterval(carouselInterval);
            showSlide(index);
            carouselInterval = setInterval(nextSlide, 3500);
        }

        // Efeito interativo que acompanha o cursor do mouse (Obsidian Gold Glow Grid)
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.course-card').forEach(card => {
                card.addEventListener('mousemove', e => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    card.style.setProperty('--mouse-x', `${x}px`);
                    card.style.setProperty('--mouse-y', `${y}px`);
                });
            });

            // Intersection Observer para Animações de Entrada/Saída ao rolar a página
            const revealOptions = {
                root: null,
                rootMargin: '0px -10px -50px -10px',
                threshold: 0.1
            };

            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    } else {
                        // Permite animar de novo se sair da tela (efeito entrada/saída contínuo)
                        entry.target.classList.remove('active');
                    }
                });
            }, revealOptions);

            document.querySelectorAll('.reveal').forEach(el => {
                revealObserver.observe(el);
            });
        });
    </script>

    <!-- Modal: Termos de Serviço -->
    <div id="termsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="glass-panel w-full max-w-2xl rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[80vh]" style="border: 1px solid rgba(242, 201, 76, 0.2); background-color: #0c0c0e;">
            <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <h3 class="text-sm font-bold text-white uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">description</span>
                    Termos de Serviço — GT Cursos
                </h3>
                <button onclick="closeModal('terms')" class="text-muted hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4 text-xs text-muted leading-relaxed">
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">1. Regras de Emissão de Certificados</h4>
                <p>Os certificados da GT Cursos são emitidos exclusivamente de forma digital e automática para os alunos que cumprirem cumulativamente os seguintes requisitos:</p>
                <ul class="list-disc list-inside space-y-1 pl-2">
                    <li>Conclusão de 100% da carga horária teórica online (aulas assistidas);</li>
                    <li>Aprovação na Avaliação Técnica Final com aproveitamento mínimo de 70%;</li>
                    <li>Frequência presencial comprovada de no mínimo 75% para os treinamentos na modalidade Híbrida.</li>
                </ul>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">2. Autenticidade e QR Code</h4>
                <p>Cada certificado emitido pela plataforma possui um código alfanumérico único e um QR Code criptográfico que atesta a sua autenticidade. O código pode ser consultado publicamente a qualquer momento no nosso validador oficial.</p>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">3. Licença de Uso e Acesso</h4>
                <p>Ao adquirir um curso, o aluno recebe uma licença de acesso pessoal, intransferível e individual de acordo com o tipo de acesso contratado (vitalício ou tempo limitado). É estritamente proibido o compartilhamento de credenciais, gravação ou distribuição não autorizada do material sob pena de rescisão imediata e medidas judiciais.</p>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">4. Resolução de Contratos</h4>
                <p>Garantimos o direito de arrependimento e reembolso integral em até 7 dias a partir da data de matrícula, conforme o Código de Defesa do Consumidor.</p>
            </div>
            <div class="px-6 py-4 flex items-center justify-end border-t border-white/5 flex-shrink-0">
                <button onclick="closeModal('terms')" class="bg-primary text-background-dark font-bold text-[10px] uppercase tracking-widest px-5 py-2.5 rounded-lg hover:bg-gold-light transition-all">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Modal: Política de Privacidade (LGPD) -->
    <div id="privacyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden">
        <div class="glass-panel w-full max-w-2xl rounded-xl overflow-hidden shadow-2xl relative flex flex-col max-h-[80vh]" style="border: 1px solid rgba(242, 201, 76, 0.2); background-color: #0c0c0e;">
            <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <h3 class="text-sm font-bold text-white uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">gpp_good</span>
                    Política de Privacidade (LGPD) — GT Cursos
                </h3>
                <button onclick="closeModal('privacy')" class="text-muted hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4 text-xs text-muted leading-relaxed">
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">1. Coleta e Finalidade dos Dados</h4>
                <p>Em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018), informamos que coletamos dados pessoais como Nome Completo, E-mail, Telefone/WhatsApp, CPF (para emissão de documentos fiscais e certificados) e dados de navegação/progresso escolar.</p>
                <p>Essas informações são usadas exclusivamente para gerenciar seu progresso de estudos, emitir e validar os certificados oficiais e garantir a segurança das transações.</p>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">2. Segurança de Pagamentos</h4>
                <p>Os pagamentos são processados em ambiente seguro de forma transparente através do parceiro homologado <strong>Mercado Pago</strong>. A GT Cursos não armazena, em nenhuma hipótese, dados confidenciais de cartão de crédito no banco de dados local.</p>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">3. Compartilhamento de Dados</h4>
                <p>Não comercializamos ou transferimos suas informações pessoais para terceiros, exceto quando estritamente necessário para fins legais de conformidade regulatória ou para o próprio processador de pagamento seguro.</p>
                <h4 class="font-bold text-white uppercase text-[10px] tracking-wider text-primary">4. Seus Direitos (Artigo 18 da LGPD)</h4>
                <p>Você tem o direito de solicitar a qualquer momento o acesso, a retificação, a limitação de tratamento ou a exclusão permanente dos seus dados cadastrais de nossa plataforma escolar, bastando entrar em contato com o nosso DPO/Suporte.</p>
            </div>
            <div class="px-6 py-4 flex items-center justify-end border-t border-white/5 flex-shrink-0">
                <button onclick="closeModal('privacy')" class="bg-primary text-background-dark font-bold text-[10px] uppercase tracking-widest px-5 py-2.5 rounded-lg hover:bg-gold-light transition-all">Entendido</button>
            </div>
        </div>
    </div>
</body>
</html>
