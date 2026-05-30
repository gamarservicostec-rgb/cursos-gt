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
$userEmail = $_SESSION['user_email'];

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca dados gerais do usuário no banco
    $userStmt = $db->prepare("SELECT xp, level, current_streak, avatar_url, created_at FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();

    $xp = (int)($user['xp'] ?? 0);
    $level = (int)($user['level'] ?? 1);
    $streak = (int)($user['current_streak'] ?? 0);
    $createdAt = $user['created_at'];

    // 2. Busca medalhas desbloqueadas com detalhes
    $achieveStmt = $db->prepare("
        SELECT a.id, a.title, a.description, a.icon_url, a.xp_bonus, ua.unlocked_at 
        FROM user_achievements ua
        JOIN achievements a ON ua.achievement_id = a.id
        WHERE ua.user_id = :user_id
        ORDER BY ua.unlocked_at DESC
    ");
    $achieveStmt->execute([':user_id' => $userId]);
    $unlockedAchievements = $achieveStmt->fetchAll();

    // 3. Busca certificados emitidos para o aluno
    $certStmt = $db->prepare("
        SELECT cert.id, cert.certificate_code, cert.issued_at, co.title as course_title 
        FROM certificates cert
        JOIN courses co ON cert.course_id = co.id
        WHERE cert.user_id = :user_id
        ORDER BY cert.issued_at DESC
    ");
    $certStmt->execute([':user_id' => $userId]);
    $certificates = $certStmt->fetchAll();

} catch (\PDOException $e) {
    die("Erro interno ao carregar perfil: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Perfil & Conquistas — Cursos GT</title>
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
                <a href="index.php" class="text-xs font-bold text-text-muted hover:text-primary transition-colors uppercase tracking-wider">Dashboard</a>
                <a href="../index.php" class="text-xs font-bold text-text-muted hover:text-primary transition-colors uppercase tracking-wider">Explorar Cursos</a>
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

    <main class="max-w-4xl mx-auto px-6 mt-12 space-y-8">
        
        <!-- Student Identity Summary -->
        <section class="glass-card rounded-xl p-8 flex flex-col sm:flex-row items-center gap-6">
            <div class="w-20 h-20 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0 text-primary">
                <span class="material-symbols-outlined text-[42px]" style="font-variation-settings: 'FILL' 1;">account_circle</span>
            </div>
            <div class="text-center sm:text-left flex-grow">
                <h1 class="text-2xl font-bold text-text-main leading-tight"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-text-muted text-xs font-semibold mt-1 uppercase tracking-wider"><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="flex flex-wrap justify-center sm:justify-start gap-x-6 gap-y-2 mt-4 text-[10px] text-text-muted font-bold uppercase tracking-widest">
                    <span>Membro desde: <?php echo date('d/m/Y', strtotime($createdAt)); ?></span>
                    <span class="text-primary">Matrícula: Aluno de Elite</span>
                </div>
            </div>
            
            <!-- Quick Stats Blocks -->
            <div class="flex gap-4 border-t sm:border-t-0 sm:border-l border-border-color pt-6 sm:pt-0 sm:pl-8 flex-shrink-0">
                <div class="text-center px-4">
                    <div class="text-[20px] font-bold text-primary font-display"><?php echo $level; ?></div>
                    <div class="text-[9px] text-text-muted font-bold uppercase tracking-widest mt-1">Nível</div>
                </div>
                <div class="text-center px-4 border-x border-border-color">
                    <div class="text-[20px] font-bold text-text-main font-display"><?php echo $xp; ?></div>
                    <div class="text-[9px] text-text-muted font-bold uppercase tracking-widest mt-1">Total XP</div>
                </div>
                <div class="text-center px-4">
                    <div class="text-[20px] font-bold text-primary font-display"><?php echo $streak; ?></div>
                    <div class="text-[9px] text-text-muted font-bold uppercase tracking-widest mt-1">Streaks</div>
                </div>
            </div>
        </section>

        <!-- Achievements Detailed Gallery -->
        <section class="glass-card rounded-xl p-8 space-y-6">
            <h2 class="text-lg font-bold text-text-main uppercase tracking-widest border-b border-border-color pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">military_tech</span>
                Minhas Conquistas & Honrarias
            </h2>
            
            <?php if (empty($unlockedAchievements)): ?>
                <div class="text-center py-8">
                    <p class="text-xs text-text-muted leading-relaxed max-w-[280px] mx-auto">Você ainda não destravou nenhuma medalha. Conclua sua primeira aula ou atinja pontuações para preencher sua galeria!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($unlockedAchievements as $ua): ?>
                        <div class="p-4 rounded-lg bg-black/40 border border-primary/20 flex items-start gap-4 hover:border-primary/40 transition-colors">
                            <div class="w-12 h-12 rounded-lg bg-primary/10 border border-primary/30 flex items-center justify-center flex-shrink-0 text-primary">
                                <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">
                                    <?php 
                                    if (strpos($ua['icon_url'], 'streak') !== false) {
                                        echo 'local_fire_department';
                                    } elseif (strpos($ua['icon_url'], 'perfect') !== false) {
                                        echo 'workspace_premium';
                                    } elseif (strpos($ua['icon_url'], 'attendance') !== false) {
                                        echo 'assignment_turned_in';
                                    } else {
                                        echo 'military_tech';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-text-main leading-tight"><?php echo htmlspecialchars($ua['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-text-muted text-[10px] leading-relaxed mt-1"><?php echo htmlspecialchars($ua['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="flex items-center gap-4 mt-2.5 text-[9px] font-bold text-primary uppercase tracking-widest">
                                    <span>+<?php echo $ua['xp_bonus']; ?> XP Bônus</span>
                                    <span class="text-text-muted">Desbloqueado: <?php echo date('d/m/Y', strtotime($ua['unlocked_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Emitted Certificates List -->
        <section class="glass-card rounded-xl p-8 space-y-6">
            <h2 class="text-lg font-bold text-text-main uppercase tracking-widest border-b border-border-color pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">workspace_premium</span>
                Certificados Digitais Emitidos
            </h2>
            
            <?php if (empty($certificates)): ?>
                <div class="text-center py-12">
                    <span class="material-symbols-outlined text-text-muted text-4xl mb-3">verified</span>
                    <h3 class="text-xs font-bold text-text-main mb-1">Nenhum certificado emitido</h3>
                    <p class="text-[11px] text-text-muted max-w-[320px] mx-auto leading-relaxed">Os certificados são liberados automaticamente após concluir 100% das aulas digitais e atingir a aprovação presencial mínima de 75%.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-border-color text-text-muted font-bold uppercase tracking-wider">
                                <th class="pb-3">Curso Híbrido</th>
                                <th class="pb-3">Código de Autenticidade</th>
                                <th class="pb-3">Data de Emissão</th>
                                <th class="pb-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-color">
                            <?php foreach ($certificates as $cert): ?>
                                <tr class="hover:bg-white/[0.02]">
                                    <td class="py-4 font-bold text-text-main"><?php echo htmlspecialchars($cert['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="py-4 font-mono font-semibold text-primary select-all"><?php echo $cert['certificate_code']; ?></td>
                                    <td class="py-4 text-text-muted"><?php echo date('d/m/Y', strtotime($cert['issued_at'])); ?></td>
                                    <td class="py-4 text-right">
                                        <a href="../generate_certificate.php?code=<?php echo $cert['certificate_code']; ?>" target="_blank" class="btn-primary font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider inline-flex items-center gap-1.5">
                                            <span>Visualizar</span>
                                            <span class="material-symbols-outlined text-sm">open_in_new</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </main>

</body>
</html>
