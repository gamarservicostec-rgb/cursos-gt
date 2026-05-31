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

// Gera token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

$errorMessage = "";
$successMessage = "";

// PROCESSAMENTO DO FORMULÁRIO POST (Ações do Aluno no Suporte)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die("Requisição inválida (falha no token CSRF).");
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_ticket') {
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if (empty($subject) || empty($message)) {
                $errorMessage = "Preencha o assunto e a mensagem inicial do chamado.";
            } else {
                $db->beginTransaction();

                // 1. Cria o cabeçalho do ticket
                $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (:user_id, :subject, 'open')");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':subject' => $subject
                ]);
                $ticketId = $db->lastInsertId();

                // 2. Insere a mensagem inicial no chat
                $msgStmt = $db->prepare("INSERT INTO support_messages (ticket_id, user_id, message) VALUES (:ticket_id, :user_id, :message)");
                $msgStmt->execute([
                    ':ticket_id' => $ticketId,
                    ':user_id' => $userId,
                    ':message' => $message
                ]);

                // Gravar log de auditoria
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (:user_id, 'criar_chamado', :details, :ip)");
                $logStmt->execute([
                    ':user_id' => $userId,
                    ':details' => "Chamado ID {$ticketId} criado pelo aluno",
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $db->commit();
                header("Location: support.php?ticket_id=" . $ticketId . "&success=1");
                exit;
            }
        } elseif ($action === 'send_reply') {
            $ticketId = filter_var($_POST['ticket_id'] ?? 0, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');

            if (!$ticketId || empty($message)) {
                $errorMessage = "Preencha a mensagem de resposta.";
            } else {
                // Valida propriedade do chamado (garante que pertence a este aluno!)
                $checkStmt = $db->prepare("SELECT id, status FROM support_tickets WHERE id = :id AND user_id = :user_id LIMIT 1");
                $checkStmt->execute([':id' => $ticketId, ':user_id' => $userId]);
                $ticket = $checkStmt->fetch();

                if (!$ticket) {
                    throw new Exception("Operação não permitida.");
                }

                $db->beginTransaction();

                // 1. Insere mensagem do aluno no chat
                $insertMsg = $db->prepare("INSERT INTO support_messages (ticket_id, user_id, message) VALUES (:ticket_id, :user_id, :message)");
                $insertMsg->execute([
                    ':ticket_id' => $ticketId,
                    ':user_id' => $userId,
                    ':message' => $message
                ]);

                // 2. Atualiza status de volta para "open" caso tenha sido respondido pelo suporte
                if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed') {
                    $updateTicket = $db->prepare("UPDATE support_tickets SET status = 'open' WHERE id = :id");
                    $updateTicket->execute([':id' => $ticketId]);
                }

                $db->commit();
                header("Location: support.php?ticket_id=" . $ticketId . "&success=2");
                exit;
            }
        }
    } catch (\Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMessage = "Erro ao processar: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $successMessage = "Chamado aberto com sucesso! Nossa equipe de instrutores responderá o mais breve possível.";
    if ($_GET['success'] == 2) $successMessage = "Mensagem enviada com sucesso.";
}

