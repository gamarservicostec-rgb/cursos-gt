<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer que o aluno esteja logado
\Middleware\AuthMiddleware::requireStudent();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$lessonId = isset($_GET['lesson_id']) ? filter_var($_GET['lesson_id'], FILTER_VALIDATE_INT) : null;

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Identifica o curso ativo do aluno para carregar a barra lateral
    $enrollStmt = $db->prepare("SELECT course_id FROM enrollments WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $enrollStmt->execute([':user_id' => $userId]);
    $courseId = $enrollStmt->fetchColumn();

    if (!$courseId) {
        die("<h1>Acesso restrito</h1><p>Você precisa possuir uma matrícula ativa em um curso para acessar a sala de aula.</p><a href='../index.php'>Voltar</a>");
    }

    // 2. Se não houver lesson_id, redireciona para a primeira aula ordenada do curso
    if (!$lessonId) {
        $firstLessonStmt = $db->prepare("
            SELECT l.id 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            WHERE m.course_id = :course_id
            ORDER BY m.sort_order ASC, s.sort_order ASC, l.sort_order ASC
            LIMIT 1
        ");
        $firstLessonStmt->execute([':course_id' => $courseId]);
        $lessonId = $firstLessonStmt->fetchColumn();

        if (!$lessonId) {
            die("<h1>Grade vazia</h1><p>Este curso ainda não possui aulas cadastradas.</p><a href='index.php'>Voltar ao Painel</a>");
        }

        header("Location: classroom.php?lesson_id=" . $lessonId);
        exit;
    }

    // 3. Busca detalhes da aula atual
    $lessonStmt = $db->prepare("SELECT id, title, description, video_provider, video_url, duration, attachment_url FROM lessons WHERE id = :id LIMIT 1");
    $lessonStmt->execute([':id' => $lessonId]);
    $lesson = $lessonStmt->fetch();

    if (!$lesson) {
        die("<h1>Aula não encontrada</h1><p>A aula solicitada não foi encontrada no catálogo.</p><a href='index.php'>Voltar ao Painel</a>");
    }

    // 4. Busca os módulos e aulas do curso para renderizar a barra lateral
    $modQuery = "SELECT id, title FROM modules WHERE course_id = :course_id ORDER BY sort_order ASC";
    $mStmt = $db->prepare($modQuery);
    $mStmt->execute([':course_id' => $courseId]);
    $modules = $mStmt->fetchAll();

    $sidebarSyllabus = [];
    foreach ($modules as $m) {
        $subQuery = "SELECT id, title FROM subjects WHERE module_id = :module_id ORDER BY sort_order ASC";
        $sStmt = $db->prepare($subQuery);
        $sStmt->execute([':module_id' => $m['id']]);
        $subjects = $sStmt->fetchAll();

        $formattedSubjects = [];
        foreach ($subjects as $s) {
            $lessQuery = "
                SELECT l.id, l.title, l.duration, COALESCE(lp.completed, 0) as completed 
                FROM lessons l
                LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :user_id
                WHERE l.subject_id = :subject_id
                ORDER BY l.sort_order ASC
            ";
            $lStmt = $db->prepare($lessQuery);
            $lStmt->execute([
                ':subject_id' => $s['id'],
                ':user_id' => $userId
            ]);
            $lessons = $lStmt->fetchAll();

            $formattedSubjects[] = [
                'id' => (int)$s['id'],
                'title' => htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'),
                'lessons' => array_map(function($l) {
                    return [
                        'id' => (int)$l['id'],
                        'title' => htmlspecialchars($l['title'], ENT_QUOTES, 'UTF-8'),
                        'duration' => (int)$l['duration'],
                        'completed' => (bool)$l['completed']
                    ];
                }, $lessons)
            ];
        }

        $sidebarSyllabus[] = [
            'id' => (int)$m['id'],
            'title' => htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8'),
            'subjects' => $formattedSubjects
        ];
    }

    // 5. Cálculo de progresso dinâmico do aluno
    $totalLessonsStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        JOIN modules m ON s.module_id = m.id
        WHERE m.course_id = :course_id
    ");
    $totalLessonsStmt->execute([':course_id' => $courseId]);
    $totalLessons = (int)$totalLessonsStmt->fetchColumn();

    $completedLessonsStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.id
        JOIN subjects s ON l.subject_id = s.id
        JOIN modules m ON s.module_id = m.id
        WHERE m.course_id = :course_id AND lp.user_id = :user_id AND lp.completed = 1
    ");
    $completedLessonsStmt->execute([
        ':course_id' => $courseId,
        ':user_id' => $userId
    ]);
    $completedLessons = (int)$completedLessonsStmt->fetchColumn();

    $progressPercentage = ($totalLessons > 0) ? round(($completedLessons / $totalLessons) * 100) : 0;

    // Busca nome do curso ativo para exibir no cabeçalho
    $courseTitleStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
    $courseTitleStmt->execute([':id' => $courseId]);
    $courseTitle = $courseTitleStmt->fetchColumn();

    // 6. Busca se o curso possui uma prova técnica (Quiz) cadastrada
    $courseQuizStmt = $db->prepare("SELECT id, title, min_score FROM quizzes WHERE course_id = :course_id LIMIT 1");
    $courseQuizStmt->execute([':course_id' => $courseId]);
    $courseQuiz = $courseQuizStmt->fetch();

    $quizStatus = null; // 'not_attempted', 'passed', 'failed'
    $quizAttempt = null;

    if ($courseQuiz) {
        // Busca a última tentativa de prova do aluno
        $attemptQuery = "SELECT score, passed, attempted_at FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id ORDER BY attempted_at DESC LIMIT 1";
        $attemptStmt = $db->prepare($attemptQuery);
        $attemptStmt->execute([
            ':user_id' => $userId,
            ':quiz_id' => $courseQuiz['id']
        ]);
        $quizAttempt = $attemptStmt->fetch();

        if ($quizAttempt) {
            $quizStatus = $quizAttempt['passed'] ? 'passed' : 'failed';
        } else {
            $quizStatus = 'not_attempted';
        }
    }

} catch (\PDOException $e) {
    die("Erro de banco de dados na sala de aula: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8'); ?> — Sala de Aula Cinematográfica</title>
    <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&amp;family=Outfit:wght@300;400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#F2C94C",
                        "secondary": "#FFD700",
                        "background": "#0A0A0C",
                        "surface": "#141417",
                        "text": "#EAEAEA",
                        "muted": "#8F8F9D",
                        "border": "#2A2A35",
                        "gold-border": "rgba(242, 201, 76, 0.2)"
                    },
                    fontFamily: {
                        "display": ["Clash Display", "sans-serif"],
                        "body": ["Outfit", "sans-serif"],
                        "sans": ["Plus Jakarta Sans", "sans-serif"]
                    },
                    backdropBlur: {
                        "md": "12px",
                        "xl": "24px"
                    }
                }
            }
        }
    </script>
    <style>
        .glass-sidebar {
            background: rgba(10, 10, 12, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .gold-glow {
            box-shadow: 0 0 20px rgba(242, 201, 76, 0.1);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(242, 201, 76, 0.2);
            border-radius: 10px;
        }

        .tab-active {
            color: #F2C94C;
            border-bottom: 2px solid #F2C94C;
        }

        .video-aspect-ratio {
            aspect-ratio: 16 / 9;
        }

        .comment-input, .note-input {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #EAEAEA !important;
            transition: all 0.3s ease;
        }

        .comment-input:focus, .note-input:focus {
            border-color: #F2C94C !important;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.15);
            outline: none;
        }
    </style>
</head>
<body class="bg-background text-text font-body h-screen w-full overflow-hidden flex flex-col selection:bg-primary selection:text-background">
    <!-- Header -->
    <header class="h-16 w-full border-b border-white/5 flex items-center justify-between px-6 shrink-0 z-50 bg-background/50 backdrop-blur-md">
        <div class="flex items-center gap-8">
            <a href="index.php" class="font-display text-xl tracking-wider text-white hover:text-primary transition-colors">
                CURSOS <span class="text-primary">GT</span>
            </a>
            <div class="h-6 w-[1px] bg-white/10"></div>
            <h1 class="text-xs font-semibold tracking-wide text-text/80 uppercase truncate max-w-xs md:max-w-md">
                <?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
        </div>
        <div class="flex items-center gap-6">
            <a href="index.php" class="flex items-center gap-2 text-xs font-semibold text-muted hover:text-primary transition-colors uppercase tracking-widest border border-white/10 hover:border-primary/40 rounded-full px-4 py-2">
                <span class="material-symbols-outlined text-sm">exit_to_app</span>
                Voltar ao Dashboard
            </a>
            <div class="flex items-center gap-3 pl-4 border-l border-white/10">
                <div class="text-right hidden sm:block">
                    <p class="text-[11px] font-bold text-primary leading-none uppercase">Estudante</p>
                    <p class="text-sm font-medium text-text"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="w-9 h-9 rounded-full bg-surface border border-gold-border overflow-hidden flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar (Left) -->
        <aside class="w-1/4 min-w-[320px] max-w-[400px] glass-sidebar flex flex-col h-full z-40">
            <!-- Progress Section -->
            <div class="p-6 border-b border-white/5 space-y-4">
                <div>
                    <div class="flex justify-between items-end mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-muted">Progresso do Curso</span>
                        <span class="text-lg font-display text-primary"><?php echo $progressPercentage; ?>%</span>
                    </div>
                    <div class="w-full h-1 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-primary gold-glow transition-all duration-1000" style="width: <?php echo $progressPercentage; ?>%;"></div>
                    </div>
                </div>

                <?php if ($courseQuiz && $progressPercentage >= 100): ?>
                    <!-- Quiz Integration when complete -->
                    <div class="pt-2">
                        <?php if ($quizStatus === 'passed'): ?>
                            <div class="p-4 rounded-xl bg-green-500/5 border border-green-500/20 text-xs text-center space-y-2">
                                <div class="flex items-center justify-center gap-1.5 text-green-400 font-bold uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-[16px] fill-1">military_tech</span>
                                    Aprovado no Exame
                                </div>
                                <p class="text-[10px] text-muted leading-relaxed">Você obteve <?php echo $quizAttempt['score']; ?>% de aproveitamento e sua credencial está liberada.</p>
                                <a href="profile.php" class="inline-flex items-center gap-1 text-[10px] font-bold text-primary hover:underline uppercase tracking-wider pt-1">
                                    Ver Certificado
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="p-4 rounded-xl bg-primary/5 border border-primary/20 text-xs text-center space-y-2">
                                <div class="flex items-center justify-center gap-1.5 text-primary font-bold uppercase tracking-wider text-glow">
                                    <span class="material-symbols-outlined text-[16px]">verified</span>
                                    Avaliação Disponível
                                </div>
                                <p class="text-[10px] text-muted leading-relaxed">Parabéns por concluir as aulas! Faça a avaliação final para emitir sua credencial técnica.</p>
                                <a href="quiz.php?id=<?php echo $courseQuiz['id']; ?>" class="w-full bg-primary text-background text-[10px] font-bold py-2.5 px-4 rounded-lg uppercase tracking-wider flex items-center justify-center gap-1.5 hover:shadow-[0_0_15px_rgba(241,200,75,0.25)] transition-all">
                                    <span>Realizar Prova</span>
                                    <span class="material-symbols-outlined text-sm">edit_note</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($courseQuiz): ?>
                    <!-- Quiz locked indicator -->
                    <div class="pt-1 flex items-center justify-between gap-2 px-1 text-[10px] text-muted">
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">lock</span>
                            Avaliação Técnica Final
                        </span>
                        <span class="font-bold uppercase opacity-60">Bloqueado</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Module Hierarchy -->
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <?php 
                $modNum = 1;
                foreach ($sidebarSyllabus as $mod): 
                ?>
                <div class="border-b border-white/5">
                    <button class="w-full p-6 flex items-center justify-between group hover:bg-white/[0.02] transition-colors">
                        <div class="text-left">
                            <span class="text-[10px] font-bold text-primary uppercase tracking-[0.2em] mb-1 block">Módulo <?php echo $modNum++; ?></span>
                            <span class="text-sm font-semibold group-hover:text-primary transition-colors"><?php echo $mod['title']; ?></span>
                        </div>
                        <span class="material-symbols-outlined text-muted text-xl transition-transform group-hover:rotate-180">expand_more</span>
                    </button>
                    <div class="px-3 pb-4 space-y-1">
                        <?php foreach ($mod['subjects'] as $sub): ?>
                            <?php foreach ($sub['lessons'] as $less): ?>
                                <?php 
                                $isActive = ($less['id'] === $lessonId);
                                $isCompleted = $less['completed'];
                                ?>
                                <?php if ($isActive): ?>
                                    <!-- Lesson Active -->
                                    <div class="flex items-center gap-3 p-4 rounded-xl bg-surface border border-gold-border group cursor-pointer relative overflow-hidden">
                                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
                                        <div class="text-primary">
                                            <span class="material-symbols-outlined fill-1 text-xl">play_circle</span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-bold text-primary">REPRODUZINDO</p>
                                            <p class="text-sm font-medium"><?php echo $less['title']; ?></p>
                                        </div>
                                        <span class="text-[10px] font-mono text-muted"><?php echo floor($less['duration'] / 60); ?>m</span>
                                    </div>
                                <?php elseif ($isCompleted): ?>
                                    <!-- Lesson Completed -->
                                    <a href="classroom.php?lesson_id=<?php echo $less['id']; ?>" class="flex items-center gap-3 p-4 rounded-xl hover:bg-white/[0.03] transition-colors group cursor-pointer">
                                        <div class="text-green-500">
                                            <span class="material-symbols-outlined fill-1 text-xl">check_circle</span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium group-hover:text-primary transition-colors"><?php echo $less['title']; ?></p>
                                        </div>
                                        <span class="text-[10px] font-mono text-muted"><?php echo floor($less['duration'] / 60); ?>m</span>
                                    </a>
                                <?php else: ?>
                                    <!-- Lesson Locked/Upcoming -->
                                    <a href="classroom.php?lesson_id=<?php echo $less['id']; ?>" class="flex items-center gap-3 p-4 rounded-xl hover:bg-white/[0.03] transition-colors group cursor-pointer">
                                        <div class="text-muted/40">
                                            <span class="material-symbols-outlined text-xl">play_circle</span>
                                        </div>
                                        <div class="flex-1 opacity-50">
                                            <p class="text-sm font-medium group-hover:text-primary transition-colors"><?php echo $less['title']; ?></p>
                                        </div>
                                        <span class="text-[10px] font-mono text-muted"><?php echo floor($less['duration'] / 60); ?>m</span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content Area (Right) -->
        <main class="flex-1 flex flex-col h-full overflow-y-auto custom-scrollbar bg-background">
            <!-- Video Player Section -->
            <section class="w-full bg-black">
                <div class="max-w-6xl mx-auto px-6 py-8">
                    <div class="video-aspect-ratio relative bg-surface rounded-2xl overflow-hidden border border-white/5 shadow-2xl">
                        <?php if ($lesson['video_url']): ?>
                            <div style="position:relative;width:100%;height:100%;" class="w-full h-full">
                                <iframe src="<?php echo AppConfig::$BUNNY_STREAM_BASE_URL . $lesson['video_url']; ?>?autoplay=true" loading="lazy" style="border:0;position:absolute;top:0;left:0;width:100%;height:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true" id="bunnyPlayer"></iframe>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-12">
                                <span class="material-symbols-outlined text-muted text-6xl mb-3">video_camera_back</span>
                                <h3 class="text-base font-bold text-text">Transmissão Offline</h3>
                                <p class="text-xs text-muted mt-2">Esta aula não possui vídeo indexado no Bunny.net no momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lesson Actions -->
                    <div class="mt-8 flex flex-wrap items-center justify-between gap-6">
                        <div class="flex gap-3">
                            <button class="flex items-center gap-2 px-6 py-3 rounded-full border border-white/10 text-sm font-bold uppercase tracking-widest hover:border-primary/40 transition-colors" id="btnPrevClass">
                                <span class="material-symbols-outlined text-lg">chevron_left</span>
                                Aula Anterior
                            </button>
                            <button class="flex items-center gap-2 px-6 py-3 rounded-full border border-primary text-sm font-bold uppercase tracking-widest text-primary hover:bg-primary/5 transition-colors" id="btnNextClass">
                                Próxima Aula
                                <span class="material-symbols-outlined text-lg">chevron_right</span>
                            </button>
                        </div>
                        <button class="flex items-center gap-2 px-10 py-3 rounded-full bg-primary text-background text-sm font-bold uppercase tracking-widest hover:bg-secondary transition-all gold-glow" id="btnCompleteClass" onclick="markCurrentLessonCompleted()">
                            <span class="material-symbols-outlined text-lg" id="completeIcon">check_circle</span>
                            <span id="completeText">Concluir Aula</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Content Area (Tabs & Info) -->
            <section class="max-w-6xl mx-auto px-6 pb-20 w-full">
                <!-- Tabs -->
                <div class="flex border-b border-white/5 mb-8">
                    <button class="px-6 py-4 text-sm font-bold uppercase tracking-widest tab-btn active" id="tab-btn-desc" onclick="switchTab('desc')">Descrição</button>
                    <button class="px-6 py-4 text-sm font-bold uppercase tracking-widest text-muted hover:text-text transition-colors tab-btn" id="tab-btn-comments" onclick="switchTab('comments')">Comentários</button>
                    <button class="px-6 py-4 text-sm font-bold uppercase tracking-widest text-muted hover:text-text transition-colors tab-btn" id="tab-btn-notes" onclick="switchTab('notes')">Minhas Anotações</button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    <!-- Left Column: Tabs Content (2/3) -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Tab Content: Description -->
                        <div id="tab-desc" class="space-y-6">
                            <h2 class="text-2xl font-display text-text mb-4">O que você aprenderá nesta aula</h2>
                            <p class="text-muted leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($lesson['description'], ENT_QUOTES, 'UTF-8')); ?>
                            </p>
                        </div>

                        <!-- Tab Content: Comments -->
                        <div id="tab-comments" class="hidden space-y-6">
                            <h3 class="text-xl font-display mb-6">Dúvidas da Aula</h3>
                            <div class="flex gap-4 mb-8">
                                <div class="w-10 h-10 rounded-full bg-surface shrink-0 flex items-center justify-center border border-white/10">
                                    <span class="material-symbols-outlined text-primary text-xl">person</span>
                                </div>
                                <div class="flex-1 bg-surface border border-white/5 rounded-2xl p-4">
                                    <textarea class="w-full bg-transparent border-none focus:ring-0 text-sm resize-none comment-input rounded p-2" id="commentText" placeholder="Escreva sua dúvida ou comentário..." rows="3"></textarea>
                                    <div class="flex justify-end mt-2">
                                        <button class="px-4 py-2 bg-primary/10 text-primary text-[11px] font-bold uppercase rounded-lg hover:bg-primary/20 transition-colors" onclick="submitMainComment()">Publicar Comentário</button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6" id="commentsContainer">
                                <div class="text-center py-6 text-muted text-xs">Carregando discussões da aula...</div>
                            </div>
                        </div>

                        <!-- Tab Content: Notes -->
                        <div id="tab-notes" class="hidden space-y-6">
                            <h3 class="text-xl font-display mb-6 font-bold uppercase tracking-wider">Caderno de Estudos Privado</h3>
                            <div class="flex gap-4 mb-8">
                                <div class="flex-1 bg-surface border border-white/5 rounded-2xl p-4 space-y-4">
                                    <textarea class="w-full bg-transparent border-none focus:ring-0 text-sm resize-none note-input rounded p-2" id="noteText" placeholder="Escreva sua anotação particular de estudos..." rows="3"></textarea>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-[10px] font-bold text-primary uppercase tracking-wider" id="noteTimeDisplay">Timestamp atual: 00:00</span>
                                        <button class="px-4 py-2 bg-primary text-background text-[11px] font-bold uppercase rounded-lg hover:bg-secondary transition-colors" onclick="submitPersonalNote()">Salvar Anotação</button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4" id="notesContainer">
                                <div class="text-center py-6 text-muted text-xs">Carregando anotações...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Materials & Support (1/3) -->
                    <div class="space-y-8">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-[0.2em] mb-6 text-primary">Arquivos da Aula</h3>
                            <div class="space-y-3">
                                <?php if ($lesson['attachment_url']): ?>
                                    <a class="flex items-center justify-between p-4 bg-surface border border-white/5 rounded-xl hover:border-primary/40 transition-all group" href="<?php echo htmlspecialchars($lesson['attachment_url'], ENT_QUOTES, 'UTF-8'); ?>" download>
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-red-500/10 flex items-center justify-center text-red-500">
                                                <span class="material-symbols-outlined">picture_as_pdf</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-white">Material Complementar.pdf</p>
                                                <p class="text-[10px] text-muted">Apostila oficial de fixação</p>
                                            </div>
                                        </div>
                                        <span class="material-symbols-outlined text-muted group-hover:text-primary">download</span>
                                    </a>
                                <?php else: ?>
                                    <div class="p-4 bg-surface border border-white/5 rounded-xl text-center text-xs text-muted">
                                        Nenhum material complementar anexado a esta aula.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-6 rounded-2xl bg-gradient-to-br from-primary/10 to-transparent border border-gold-border relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-110 transition-transform duration-700">
                                <span class="material-symbols-outlined text-6xl">school</span>
                            </div>
                            <h4 class="text-lg font-display mb-2">Suporte Prioritário</h4>
                            <p class="text-sm text-muted mb-4 leading-relaxed">Teve alguma dúvida técnica complexa? Fale diretamente com nossos instrutores no canal exclusivo.</p>
                            <a href="../admin/support.php" class="w-full py-3 rounded-xl bg-background border border-gold-border text-[11px] font-bold uppercase tracking-widest text-primary hover:bg-primary hover:text-background transition-all block text-center">Abrir Ticket de Suporte</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Scripts de Integração e Bunny.net API -->
    <script>
        const lessonId = <?php echo $lessonId; ?>;
        const totalDuration = <?php echo $lesson['duration']; ?>;
        let currentTime = 0;
        let videoDuration = totalDuration;

        // --- 1. COMUNICAÇÃO BUNNY.NET EMBED API ---
        window.addEventListener('message', function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.event === 'timeupdate') {
                    currentTime = data.currentTime; // segundos atuais
                    videoDuration = data.duration || totalDuration;
                    
                    // Atualiza o display de anotação
                    updateNoteTimeDisplay(currentTime);
                }
            } catch(err) {}
        });

        function updateNoteTimeDisplay(seconds) {
            const display = document.getElementById('noteTimeDisplay');
            if (display) {
                display.textContent = `Timestamp atual: ${formatTime(seconds)}`;
            }
        }

        function formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = Math.floor(seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }

        // --- 2. SALVAR PROGRESSO AUTOMÁTICO A CADA 10 SEGUNDOS ---
        setInterval(function() {
            if (currentTime > 0) {
                const isCompleted = (currentTime >= (videoDuration - 10)); // Marca como feito faltarem 10s ou menos

                fetch('../api/classroom/progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        lesson_id: lessonId,
                        watched_duration: Math.round(currentTime),
                        completed: isCompleted
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.level_up) {
                        console.log('Parabéns! Nível aumentado!');
                    }
                });
            }
        }, 10000);

        // --- 3. CONTROLE DE TABS ---
        function switchTab(tab) {
            // Esconde todas
            document.getElementById('tab-desc').classList.add('hidden');
            document.getElementById('tab-comments').classList.add('hidden');
            document.getElementById('tab-notes').classList.add('hidden');

            // Remove classe ativa de botões
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active', 'tab-active');
                btn.classList.add('text-muted');
            });

            // Exibe a ativa
            document.getElementById(`tab-${tab}`).classList.remove('hidden');
            
            // Ativa botão
            const activeBtn = document.getElementById(`tab-btn-${tab}`);
            activeBtn.classList.add('active', 'tab-active');
            activeBtn.classList.remove('text-muted');

            // Carrega dinamicamente os dados correspondentes
            if (tab === 'comments') {
                loadComments();
            } else if (tab === 'notes') {
                loadNotes();
            }
        }

        // --- 4. CENTRAL DE COMENTÁRIOS E DÚVIDAS ---
        function loadComments() {
            const container = document.getElementById('commentsContainer');
            
            fetch(`../api/classroom/comments.php?lesson_id=${lessonId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(comments => {
                if (comments.length === 0) {
                    container.innerHTML = `<div class="text-center py-8 text-muted text-xs">Nenhuma dúvida registrada nesta aula. Seja o primeiro a perguntar!</div>`;
                    return;
                }

                let html = '';
                comments.forEach(c => {
                    const avatarStr = c.user.avatar_url ? `<img src="${c.user.avatar_url}" class="w-8 h-8 rounded-full border border-border">` : `<span class="material-symbols-outlined text-primary text-[20px]">person</span>`;
                    const roleBadge = c.user.role === 'admin' ? '<span class="text-[8px] font-bold text-primary border border-primary/20 bg-primary/5 rounded px-2 py-0.5 ml-2 uppercase">Diretoria</span>' : '';
                    
                    html += `
                        <div class="bg-surface rounded-xl p-5 space-y-4 border border-white/5">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0">
                                        ${avatarStr}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-text flex items-center">
                                            ${c.user.name} ${roleBadge}
                                        </div>
                                        <div class="text-[9px] text-muted font-semibold mt-0.5">${c.created_at}</div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-text/90 leading-relaxed font-medium pl-11">${c.comment}</p>
                            
                            <!-- Replies Tree -->
                            <div class="pl-11 space-y-3">
                                ${c.replies.map(r => {
                                    const rAvatar = r.user.avatar_url ? `<img src="${r.user.avatar_url}" class="w-6 h-6 rounded-full">` : `<span class="material-symbols-outlined text-primary text-xs">person</span>`;
                                    const rRoleBadge = r.user.role === 'admin' ? '<span class="text-[8px] font-bold text-primary border border-primary/20 bg-primary/5 rounded px-2 py-0.5 ml-2 uppercase">Diretoria</span>' : '';
                                    return `
                                        <div class="p-3 bg-black/30 border border-white/5 rounded-lg space-y-2">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                    ${rAvatar}
                                                </div>
                                                <div>
                                                    <span class="text-[11px] font-bold text-text">${r.user.name}</span>
                                                    ${rRoleBadge}
                                                    <span class="text-[8px] text-muted pl-2">${r.created_at}</span>
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-text/90 leading-relaxed font-medium pl-8">${r.comment}</p>
                                        </div>
                                    `;
                                }).join('')}
                                
                                <!-- Quick Reply Toggle -->
                                <button class="text-[10px] font-bold text-primary hover:underline uppercase tracking-wider mt-2 block" onclick="toggleReplyInput(${c.id})">Responder</button>
                                
                                <!-- Reply Input Container -->
                                <div class="hidden mt-3" id="reply-box-${c.id}">
                                    <div class="flex gap-2">
                                        <input type="text" class="comment-input flex-grow rounded px-3 py-2 text-xs" id="reply-input-${c.id}" placeholder="Escreva uma resposta tática...">
                                        <button class="px-4 py-2 bg-primary text-background font-bold rounded text-[10px] uppercase" onclick="submitReply(${c.id})">Enviar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            });
        }

        function submitMainComment() {
            const text = document.getElementById('commentText');
            if (!text.value.trim()) return;
            
            fetch('../api/classroom/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    comment: text.value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    text.value = '';
                    loadComments(); // Recarrega
                }
            });
        }

        function toggleReplyInput(commentId) {
            const box = document.getElementById(`reply-box-${commentId}`);
            box.classList.toggle('hidden');
        }

        function submitReply(parentId) {
            const input = document.getElementById(`reply-input-${parentId}`);
            if (!input.value.trim()) return;

            fetch('../api/classroom/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    comment: input.value,
                    parent_id: parentId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    document.getElementById(`reply-box-${parentId}`).classList.add('hidden');
                    loadComments();
                }
            });
        }

        // --- 5. CADERNO DE NOTAS INDIVIDUAIS ---
        function loadNotes() {
            const container = document.getElementById('notesContainer');
            
            fetch(`../api/classroom/notes.php?lesson_id=${lessonId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(notes => {
                if (notes.length === 0) {
                    container.innerHTML = `<div class="text-center py-8 text-muted text-xs">Nenhuma anotação neste treinamento. Guarde anotações para sua revisão rápida!</div>`;
                    return;
                }

                let html = '';
                notes.forEach(n => {
                    html += `
                        <div class="bg-surface rounded-xl p-4 flex justify-between items-center gap-4 border border-white/5 hover:border-primary/20 transition-all">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-xl flex-shrink-0 mt-0.5">bookmark</span>
                                <div>
                                    <span class="text-[10px] font-bold text-primary uppercase tracking-widest cursor-pointer hover:underline" onclick="seekPlayer(${n.video_timestamp})">Nota aos ${formatTime(n.video_timestamp)}</span>
                                    <p class="text-xs text-text leading-relaxed font-semibold mt-1"><?= htmlspecialchars('${n.notes_text}', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <span class="text-[9px] text-muted font-bold">${n.created_at}</span>
                        </div>
                    `;
                });
                container.innerHTML = html;
            });
        }

        function submitPersonalNote() {
            const text = document.getElementById('noteText');
            if (!text.value.trim()) return;
            
            fetch('../api/classroom/notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    notes_text: text.value,
                    video_timestamp: Math.round(currentTime)
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    text.value = '';
                    loadNotes();
                }
            });
        }

        function seekPlayer(seconds) {
            const playerIframe = document.getElementById('bunnyPlayer');
            if (playerIframe) {
                playerIframe.contentWindow.postMessage(JSON.stringify({
                    event: 'seek',
                    value: seconds
                }), '*');
            }
        }

        // --- 6. NAVEGAÇÃO DE AULAS DINÂMICA ---
        const lessonsList = [];
        <?php 
        foreach ($sidebarSyllabus as $mod) {
            foreach ($mod['subjects'] as $sub) {
                foreach ($sub['lessons'] as $less) {
                    echo "lessonsList.push({ id: " . $less['id'] . ", title: '" . addslashes($less['title']) . "' });\n";
                }
            }
        }
        ?>

        const currentIdx = lessonsList.findIndex(l => l.id === lessonId);
        const btnPrev = document.getElementById('btnPrevClass');
        const btnNext = document.getElementById('btnNextClass');

        if (currentIdx > 0) {
            btnPrev.onclick = () => window.location.href = `classroom.php?lesson_id=${lessonsList[currentIdx - 1].id}`;
        } else {
            btnPrev.classList.add('opacity-40', 'cursor-not-allowed');
            btnPrev.disabled = true;
        }

        if (currentIdx < lessonsList.length - 1) {
            btnNext.onclick = () => window.location.href = `classroom.php?lesson_id=${lessonsList[currentIdx + 1].id}`;
        } else {
            btnNext.classList.add('opacity-40', 'cursor-not-allowed');
            btnNext.disabled = true;
        }

        // --- 7. REGISTRO DE CONCLUSÃO IMEDIATA AO CLICAR NO BOTÃO ---
        function markCurrentLessonCompleted() {
            const btn = document.getElementById('btnCompleteClass');
            const text = document.getElementById('completeText');
            const icon = document.getElementById('completeIcon');
            
            text.textContent = 'Processando...';
            
            fetch('../api/classroom/progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    watched_duration: totalDuration,
                    completed: true
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    text.textContent = 'Concluída!';
                    icon.textContent = 'check_circle';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    text.textContent = 'Concluir Aula';
                    alert('Falha ao registrar conclusão.');
                }
            })
            .catch(() => {
                text.textContent = 'Concluir Aula';
                alert('Erro de conexão.');
            });
        }
    </script>
</body>
</html>
