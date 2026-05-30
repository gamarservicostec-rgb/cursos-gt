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
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Cursos GT — Neon Amber Fusion</title>
    <!-- Fonts -->
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@600&f[]=satoshi@400,600&display=swap" rel="stylesheet">
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
                        "secondary": "#FFD700",
                        "background-dark": "#0A0A0C",
                        "surface": "rgba(20, 20, 23, 0.7)",
                        "text-main": "#EAEAEA",
                        "muted": "#8F8F9D",
                        "border-color": "rgba(255, 255, 255, 0.05)",
                        "success": "#00C853"
                    },
                    fontFamily: {
                        "heading": ["Clash Display", "sans-serif"],
                        "body": ["Satoshi", "sans-serif"]
                    },
                    borderRadius: {
                        "sm": "4px",
                        "DEFAULT": "8px",
                        "lg": "12px",
                        "xl": "16px",
                        "full": "9999px"
                    },
                    boxShadow: {
                        "glow": "0 4px 24px rgba(242, 201, 76, 0.15)"
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
            background: #0A0A0C;
        }
        ::-webkit-scrollbar-thumb {
            background: #2A2A35;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #8F8F9D;
        }
        .glass-panel {
            background-color: rgba(20, 20, 23, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="bg-background-dark text-text-main font-body antialiased overflow-x-hidden">
    <div class="relative flex h-auto min-h-screen w-full flex-col bg-background-dark group/design-root overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <div class="px-0 md:px-10 flex flex-1 justify-center py-5">
                <div class="layout-content-container flex flex-col w-full max-w-[1440px] flex-1">
                    
                    <!-- Header -->
                    <header class="flex items-center justify-between border-b border-solid border-border-color px-6 md:px-10 py-4 glass-panel sticky top-0 z-50 rounded-t-xl">
                        <div class="flex items-center gap-3 text-text-main">
                            <img src="assets/images/logo.png" alt="Logo GT Cursos" onerror="this.style.display='none'" class="h-10 w-auto object-contain">
                            <span class="font-heading text-2xl font-bold tracking-widest uppercase">
                                CURSOS <span class="text-primary">GT</span>
                            </span>
                        </div>
                        <div class="flex flex-1 justify-end items-center gap-6">
                            <!-- Buscador Mocado -->
                            <label class="flex flex-col min-w-40 !h-10 max-w-64 hidden sm:block">
                                <div class="flex w-full flex-1 items-stretch rounded-lg h-full border border-border-color bg-black/40 focus-within:border-primary transition-colors">
                                    <div class="text-muted flex items-center justify-center pl-4 rounded-l-lg" data-icon="search" data-size="24px" data-weight="regular">
                                        <span class="material-symbols-outlined" style="font-size: 20px;">search</span>
                                    </div>
                                    <input class="form-input flex w-full min-w-0 flex-1 rounded-lg text-text-main focus:outline-0 focus:ring-0 border-none bg-transparent h-full placeholder:text-muted px-4 rounded-l-none pl-2 text-sm font-normal" placeholder="Buscar cursos...">
                                </div>
                            </label>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <!-- Logged in actions -->
                                <div class="flex items-center gap-4">
                                    <a href="<?php echo ($_SESSION['user_role'] === 'admin') ? 'admin/index.php' : 'dashboard/index.php'; ?>" class="text-xs font-bold text-primary uppercase tracking-widest hover:underline">
                                        Meu Painel
                                    </a>
                                    <div class="h-6 w-[1px] bg-white/10"></div>
                                    <a href="logout.php" class="text-xs font-bold text-muted hover:text-white uppercase tracking-widest">
                                        Sair
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Logged out buttons -->
                                <div class="flex items-center gap-4">
                                    <a href="login.php" class="text-xs font-bold text-text-main hover:text-primary uppercase tracking-widest transition-colors">
                                        Entrar
                                    </a>
                                    <a href="register.php" class="flex items-center justify-center rounded bg-primary text-background-dark text-xs font-bold px-4 py-2 uppercase tracking-widest hover:bg-secondary transition-all">
                                        Cadastrar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="flex flex-col md:flex-row h-full min-h-[800px] w-full">
                        <!-- Sidebar -->
                        <div class="flex w-full md:w-64 flex-col gap-6 p-6 border-r border-border-color glass-panel">
                            <h1 class="text-muted text-xs font-bold uppercase tracking-widest">Explorar Catálogo</h1>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-3 px-4 py-3 rounded-md bg-surface border-l-2 border-primary cursor-pointer hover:bg-white/5 transition-colors">
                                    <div class="text-primary">
                                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">security</span>
                                    </div>
                                    <p class="text-text-main text-sm font-semibold leading-normal">Segurança e Defesa</p>
                                </div>
                                <div class="flex items-center gap-3 px-4 py-3 rounded-md border-l-2 border-transparent cursor-pointer hover:bg-surface transition-colors">
                                    <div class="text-muted">
                                        <span class="material-symbols-outlined">code</span>
                                    </div>
                                    <p class="text-muted text-sm font-medium leading-normal">Tecnologia & Dev</p>
                                </div>
                                <div class="flex items-center gap-3 px-4 py-3 rounded-md border-l-2 border-transparent cursor-pointer hover:bg-surface transition-colors">
                                    <div class="text-muted">
                                        <span class="material-symbols-outlined">campaign</span>
                                    </div>
                                    <p class="text-muted text-sm font-medium leading-normal">Negócios & Marketing</p>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content Area -->
                        <div class="flex flex-1 flex-col p-6 lg:p-8 overflow-y-auto">
                            
                            <!-- Hero Carousel Area -->
                            <div class="flex overflow-hidden rounded-xl mb-10 w-full relative group">
                                <div class="flex h-[380px] w-full flex-col justify-end bg-center bg-no-repeat bg-cover p-10 relative overflow-hidden transition-transform duration-700 hover:scale-102" style="background-image: linear-gradient(to top, rgba(10, 10, 12, 1) 0%, rgba(10, 10, 12, 0.4) 50%, rgba(10, 10, 12, 0) 100%), url('https://lh3.googleusercontent.com/aida-public/AB6AXuCFxvMNQ91f_nfS0isFpOV1vaeiX2VlZTMX4Y0x7RqqCvPtl6JLOfgOMJ1zlpqNS7yrn-lGdil6HfHk3miu866phRlhp6PszWnsa-RSvWplX-CBskdDJ0VIIyzQ9mIeBH8C_LlsJv25--ClifepKrpcOLoh1DSDZImk86P2ODcTyT1S4pI_61b3T1ai7TT6meVe1VX5DbMVytqO7DLOpN1y3pYRYiT4jQnZsFXSkU2imrbidIlw26R76YUqhq_Ga8jJzihiZc1Ei1Ec');">
                                    <div class="relative z-10 max-w-2xl flex flex-col gap-4">
                                        <h1 class="text-text-main text-3xl md:text-4xl font-heading font-semibold leading-tight tracking-tight uppercase">Plataforma Híbrida de Elite</h1>
                                        <p class="text-text-main/80 text-sm md:text-base font-normal leading-relaxed mb-4">Alcance o próximo nível operacional. Cursos de alta segurança física, inteligência digital, marketing de escala e tecnologia estratégica.</p>
                                        <div class="flex gap-4">
                                            <a href="#catalogo" class="flex min-w-[140px] items-center justify-center rounded h-12 px-6 bg-primary text-background-dark text-[13px] font-bold tracking-[0.05em] uppercase hover:bg-secondary hover:shadow-glow transition-all duration-300">
                                                <span class="truncate">Explorar Catálogo</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Title -->
                            <h2 id="catalogo" class="text-text-main text-xl font-heading font-semibold mb-6 uppercase tracking-wider">Cursos Disponíveis</h2>
                            
                            <!-- Grid Dinâmico -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php if (!empty($courses)): ?>
                                    <?php foreach ($courses as $course): ?>
                                        <a href="course_details.php?id=<?php echo $course['id']; ?>" class="group flex flex-col gap-3 cursor-pointer">
                                            <div class="relative bg-cover bg-center rounded-lg aspect-video overflow-hidden border border-border-color transition-transform duration-300 group-hover:scale-[1.03] group-hover:border-primary/50 group-hover:shadow-glow" style="background-image: url('<?php echo $course['thumbnail_url'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOZxoS-KaOHz2AQP_l-4pCnAOU55dDkFGrPU1UWvoYfvguKBjWVSTpGWkrosgpc5tAulMSWltO9FEY_pPGWgXIfJSk3nDGa5Sln93zKm49t0cfx3Rt41EpQmF0oZA7nVtIAsObChnhjSwTCqnSr2bGJfedSqdorO8A6LPiwU6Bzh57MN4fFHkKkFqbp5n1YBlJoOQrhpxl6yUFhz_gymvmJPCHnFCBE487_7b-yyGcSpGHu_NNTksWusxyIRG87m9YbpHDk5klzSnG'; ?>');">
                                                <div class="absolute inset-0 bg-gradient-to-t from-background-dark via-background-dark/20 to-transparent opacity-80"></div>
                                                <div class="absolute top-3 right-3 bg-secondary text-background-dark text-[10px] font-bold px-2 py-1 rounded-sm uppercase tracking-wider shadow-glow">
                                                    <?php echo strtoupper($course['type']); ?>
                                                </div>
                                                <!-- Hover Play Overlay -->
                                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/40 backdrop-blur-sm">
                                                    <div class="w-12 h-12 rounded-full border-2 border-primary flex items-center justify-center text-primary bg-background-dark/80">
                                                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">visibility</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col mt-2">
                                                <h3 class="text-text-main text-base font-bold leading-tight line-clamp-1 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                <p class="text-muted text-xs mt-1">R$ <?php echo number_format($course['price'], 2, ',', '.'); ?></p>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-span-full py-12 text-center text-muted">
                                        Nenhum curso ativo disponível no momento.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
