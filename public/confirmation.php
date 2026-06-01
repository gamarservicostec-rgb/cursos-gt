<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';

// Requer que o aluno esteja logado
\Middleware\AuthMiddleware::requireStudent();

$userId = $_SESSION['user_id'];
$paymentId = $_GET['payment_id'] ?? '';

if (empty($paymentId)) {
    header("Location: dashboard/index.php");
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca dados da transação (incluindo status real da transação no banco)
    $transStmt = $db->prepare("
        SELECT t.amount, t.payment_method, t.status as trans_status, t.payment_details, c.title as course_title, l.id as first_lesson_id
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
        ':user_id' => $userId
    ]);
    $transaction = $transStmt->fetch();

    if (!$transaction) {
        die("<h1>Pedido não localizado</h1><p>A transação solicitada não pertence a este usuário ou não existe.</p><a href='dashboard/index.php'>Ir ao Painel</a>");
    }

    // O status real vem da transação no banco de dados local
    $status = $transaction['trans_status'] ?? 'pending';
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
    </style>
</head>
<body class="antialiased bg-radial-glow min-h-screen flex items-center justify-center py-12 select-none">
    
    <main class="w-full max-w-[560px] p-6">
        
        <div class="glass-card rounded-xl p-8 space-y-8 text-center relative overflow-hidden">
            
            <?php if ($status === 'success'): ?>
                <!-- ================= SUCCESS BLOCK ================= -->
                <div class="flex justify-center mb-2">
                    <div class="w-16 h-16 rounded-full success-glow flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-4xl animate-bounce" style="font-variation-settings: 'FILL' 1;">check</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl font-bold text-text-main uppercase tracking-widest">Matrícula Confirmada!</h1>
                    <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Acesso Tático Total Liberado com Sucesso</p>
                </div>

                <div class="p-6 bg-black/40 border border-border-color rounded-lg text-left space-y-3 text-xs leading-normal">
                    <div class="flex justify-between">
                        <span class="text-text-muted">Treinamento</span>
                        <span class="text-text-main font-bold"><?php echo $transaction['course_title']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Código Transação</span>
                        <span class="text-primary font-semibold font-mono"><?php echo $paymentId; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Valor Processado</span>
                        <span class="text-text-main font-bold">R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Forma de Pagamento</span>
                        <span class="text-text-main uppercase tracking-widest text-[9px] font-bold"><?php echo $transaction['payment_method']; ?></span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="dashboard/classroom.php?lesson_id=<?php echo $transaction['first_lesson_id']; ?>" class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px] flex items-center justify-center gap-2">
                        <span>Iniciar Treinamento Imediato</span>
                        <span class="material-symbols-outlined text-sm">play_circle</span>
                    </a>
                </div>
            <?php else: ?>
                <!-- ================= PENDING / IN PROCESS BLOCK ================= -->
                
                <div class="flex justify-center mb-2">
                    <div class="w-16 h-16 rounded-full border-2 border-primary/40 bg-primary/5 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-4xl animate-spin">schedule</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl font-bold text-text-main uppercase tracking-widest">Aguardando Pagamento</h1>
                    <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Finalize o processo para liberar seu acesso</p>
                </div>

                <?php if ($transaction['payment_method'] === 'pix'): ?>
                    <!-- Pix Details Box -->
                    <div class="p-6 bg-black/40 border border-border-color rounded-lg text-center space-y-4">
                        <p class="text-[11px] text-text-muted leading-relaxed">
                            Escaneie o QR Code abaixo com o app do seu banco ou utilize a chave Pix Copia e Cola. O acesso é liberado em segundos!
                        </p>
                        
                        <!-- Simulated QR Code visual -->
                        <div class="w-36 h-36 bg-white rounded-lg p-2 mx-auto flex items-center justify-center border-2 border-primary/20 shadow-2xl">
                            <?php if (!empty($paymentDetails['qr_code_base64'])): ?>
                                <img src="data:image/png;base64,<?php echo $paymentDetails['qr_code_base64']; ?>" class="w-full h-full object-contain" alt="QR Code Pix">
                            <?php else: ?>
                                <!-- Fallback se não tiver base64 -->
                                <div class="w-full h-full bg-slate-900 flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined text-5xl">qr_code_2</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pix Key Copy Paste -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest text-left">Chave Pix Copia e Cola</label>
                            <div class="flex gap-2">
                                <input type="text" readonly class="custom-input flex-grow rounded text-xs select-all text-ellipsis overflow-hidden" id="pixKey" value="<?php echo htmlspecialchars($paymentDetails['qr_code'] ?? 'Não gerado.', ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn-secondary px-4 py-2 rounded text-[10px] uppercase font-bold" onclick="copyPixKey()">Copiar</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Boleto Details Box -->
                    <div class="p-6 bg-black/40 border border-border-color rounded-lg text-center space-y-4">
                        <p class="text-[11px] text-text-muted leading-relaxed">
                            Seu boleto bancário foi gerado com sucesso. A compensação é feita de 1 a 2 dias úteis. Copie o código de barras abaixo para efetuar o pagamento.
                        </p>

                        <!-- Simulated Barcode details -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-bold text-primary uppercase tracking-widest text-left">Linha Digitável do Boleto</label>
                            <div class="flex gap-2">
                                <input type="text" readonly class="custom-input flex-grow rounded text-xs select-all" id="boletoCode" value="<?php echo htmlspecialchars($paymentDetails['barcode'] ?? 'Não gerado.', ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn-secondary px-4 py-2 rounded text-[10px] uppercase font-bold" onclick="copyBoletoCode()">Copiar</button>
                            </div>
                        </div>

                        <?php if (!empty($paymentDetails['ticket_url'])): ?>
                            <div class="pt-2">
                                <a href="<?php echo htmlspecialchars($paymentDetails['ticket_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="w-full btn-secondary font-bold py-3.5 rounded-lg uppercase tracking-wider text-[10px] flex items-center justify-center gap-2 transition-all">
                                    <span>Visualizar / Baixar Boleto PDF</span>
                                    <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="pt-2">
                    <a href="dashboard/index.php" class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[11px] flex items-center justify-center gap-2">
                        <span>Ir para Área do Aluno</span>
                        <span class="material-symbols-outlined text-sm">dashboard</span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        function copyPixKey() {
            const pixInput = document.getElementById('pixKey');
            pixInput.select();
            document.execCommand('copy');
            alert('Chave Pix copiada com sucesso!');
        }

        function copyBoletoCode() {
            const boletoInput = document.getElementById('boletoCode');
            boletoInput.select();
            document.execCommand('copy');
            alert('Linha digitável copiada com sucesso!');
        }

        <?php if ($status !== 'approved' && $status !== 'success'): ?>
        // Mecanismo de Polling Inteligente e Redundante
        const paymentId = "<?php echo htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8'); ?>";
        const intervalId = setInterval(async () => {
            try {
                const response = await fetch('api/checkout/check_payment.php?payment_id=' + encodeURIComponent(paymentId));
                const data = await response.json();
                
                if (response.ok && data.success && data.status === 'approved') {
                    clearInterval(intervalId);
                    // Redireciona para exibir a tela de Matrícula Confirmada instantaneamente
                    window.location.href = 'confirmation.php?status=success&payment_id=' + encodeURIComponent(paymentId);
                }
            } catch (err) {
                console.error("Erro no polling de pagamento:", err);
            }
        }, 3000); // Polling a cada 3 segundos
        <?php endif; ?>
    </script>
</body>
</html>
