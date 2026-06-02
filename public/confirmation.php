<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';

// Requer que o aluno esteja logado
\Middleware\AuthMiddleware::requireStudent();

$userId    = $_SESSION['user_id'];
$paymentId = $_GET['payment_id'] ?? '';

if (empty($paymentId)) {
    header("Location: dashboard/index.php");
    exit;
}

$dbInstance = Database::getInstance();
$db         = $dbInstance->getConnection();

try {
    // Busca dados da transação (status real do banco de dados)
    $transStmt = $db->prepare("
        SELECT t.amount, t.payment_method, t.status AS trans_status, t.payment_details,
               c.title AS course_title,
               l.id AS first_lesson_id
        FROM transactions t
        JOIN courses c ON t.course_id = c.id
        LEFT JOIN modules m ON m.course_id = c.id
        LEFT JOIN subjects s ON s.module_id = m.id
        LEFT JOIN lessons l ON l.subject_id = s.id
        WHERE t.payment_id = :payment_id AND t.user_id = :user_id
        ORDER BY m.sort_order ASC, s.sort_order ASC, l.sort_order ASC
        LIMIT 1
    ");
    $transStmt->execute([
        ':payment_id' => $paymentId,
        ':user_id'    => $userId
    ]);
    $transaction = $transStmt->fetch();

    if (!$transaction) {
        die("<h1>Pedido não localizado</h1><p>A transação solicitada não pertence a este usuário ou não existe.</p><a href='dashboard/index.php'>Ir ao Painel</a>");
    }

    // O status real vem do banco — tanto 'approved' quanto 'success' indicam aprovação
    $rawStatus      = $transaction['trans_status'] ?? 'pending';
    $isApproved     = in_array($rawStatus, ['approved', 'success', 'paid']);
    $paymentDetails = !empty($transaction['payment_details']) ? json_decode($transaction['payment_details'], true) : [];

} catch (\PDOException $e) {
    die("Erro interno ao carregar confirmação: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Confirmação do Pedido — Cursos GT</title>
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
                        "primary":      "#f1c84b",
                        "deep-obsidian":"#0A0A0C",
                        "surface":      "rgba(20, 20, 23, 0.7)",
                        "text-main":    "#F5F5F7",
                        "text-muted":   "#8F8F9D",
                        "border-color": "rgba(255, 255, 255, 0.05)"
                    },
                    fontFamily: {
                        "display": ["Clash Display", "sans-serif"],
                        "sans":    ["Satoshi", "sans-serif"]
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

        .btn-secondary {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #F5F5F7;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .success-glow {
            box-shadow: 0 0 30px rgba(241, 200, 75, 0.25);
            border: 2px solid #f1c84b;
        }

        .custom-input {
            background-color: #050505 !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            padding: 10px 14px !important;
            outline: none;
            transition: all 0.3s ease;
        }
        .custom-input:focus {
            border-color: #f1c84b !important;
            box-shadow: 0 0 10px rgba(241, 200, 75, 0.15);
        }

        /* Animação do ícone de espera */
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .spin-slow { animation: spin-slow 2s linear infinite; }

        /* Pulso verde de aprovação */
        @keyframes success-pulse {
            0%, 100% { box-shadow: 0 0 20px rgba(241, 200, 75, 0.2); }
            50%       { box-shadow: 0 0 40px rgba(241, 200, 75, 0.5); }
        }
        .success-pulse { animation: success-pulse 2s ease-in-out infinite; }
    </style>
</head>
<body class="antialiased bg-radial-glow min-h-screen flex items-center justify-center py-12 select-none">
    
    <main class="w-full max-w-[560px] p-6">
        
        <div class="glass-card rounded-xl p-8 space-y-8 text-center relative overflow-hidden">
            
            <?php if ($isApproved): ?>
                <!-- ================= SUCCESS BLOCK ================= -->
                <div class="flex justify-center mb-2">
                    <div class="w-16 h-16 rounded-full success-glow success-pulse flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1; animation: bounce 1s infinite;">check</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl font-bold text-text-main uppercase tracking-widest">Matrícula Confirmada!</h1>
                    <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Acesso Tático Total Liberado com Sucesso</p>
                </div>

                <div class="p-6 bg-black/40 border border-border-color rounded-lg text-left space-y-3 text-xs leading-normal">
                    <div class="flex justify-between">
                        <span class="text-text-muted">Treinamento</span>
                        <span class="text-text-main font-bold"><?php echo htmlspecialchars($transaction['course_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Código Transação</span>
                        <span class="text-primary font-semibold font-mono"><?php echo htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Valor Processado</span>
                        <span class="text-text-main font-bold">R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Forma de Pagamento</span>
                        <span class="text-text-main uppercase tracking-widest text-[9px] font-bold"><?php echo htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="pt-2">
                    <?php if ($transaction['first_lesson_id']): ?>
                        <a href="dashboard/classroom.php?lesson_id=<?php echo (int)$transaction['first_lesson_id']; ?>"
                           class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px] flex items-center justify-center gap-2 mb-3">
                            <span>Iniciar Treinamento Imediato</span>
                            <span class="material-symbols-outlined text-sm">play_circle</span>
                        </a>
                    <?php endif; ?>
                    <a href="dashboard/index.php"
                       class="w-full btn-secondary font-bold py-3 rounded-lg uppercase tracking-[0.15em] text-[10px] flex items-center justify-center gap-2">
                        <span>Ir para Área do Aluno</span>
                        <span class="material-symbols-outlined text-sm">dashboard</span>
                    </a>
                </div>

            <?php else: ?>
                <!-- ================= PENDING BLOCK ================= -->
                
                <!-- Indicador de status em tempo real -->
                <div id="statusIndicator" class="flex justify-center mb-2">
                    <div class="w-16 h-16 rounded-full border-2 border-primary/40 bg-primary/5 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-4xl spin-slow">schedule</span>
                    </div>
                </div>

                <div class="space-y-2" id="statusTitle">
                    <h1 class="text-2xl font-bold text-text-main uppercase tracking-widest">Aguardando Pagamento</h1>
                    <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Finalize o processo para liberar seu acesso</p>
                </div>

                <!-- Barra de progresso do polling -->
                <div class="w-full bg-white/5 rounded-full h-1 overflow-hidden">
                    <div id="pollingBar" class="h-full bg-primary rounded-full transition-all duration-1000" style="width: 0%"></div>
                </div>
                <p class="text-[10px] text-text-muted -mt-2" id="pollingMsg">Verificando pagamento automaticamente...</p>

                <?php if ($transaction['payment_method'] === 'pix'): ?>
                    <!-- Pix Details Box -->
                    <div class="p-6 bg-black/40 border border-border-color rounded-lg text-center space-y-4">
                        <p class="text-[11px] text-text-muted leading-relaxed">
                            Escaneie o QR Code abaixo com o app do seu banco ou utilize a chave Pix Copia e Cola. O acesso é liberado em segundos!
                        </p>
                        
                        <!-- QR Code real -->
                        <div class="w-40 h-40 bg-white rounded-lg p-2 mx-auto flex items-center justify-center border-2 border-primary/20 shadow-2xl">
                            <?php if (!empty($paymentDetails['qr_code_base64'])): ?>
                                <img src="data:image/png;base64,<?php echo $paymentDetails['qr_code_base64']; ?>"
                                     class="w-full h-full object-contain" alt="QR Code Pix">
                            <?php else: ?>
                                <div class="w-full h-full bg-slate-900 flex items-center justify-center text-primary flex-col gap-2">
                                    <span class="material-symbols-outlined text-5xl">qr_code_2</span>
                                    <span class="text-[9px] text-center text-text-muted">QR Code não gerado</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pix Key Copy Paste -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest text-left">Chave Pix Copia e Cola</label>
                            <div class="flex gap-2">
                                <input type="text" readonly
                                       class="custom-input flex-grow rounded text-xs select-all text-ellipsis overflow-hidden"
                                       id="pixKey"
                                       value="<?php echo htmlspecialchars($paymentDetails['qr_code'] ?? 'Não gerado.', ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn-secondary px-4 py-2 rounded text-[10px] uppercase font-bold"
                                        onclick="copyPixKey()">Copiar</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Boleto Details Box -->
                    <div class="p-6 bg-black/40 border border-border-color rounded-lg text-center space-y-4">
                        <p class="text-[11px] text-text-muted leading-relaxed">
                            Seu boleto bancário foi gerado com sucesso. A compensação é feita de 1 a 2 dias úteis. Copie o código de barras abaixo para efetuar o pagamento.
                        </p>

                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest text-left">Linha Digitável do Boleto</label>
                            <div class="flex gap-2">
                                <input type="text" readonly
                                       class="custom-input flex-grow rounded text-xs select-all"
                                       id="boletoCode"
                                       value="<?php echo htmlspecialchars($paymentDetails['barcode'] ?? 'Não gerado.', ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn-secondary px-4 py-2 rounded text-[10px] uppercase font-bold"
                                        onclick="copyBoletoCode()">Copiar</button>
                            </div>
                        </div>

                        <?php if (!empty($paymentDetails['ticket_url'])): ?>
                            <div class="pt-2">
                                <a href="<?php echo htmlspecialchars($paymentDetails['ticket_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank"
                                   class="w-full btn-secondary font-bold py-3.5 rounded-lg uppercase tracking-wider text-[10px] flex items-center justify-center gap-2 transition-all">
                                    <span>Visualizar / Baixar Boleto PDF</span>
                                    <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="pt-2">
                    <a href="dashboard/index.php"
                       class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px] flex items-center justify-center gap-2">
                        <span>Ir para Área do Aluno</span>
                        <span class="material-symbols-outlined text-sm">dashboard</span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        // ─────────────────────────────────────────────
        // Funções de cópia de chaves de pagamento
        // ─────────────────────────────────────────────
        function copyPixKey() {
            const el = document.getElementById('pixKey');
            el.select();
            navigator.clipboard.writeText(el.value).catch(() => document.execCommand('copy'));
            showToast('Chave Pix copiada!');
        }

        function copyBoletoCode() {
            const el = document.getElementById('boletoCode');
            el.select();
            navigator.clipboard.writeText(el.value).catch(() => document.execCommand('copy'));
            showToast('Linha digitável copiada!');
        }

        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 bg-primary text-black font-bold text-xs px-6 py-3 rounded-full shadow-xl z-50 transition-all';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 2500);
        }

        // ─────────────────────────────────────────────
        // POLLING DE PAGAMENTO — só executa se PENDENTE
        // ─────────────────────────────────────────────
        <?php if (!$isApproved): ?>
        (function() {
            const paymentId    = "<?php echo htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8'); ?>";
            const checkUrl     = 'api/checkout/check_payment.php?payment_id=' + encodeURIComponent(paymentId);
            const pollingBar   = document.getElementById('pollingBar');
            const pollingMsg   = document.getElementById('pollingMsg');
            
            let attempts    = 0;
            const maxAttempts = 60; // 3 min (60 × 3s)
            let barWidth    = 0;

            // Animação contínua da barra de progresso
            const barInterval = setInterval(() => {
                barWidth = Math.min(barWidth + (100 / maxAttempts), 98);
                if (pollingBar) pollingBar.style.width = barWidth + '%';
            }, 3000);

            const pollInterval = setInterval(async () => {
                attempts++;

                try {
                    const res  = await fetch(checkUrl);
                    const data = await res.json();

                    // Verifica aprovação — aceita tanto 'approved' quanto 'success'
                    const approved = res.ok && data.success &&
                                     (data.status === 'approved' || data.status === 'success' || data.status === 'paid');

                    if (approved) {
                        clearInterval(pollInterval);
                        clearInterval(barInterval);

                        // Atualiza visual para aprovado antes de redirecionar
                        if (pollingBar) pollingBar.style.width = '100%';
                        if (pollingMsg) pollingMsg.textContent = '✅ Pagamento aprovado! Redirecionando...';

                        const indicator = document.getElementById('statusIndicator');
                        if (indicator) {
                            indicator.innerHTML = `
                                <div class="w-16 h-16 rounded-full success-glow flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined text-4xl" style="font-variation-settings:'FILL' 1;">check_circle</span>
                                </div>`;
                        }

                        const titleEl = document.getElementById('statusTitle');
                        if (titleEl) {
                            titleEl.innerHTML = `
                                <h1 class="text-2xl font-bold text-primary uppercase tracking-widest">Pagamento Aprovado!</h1>
                                <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Redirecionando para sua área de estudos...</p>`;
                        }

                        // Redireciona após 1.5s para o banco ter tempo de atualizar
                        setTimeout(() => {
                            window.location.href = 'confirmation.php?payment_id=' + encodeURIComponent(paymentId) + '&t=' + Date.now();
                        }, 1500);
                        return;
                    }

                    // Expirou o limite de tentativas
                    if (attempts >= maxAttempts) {
                        clearInterval(pollInterval);
                        clearInterval(barInterval);
                        if (pollingMsg) {
                            pollingMsg.textContent = 'Verificação encerrada. Se você já pagou, acesse sua área do aluno.';
                            pollingMsg.className   = 'text-[10px] text-yellow-400 -mt-2';
                        }
                    }

                } catch (err) {
                    console.warn('[GT Polling] Tentativa ' + attempts + ' falhou:', err.message);
                }

            }, 3000); // a cada 3 segundos
        })();
        <?php endif; ?>
    </script>
</body>
</html>
