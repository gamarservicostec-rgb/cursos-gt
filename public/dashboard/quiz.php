<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;
use Middleware\SecurityHeaders;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Middleware/SecurityHeaders.php';

// Requer que o aluno esteja logado
\Middleware\AuthMiddleware::requireStudent();

$userId = $_SESSION['user_id'];
$quizId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$quizId) {
    header("Location: index.php");
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca os detalhes da prova
    $quizStmt = $db->prepare("SELECT id, course_id, title, min_score FROM quizzes WHERE id = :id LIMIT 1");
    $quizStmt->execute([':id' => $quizId]);
    $quiz = $quizStmt->fetch();

    if (!$quiz) {
        die("<h1>Prova não localizada</h1><p>Esta avaliação não existe no catálogo de cursos.</p><a href='index.php'>Voltar</a>");
    }

    $courseId = $quiz['course_id'];

    // Busca o título do curso
    $courseStmt = $db->prepare("SELECT title FROM courses WHERE id = :id LIMIT 1");
    $courseStmt->execute([':id' => $courseId]);
    $courseTitle = $courseStmt->fetchColumn();

    // 2. Busca todas as questões vinculadas a esta prova
    $questStmt = $db->prepare("SELECT id, question_text, type FROM questions WHERE quiz_id = :quiz_id ORDER BY id ASC");
    $questStmt->execute([':quiz_id' => $quizId]);
    $questions = $questStmt->fetchAll();

    $quizQuestions = [];
    foreach ($questions as $q) {
        // Busca as opções de resposta para cada questão
        $optStmt = $db->prepare("SELECT id, option_text FROM question_options WHERE question_id = :question_id ORDER BY id ASC");
        $optStmt->execute([':question_id' => $q['id']]);
        $options = $optStmt->fetchAll();

        $quizQuestions[] = [
            'id' => (int)$q['id'],
            'text' => htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8'),
            'type' => $q['type'],
            'options' => array_map(function($o) {
                return [
                    'id' => (int)$o['id'],
                    'text' => htmlspecialchars($o['option_text'], ENT_QUOTES, 'UTF-8')
                ];
            }, $options)
        ];
    }

    // 3. Processamento do Envio da Prova (POST)
    $quizFeedback = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $answers = $_POST['answers'] ?? []; // Array indexado pelo ID da questão contendo o ID da opção selecionada
        $totalQuestions = count($quizQuestions);
        $correctCount = 0;

        foreach ($quizQuestions as $q) {
            $qId = $q['id'];
            $selectedOptId = isset($answers[$qId]) ? filter_var($answers[$qId], FILTER_VALIDATE_INT) : null;

            if ($selectedOptId) {
                // Verifica no banco se a opção é de fato a correta
                $checkStmt = $db->prepare("SELECT is_correct FROM question_options WHERE id = :id AND question_id = :q_id LIMIT 1");
                $checkStmt->execute([
                    ':id' => $selectedOptId,
                    ':q_id' => $qId
                ]);
                $isCorrect = (bool)$checkStmt->fetchColumn();

                if ($isCorrect) {
                    $correctCount++;
                }
            }
        }

        // Calcula a porcentagem final de acerto
        $score = ($totalQuestions > 0) ? round(($correctCount / $totalQuestions) * 100) : 0;
        $passed = ($score >= $quiz['min_score']) ? 1 : 0;

        $db->beginTransaction();

        // Insere a tentativa de prova no banco
        $attemptStmt = $db->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, passed) VALUES (:user_id, :quiz_id, :score, :passed)");
        $attemptStmt->execute([
            ':user_id' => $userId,
            ':quiz_id' => $quizId,
            ':score' => $score,
            ':passed' => $passed
        ]);

        $xpBonus = 0;
        $levelUp = false;
        $earnedCerebroBadge = false;
        $certificateIssued = false;
        $certificateCode = '';

        if ($passed === 1) {
            // Recompensa padrão de 200 XP por aprovação
            $xpBonus += 200;

            // Se gabaritou (100% de acerto), ganha a medalha "Cérebro de Elite" e +500 XP
            if ($score === 100) {
                $badgeCheck = $db->prepare("SELECT id FROM user_achievements WHERE user_id = :user_id AND achievement_id = 3 LIMIT 1");
                $badgeCheck->execute([':user_id' => $userId]);
                
                if (!$badgeCheck->fetch()) {
                    $badgeInsert = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (:user_id, 3)");
                    $badgeInsert->execute([':user_id' => $userId]);
                    
                    $xpBonus += 500;
                    $earnedCerebroBadge = true;

                    // Notificação de Medalha
                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Medalha Conquistada! 🎖️', 'Parabéns! Você alcançou 100% de aproveitamento na avaliação e desbloqueou a medalha: Cérebro de Elite!', 'achievement')");
                    $notifStmt->execute([':user_id' => $userId]);
                }
            }

            // Atualiza o perfil do aluno somando o XP bônus
            $userStmt = $db->prepare("SELECT xp, level FROM users WHERE id = :id LIMIT 1");
            $userStmt->execute([':id' => $userId]);
            $user = $userStmt->fetch();

            $newXp = ($user['xp'] ?? 0) + $xpBonus;
            $newLevel = floor($newXp / 1000) + 1;
            if ($newLevel > ($user['level'] ?? 1)) {
                $levelUp = true;
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Subiu de Nível! 🏆', :msg, 'achievement')");
                $notifStmt->execute([
                    ':user_id' => $userId,
                    ':msg' => "Parabéns! Você alcançou o Nível {$newLevel} na GT Cursos. Continue avançando!"
                ]);
            }

            $updateUser = $db->prepare("UPDATE users SET xp = :xp, level = :level, last_activity = NOW() WHERE id = :id");
            $updateUser->execute([
                ':xp' => $newXp,
                ':level' => $newLevel,
                ':id' => $userId
            ]);

            // --- REQUISITOS DE LIBERAÇÃO DE CERTIFICADO ---
            // 1. Concluiu 100% das aulas digitais (EAD)
            $totalStmt = $db->prepare("SELECT COUNT(l.id) FROM lessons l JOIN subjects s ON l.subject_id = s.id JOIN modules m ON s.module_id = m.id WHERE m.course_id = :course_id");
            $totalStmt->execute([':course_id' => $courseId]);
            $totalLessons = (int)$totalStmt->fetchColumn();

            $compStmt = $db->prepare("SELECT COUNT(lp.id) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id JOIN subjects s ON l.subject_id = s.id JOIN modules m ON s.module_id = m.id WHERE lp.user_id = :user_id AND m.course_id = :course_id AND lp.completed = 1");
            $compStmt->execute([':user_id' => $userId, ':course_id' => $courseId]);
            $completedLessons = (int)$compStmt->fetchColumn();

            $eadComplete = ($totalLessons > 0 && $completedLessons === $totalLessons);

            // 2. Frequência presencial >= 75% (Se possuir chamadas presenciais)
            $attTotalStmt = $db->prepare("SELECT COUNT(*) FROM physical_attendance WHERE user_id = :user_id AND course_id = :course_id");
            $attTotalStmt->execute([':user_id' => $userId, ':course_id' => $courseId]);
            $totalAttendance = (int)$attTotalStmt->fetchColumn();

            $hasAttendanceCheck = ($totalAttendance > 0);
            $presencePasses = true;

            if ($hasAttendanceCheck) {
                $attPresentStmt = $db->prepare("SELECT COUNT(*) FROM physical_attendance WHERE user_id = :user_id AND course_id = :course_id AND attended = 1");
                $attPresentStmt->execute([':user_id' => $userId, ':course_id' => $courseId]);
                $presentCount = (int)$attPresentStmt->fetchColumn();

                $presenceRate = ($presentCount / $totalAttendance) * 100;
                $presencePasses = ($presenceRate >= 75);
            }

            // Se atendeu todos os critérios de emissão, emite o certificado!
            if ($eadComplete && $presencePasses) {
                // Verifica se já não emitiu
                $certCheck = $db->prepare("SELECT certificate_code FROM certificates WHERE user_id = :user_id AND course_id = :course_id LIMIT 1");
                $certCheck->execute([
                    ':user_id' => $userId,
                    ':course_id' => $courseId
                ]);
                $existingCert = $certCheck->fetchColumn();

                if (!$existingCert) {
                    $certificateCode = 'GT-' . strtoupper(bin2hex(random_bytes(6)));
                    
                    $certInsert = $db->prepare("INSERT INTO certificates (user_id, course_id, certificate_code) VALUES (:user_id, :course_id, :code)");
                    $certInsert->execute([
                        ':user_id' => $userId,
                        ':course_id' => $courseId,
                        ':code' => $certificateCode
                    ]);

                    $certificateIssued = true;

                    // Insere notificação de certificado emitido
                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, 'Certificado Liberado! 🎓', :msg, 'achievement')");
                    $notifStmt->execute([
                        ':user_id' => $userId,
                        ':msg' => "Parabéns! O seu certificado para o curso '{$courseTitle}' foi emitido e já está disponível em seu perfil!"
                    ]);
                } else {
                    $certificateCode = $existingCert;
                    $certificateIssued = true;
                }
            }
        }

        $db->commit();

        $quizFeedback = [
            'score' => $score,
            'passed' => ($passed === 1),
            'xp_bonus' => $xpBonus,
            'level_up' => $levelUp,
            'cerebro_badge' => $earnedCerebroBadge,
            'certificate_issued' => $certificateIssued,
            'certificate_code' => $certificateCode
        ];
    }

} catch (\PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Erro interno na prova: " . $e->getMessage());
}

