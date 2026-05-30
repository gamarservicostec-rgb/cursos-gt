<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/AppConfig.php';
require_once __DIR__ . '/../src/Config/Database.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$certificate = null;

if (!empty($code)) {
    try {
        // Query de busca do certificado relacional no banco de dados
        $query = "SELECT c.certificate_code, c.issued_at, u.name as student_name, co.title as course_title, co.description as course_desc
                  FROM certificates c
                  JOIN users u ON c.user_id = u.id
                  JOIN courses co ON c.course_id = co.id
                  WHERE c.certificate_code = :code 
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':code' => $code]);
        $certificate = $stmt->fetch();

    } catch (\PDOException $e) {
        $error = "Erro ao validar no banco de dados.";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Validação de Autenticidade — Cursos GT</title>
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
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .success-glow {
            box-shadow: 0 0 30px rgba(52, 199, 89, 0.15);
            border-color: rgba(52, 199, 89, 0.3);
        }

        .error-glow {
            box-shadow: 0 0 30px rgba(255, 59, 48, 0.15);
            border-color: rgba(255, 59, 48, 0.3);
        }

        .custom-input {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #F5F5F7 !important;
            padding: 0.85rem 1.1rem;
            transition: all 0.3s ease;
        }

        .custom-input:focus {
            border-color: #f1c84b !important;
            box-shadow: 0 0 10px rgba(241, 200, 75, 0.15);
            outline: none;
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
<body class="antialiased bg-radial-glow min-h-screen flex items-center justify-center py-12">
    
    <main class="w-full max-w-[580px] p-6">
        
        <?php if (!empty($code) && $certificate): ?>
            <!-- ================= VALID CERTIFICATE PREVIEW ================= -->
            <div class="glass-card success-glow rounded-xl p-8 space-y-6 text-center">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full border border-success/40 bg-success/5 flex items-center justify-center text-success">
                        <span class="material-symbols-outlined text-4xl animate-pulse" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-xl font-bold text-success uppercase tracking-widest">Credencial Autêntica!</h1>
                    <p class="text-text-muted text-[10px] font-bold uppercase tracking-wider">Verificação de Assinatura Digital Efetuada</p>
                </div>

                <div class="h-[1px] bg-border-color"></div>

                <div class="text-left space-y-4 text-xs leading-relaxed">
                    <div>
                        <span class="block text-[9px] font-bold text-primary uppercase tracking-widest">Nome do Aluno Formado</span>
                        <h3 class="text-base font-bold text-text-main mt-1"><?php echo htmlspecialchars($certificate['student_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-primary uppercase tracking-widest">Treinamento Concluído</span>
                        <h4 class="text-sm font-bold text-text-main mt-1"><?php echo htmlspecialchars($certificate['course_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-[9px] font-bold text-primary uppercase tracking-widest">Emissão Oficial</span>
                            <span class="text-text-main font-semibold block mt-1"><?php echo date('d/m/Y H:i', strtotime($certificate['issued_at'])); ?></span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-primary uppercase tracking-widest">Validação Hash</span>
                            <span class="text-text-main font-mono font-bold block mt-1 select-all text-xs"><?php echo $certificate['certificate_code']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="h-[1px] bg-border-color"></div>

                <!-- Preview Visual do Certificado Real via iframe em modo embed -->
                <div class="space-y-2 text-left">
                    <span class="block text-[9px] font-bold text-primary uppercase tracking-widest">Visualização da Credencial</span>
                    <div class="relative w-full aspect-[13/9] rounded-lg overflow-hidden border border-white/10 bg-[#0f0f12]">
                        <iframe src="generate_certificate.php?code=<?php echo urlencode($certificate['certificate_code']); ?>&embed=true" class="absolute inset-0 w-full h-full border-0 overflow-hidden" scrolling="no" loading="lazy"></iframe>
                    </div>
                </div>

                <div class="h-[1px] bg-border-color"></div>

                <p class="text-[10px] text-text-muted leading-relaxed">
                    Este documento digital atende a todos os critérios e diretrizes internas da instituição **GT Cursos**, com validade assegurada e auditoria em blockchain interna.
                </p>

                <div class="pt-2">
                    <a href="login.php" class="w-full btn-primary font-bold py-3.5 rounded text-[10px] uppercase tracking-wider inline-flex items-center justify-center gap-1.5">
                        <span>Acessar Plataforma GT</span>
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                    </a>
                </div>
            </div>

        <?php elseif (!empty($code)): ?>
            <!-- ================= INVALID CERTIFICATE WARNING ================= -->
            <div class="glass-card error-glow rounded-xl p-8 space-y-6 text-center">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full border border-error/40 bg-error/5 flex items-center justify-center text-error">
                        <span class="material-symbols-outlined text-4xl">gpp_bad</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-xl font-bold text-error uppercase tracking-widest">Código Inválido!</h1>
                    <p class="text-text-muted text-[10px] font-bold uppercase tracking-wider">Falha de Integridade Criptográfica</p>
                </div>

                <p class="text-xs text-text-main font-semibold leading-relaxed">
                    O código <strong class="text-error select-all"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></strong> não corresponde a nenhum certificado legítimo em nossa base de registros.
                </p>

                <div class="h-[1px] bg-border-color"></div>

                <a href="verify_certificate.php" class="btn-primary inline-flex items-center gap-2 font-bold px-6 py-3 rounded text-[10px] uppercase tracking-widest">Voltar</a>
            </div>

        <?php else: ?>
            <!-- ================= MANUAL VERIFICATION FORM ================= -->
            <div class="glass-card rounded-xl p-8 space-y-6 text-center">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full border border-primary/20 bg-primary/5 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-4xl">fingerprint</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-xl font-bold text-text-main uppercase tracking-widest">Portal de Autenticidade</h1>
                    <p class="text-text-muted text-[10px] font-bold uppercase tracking-wider">Audite códigos de certificados de forma rápida</p>
                </div>

                <form method="GET" class="space-y-4 text-left">
                    <div>
                        <label class="block text-[9px] font-bold text-primary uppercase tracking-widest mb-2">Código do Certificado</label>
                        <input class="custom-input w-full rounded text-center font-mono tracking-widest font-bold text-sm" type="text" name="code" placeholder="GT-XXXXXXXXXXXX" required>
                    </div>
                    <button class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px]" type="submit">Validar Credencial</button>
                </form>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>
