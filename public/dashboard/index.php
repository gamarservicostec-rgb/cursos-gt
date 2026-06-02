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

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca dados gerais do usuário no banco
    $userStmt = $db->prepare("SELECT xp, level, current_streak, avatar_url, last_activity FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();

    $xp = (int)($user['xp'] ?? 0);
    $level = (int)($user['level'] ?? 1);
    $streak = (int)($user['current_streak'] ?? 0);
    $avatarUrl = $user['avatar_url'] ?? null;

    // --- ALGORITMO DE CÁLCULO DE STREAKS DE PRESENÇA ---
    $lastActivityStr = $user['last_activity'] ?? null;
    $currentDate = new DateTime();
    $currentDate->setTime(0, 0, 0); // Zera hora para comparar apenas datas

    $streakUpdated = false;
    $newStreak = $streak;

    if ($lastActivityStr) {
        $lastActivity = new DateTime($lastActivityStr);
        $lastActivity->setTime(0, 0, 0);

        // Diferença em dias
        $diff = $currentDate->diff($lastActivity)->days;

        if ($diff === 1) {
            // A última atividade foi ontem! Incrementa a ofensiva!
            $newStreak = $streak + 1;
            $streakUpdated = true;
        } elseif ($diff > 1) {
            // Quebrou a ofensiva. Reseta para 1.
            $newStreak = 1;
            $streakUpdated = true;
        }
        // Se diff === 0, a última atividade foi hoje. Mantém o streak.
    } else {
        // Primeira atividade
        $newStreak = 1;
        $streakUpdated = true;
    }

    if ($streakUpdated) {
        $db->beginTransaction();

        // Atualiza a ofensiva do usuário no banco de dados e seta last_activity para a data/hora atual
        $updateStreakStmt = $db->prepare("UPDATE users SET current_streak = :streak, last_activity = NOW() WHERE id = :id");
        $updateStreakStmt->execute([
            ':streak' => $newStreak,
            ':id' => $userId
        ]);

        $streak = $newStreak; // atualiza variável local

        // --- VERIFICA DESBLOQUEIO DE CONQUISTA: SOBREVIVENTE (STREAK >= 7) ---
        if ($streak >= 7) {
            // Verifica se o aluno já possui a medalha de streak de 7 dias (ID 2)
            $achCheck = $db->prepare("SELECT id FROM user_achievements WHERE user_id = :user_id AND achievement_id = 2 LIMIT 1");
            $achCheck->execute([':user_id' => $userId]);
            
            if (!$achCheck->fetch()) {
                // Desbloqueia!
                $achInsert = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (:user_id, 2)");
                $achInsert->execute([':user_id' => $userId]);

                // Credita 250 XP
                $xpBonus = 250;
                $newXp = $xp + $xpBonus;
                $newLevel = floor($newXp / 1000) + 1;

                $updateXp = $db->prepare("UPDATE users SET xp = :xp, level = :level WHERE id = :id");
                $updateXp->execute([
                    ':xp' => $newXp,
                    ':level' => $newLevel,
                    ':id' => $userId
                ]);

                // Atualiza variáveis locais
                $xp = $newXp;
                $level = $newLevel;

                // Insere notificação de Medalha conquistada
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Medalha Conquistada! 🎖️', 'Excelente consistência! Você manteve uma ofensiva de 7 dias consecutivos de estudos e desbloqueou a medalha: Sobrevivente!', 'achievement')");
                $notifStmt->execute([':user_id' => $userId]);
            }
        }

        $db->commit();
    }

    // Cálculo do progresso da barra de nível pós cálculo de gamificação
    $xpInCurrentLevel = $xp % 1000;
    $xpProgressPercentage = round(($xpInCurrentLevel / 1000) * 100);

    // --- SINCRONIZAÇÃO AUTOMÁTICA DE MATRÍCULAS DE CURSO BÔNUS ---
    try {
        // Busca IDs dos cursos em que o aluno já está matriculado
        $myEnrollmentsStmt = $db->prepare("SELECT course_id FROM enrollments WHERE user_id = :user_id AND status IN ('active', 'completed')");
        $myEnrollmentsStmt->execute([':user_id' => $userId]);
        $myCourseIds = $myEnrollmentsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($myCourseIds)) {
            // Busca todos os cursos extras cadastrados como bônus para esses cursos
            $inClause = implode(',', array_map('intval', $myCourseIds));
            $bonusCoursesStmt = $db->prepare("SELECT DISTINCT bonus_course_id FROM course_bonuses WHERE course_id IN ($inClause) AND type = 'course' AND bonus_course_id IS NOT NULL");
            $bonusCoursesStmt->execute();
            $bonusCourseIds = $bonusCoursesStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($bonusCourseIds as $bonusId) {
                if (!in_array($bonusId, $myCourseIds)) {
                    // Matricula silenciosa
                    $insStmt = $db->prepare("INSERT IGNORE INTO enrollments (user_id, course_id, status) VALUES (:user_id, :course_id, 'active')");
                    $insStmt->execute([
                        ':user_id' => $userId,
                        ':course_id' => $bonusId
                    ]);
                    
                    // Insere uma notificação parabenizando o aluno pelo bônus recebido!
                    $bonusTitleStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
                    $bonusTitleStmt->execute([':id' => $bonusId]);
                    $bonusTitle = $bonusTitleStmt->fetchColumn();

                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Novo Bônus Liberado! 🎁', :msg, 'success')");
                    $notifStmt->execute([
                        ':user_id' => $userId,
                        ':msg' => "Parabéns! Você ganhou acesso gratuito ao curso '" . $bonusTitle . "' como bônus especial!"
                    ]);
                }
            }
        }
    } catch (\Exception $ex) {
        // Silencioso
    }

    // 2. Busca os cursos matriculados do aluno (agora atualizado com possíveis cursos bônus)
    $coursesStmt = $db->prepare("
        SELECT c.id, c.title, c.description, c.thumbnail_url, e.status as enroll_status 
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = :user_id AND e.status IN ('active', 'completed')
    ");
    $coursesStmt->execute([':user_id' => $userId]);
    $enrolledCourses = $coursesStmt->fetchAll();

    $coursesList = [];
    foreach ($enrolledCourses as $ec) {
        $courseId = $ec['id'];

        // Conta total de aulas no curso
        $totalStmt = $db->prepare("
            SELECT COUNT(l.id) 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            WHERE m.course_id = :course_id
        ");
        $totalStmt->execute([':course_id' => $courseId]);
        $totalLessons = (int)$totalStmt->fetchColumn();

        // Conta aulas concluídas pelo aluno no curso
        $compStmt = $db->prepare("
            SELECT COUNT(lp.id) 
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            WHERE lp.user_id = :user_id AND m.course_id = :course_id AND lp.completed = 1
        ");
        $compStmt->execute([
            ':user_id' => $userId,
            ':course_id' => $courseId
        ]);
        $completedLessons = (int)$compStmt->fetchColumn();

        $percentage = ($totalLessons > 0) ? round(($completedLessons / $totalLessons) * 100) : 0;

        // Busca qual foi a última aula assistida ou a primeira aula do curso para o botão "Continuar"
        $lastLessonStmt = $db->prepare("
            SELECT l.id 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            JOIN modules m ON s.module_id = m.id
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :user_id
            WHERE m.course_id = :course_id
            ORDER BY COALESCE(lp.completed_at, '1970-01-01') DESC, m.sort_order ASC, s.sort_order ASC, l.sort_order ASC
            LIMIT 1
        ");
        $lastLessonStmt->execute([
            ':user_id' => $userId,
            ':course_id' => $courseId
        ]);
        $startLessonId = $lastLessonStmt->fetchColumn();

        // Busca os bônus associados a este curso específico
        $cBonusStmt = $db->prepare("SELECT id, type, title, ebook_url, bonus_course_id FROM course_bonuses WHERE course_id = :course_id ORDER BY sort_order ASC");
        $cBonusStmt->execute([':course_id' => $courseId]);
        $cBonuses = $cBonusStmt->fetchAll();

        $coursesList[] = [
            'id' => (int)$courseId,
            'title' => htmlspecialchars($ec['title'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars($ec['description'], ENT_QUOTES, 'UTF-8'),
            'thumbnail_url' => $ec['thumbnail_url'],
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'percentage' => (int)$percentage,
            'start_lesson_id' => $startLessonId ? (int)$startLessonId : null,
            'bonuses' => $cBonuses
        ];
    }

    // 3. Busca conquistas possíveis e marca as desbloqueadas pelo aluno
    $achieveStmt = $db->prepare("SELECT id, title, description, icon_url, xp_bonus FROM achievements");
    $achieveStmt->execute();
    $allAchievements = $achieveStmt->fetchAll();

    // Busca conquistas desbloqueadas do usuário
    $userAchieveStmt = $db->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = :user_id");
    $userAchieveStmt->execute([':user_id' => $userId]);
    $userAchievements = $userAchieveStmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Busca notificações não lidas
    $notifStmt = $db->prepare("SELECT id, title, message, type, created_at FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 4");
    $notifStmt->execute([':user_id' => $userId]);
    $notifications = $notifStmt->fetchAll();

} catch (\PDOException $e) {
    die("Erro interno ao carregar o painel do aluno: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Painel do Aluno — Cursos GT</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.cdnfonts.com/css/clash-display" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/satoshi" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f1c84b",
                        "deep-obsidian": "#0A0A0C",
                        "surface": "rgba(20, 20, 23, 0.7)",
                        "text-main": "#F5F5F7",
                        "text-muted": "#8F8F9D",
                        "border-color": "rgba(255, 255, 255, 0.05)"
                    },
                    fontFamily: {
                        "display": ["Clash Display", "sans-serif"],
                        "sans": ["Satoshi", "sans-serif"]
                    },
                    backgroundImage: {
                        'radial-glow': 'radial-gradient(circle at 50% 50%, rgba(241, 200, 75, 0.03) 0%, rgba(10, 10, 12, 1) 100%)',
                    }
                },
            },
        }
    </script>
    
    <style>
        body {
            font-family: 'Satoshi', sans-serif;
            background-color: #0A0A0C;
            color: #F5F5F7;
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4 {
            font-family: 'Clash Display', sans-serif;
        }

        .glass-card {
            background: rgba(20, 20, 23, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(241, 200, 75, 0.15);
            box-shadow: 0 20px 40px -10px rgba(241, 200, 75, 0.05);
        }

        .level-badge {
            box-shadow: 0 0 20px rgba(241, 200, 75, 0.2);
            border: 1px solid rgba(241, 200, 75, 0.3);
        }

        .btn-primary {
            background-color: #f1c84b;
            color: #0A0A0C;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            box-shadow: 0 0 20px rgba(241, 200, 75, 0.35);
            transform: translateY(-1px);
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #f1c84b 0%, #FFD700 100%);
            box-shadow: 0 0 10px rgba(241, 200, 75, 0.5);
        }
    </style>
</head>
<body class="antialiased bg-radial-glow min-h-screen pb-16">
    
    <!-- Top Navigation Header -->
    <header class="border-b border-border-color bg-deep-obsidian/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="Logo GT Cursos" onerror="this.style.display='none'" class="h-10 w-auto object-contain">
                <span class="font-display text-2xl font-bold tracking-widest uppercase">
                    CURSOS <span class="text-primary">GT</span>
                </span>
            </div>
            
            <nav class="flex items-center gap-6">
                <a href="../index.php" class="text-xs font-bold text-text-muted hover:text-primary transition-colors uppercase tracking-wider">Explorar Cursos</a>
                <a href="profile.php" class="text-xs font-bold text-text-muted hover:text-primary transition-colors uppercase tracking-wider">Perfil & Medalhas</a>
                <div class="h-6 w-[1px] bg-border-color"></div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                    </div>
                    <span class="text-xs font-bold text-text-main hidden sm:inline"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <a href="../logout.php" class="text-text-muted hover:text-error transition-colors flex items-center" title="Sair da Conta">
                    <span class="material-symbols-outlined text-[22px]">logout</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Grid -->
    <main class="max-w-7xl mx-auto px-6 mt-12 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Welcome & Left Stats Columns (8 cols) -->
        <section class="lg:col-span-8 space-y-8">
            <!-- Welcome Banner -->
            <div class="glass-card rounded-xl p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-text-main">
                        Olá, <span class="text-primary"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>!
                    </h1>
                    <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-2">
                        Seja bem-vindo de volta ao seu painel tático de estudos.
                    </p>
                </div>
                <!-- Mini XP Indicator -->
                <div class="flex items-center gap-4 bg-black/40 border border-border-color rounded-lg px-6 py-4">
                    <div class="w-12 h-12 rounded-lg level-badge bg-primary/5 flex items-center justify-center font-display text-xl font-bold text-primary">
                        <?php echo $level; ?>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-primary uppercase tracking-widest">Nível Atual</div>
                        <div class="text-sm font-semibold text-text-main mt-0.5"><?php echo $xp; ?> total XP</div>
                    </div>
                </div>
            </div>

            <!-- Level Up Progress Card -->
            <div class="glass-card rounded-xl p-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-primary">Status de Evolução de Elite</h3>
                    <span class="text-xs font-bold text-text-muted"><?php echo $xpInCurrentLevel; ?> / 1000 XP para Nível <?php echo $level + 1; ?></span>
                </div>
                <!-- Progress Bar -->
                <div class="w-full h-3 bg-black/50 rounded-full border border-border-color overflow-hidden">
                    <div class="h-full progress-bar-fill rounded-full" style="width: <?php echo $xpProgressPercentage; ?>%"></div>
                </div>
                <div class="flex justify-between mt-3 text-[10px] text-text-muted font-bold uppercase tracking-wider">
                    <span>Nível <?php echo $level; ?></span>
                    <span>Nível <?php echo $level + 1; ?></span>
                </div>
            </div>

            <!-- Active Courses Syllabus -->
            <div class="space-y-4">
                <h2 class="text-lg font-bold text-text-main uppercase tracking-widest">Meus Cursos Táticos</h2>
                
                <?php if (empty($coursesList)): ?>
                    <div class="glass-card rounded-xl p-12 text-center">
                        <span class="material-symbols-outlined text-text-muted text-5xl mb-4">school</span>
                        <h3 class="text-md font-bold text-text-main mb-2">Nenhum treinamento matriculado</h3>
                        <p class="text-xs text-text-muted max-w-[340px] mx-auto mb-6">Você ainda não se inscreveu em nossos treinamentos táticos. Visite nossa página de vendas e garanta seu acesso.</p>
                        <a href="../index.php" class="btn-primary inline-flex items-center gap-2 font-bold px-6 py-3 rounded-lg text-xs uppercase tracking-widest">Explorar Cursos</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($coursesList as $course): ?>
                            <div class="glass-card rounded-xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                                <div class="flex items-start gap-4">
                                    <div class="w-16 h-16 rounded-lg bg-primary/5 border border-primary/20 flex items-center justify-center text-primary flex-shrink-0">
                                        <span class="material-symbols-outlined text-[32px]">shield</span>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-text-main leading-tight"><?php echo $course['title']; ?></h3>
                                        <p class="text-text-muted text-[11px] mt-1 line-clamp-2 leading-relaxed"><?php echo $course['description']; ?></p>
                                        <!-- Course Progress percentage bar -->
                                        <div class="flex items-center gap-3 mt-4">
                                            <div class="w-28 h-1.5 bg-black/40 border border-border-color rounded-full overflow-hidden">
                                                <div class="h-full bg-primary rounded-full" style="width: <?php echo $course['percentage']; ?>%"></div>
                                            </div>
                                            <span class="text-[10px] font-bold text-primary uppercase tracking-wider"><?php echo $course['percentage']; ?>% Concluído</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="w-full md:w-auto flex-shrink-0 flex flex-col sm:flex-row md:flex-col gap-3">
                                    <?php if ($course['start_lesson_id']): ?>
                                        <a href="classroom.php?lesson_id=<?php echo $course['start_lesson_id']; ?>" class="w-full md:w-auto btn-primary font-bold py-3.5 px-6 rounded-lg text-[10px] uppercase tracking-wider flex items-center justify-center gap-2">
                                            <span>Continuar Assistindo</span>
                                            <span class="material-symbols-outlined text-[16px]">play_circle</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-primary bg-primary/10 border border-primary/20 rounded px-4 py-2 uppercase text-center">Curso Concluído 🏆</span>
                                    <?php endif; ?>

                                    <?php if (!empty($course['bonuses'])): ?>
                                        <?php 
                                        $encodedBonuses = htmlspecialchars(json_encode($course['bonuses']), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <button onclick="openStudentBonusModal('<?php echo $encodedBonuses; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>')" class="w-full md:w-auto border border-primary/30 bg-primary/5 hover:bg-primary/15 text-primary font-bold py-2.5 px-6 rounded-lg text-[10px] uppercase tracking-wider flex items-center justify-center gap-2 transition-all">
                                            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">gift</span>
                                            <span>Bônus Inclusos</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Sidebar Widgets (4 cols) -->
        <aside class="lg:col-span-4 space-y-8">
            <!-- Streak Card -->
            <div class="glass-card rounded-xl p-8 text-center relative overflow-hidden">
                <!-- Streak Glow Background -->
                <div class="absolute inset-0 bg-gradient-to-b from-[#f1c84b]/5 to-transparent pointer-events-none"></div>
                
                <span class="material-symbols-outlined text-primary text-5xl mb-2 animate-pulse" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                <h3 class="text-[28px] font-bold text-text-main leading-tight"><?php echo $streak; ?></h3>
                <p class="text-text-muted text-[10px] font-bold uppercase tracking-widest mt-1">Dias Consecutivos de Ofensiva</p>
                <div class="h-[1px] bg-border-color my-4"></div>
                <p class="text-text-muted text-[11px] leading-relaxed">Sua ofensiva é mantida registrando sua presença nas aulas diárias, presenciais ou digitais.</p>
            </div>

            <!-- Notifications Center -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-xs font-bold text-text-main uppercase tracking-widest mb-6">Central de Inteligência 📡</h3>
                
                <?php if (empty($notifications)): ?>
                    <p class="text-xs text-text-muted py-4 text-center">Nenhum alerta recente para reportar.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($notifications as $notif): ?>
                            <div class="p-4 rounded-lg bg-black/30 border border-border-color flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">
                                    <?php echo ($notif['type'] === 'achievement') ? 'military_tech' : 'info'; ?>
                                </span>
                                <div>
                                    <h4 class="text-xs font-bold text-text-main leading-tight"><?php echo htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="text-text-muted text-[11px] mt-1 leading-normal"><?php echo htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Achievements Widget -->
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xs font-bold text-text-main uppercase tracking-widest">Medalhas de Honra 🎖️</h3>
                    <a href="profile.php" class="text-[10px] font-bold text-primary hover:underline uppercase tracking-wider">Ver Galeria</a>
                </div>
                
                <div class="grid grid-cols-4 gap-4">
                    <?php foreach ($allAchievements as $ach): ?>
                        <?php 
                        $unlocked = in_array($ach['id'], $userAchievements); 
                        $opacityClass = $unlocked ? 'opacity-100 filter-none' : 'opacity-25 grayscale';
                        $borderClass = $unlocked ? 'border-primary/40 bg-primary/5' : 'border-border-color bg-black/40';
                        ?>
                        <div class="aspect-square rounded-lg border flex flex-col items-center justify-center <?php echo $borderClass; ?> <?php echo $opacityClass; ?>" title="<?php echo htmlspecialchars($ach['title'] . ': ' . $ach['description'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="material-symbols-outlined text-[26px] <?php echo $unlocked ? 'text-primary' : 'text-text-muted'; ?>">
                                <?php 
                                // Ícone dinâmico simples
                                if (strpos($ach['icon_url'], 'badge_streak') !== false) {
                                    echo 'local_fire_department';
                                } elseif (strpos($ach['icon_url'], 'perfect') !== false) {
                                    echo 'workspace_premium';
                                } elseif (strpos($ach['icon_url'], 'attendance') !== false) {
                                    echo 'assignment_turned_in';
                                } else {
                                    echo 'military_tech';
                                }
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

    </main>

<!-- MODAL DE BÔNUS DO ALUNO -->
<div id="studentBonusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <!-- Overlay de fechamento -->
    <div onclick="closeStudentBonusModal()" class="absolute inset-0 bg-black/40"></div>
    
    <div class="glass-card w-full max-w-md rounded-2xl overflow-hidden shadow-2xl relative z-10 flex flex-col max-h-[85vh]" style="border-color: rgba(241, 200, 75, 0.25); background: linear-gradient(145deg, #111116 0%, #1a1a22 100%);">
        
        <!-- Top border glow line -->
        <div class="h-1 bg-gradient-to-r from-primary/10 via-primary/50 to-primary/10"></div>
        
        <!-- Header -->
        <div class="border-b border-white/5 px-6 py-4 flex items-center justify-between">
            <div>
                <span class="text-[9px] font-bold text-primary uppercase tracking-[0.2em]">Recompensas Inclusas</span>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider font-display" id="studentBonusModalTitle">Nome do Curso</h3>
            </div>
            <button onclick="closeStudentBonusModal()" class="text-text-muted hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Conteúdo -->
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4" id="studentBonusModalContent">
            <!-- Os bônus serão montados dinamicamente via JS -->
        </div>

        <div class="px-6 py-4 border-t border-white/5 bg-black/20 flex justify-end">
            <button onclick="closeStudentBonusModal()" class="border border-white/10 hover:border-white/20 text-text-muted hover:text-white px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors">Fechar Painel</button>
        </div>
    </div>
</div>

<script>
    function openStudentBonusModal(bonusesJson, courseTitle) {
        const bonuses = JSON.parse(bonusesJson);
        document.getElementById('studentBonusModalTitle').innerText = courseTitle;
        const container = document.getElementById('studentBonusModalContent');
        container.innerHTML = '';

        if (!bonuses || bonuses.length === 0) {
            container.innerHTML = `
                <div class="text-center py-6 text-text-muted text-xs italic">
                    Nenhum bônus associado.
                </div>
            `;
        } else {
            bonuses.forEach(b => {
                let actionHtml = '';
                let icon = '';
                let desc = '';

                if (b.type === 'ebook') {
                    icon = 'library_books';
                    desc = 'E-book em PDF / Documento Complementar';
                    actionHtml = `
                        <a href="../${b.ebook_url}" download class="btn-primary font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider flex items-center gap-1.5 hover:-translate-y-0.5 transition-all">
                            <span class="material-symbols-outlined text-[14px]">download</span>
                            Baixar Ebook
                        </a>
                    `;
                } else {
                    icon = 'local_library';
                    desc = 'Curso Extra Concedido de Graça';
                    actionHtml = `
                        <a href="classroom.php?lesson_id=first&course_id=${b.bonus_course_id}" class="btn-primary font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider flex items-center gap-1.5 hover:-translate-y-0.5 transition-all">
                            <span class="material-symbols-outlined text-[14px]">play_circle</span>
                            Estudar Agora
                        </a>
                    `;
                }

                container.innerHTML += `
                    <div class="p-4 rounded-xl border border-white/5 bg-black/40 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                                <span class="material-symbols-outlined text-[20px]">${icon}</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white leading-tight">${b.title}</h4>
                                <p class="text-[9px] text-text-muted mt-0.5 uppercase tracking-wide font-medium">${desc}</p>
                            </div>
                        </div>
                        <div class="shrink-0">
                            ${actionHtml}
                        </div>
                    </div>
                `;
            });
        }

        document.getElementById('studentBonusModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeStudentBonusModal() {
        document.getElementById('studentBonusModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeStudentBonusModal();
    });
</script>

</body>
</html>
