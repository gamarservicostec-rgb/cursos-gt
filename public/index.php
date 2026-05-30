<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/Database.php';

// Inicia sessão
AppConfig::startSession();

// Conecta ao banco de dados e busca cursos ativos
$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    $stmt = $db->prepare("SELECT id, title, description, thumbnail_url, type, price FROM courses WHERE status = 'active' ORDER BY id ASC");
    $stmt->execute();
    $courses = $stmt->fetchAll();
} catch (\PDOException $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="description" content="GT Cursos - Plataforma de Educação de Elite. Treinamento híbrido avançado, segurança operacional, tecnologia e desenvolvimento estratégico.">
    <title>GT Cursos — Educação Híbrida de Elite</title>
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
    </style>
</head>
<body class="bg-background-dark text-text-main font-body antialiased overflow-x-hidden bg-grid-pattern">
    
    <!-- 1. Header (Navegação) -->
    <header class="w-full border-b border-solid border-border-color px-6 md:px-16 py-4 glass-panel sticky top-0 z-50 transition-all duration-300">
        <div class="max-w-[1440px] mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3 text-text-main">
                <div class="h-10 w-10 bg-primary/10 rounded border border-primary/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-glow font-bold" style="font-size: 24px;">shield</span>
                </div>
                <span class="font-heading text-2xl font-bold tracking-wider uppercase">
                    GT <span class="text-primary text-glow">CURSOS</span>
                </span>
            </div>
            
            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold tracking-wider uppercase">
                <a href="#inicio" class="hover:text-primary transition-colors">Início</a>
                <a href="#diferenciais" class="hover:text-primary transition-colors">Diferenciais</a>
                <a href="#catalogo" class="hover:text-primary transition-colors">Cursos</a>
                <a href="#metodo" class="hover:text-primary transition-colors">Metodologia</a>
                <a href="#faq" class="hover:text-primary transition-colors">FAQ</a>
            </nav>

            <div class="flex items-center gap-6">
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

    <!-- 2. Hero Section (Seção 1) -->
    <section id="inicio" class="relative min-h-[90vh] flex items-center justify-center px-6 md:px-16 py-12 md:py-24 overflow-hidden border-b border-border-color">
        <!-- Glows de Fundo -->
        <div class="absolute top-1/4 left-1/4 -translate-x-1/2 -translate-y-1/2 w-[350px] md:w-[600px] h-[350px] md:h-[600px] rounded-full bg-primary/5 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-1/4 right-1/4 translate-x-1/2 translate-y-1/2 w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-secondary/5 blur-[100px] pointer-events-none"></div>

        <div class="max-w-[1440px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
            <!-- Texto Esquerda -->
            <div class="lg:col-span-7 flex flex-col items-start text-left gap-6">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold uppercase tracking-widest">
                    <span class="w-2 h-2 rounded-full bg-success animate-ping"></span>
                    Matrículas Abertas • Ambiente de Elite
                </div>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-heading font-bold leading-[1.05] uppercase tracking-tight">
                    Domine Habilidades <br>
                    <span class="text-primary text-glow">Operacionais de Elite</span>
                </h1>
                <p class="text-muted text-base md:text-xl font-normal leading-relaxed max-w-xl">
                    Capacitação profissional tática e estratégica. Cursos de segurança operacional avançada, tecnologia de ponta, inteligência estratégica e gestão de escala. 
                </p>
                <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mt-2">
                    <a href="#catalogo" class="flex items-center justify-center rounded h-14 px-8 bg-primary text-background-dark text-sm font-bold tracking-[0.05em] uppercase hover:bg-gold-light hover:shadow-glow-strong transition-all duration-300">
                        Começar Agora
                    </a>
                    <a href="#metodo" class="flex items-center justify-center rounded h-14 px-8 border border-white/10 hover:border-primary/50 text-text-main text-sm font-bold tracking-[0.05em] uppercase hover:bg-white/5 transition-all duration-300">
                        Nossa Metodologia
                    </a>
                </div>
                <!-- Mini Estatísticas Hero -->
                <div class="grid grid-cols-3 gap-6 md:gap-10 border-t border-white/5 pt-8 mt-4 w-full">
                    <div>
                        <h4 class="text-2xl md:text-3xl font-heading font-bold text-primary">+5.000</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">Alunos Formados</p>
                    </div>
                    <div>
                        <h4 class="text-2xl md:text-3xl font-heading font-bold text-primary">99.4%</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">Aprovação Final</p>
                    </div>
                    <div>
                        <h4 class="text-2xl md:text-3xl font-heading font-bold text-primary">100%</h4>
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold">Híbrido e Prático</p>
                    </div>
                </div>
            </div>

            <!-- Card/Imagem Direita (Hero) -->
            <div class="lg:col-span-5 relative">
                <div class="relative w-full aspect-[4/5] rounded-2xl overflow-hidden border border-primary/20 shadow-glow bg-cover bg-center group" style="background-image: linear-gradient(to top, rgba(6, 6, 8, 0.9) 0%, rgba(6, 6, 8, 0.3) 50%, rgba(6, 6, 8, 0) 100%), url('https://lh3.googleusercontent.com/aida-public/AB6AXuCFxvMNQ91f_nfS0isFpOV1vaeiX2VlZTMX4Y0x7RqqCvPtl6JLOfgOMJ1zlpqNS7yrn-lGdil6HfHk3miu866phRlhp6PszWnsa-RSvWplX-CBskdDJ0VIIyzQ9mIeBH8C_LlsJv25--ClifepKrpcOLoh1DSDZImk86P2ODcTyT1S4pI_61b3T1ai7TT6meVe1VX5DbMVytqO7DLOpN1y3pYRYiT4jQnZsFXSkU2imrbidIlw26R76YUqhq_Ga8jJzihiZc1Ei1Ec');">
                    <!-- Overlay de Scanner Tático -->
                    <div class="absolute inset-0 border border-primary/10 rounded-2xl pointer-events-none"></div>
                    <div class="absolute top-4 left-4 inline-flex items-center gap-1.5 px-3 py-1 rounded bg-black/80 backdrop-blur border border-white/10 text-[10px] text-primary uppercase font-bold tracking-widest">
                        <span class="material-symbols-outlined text-[12px] animate-pulse">radar</span> OPERAÇÃO ATIVA
                    </div>
                    <div class="absolute bottom-6 left-6 right-6 flex flex-col gap-2 z-10">
                        <span class="text-primary text-xs uppercase font-bold tracking-widest font-heading">CURSO DE DESTAQUE</span>
                        <h3 class="text-text-main text-2xl font-heading font-bold uppercase leading-tight">MASTERCLASS EM SEGURANÇA DE ELITE</h3>
                        <p class="text-muted text-xs font-normal">Aprenda a operar sob condições críticas e a gerenciar riscos táticos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Diferenciais (Seção 2) -->
    <section id="diferenciais" class="px-6 md:px-16 py-20 bg-surface-dark/40 border-b border-border-color relative">
        <div class="max-w-[1440px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">POR QUE ESCOLHER A GT?</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">OS PILARES DO NOSSO TREINAMENTO</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Desenvolvemos uma estrutura de ensino voltada para a prática absoluta, com tecnologias que impulsionam o seu potencial de ação.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Diferencial 1 -->
                <div class="glass-panel p-8 rounded-xl flex flex-col gap-4 hover:border-primary/30 transition-all duration-300 hover:-translate-y-1">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">school</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">ENSINO HÍBRIDO PRÁTICO</h3>
                    <p class="text-muted text-sm leading-relaxed">Combine teoria teórica avançada em nossa plataforma digital EAD com treinamentos táticos e avaliações práticas presenciais de campo.</p>
                </div>
                
                <!-- Diferencial 2 -->
                <div class="glass-panel p-8 rounded-xl flex flex-col gap-4 hover:border-primary/30 transition-all duration-300 hover:-translate-y-1">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">workspace_premium</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">CERTIFICAÇÃO RECONHECIDA</h3>
                    <p class="text-muted text-sm leading-relaxed">Emita certificados profissionais dinâmicos de alta definição contendo verificação por QR Code criptografado na HostGator.</p>
                </div>

                <!-- Diferencial 3 -->
                <div class="glass-panel p-8 rounded-xl flex flex-col gap-4 hover:border-primary/30 transition-all duration-300 hover:-translate-y-1">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">military_tech</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">GAMIFICAÇÃO & XP</h3>
                    <p class="text-muted text-sm leading-relaxed">Aprenda ganhando pontos de XP, suba de nível, ganhe medalhas táticas e apareça no topo do ranking de alunos de elite.</p>
                </div>

                <!-- Diferencial 4 -->
                <div class="glass-panel p-8 rounded-xl flex flex-col gap-4 hover:border-primary/30 transition-all duration-300 hover:-translate-y-1">
                    <div class="h-12 w-12 rounded bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-glow">
                        <span class="material-symbols-outlined text-2xl font-bold">support_agent</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider">SUPORTE E CHATS</h3>
                    <p class="text-muted text-sm leading-relaxed">Comunicação fluida com suporte técnico direto via WhatsApp e painel de tickets administrativo Obsidian Gold integrado.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Catálogo de Cursos (Seção 3) -->
    <section id="catalogo" class="px-6 md:px-16 py-20 border-b border-border-color">
        <div class="max-w-[1440px] mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div class="flex flex-col gap-4">
                    <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">NOSSOS TREINAMENTOS</span>
                    <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">PROGRAMAS DE FORMAÇÃO DISPONÍVEIS</h2>
                </div>
                <p class="text-muted text-base max-w-md">Escolha a sua especialização. Faça a sua matrícula online com opções facilitadas em PIX e Cartão com liberação imediata.</p>
            </div>

            <!-- Grid de Cursos Dinâmicos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="glass-panel rounded-xl overflow-hidden flex flex-col justify-between group hover:border-primary/20 hover:shadow-glow transition-all duration-300">
                            <!-- Thumbnail Area -->
                            <div class="relative aspect-video w-full overflow-hidden bg-cover bg-center border-b border-white/5" style="background-image: url('<?php echo $course['thumbnail_url'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOZxoS-KaOHz2AQP_l-4pCnAOU55dDkFGrPU1UWvoYfvguKBjWVSTpGWkrosgpc5tAulMSWltO9FEY_pPGWgXIfJSk3nDGa5Sln93zKm49t0cfx3Rt41EpQmF0oZA7nVtIAsObChnhjSwTCqnSr2bGJfedSqdorO8A6LPiwU6Bzh57MN4fFHkKkFqbp5n1YBlJoOQrhpxl6yUFhz_gymvmJPCHnFCBE487_7b-yyGcSpGHu_NNTksWusxyIRG87m9YbpHDk5klzSnG'; ?>');">
                                <div class="absolute inset-0 bg-gradient-to-t from-background-dark/95 to-transparent opacity-60"></div>
                                <div class="absolute top-4 right-4 bg-primary text-background-dark text-[10px] font-bold px-2.5 py-1 rounded shadow-glow uppercase tracking-widest font-heading">
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
            <div class="text-center flex flex-col items-center gap-4 mb-16">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">COMO FUNCIONA</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">O CAMINHO DA EXCELÊNCIA OPERACIONAL</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Entenda a nossa metodologia de alta performance, projetada para consolidar o aprendizado em nível profissional.</p>
            </div>

            <!-- Steps Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 relative">
                <!-- Step 1 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        01
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Estudo Teórico Online (EAD)</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Acesse vídeo-aulas profissionais em Bunny.net Stream, faça anotações integradas, tire dúvidas nos fóruns e conclua os módulos em nossa interface Obsidian Gold.</p>
                </div>

                <!-- Step 2 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        02
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Treinamentos e Provas</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Realize exames táticos de múltipla escolha e valide sua presença física nas aulas presenciais práticas, ganhando pontuação XP e medalhas.</p>
                </div>

                <!-- Step 3 -->
                <div class="flex flex-col gap-4 items-center text-center relative z-10">
                    <div class="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-primary font-heading text-2xl font-bold shadow-glow">
                        03
                    </div>
                    <h3 class="text-xl font-heading font-bold uppercase tracking-wider mt-2">Certificação Autêntica</h3>
                    <p class="text-muted text-sm leading-relaxed max-w-xs">Gere e baixe seu certificado de conclusão com posicionamento milimétrico gráfico de assinatura, logo e autenticação pública por QR Code.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Estatísticas & Impacto (Seção 5) -->
    <section class="px-6 md:px-16 py-20 border-b border-border-color">
        <div class="max-w-[1440px] mx-auto glass-panel p-10 md:p-16 rounded-2xl relative overflow-hidden">
            <!-- Glow do Card -->
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 text-center items-center divide-y lg:divide-y-0 lg:divide-x divide-white/5">
                <div class="flex flex-col gap-2 py-4 lg:py-0">
                    <span class="text-4xl md:text-5xl font-heading font-bold text-primary text-glow">+5.000</span>
                    <span class="text-xs text-muted uppercase tracking-wider font-semibold">Alunos Capacitados</span>
                </div>
                <div class="flex flex-col gap-2 py-4 lg:py-0">
                    <span class="text-4xl md:text-5xl font-heading font-bold text-primary text-glow">+400</span>
                    <span class="text-xs text-muted uppercase tracking-wider font-semibold">Turmas Práticas</span>
                </div>
                <div class="flex flex-col gap-2 py-4 lg:py-0">
                    <span class="text-4xl md:text-5xl font-heading font-bold text-primary text-glow">99.4%</span>
                    <span class="text-xs text-muted uppercase tracking-wider font-semibold">Aprovação nos Exames</span>
                </div>
                <div class="flex flex-col gap-2 py-4 lg:py-0">
                    <span class="text-4xl md:text-5xl font-heading font-bold text-primary text-glow">+25</span>
                    <span class="text-xs text-muted uppercase tracking-wider font-semibold">Cidades Atendidas</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Depoimentos de Elite (Seção 6) -->
    <section class="px-6 md:px-16 py-20 bg-surface-dark/40 border-b border-border-color">
        <div class="max-w-[1440px] mx-auto">
            <div class="text-center flex flex-col items-center gap-4 mb-16">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">DEPOIMENTOS</span>
                <h2 class="text-3xl md:text-5xl font-heading font-bold uppercase tracking-tight">O QUE DIZEM OS NOSSOS OPERADORES</h2>
                <p class="text-muted text-base md:text-lg max-w-2xl">Confira a avaliação de profissionais que passaram pelo nosso treinamento de alto impacto e transformaram suas carreiras.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Depoimento 1 -->
                <div class="glass-panel p-8 rounded-xl flex flex-col justify-between gap-6">
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
                <div class="glass-panel p-8 rounded-xl flex flex-col justify-between gap-6">
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
                <div class="glass-panel p-8 rounded-xl flex flex-col justify-between gap-6">
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
            <div class="text-center flex flex-col items-center gap-4 mb-16">
                <span class="text-primary text-xs font-bold uppercase tracking-widest font-heading">DÚVIDAS FREQUENTES</span>
                <h2 class="text-3xl md:text-4xl font-heading font-bold uppercase tracking-tight text-center">PERGUNTAS FREQUENTES</h2>
            </div>

            <div class="flex flex-col gap-4">
                <!-- FAQ Item 1 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Como funciona a metodologia híbrida da GT Cursos?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Nossa metodologia combina o melhor dos dois mundos: você estuda toda a base teórica e de fundamentos online (EAD) por meio de aulas gravadas e exercícios, e depois participa de turmas presenciais para aulas práticas de aplicação física, recebendo presenças reais computadas no seu painel.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Quais são as formas de pagamento disponíveis?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Oferecemos pagamento totalmente integrado via Pix (com liberação automática e instantânea) e Cartão de Crédito em até 12 parcelas pelo Checkout do Mercado Pago, além de opção em Boleto Bancário.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Como funciona a autenticidade por QR Code nos certificados?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Todos os certificados emitidos na plataforma possuem um código alfanumérico único e um QR Code de verificação. Qualquer contratante pode ler o QR Code ou acessar a nossa página pública de validação para verificar a autenticidade dos seus dados de aprovação.
                        </p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="glass-panel rounded-lg overflow-hidden transition-all duration-300">
                    <button class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none" onclick="toggleFaq(this)">
                        <span class="text-base font-bold uppercase tracking-wide text-text-main font-heading">Posso usar cupons de desconto nas matrículas?</span>
                        <span class="material-symbols-outlined text-primary transition-transform duration-300">expand_more</span>
                    </button>
                    <div class="max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                        <p class="px-6 pb-6 text-sm text-muted leading-relaxed">
                            Sim! Oferecemos campanhas sazonais de descontos fixos ou percentuais. Basta inserir o código de cupom ativo no formulário de checkout para recalcular o valor de forma automática no fechamento da sua matrícula.
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
                <div class="h-10 w-10 bg-primary/10 rounded border border-primary/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-glow font-bold">shield</span>
                </div>
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
                <a href="#" class="hover:text-primary transition-colors">Termos de Serviço</a>
                <a href="#" class="hover:text-primary transition-colors">Política de Privacidade</a>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-success"></span>
                <span>Conexão Segura SSL HostGator</span>
            </div>
        </div>
    </footer>

    <!-- FAQ Accordion JS Script -->
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
    </script>
</body>
</html>