// RECUPERAÇÃO DE DADOS (Chamados do Aluno)
try {
    // 1. Listar apenas os chamados deste aluno
    $ticketsStmt = $db->prepare("
        SELECT id, subject, status, created_at 
        FROM support_tickets 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $ticketsStmt->execute([':user_id' => $userId]);
    $tickets = $ticketsStmt->fetchAll();

    // 2. Chamado ativo selecionado
    $activeTicket = null;
    $chatMessages = [];
    $activeTicketId = isset($_GET['ticket_id']) ? filter_var($_GET['ticket_id'], FILTER_VALIDATE_INT) : null;

    if ($activeTicketId) {
        // Busca e valida cabeçalho do chamado (segurança contra ID de outros alunos!)
        $ticketStmt = $db->prepare("
            SELECT id, subject, status, created_at 
            FROM support_tickets 
            WHERE id = :id AND user_id = :user_id 
            LIMIT 1
        ");
        $ticketStmt->execute([':id' => $activeTicketId, ':user_id' => $userId]);
        $activeTicket = $ticketStmt->fetch();

        if ($activeTicket) {
            // Busca mensagens do chat correspondente
            $msgsStmt = $db->prepare("
                SELECT sm.id, sm.message, sm.created_at, sm.user_id, u.name as sender_name, u.role as sender_role 
                FROM support_messages sm
                JOIN users u ON sm.user_id = u.id
                WHERE sm.ticket_id = :ticket_id
                ORDER BY sm.created_at ASC
            ");
            $msgsStmt->execute([':ticket_id' => $activeTicketId]);
            $chatMessages = $msgsStmt->fetchAll();
        }
    }
} catch (\PDOException $e) {
    die("Erro interno ao carregar suporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Central de Suporte — Cursos GT</title>
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
                        "success": "#00E676"
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
        }
        h1, h2, h3, h4 {
            font-family: 'Clash Display', sans-serif;
        }
        .glass-card {
            background: rgba(20, 20, 23, 0.75);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }
        .input-glass {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            transition: all 0.3s ease;
        }
        .input-glass:focus {
            border-color: #f1c84b !important;
            box-shadow: 0 0 10px rgba(241, 200, 75, 0.15);
            outline: none;
        }
        .status-pill {
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-open {
            background-color: rgba(241, 200, 75, 0.1);
            color: #f1c84b;
            border: 1px solid rgba(241, 200, 75, 0.2);
        }
        .status-progress {
            background-color: rgba(0, 230, 118, 0.1);
            color: #00E676;
            border: 1px solid rgba(0, 230, 118, 0.2);
        }
        .status-resolved {
            background-color: rgba(255, 255, 255, 0.05);
            color: #8F8F9D;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .status-closed {
            background-color: rgba(255, 255, 255, 0.02);
            color: #6B6B76;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(241, 200, 75, 0.15);
            border-radius: 10px;
        }
    </style>
</head>
<body class="antialiased bg-radial-glow min-h-screen flex flex-col">

    <!-- Top Navigation Header -->
    <header class="border-b border-border-color bg-deep-obsidian/85 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="Logo GT Cursos" onerror="this.style.display='none'" class="h-10 w-auto object-contain">
                <span class="font-display text-2xl font-bold tracking-widest uppercase">
                    CURSOS <span class="text-primary">GT</span>
                </span>
            </div>
            
            <nav class="flex items-center gap-6">
                <a href="index.php" class="text-xs font-bold text-text-muted hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">dashboard</span> Painel do Aluno
                </a>
                <a href="../logout.php" class="text-text-muted hover:text-error transition-colors flex items-center" title="Sair da Conta">
                    <span class="material-symbols-outlined text-[22px]">logout</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-6 mt-10 w-full flex-grow flex flex-col pb-16">
        
        <!-- Alerts Status -->
        <?php if (!empty($errorMessage)): ?>
            <div class="p-4 mb-6 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">error</span>
                <span><?php echo $errorMessage; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="p-4 mb-6 rounded-lg bg-success/10 border border-success/20 text-success text-xs font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span><?php echo $successMessage; ?></span>
            </div>
        <?php endif; ?>

        <!-- Welcome Support Header -->
        <div class="glass-card rounded-xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white uppercase">Central de Suporte Operacional</h1>
                <p class="text-text-muted text-xs uppercase font-semibold tracking-wider mt-1">Abra chamados técnicos e pedagógicos diretamente com a nossa diretoria de instrução</p>
            </div>
            <button onclick="openNewTicketModal()" class="bg-primary px-5 py-3 rounded-lg text-deep-obsidian font-bold text-xs uppercase tracking-widest shadow-[0_0_20px_rgba(241,200,75,0.2)] hover:shadow-[0_0_30px_rgba(241,200,75,0.4)] transition-all flex items-center gap-1.5 active:scale-95">
                <span class="material-symbols-outlined text-[16px] font-bold">add</span>
                Abrir Novo Chamado
            </button>
        </div>

        <!-- Chat & History Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start flex-grow">
            
            <!-- Left Side: History of Tickets (5 cols) -->
            <div class="lg:col-span-4 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-widest text-primary mb-2">Seus Chamados e Dúvidas</h3>
                
                <div class="glass-card rounded-xl overflow-hidden divide-y divide-white/5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php 
                            $statusClass = $t['status'] === 'open' ? 'status-open' : ($t['status'] === 'in_progress' ? 'status-progress' : ($t['status'] === 'resolved' ? 'status-resolved' : 'status-closed'));
                            $statusLabel = $t['status'] === 'open' ? 'Aberto' : ($t['status'] === 'in_progress' ? 'Em Progresso' : ($t['status'] === 'resolved' ? 'Resolvido' : 'Fechado'));
                            $activeClass = ($activeTicketId === $t['id']) ? 'bg-primary/5 border-l-2 border-primary' : '';
                            ?>
                            <a href="support.php?ticket_id=<?php echo $t['id']; ?>" class="block p-4 hover:bg-white/[0.02] transition-colors relative <?php echo $activeClass; ?>">
                                <div class="flex justify-between items-start gap-2 mb-2">
                                    <h4 class="text-xs font-bold text-white uppercase truncate pr-4"><?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <span class="status-pill <?php echo $statusClass; ?> shrink-0">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center text-[10px] text-text-muted">
                                    <span>ID: #<?php echo $t['id']; ?></span>
                                    <span><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-text-muted text-xs">
                            <span class="material-symbols-outlined text-4xl block mb-2 opacity-50">support_agent</span>
                            Você não possui nenhum chamado de suporte aberto.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Interactive Chat Display (8 cols) -->
            <div class="lg:col-span-8 flex flex-col h-full min-h-[50vh]">
                <?php if ($activeTicket): ?>
                    <?php 
                    $tStatus = $activeTicket['status'];
                    $tStatusClass = $tStatus === 'open' ? 'status-open' : ($tStatus === 'in_progress' ? 'status-progress' : ($tStatus === 'resolved' ? 'status-resolved' : 'status-closed'));
                    $tStatusLabel = $tStatus === 'open' ? 'Aberto' : ($tStatus === 'in_progress' ? 'Em Progresso' : ($tStatus === 'resolved' ? 'Resolvido' : 'Fechado'));
                    ?>
                    <!-- Chat Header -->
                    <div class="glass-card rounded-t-xl p-4 border-b border-white/5 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-bold text-primary uppercase tracking-widest">Chamado Ativo</span>
                            <h2 class="text-sm font-bold text-white uppercase mt-0.5"><?php echo htmlspecialchars($activeTicket['subject'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <span class="status-pill <?php echo $tStatusClass; ?>">
                            <?php echo $tStatusLabel; ?>
                        </span>
                    </div>

                    <!-- Chat Messages list -->
                    <div class="glass-card border-t-0 p-6 flex-grow max-h-[50vh] overflow-y-auto custom-scrollbar flex flex-col gap-4" style="background: rgba(14, 14, 17, 0.4);" id="chatMessagesBox">
                        <?php foreach ($chatMessages as $msg): ?>
                            <?php 
                            $isAdmin = ($msg['sender_role'] === 'admin');
                            $bubbleAlign = $isAdmin ? 'self-start bg-white/5 border border-white/5 text-left' : 'self-end bg-primary/10 border border-primary/20 text-left';
                            $senderTag = $isAdmin ? '<span class="text-[9px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded ml-2 uppercase">Suporte GT</span>' : '';
                            ?>
                            <div class="max-w-[80%] rounded-xl p-4 <?php echo $bubbleAlign; ?>">
                                <div class="flex items-center justify-between mb-1.5 gap-4">
                                    <span class="text-[10px] font-bold text-white"><?php echo htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8') . $senderTag; ?></span>
                                    <span class="text-[9px] text-text-muted"><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-text-main leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Chat Form Input -->
                    <?php if ($activeTicket['status'] !== 'closed'): ?>
                        <div class="glass-card border-t-0 rounded-b-xl p-4 bg-deep-obsidian/40">
                            <form method="POST" action="support.php" class="flex gap-2 mb-0">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="send_reply">
                                <input type="hidden" name="ticket_id" value="<?php echo $activeTicket['id']; ?>">
                                
                                <textarea name="message" required rows="1" placeholder="Escreva sua resposta para o instrutor..." class="flex-grow px-4 py-3 rounded-lg input-glass text-xs resize-none h-11 focus:h-20 transition-all custom-scrollbar"></textarea>
                                <button type="submit" class="bg-primary hover:bg-gold-light text-deep-obsidian font-bold px-5 rounded-lg flex items-center justify-center shrink-0 shadow-glow active:scale-95 transition-all">
                                    <span class="material-symbols-outlined">send</span>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="glass-card border-t-0 rounded-b-xl p-4 text-center text-xs text-text-muted bg-black/40">
                            Este chamado foi finalizado pelo suporte e está fechado para novas mensagens.
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Fallback / No ticket selected -->
                    <div class="glass-card rounded-xl p-12 text-center flex flex-col items-center justify-center gap-3 flex-grow bg-black/20">
                        <span class="material-symbols-outlined text-muted text-5xl animate-pulse">chat</span>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-white">Nenhum Canal Aberto</h4>
                        <p class="text-xs text-text-muted max-w-sm leading-relaxed">Selecione um chamado na barra lateral para interagir ou abra um novo ticket para falar diretamente com a nossa diretoria de instrutores.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal: Novo Chamado -->
    <div id="newTicketModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm hidden p-4">
        <div class="w-full max-w-md p-6 glass-card rounded-xl shadow-2xl relative" style="background: #0d0d10;">
            <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-widest">Abrir Canal de Suporte</h3>
                <button onclick="closeNewTicketModal()" class="material-symbols-outlined text-text-muted hover:text-white transition-colors">close</button>
            </div>
            <form method="POST" action="support.php" class="space-y-4 mb-0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_ticket">
                
                <div>
                    <label class="block text-[10px] font-bold text-primary uppercase tracking-widest mb-2">Assunto / Tópico Principal</label>
                    <input name="subject" required type="text" placeholder="Ex: Dúvida de Redes no Módulo 2" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-primary uppercase tracking-widest mb-2">Mensagem Detalhada</label>
                    <textarea name="message" required rows="4" placeholder="Descreva sua dúvida ou problema para que possamos auxiliá-lo com precisão..." class="w-full px-4 py-3 rounded-lg input-glass text-xs resize-none custom-scrollbar"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-white/5">
                    <button type="button" onclick="closeNewTicketModal()" class="px-5 py-2.5 rounded border border-white/10 text-text-muted hover:text-white hover:bg-white/5 text-xs font-bold uppercase tracking-widest transition-all">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded bg-primary text-deep-obsidian text-xs font-bold uppercase tracking-widest hover:bg-gold-light transition-all shadow-glow">Enviar Chamado</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewTicketModal() {
            document.getElementById('newTicketModal').classList.remove('hidden');
        }
        function closeNewTicketModal() {
            document.getElementById('newTicketModal').classList.add('hidden');
        }

        // Rola o chat de mensagens para o final
        document.addEventListener("DOMContentLoaded", function() {
            const chatBox = document.getElementById("chatMessagesBox");
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    </script>
</body>
</html>