$csrfToken = \Middleware\SecurityHeaders::generateCSRFToken();
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Avaliação Tática: <?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?> — Cursos GT</title>
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
                        "border-color": "rgba(255, 255, 255, 0.05)",
                        "error": "#FF3B30",
                        "success": "#34C759"
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
        }

        .btn-primary {
            background-color: #f1c84b;
            color: #0A0A0C;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            box-shadow: 0 0 20px rgba(241, 200, 75, 0.35);
        }

        .custom-radio {
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.4);
            transition: all 0.25s ease;
        }

        .custom-radio:hover, .custom-radio.selected {
            border-color: #f1c84b;
            background: rgba(241, 200, 75, 0.04);
        }
    </style>
</head>
<body class="antialiased bg-radial-glow min-h-screen pb-20 select-none">
    
    <!-- Top Classroom Header -->
    <header class="border-b border-border-color bg-deep-obsidian/90 backdrop-blur-md h-16 flex items-center justify-between px-6 sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-text-muted hover:text-primary transition-colors flex items-center" title="Voltar ao Painel">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="Logo GT Cursos" onerror="this.style.display='none'" class="h-8 w-auto object-contain">
                <span class="font-display text-lg font-bold tracking-widest uppercase">
                    AVALIAÇÃO <span class="text-primary">TÁTICA</span>
                </span>
            </div>
        </div>
        
        <h2 class="text-xs font-bold text-text-main uppercase tracking-wider hidden sm:block">
            Treinamento: <span class="text-primary"><?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        </h2>

        <span class="text-[10px] font-bold text-primary border border-primary/20 bg-primary/5 rounded px-3 py-1.5 uppercase tracking-widest">
            Min. Aprovação: <?php echo $quiz['min_score']; ?>%
        </span>
    </header>

    <main class="max-w-3xl mx-auto px-6 mt-12 space-y-8">
        
        <?php if ($quizFeedback): ?>
            <!-- ================= EXAM RESULTS FEEDBACK CARD ================= -->
            <div class="glass-card rounded-xl p-8 text-center space-y-6">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 <?php echo $quizFeedback['passed'] ? 'border-success/40 bg-success/5 text-success' : 'border-error/40 bg-error/5 text-error'; ?>">
                        <span class="material-symbols-outlined text-4xl">
                            <?php echo $quizFeedback['passed'] ? 'military_tech' : 'cancel'; ?>
                        </span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl font-bold <?php echo $quizFeedback['passed'] ? 'text-success' : 'text-error'; ?> uppercase tracking-widest">
                        <?php echo $quizFeedback['passed'] ? 'Aprovado na Avaliação!' : 'Reprovado na Prova'; ?>
                    </h1>
                    <p class="text-text-muted text-xs font-bold uppercase tracking-wider">
                        Sua pontuação final de aproveitamento foi de <strong class="text-primary font-display text-base"><?php echo $quizFeedback['score']; ?>%</strong>.
                    </p>
                </div>

                <?php if ($quizFeedback['passed']): ?>
                    <!-- Success Details Block -->
                    <div class="p-6 bg-black/40 border border-border-color rounded-lg text-left space-y-4 text-xs">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-xl">workspace_premium</span>
                            <div>
                                <h4 class="font-bold text-text-main">Certificado Digital de Conclusão</h4>
                                <?php if ($quizFeedback['certificate_issued']): ?>
                                    <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Parabéns! Todos os requisitos foram atendidos e o certificado de autenticidade criptográfica foi gerado com sucesso. Código: <strong class="text-primary select-all font-mono"><?php echo $quizFeedback['certificate_code']; ?></strong>.</p>
                                <?php else: ?>
                                    <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Você foi aprovado na prova técnica! O certificado será liberado assim que você concluir 100% das aulas digitais e registrar pelo menos 75% de presenças físicas nas aulas presenciais.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($quizFeedback['cerebro_badge']): ?>
                            <div class="h-[1px] bg-border-color"></div>
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                                <div>
                                    <h4 class="font-bold text-text-main">Nova Medalha: Cérebro de Elite 🎖️</h4>
                                    <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Desbloqueada por obter nota perfeita de 100% no exame técnico. Bônus de 500 XP creditados!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Warning Retry Block -->
                    <div class="p-4 bg-error/5 border border-error/20 rounded-lg flex items-start gap-3 text-left">
                        <span class="material-symbols-outlined text-error text-xl mt-0.5">warning</span>
                        <p class="text-[11px] text-text-main font-semibold leading-relaxed">
                            Infelizmente, sua pontuação de <?php echo $quizFeedback['score']; ?>% foi menor que a nota mínima exigida de <?php echo $quiz['min_score']; ?>%. Estude novamente os conceitos das aulas e tente mais uma vez para destravar seu certificado!
                        </p>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="index.php" class="btn-primary font-bold py-3.5 px-6 rounded text-[10px] uppercase tracking-widest inline-flex items-center justify-center gap-2">
                        <span>Ir para o Painel</span>
                        <span class="material-symbols-outlined text-[16px]">dashboard</span>
                    </a>
                    <?php if (!$quizFeedback['passed']): ?>
                        <a href="quiz.php?id=<?php echo $quizId; ?>" class="btn-secondary font-bold py-3.5 px-6 rounded text-[10px] uppercase tracking-widest inline-flex items-center justify-center gap-2">
                            <span>Refazer Prova</span>
                            <span class="material-symbols-outlined text-[16px]">refresh</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- ================= EXAM QUESTIONS FORM ================= -->
            <form method="POST" class="space-y-8">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="space-y-6">
                    <?php $qNum = 1; ?>
                    <?php foreach ($quizQuestions as $q): ?>
                        <div class="glass-card rounded-xl p-6 space-y-4">
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded bg-primary/10 border border-primary/20 flex items-center justify-center font-display text-xs font-bold text-primary flex-shrink-0 mt-0.5">
                                    <?php echo $qNum; ?>
                                </span>
                                <h3 class="text-xs font-bold text-text-main leading-snug"><?php echo $q['text']; ?></h3>
                            </div>
                            
                            <!-- Choices List -->
                            <div class="space-y-2.5 pl-9">
                                <?php foreach ($q['options'] as $o): ?>
                                    <label class="custom-radio flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-semibold cursor-pointer select-none" id="label-opt-<?php echo $o['id']; ?>">
                                        <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $o['id']; ?>" class="focus:ring-primary h-4 w-4 text-primary bg-black border-border-color focus:outline-none" required onclick="selectRadio(<?php echo $q['id']; ?>, <?php echo $o['id']; ?>)">
                                        <span><?php echo $o['text']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $qNum++; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Submit Button -->
                <button class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px] flex items-center justify-center gap-2" type="submit">
                    <span>Enviar Respostas e Finalizar Prova</span>
                    <span class="material-symbols-outlined text-[16px]">verified</span>
                </button>
            </form>
        <?php endif; ?>

    </main>

    <script>
        // Custom visual select handler for radios
        function selectRadio(questionId, optionId) {
            // Remove selection class from all choices of this question
            const radios = document.querySelectorAll(`input[name="answers[${questionId}]"]`);
            radios.forEach(r => {
                const label = document.getElementById(`label-opt-${r.value}`);
                if (label) {
                    label.classList.remove('selected');
                }
            });

            // Add selected class to the checked radio
            const selectedLabel = document.getElementById(`label-opt-${optionId}`);
            if (selectedLabel) {
                selectedLabel.classList.add('selected');
            }
        }
    </script>
</body>
</html>
