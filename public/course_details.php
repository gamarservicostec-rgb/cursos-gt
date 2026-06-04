<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/Database.php';
// Garante migração de campos adicionais
require_once __DIR__ . '/../database/add_course_fields.php';

AppConfig::startSession();

$courseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$courseId) {
    header("Location: index.php");
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca detalhes do curso
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute([':id' => $courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        header("Location: index.php");
        exit;
    }

    // 2. Verifica se o aluno logado já está matriculado
    $isEnrolled = false;
    $firstLessonId = null;
    if (isset($_SESSION['user_id'])) {
        $enrollStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = :uid AND course_id = :cid AND status = 'active' LIMIT 1");
        $enrollStmt->execute([
            ':uid' => $_SESSION['user_id'],
            ':cid' => $courseId
        ]);
        if ($enrollStmt->fetch()) {
            $isEnrolled = true;
        }
    }

    // 3. Busca Módulos, Matérias e Aulas organizados
    $modulesStmt = $db->prepare("SELECT * FROM modules WHERE course_id = :cid ORDER BY sort_order ASC, id ASC");
    $modulesStmt->execute([':cid' => $courseId]);
    $modules = $modulesStmt->fetchAll();

    foreach ($modules as $key => $module) {
        // Busca matérias do módulo
        $subStmt = $db->prepare("SELECT * FROM subjects WHERE module_id = :mid ORDER BY sort_order ASC, id ASC");
        $subStmt->execute([':mid' => $module['id']]);
        $subjects = $subStmt->fetchAll();

        foreach ($subjects as $sKey => $subject) {
            // Busca aulas da matéria
            $lesStmt = $db->prepare("SELECT id, title, duration FROM lessons WHERE subject_id = :sid ORDER BY sort_order ASC, id ASC");
            $lesStmt->execute([':sid' => $subject['id']]);
            $lessons = $lesStmt->fetchAll();
            $subjects[$sKey]['lessons'] = $lessons;

            if (empty($firstLessonId) && !empty($lessons)) {
                $firstLessonId = $lessons[0]['id'];
            }
        }
        $modules[$key]['subjects'] = $subjects;
    }

} catch (\PDOException $e) {
    die("Erro ao carregar detalhes do curso.");
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?> — Cursos GT</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f1c84b",
                        "background-light": "#f8f8f6",
                        "background-dark": "#0A0A0C",
                        "surface": "rgba(20, 20, 23, 0.7)",
                        "border-color": "rgba(255, 255, 255, 0.05)",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.5rem", "lg": "1rem", "xl": "1.5rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0A0A0C;
            color: #EAEAEA;
        }
        .glass-panel {
            background-color: rgba(20, 20, 23, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .glow-primary {
            box-shadow: 0 4px 24px rgba(241, 200, 75, 0.15);
        }
        .glow-primary:hover {
            box-shadow: 0 4px 32px rgba(241, 200, 75, 0.25);
        }
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
    </style>
</head>
<body class="antialiased min-h-screen overflow-x-hidden selection:bg-primary selection:text-black">
    <div class="relative flex h-auto w-full flex-col bg-[#0A0A0C] group/design-root overflow-x-hidden">
        
        <!-- Header -->
        <header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-[#2A2A35] px-6 lg:px-10 py-3 glass-panel sticky top-0 z-50">
            <div class="flex items-center gap-3 text-white">
                <a href="index.php" class="flex items-center gap-3 font-display text-2xl font-bold tracking-widest uppercase">
                    <img src="assets/images/logo.png" alt="Logo GT Cursos" onerror="this.style.display='none'" class="h-10 w-auto object-contain">
                    CURSOS <span class="text-primary">GT</span>
                </a>
            </div>
            <div class="flex flex-1 justify-end gap-8 items-center">
                <div class="hidden md:flex items-center gap-9">
                    <a class="text-[#8F8F9D] text-sm font-medium leading-normal hover:text-white transition-colors" href="index.php">Catálogo</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a class="text-[#8F8F9D] text-sm font-medium leading-normal hover:text-white transition-colors" href="dashboard/index.php">Meus Cursos</a>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard/index.php" class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-[#0A0A0C] text-sm font-bold transition-transform hover:scale-105">
                        Painel Aluno
                    </a>
                <?php else: ?>
                    <a href="login.php" class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-[#0A0A0C] text-sm font-bold transition-transform hover:scale-105">
                        Entrar
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Layout -->
        <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-8 lg:py-12 w-full">
            <!-- Breadcrumb -->
            <div class="mb-8 flex items-center gap-2 text-sm text-[#8F8F9D]">
                <a class="hover:text-primary transition-colors flex items-center gap-1" href="index.php">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Catálogo
                </a>
                <span>/</span>
                <span class="text-[#EAEAEA]"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                
                <!-- Left Column -->
                <div class="lg:col-span-8 space-y-10">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel border-primary/30 bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider mb-2">
                            <span class="size-2 rounded-full bg-primary animate-pulse"></span>
                            Treinamento Híbrido Elite
                        </div>
                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.1] text-white">
                            <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h1>
                        <p class="text-base sm:text-lg text-[#8F8F9D] max-w-2xl leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')); ?>
                        </p>
                    </div>

                    <!-- Trailer / Banner Cover -->
                    <div class="relative aspect-video rounded-xl overflow-hidden glass-panel group border-[#2A2A35]">
                        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?php echo $course['thumbnail_url'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuC_x2hsA7_NrkCVPOoGECxCmVkM_hxQRugSZ1TBA0ZkSKrl8ZB2thIuQnbHspAuuN4DV4saZ7MXElBUV4j4NetP42AQcaqvoLrWB1a39iSx_Y-rWZuD20ilQUe3qRBJfS2rRrnX_sVB_PXtosmKvLxGdcfqH13WG4qHGD5hoxXRgGdrPp-q7ZRFHw1jYFbyIsLae26LADOHQht28dLfL__HCrx2ohkHyZsUey1T_4fPeREJkwse44vT_wf4T2x8Q5fpPxVaCFBExgLm'; ?>');"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0C] via-transparent to-transparent opacity-80"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <button class="flex items-center justify-center rounded-full size-16 bg-primary/95 text-[#0A0A0C] shadow-[0_0_30px_rgba(241,200,75,0.4)] transition-all duration-300 group-hover:scale-105">
                                <span class="material-symbols-outlined text-[28px] ml-1">play_arrow</span>
                            </button>
                        </div>
                    </div>

                    <!-- O que você vai aprender / Material / Bônus (apenas EAD) -->
                    <?php if ($course['type'] === 'ead'): ?>

                    <?php
                    $learnItems = [];
                    if (!empty($course['what_learn'])) {
                        $lines = explode("\n", str_replace("\r", "", $course['what_learn']));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            $parts = explode("|", $line);
                            $title = trim($parts[0] ?? '');
                            $desc  = trim($parts[1] ?? '');
                            if (!empty($title)) {
                                $learnItems[] = ['title' => $title, 'desc' => $desc];
                            }
                        }
                    }
                    ?>
                    <?php if (!empty($learnItems)): ?>
                    <div class="space-y-6 pt-4">
                        <h3 class="text-xl font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">school</span>
                            O que você vai aprender
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($learnItems as $item): ?>
                                <div class="glass-panel p-6 rounded-lg flex gap-4">
                                    <div class="shrink-0 text-primary">
                                        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">verified</span>
                                    </div>
                                    <div>
                                        <h4 class="text-white font-bold mb-1"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <?php if (!empty($item['desc'])): ?>
                                            <p class="text-sm text-[#8F8F9D]"><?php echo htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Material Didático
                    $materialItems = [];
                    if (!empty($course['materials_included'])) {
                        $lines = explode("\n", str_replace("\r", "", $course['materials_included']));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) $materialItems[] = $line;
                        }
                    }
                    ?>
                    <?php if (!empty($materialItems)): ?>
                    <div class="space-y-4 pt-4">
                        <h3 class="text-xl font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">menu_book</span>
                            Material Didático
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($materialItems as $mat): ?>
                                <div class="glass-panel p-4 rounded-lg flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings:'FILL' 1;">library_books</span>
                                    <span class="text-sm text-[#F5F5F7]"><?php echo htmlspecialchars($mat, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Bônus
                    $bonusItems = [];
                    if (!empty($course['bonus'])) {
                        $lines = explode("\n", str_replace("\r", "", $course['bonus']));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) $bonusItems[] = $line;
                        }
                    }
                    ?>
                    <?php if (!empty($bonusItems)): ?>
                    <div class="space-y-4 pt-4">
                        <h3 class="text-xl font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">card_giftcard</span>
                            Bônus Exclusivos
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($bonusItems as $bonus): ?>
                                <div class="glass-panel p-4 rounded-lg flex items-center gap-3 border border-primary/20">
                                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings:'FILL' 1;">star</span>
                                    <span class="text-sm text-[#F5F5F7] font-medium"><?php echo htmlspecialchars($bonus, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Público Alvo
                    $targetItems = [];
                    if (!empty($course['target_audience'])) {
                        $lines = explode("\n", str_replace("\r", "", $course['target_audience']));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) $targetItems[] = $line;
                        }
                    }
                    ?>
                    <?php if (!empty($targetItems)): ?>
                    <div class="space-y-4 pt-4">
                        <h3 class="text-xl font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">people</span>
                            Para Quem é Este Curso
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($targetItems as $target): ?>
                                <div class="glass-panel p-4 rounded-lg flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings:'FILL' 1;">person_check</span>
                                    <span class="text-sm text-[#F5F5F7]"><?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endif; // fim type === ead ?>
                </div>

                <!-- Right Sticky Card -->
                <div class="lg:col-span-4 relative">
                    <div class="sticky top-24 space-y-6">
                        
                        <!-- CTA Price Card -->
                        <div class="glass-panel rounded-xl p-6 sm:p-8 flex flex-col gap-6 shadow-2xl relative overflow-hidden">
                            <div class="absolute -top-24 -right-24 size-48 bg-primary/20 blur-[80px] rounded-full pointer-events-none"></div>
                            
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2 text-[#8F8F9D] line-through text-base">
                                    R$ <?php echo number_format($course['price'] * 1.5, 2, ',', '.'); ?>
                                </div>
                                <div class="flex items-end gap-2 text-white">
                                    <span class="text-3xl font-extrabold tracking-tight">R$ <?php echo number_format($course['price'], 2, ',', '.'); ?></span>
                                </div>
                                <p class="text-xs text-[#00C853] font-bold flex items-center gap-1 mt-1 uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    Em até 12x no cartão
                                </p>
                            </div>

                            <!-- CTA Button -->
                            <?php if ($isEnrolled): ?>
                                <a href="dashboard/classroom.php?lesson_id=<?php echo $firstLessonId; ?>" class="w-full h-14 bg-primary text-[#0A0A0C] rounded-lg font-bold text-[14px] uppercase tracking-widest glow-primary flex items-center justify-center gap-2 hover:-translate-y-1 transition-all duration-300">
                                    <span class="material-symbols-outlined text-[20px]">school</span>
                                    Estudar Agora
                                </a>
                            <?php elseif (!empty($course['is_private'])): ?>
                                <!-- Curso Privado: botão Em Breve -->
                                <button onclick="openLaunchModal()" class="w-full h-14 relative overflow-hidden rounded-lg font-bold text-[14px] uppercase tracking-widest flex items-center justify-center gap-2 transition-all duration-300 group" style="background: linear-gradient(135deg, #1a1a20 0%, #252530 100%); border: 1px solid rgba(241,200,75,0.3);">
                                    <div class="absolute inset-0 bg-gradient-to-r from-primary/0 via-primary/10 to-primary/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
                                    <span class="material-symbols-outlined text-primary text-[20px]" style="font-variation-settings:'FILL' 1;">schedule</span>
                                    <span class="text-primary">Em Breve</span>
                                    <span class="absolute top-1.5 right-2 text-[8px] font-bold text-primary/60 uppercase tracking-[0.2em] bg-primary/10 px-2 py-0.5 rounded-full">Lançamento</span>
                                </button>
                            <?php else: ?>
                                <a href="checkout.php?id=<?php echo $course['id']; ?>" class="w-full h-14 bg-primary text-[#0A0A0C] rounded-lg font-bold text-[14px] uppercase tracking-widest glow-primary flex items-center justify-center gap-2 hover:-translate-y-1 transition-all duration-300">
                                    <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                                    Garantir Minha Vaga
                                </a>
                            <?php endif; ?>

                            <!-- Dynamic materials_included and resources -->
                            <?php
                            $materialItems = [];
                            if (!empty($course['materials_included'])) {
                                $lines = explode("\n", str_replace("\r", "", $course['materials_included']));
                                foreach ($lines as $line) {
                                    $line = trim($line);
                                    if (!empty($line)) {
                                        $materialItems[] = $line;
                                    }
                                }
                            }
                            ?>
                            <?php if (!empty($materialItems)): ?>
                                <div class="flex flex-col gap-3 text-xs text-[#8F8F9D] pt-4 border-t border-[#2A2A35]">
                                    <?php foreach ($materialItems as $mat): ?>
                                        <div class="flex items-center gap-3">
                                            <span class="material-symbols-outlined text-[18px] text-white">check_circle</span>
                                            <span><?php echo htmlspecialchars($mat, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Course Syllabus -->
                        <div class="glass-panel rounded-xl p-6 shadow-xl">
                            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2 uppercase tracking-wide">
                                <span class="material-symbols-outlined text-primary">menu_book</span>
                                Ementa do Curso
                            </h3>
                            
                            <div class="space-y-3">
                                <?php if (!empty($modules)): ?>
                                    <?php foreach ($modules as $mIndex => $mod): ?>
                                        <div class="border border-[#2A2A35] rounded-lg bg-[#141417] overflow-hidden">
                                            <!-- Cabeçalho do Accordion interativo -->
                                            <button onclick="toggleModuleSyllabus(<?php echo $mod['id']; ?>)" class="w-full flex items-center justify-between p-4 text-left focus:outline-none hover:bg-white/[0.02] transition-colors">
                                                <div>
                                                    <span class="text-[10px] text-primary font-bold uppercase tracking-wider mb-1 block">Módulo <?php echo $mIndex + 1; ?></span>
                                                    <h4 class="text-white font-bold text-sm flex items-center gap-1.5">
                                                        <?php echo htmlspecialchars($mod['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if (!$isEnrolled): ?>
                                                            <span class="material-symbols-outlined text-xs text-[#8F8F9D]" title="Conteúdo Restrito">lock</span>
                                                        <?php endif; ?>
                                                    </h4>
                                                </div>
                                                <span id="modArrow-<?php echo $mod['id']; ?>" class="material-symbols-outlined text-primary text-[20px] transition-transform duration-200 <?php echo ($mIndex === 0) ? 'rotate-180' : ''; ?>">expand_more</span>
                                            </button>
                                            <!-- Conteúdo do Módulo -->
                                            <div id="modBody-<?php echo $mod['id']; ?>" class="px-4 pb-4 pt-1 space-y-2 border-t border-[#2A2A35]/50 bg-[#0A0A0C]/50 transition-all <?php echo ($mIndex === 0) ? '' : 'hidden'; ?>">
                                                <?php if (!empty($mod['subjects'])): ?>
                                                    <?php foreach ($mod['subjects'] as $sub): ?>
                                                        <!-- Materia Header Accordion -->
                                                        <button onclick="toggleSubjectSyllabus(<?php echo $sub['id']; ?>)" class="w-full py-2 px-3 rounded-lg flex items-center justify-between hover:bg-white/[0.03] text-left transition-colors border border-white/5 hover:border-primary/20 bg-white/[0.01] mt-2 mb-1">
                                                            <div class="flex items-center gap-2">
                                                                <span class="material-symbols-outlined text-primary text-[14px]">folder_open</span>
                                                                <span class="text-xs font-bold text-[#EAEAEA] uppercase tracking-wide"><?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php if (!$isEnrolled): ?>
                                                                    <span class="material-symbols-outlined text-xs text-[#8F8F9D]" title="Conteúdo Restrito">lock</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span id="subArrow-<?php echo $sub['id']; ?>" class="material-symbols-outlined text-muted text-sm transition-transform">expand_more</span>
                                                        </button>

                                                        <!-- Aulas da Materia -->
                                                        <div id="subBody-<?php echo $sub['id']; ?>" class="hidden pl-2 pr-1 py-1 space-y-1">
                                                            <?php if (!empty($sub['lessons'])): ?>
                                                                <?php foreach ($sub['lessons'] as $les): ?>
                                                                    <div class="flex items-center justify-between py-1.5 text-xs">
                                                                        <div class="flex items-center gap-2 text-[#EAEAEA]">
                                                                            <?php if ($isEnrolled): ?>
                                                                                <span class="material-symbols-outlined text-[16px] text-primary">play_circle</span>
                                                                            <?php else: ?>
                                                                                <span class="material-symbols-outlined text-[16px] text-[#8F8F9D]" title="Matricule-se para desbloquear">lock</span>
                                                                            <?php endif; ?>
                                                                            <span><?php echo htmlspecialchars($les['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                        </div>
                                                                        <span class="text-[#8F8F9D]"><?php echo floor($les['duration'] / 60); ?>m</span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <p class="text-[10px] text-[#8F8F9D] pl-6 py-1">Nenhuma aula cadastrada nesta matéria.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-xs text-muted">Aulas sendo carregadas.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-xs text-muted">Estrutura de módulos indisponível.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script de Accordion -->
    <script>
        function toggleModuleSyllabus(modId) {
            const body = document.getElementById(`modBody-${modId}`);
            const arrow = document.getElementById(`modArrow-${modId}`);
            if (body) {
                body.classList.toggle('hidden');
            }
            if (arrow) {
                arrow.classList.toggle('rotate-180');
            }
        }
        function toggleSubjectSyllabus(subId) {
            const body = document.getElementById(`subBody-${subId}`);
            const arrow = document.getElementById(`subArrow-${subId}`);
            if (body) {
                body.classList.toggle('hidden');
            }
            if (arrow) {
                arrow.classList.toggle('rotate-180');
            }
        }
    </script>

    <?php if (!empty($course['is_private'])): ?>
    <!-- Modal "Em Breve — Lançamento" -->
    <div id="launchModal"
         class="fixed inset-0 z-[999] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-500"
         style="backdrop-filter: blur(0px);">

        <!-- Overlay -->
        <div onclick="closeLaunchModal()" class="absolute inset-0 bg-black/80"></div>

        <!-- Card do Modal -->
        <div id="launchCard"
             class="relative z-10 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl scale-90 transition-transform duration-500"
             style="background: linear-gradient(145deg, #111116 0%, #1a1a22 100%); border: 1px solid rgba(241,200,75,0.15);">

            <!-- Brilho decorativo de topo -->
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-64 h-64 bg-primary/15 blur-[80px] rounded-full pointer-events-none"></div>

            <!-- Conteúdo -->
            <div class="relative flex flex-col items-center text-center px-8 py-12 gap-6">

                <!-- Anel decorativo animado -->
                <div class="relative flex items-center justify-center">
                    <div class="absolute size-28 rounded-full border border-primary/20 animate-ping" style="animation-duration: 2.5s;"></div>
                    <div class="absolute size-24 rounded-full border border-primary/30"></div>
                    <div class="size-20 rounded-full flex items-center justify-center"
                         style="background: radial-gradient(circle, rgba(241,200,75,0.12) 0%, transparent 70%); border: 1px solid rgba(241,200,75,0.25);">
                        <span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings:'FILL' 1;">rocket_launch</span>
                    </div>
                </div>

                <!-- Badge -->
                <div class="flex items-center gap-2 px-4 py-1.5 rounded-full"
                     style="background: rgba(241,200,75,0.08); border: 1px solid rgba(241,200,75,0.2);">
                    <span class="size-1.5 rounded-full bg-primary animate-pulse"></span>
                    <span class="text-[10px] font-bold text-primary uppercase tracking-[0.2em]">Lançamento em Breve</span>
                </div>

                <!-- Título -->
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold text-white leading-tight">
                        Este curso está<br>
                        <span class="text-primary">chegando em breve!</span>
                    </h2>
                    <p class="text-sm text-[#8F8F9D] leading-relaxed max-w-xs mx-auto">
                        Estamos preparando algo incrível para você. Em breve abriremos as inscrições para este treinamento exclusivo.
                    </p>
                </div>

                <!-- Nome do curso -->
                <div class="w-full px-4 py-3 rounded-lg text-center"
                     style="background: rgba(241,200,75,0.05); border: 1px solid rgba(241,200,75,0.1);">
                    <p class="text-xs text-primary/70 uppercase tracking-widest mb-1 font-bold">Curso</p>
                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <!-- Botão Voltar -->
                <button onclick="closeLaunchModal()"
                        class="w-full h-12 rounded-xl font-bold text-sm uppercase tracking-widest transition-all duration-300 hover:-translate-y-0.5 flex items-center justify-center gap-2"
                        style="background: linear-gradient(135deg, rgba(241,200,75,0.1) 0%, rgba(241,200,75,0.05) 100%); border: 1px solid rgba(241,200,75,0.25); color: #F1C84B;">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Voltar à Página de Vendas
                </button>

            </div>
        </div>
    </div>

    <style>
        #launchModal.modal-open {
            opacity: 1;
            pointer-events: all;
            backdrop-filter: blur(12px);
        }
        #launchModal.modal-open #launchCard {
            transform: scale(1);
        }
    </style>

    <script>
        function openLaunchModal() {
            document.getElementById('launchModal').classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
        function closeLaunchModal() {
            document.getElementById('launchModal').classList.remove('modal-open');
            document.body.style.overflow = '';
        }
        // Fechar com Esc
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeLaunchModal();
        });
    </script>
    <?php endif; ?>

</body>
</html>
