<?php
use Config\Database;
use Config\AppConfig;
use Middleware\SecurityHeaders;

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Middleware/SecurityHeaders.php';

// Inicia sessão sem exigir login — checkout aceita visitantes
AppConfig::startSession();

// Detecta se já está logado
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userId    = $isLoggedIn ? $_SESSION['user_id'] : null;
$userName  = $isLoggedIn ? ($_SESSION['user_name'] ?? '')  : '';
$userEmail = $isLoggedIn ? ($_SESSION['user_email'] ?? '') : '';

$courseId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$courseId) {
    header("Location: index.php");
    exit;
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // Busca dados do curso (available_hours migrado automaticamente)
    $courseStmt = $db->prepare("SELECT id, title, description, price, thumbnail_url, type, available_hours, access_type, certificate_info FROM courses WHERE id = :id AND status = 'active' LIMIT 1");
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course) {
        die("<h1>Curso indisponível</h1><p>O treinamento solicitado não está ativo.</p><a href='index.php'>Voltar</a>");
    }

    // Se já logado, verifica matrícula ativa
    if ($isLoggedIn) {
        $enrollStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = :user_id AND course_id = :course_id AND status = 'active' LIMIT 1");
        $enrollStmt->execute([':user_id' => $userId, ':course_id' => $courseId]);
        if ($enrollStmt->fetch()) {
            header("Location: dashboard/classroom.php");
            exit;
        }
    }

} catch (\PDOException $e) {
    die("Erro interno ao recuperar checkout: " . $e->getMessage());
}

$csrfToken = \Middleware\SecurityHeaders::generateCSRFToken();
$courseImage = !empty($course['thumbnail_url']) ? $course['thumbnail_url'] : 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=1470&auto=format&fit=crop';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Cursos GT - Checkout Premium</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&amp;family=Outfit:wght@300;400;500;600&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2c94c",
                        "obsidian": "#050505",
                        "surface": "rgba(25, 25, 25, 0.6)",
                        "text-main": "#EAEAEA",
                        "muted": "#8F8F9D",
                        "border-gold": "rgba(242, 201, 76, 0.2)",
                        "error": "#FF3B30",
                        "success": "#00C853"
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "body": ["Outfit", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "0.75rem",
                        "xl": "1.5rem",
                    },
                    boxShadow: {
                        'glow-gold': '0 0 20px rgba(242, 201, 76, 0.15)',
                        'neon-gold': '0 0 40px rgba(242, 201, 76, 0.1)',
                    }
                },
            },
        }
    </script>
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: #000000; 
            color: #EAEAEA; 
        } 
        h1, h2, h3, h4 { 
            font-family: 'Space Grotesk', sans-serif; 
        } 
        .glass-panel { 
            background-color: #0a0a0a; 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        } 
        .floating-input-container { 
            position: relative; 
        } 
        .floating-input { 
            width: 100%; 
            height: 52px; 
            background-color: #0a0a0a; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 0.75rem; 
            color: #ffffff; 
            padding: 20px 16px 4px 16px; 
            font-size: 15px; 
            transition: all 0.2s ease; 
        } 
         /* Combate ao autofill do Chrome - Força fundo escuro e cor de fonte branca */
         .floating-input:-webkit-autofill,
         .floating-input:-webkit-autofill:hover, 
         .floating-input:-webkit-autofill:focus, 
         .floating-input:-webkit-autofill:active {
             -webkit-text-fill-color: #ffffff !important;
             -webkit-box-shadow: 0 0 0 30px #0a0a0a inset !important;
             box-shadow: 0 0 0 30px #0a0a0a inset !important;
             transition: background-color 5000s ease-in-out 0s;
         }
        .floating-input::placeholder { 
            color: #4d4635; 
        } 
        .floating-input:focus { 
            outline: none; 
            border-color: #f2c94c; 
            background-color: #0d0d0d; 
            box-shadow: 0 0 15px rgba(242, 201, 76, 0.15); 
        } 
        .floating-label { 
            position: absolute; 
            top: 14px; 
            left: 16px; 
            color: #ffffff; 
            font-size: 15px; 
            transition: all 0.2s ease; 
            pointer-events: none; 
            opacity: 0.8; 
            background: transparent; 
        } 
        .floating-input:focus ~ .floating-label, .floating-input:not(:placeholder-shown) ~ .floating-label { 
            top: 6px; 
            font-size: 11px; 
            color: #f2c94c; 
            opacity: 1; 
            background: transparent; 
        } 
        .payment-card { 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        } 
        .payment-card.selected { 
            border-color: #f2c94c; 
            background: rgba(242, 201, 76, 0.05); 
            box-shadow: 0 0 20px rgba(242, 201, 76, 0.1); 
        } 
        .cinematic-glow { 
            position: fixed; 
            width: 60vw; 
            height: 60vw; 
            border-radius: 50%; 
            filter: blur(120px); 
            z-index: -1; 
            opacity: 0.05; 
            pointer-events: none; 
        }
    </style>
</head>
<body class="min-h-screen flex flex-col relative overflow-x-hidden" style="background-color: #000000;">
    <!-- Cinematic Glows -->
    <div class="cinematic-glow bg-primary top-[-30%] left-[-15%] blur-[150px] opacity-5 scale-50"></div>
    <div class="cinematic-glow bg-primary bottom-[-30%] right-[-15%] blur-[150px] opacity-5 scale-50"></div>
    
    <!-- Header with Logo -->
    <header class="w-full p-6 lg:px-12 flex justify-between items-center z-10 border-b border-white/5 bg-black/60">
        <div class="flex items-center gap-4">
            <img alt="GT Cursos Logo" class="h-10 lg:h-12 w-auto object-contain" src="assets/images/logo.png">
        </div>
        <button class="flex items-center gap-2 text-muted hover:text-primary transition-colors text-sm font-medium uppercase tracking-widest" onclick="history.back()">
            <div class="hidden md:flex items-center gap-6 mr-8 border-r border-white/10 pr-8">
                <div class="flex items-center gap-2 text-[10px] text-muted uppercase tracking-[0.2em] font-bold">
                    <span class="material-symbols-outlined text-[16px] text-success">lock</span>
                    <span>SSL ENCRYPTED</span>
                </div>
                <div class="flex items-center gap-2 text-[10px] text-muted uppercase tracking-[0.2em] font-bold">
                    <span class="material-symbols-outlined text-[16px]">verified_user</span>
                    <span>SECURE CHECKOUT</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            Voltar
        </button>
    </header>

    <!-- Main Content Grid -->
    <main class="flex-1 w-full max-w-[1280px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 px-6 lg:px-12 py-8">
        
        <!-- LEFT COLUMN: Course Info (5 cols) -->
        <div class="lg:col-span-5 flex flex-col gap-8">
            <div class="relative group">
                <!-- Image Glow -->
                <div class="absolute -inset-1 bg-gradient-to-r from-primary/20 to-transparent rounded-xl blur opacity-25 group-hover:opacity-40 transition duration-1000"></div>
                <div class="relative overflow-hidden rounded-xl border border-white/10 shadow-2xl">
                    <img alt="<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-auto aspect-video object-cover scale-105 group-hover:scale-100 transition-transform duration-700 bg-neutral-900" src="<?php echo $courseImage; ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-transparent to-transparent"></div>
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <span class="text-sm font-bold tracking-[0.2em] uppercase text-primary">Módulo Avançado</span>
                <h1 class="text-3xl lg:text-4xl font-bold leading-tight text-white"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted text-base font-light leading-relaxed">
                    <?php echo htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
            <?php 
            $hasAccessBadge = !empty($course['access_type']);
            $hasCertBadge = !empty($course['certificate_info']);
            if ($hasAccessBadge || $hasCertBadge): 
                $gridCols = ($hasAccessBadge && $hasCertBadge) ? 'grid-cols-2' : 'grid-cols-1';
            ?>
            <div class="grid <?php echo $gridCols; ?> gap-4 mt-4">
                <?php if ($hasAccessBadge): ?>
                <div class="glass-panel p-4 rounded-lg flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">verified_user</span>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($course['access_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($hasCertBadge): ?>
                <div class="glass-panel p-4 rounded-lg flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">workspace_premium</span>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($course['certificate_info'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: Payment Flow (7 cols) -->
        <div class="lg:col-span-7 flex flex-col gap-6">
            
            <!-- TOP SECTION: Order Summary -->
            <section class="glass-panel rounded-xl p-6 lg:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 blur-3xl rounded-full translate-x-1/2 -translate-y-1/2"></div>
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">shopping_bag</span>
                    Resumo do Pedido
                </h2>
                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-muted">Subtotal do curso</span>
                        <span class="text-white font-medium" id="courseSubtotal">R$ <?php echo number_format($course['price'], 2, ',', '.'); ?></span>
                    </div>
                    
                    <!-- Coupon Input -->
                    <div class="flex gap-2 mt-2">
                        <input class="flex-1 border border-white/10 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-primary transition-all bg-[#0a0a0a]" id="couponInput" placeholder="Código de cupom" type="text">
                        <button class="px-4 py-2 bg-primary/10 border border-primary/20 text-primary rounded-lg text-sm font-bold hover:bg-primary/20 transition-all uppercase tracking-wider" onclick="applyCoupon()">Aplicar</button>
                    </div>
                    <div class="hidden justify-between items-center text-sm border-t border-white/5 pt-4 text-success" id="discountRow">
                        <span class="text-success font-medium">Desconto aplicado (<span id="couponCodeDisplay"></span>)</span>
                        <span class="font-bold" id="discountValue">- R$ 0,00</span>
                    </div>
                    
                    <div class="flex justify-between items-end pt-2 border-t border-white/5 mt-2">
                        <div class="flex flex-col">
                            <span class="text-xs text-muted uppercase tracking-widest font-bold">Total a Investir</span>
                            <span class="text-3xl font-bold text-primary tracking-tighter" id="totalPrice">R$ <?php echo number_format($course['price'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-muted block">ou 12x de</span>
                            <span class="text-xl font-bold text-white" id="installmentPrice">R$ <?php echo number_format($course['price'] / 12, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- BOTTOM SECTION: Payment Selector & Form -->
            <section class="glass-panel rounded-xl p-6 lg:p-8">
                <h2 class="text-xl font-bold text-white mb-6">Método de Pagamento</h2>
                
                <!-- Payment Options Grid -->
                <div class="grid grid-cols-3 gap-4 mb-8">
                    <button class="payment-card selected flex flex-col items-center justify-center p-4 rounded-xl border border-primary bg-primary/10 transition-all text-center" id="btn-pix" onclick="selectPaymentMethod('pix')">
                        <span class="material-symbols-outlined mb-2 text-3xl text-primary">qr_code_2</span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-white">PIX</span>
                    </button>
                    <button class="payment-card flex flex-col items-center justify-center p-4 rounded-xl border border-white/10 bg-white/5 hover:border-primary/50 transition-all text-center" id="btn-credit_card" onclick="selectPaymentMethod('credit_card')">
                        <span class="material-symbols-outlined mb-2 text-3xl text-muted">credit_card</span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Cartão</span>
                    </button>
                    <button class="payment-card flex flex-col items-center justify-center p-4 rounded-xl border border-white/10 bg-white/5 hover:border-primary/50 transition-all text-center" id="btn-boleto" onclick="selectPaymentMethod('boleto')">
                        <span class="material-symbols-outlined mb-2 text-3xl text-muted">receipt_long</span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Boleto</span>
                    </button>
                </div>

                <!-- Form Flow Container -->
                <form class="flex flex-col gap-6" id="checkoutForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                    <input type="hidden" name="payment_method" id="selectedMethod" value="pix">
                    <input type="hidden" name="coupon" id="selectedCoupon" value="">

                    <?php if (!$isLoggedIn): ?>
                    <!-- Seção: Dados Pessoais para Criar Conta + Comprar em 1 Passo -->
                    <div class="p-6 rounded-xl border border-primary/20 bg-primary/5 flex flex-col gap-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-primary text-xl">person_add</span>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Seus Dados de Acesso</h3>
                        </div>
                        <p class="text-[11px] text-muted -mt-2 leading-relaxed">Preencha abaixo para criar sua conta e finalizar a compra em um único passo. Você usará estes dados para acessar as aulas.</p>
                        <div class="floating-input-container">
                            <input class="floating-input" id="guestName" name="guest_name" placeholder="Seu Nome Completo" type="text" required autocomplete="name">
                            <label class="floating-label" for="guestName">Nome Completo</label>
                        </div>
                        <div class="floating-input-container">
                            <input class="floating-input" id="guestEmail" name="guest_email" placeholder="seu@email.com" type="email" required autocomplete="email">
                            <label class="floating-label" for="guestEmail">E-mail</label>
                        </div>
                        <div class="floating-input-container">
                            <input class="floating-input" id="guestPassword" name="guest_password" placeholder="Mínimo 6 caracteres" type="password" required autocomplete="new-password" minlength="6">
                            <label class="floating-label" for="guestPassword">Crie sua Senha</label>
                        </div>
                        <p class="text-[10px] text-muted">Já tem conta? <a href="login.php" class="text-primary underline hover:text-yellow-300 transition-colors">Faça login aqui</a> e volte para comprar.</p>
                    </div>
                    <?php else: ?>
                    <!-- Usuário logado: exibe boas vindas -->
                    <div class="p-4 rounded-xl border border-white/10 bg-white/[0.03] flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings:'FILL' 1">account_circle</span>
                        <div>
                            <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-[10px] text-muted"><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <a href="logout.php" class="ml-auto text-[10px] text-muted hover:text-primary uppercase tracking-widest font-bold transition-colors">Sair</a>
                    </div>
                    <?php endif; ?>

                    <?php if ($course['type'] === 'hybrid'): ?>
                        <!-- Seletor Premium de Horário Presencial (Obsidian Gold) -->
                        <div class="p-6 rounded-xl bg-gradient-to-br from-primary/10 to-transparent border border-border-gold flex flex-col gap-4">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-xl">schedule</span>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Agendamento Presencial</h3>
                            </div>
                            <p class="text-[11px] text-muted leading-relaxed">
                                Este treinamento é da modalidade **Híbrida**. Por favor, selecione abaixo o seu horário disponível para as instruções práticas presenciais que serão realizadas na nossa unidade física:
                            </p>
                            <div>
                                <select name="schedule_time" id="scheduleTimeSelect" required class="w-full bg-[#0a0a0a] border border-white/10 rounded-xl py-3.5 px-4 text-white text-xs font-semibold focus:border-primary outline-none cursor-pointer transition-all">
                                    <option value="" disabled selected>-- Selecione seu Horário de Aula --</option>
                                    <?php 
                                    $hoursList = !empty($course['available_hours']) ? explode(',', $course['available_hours']) : ['08:00 às 10:00', '10:00 às 12:00', '14:00 às 16:00', '19:00 às 21:00'];
                                    foreach ($hoursList as $hour) {
                                        $hourTrimmed = htmlspecialchars(trim($hour), ENT_QUOTES, 'UTF-8');
                                        echo "<option value=\"{$hourTrimmed}\">{$hourTrimmed}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- PIX Form View -->
                    <div class="space-y-4" id="form-pix">
                        <div class="p-4 bg-primary/5 border border-primary/20 rounded-lg flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-xl mt-0.5 font-bold">info</span>
                            <p class="text-[11px] text-[#EAEAEA] leading-relaxed font-medium">
                                O pagamento via PIX é processado de forma instantânea. Logo após clicar em finalizar compra, o QR Code e código Copia e Cola serão exibidos na tela para você pagar em qualquer banco!
                            </p>
                        </div>
                    </div>

                    <!-- Credit Card Form View -->
                    <div class="hidden flex flex-col gap-6" id="form-credit_card">
                        <div class="floating-input-container">
                            <input autocomplete="cc-number" class="floating-input" id="cardNumber" placeholder="0000 0000 0000 0000" type="text">
                            <label class="floating-label" for="cardNumber">Número do Cartão</label>
                        </div>
                        <div class="floating-input-container">
                            <input autocomplete="cc-name" class="floating-input" id="cardName" placeholder="Nome no cartão" type="text">
                            <label class="floating-label" for="cardName">Nome Completo (como no cartão)</label>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="floating-input-container">
                                <input autocomplete="cc-exp" class="floating-input" id="cardExpiry" placeholder="MM/AA" type="text">
                                <label class="floating-label" for="cardExpiry">Validade</label>
                            </div>
                            <div class="floating-input-container">
                                <input autocomplete="cc-csc" class="floating-input" id="cardCvc" placeholder="123" type="password">
                                <label class="floating-label" for="cardCvc">CVV</label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-primary uppercase tracking-wider mb-2">Parcelamento</label>
                            <select class="w-full bg-[#0a0a0a] border border-white/10 rounded-xl py-3 px-4 text-white text-sm focus:border-primary outline-none" id="installmentsSelect">
                                <option value="1">1x de R$ <?php echo number_format($course['price'], 2, ',', '.'); ?> sem juros</option>
                                <option value="2">2x de R$ <?php echo number_format($course['price'] / 2, 2, ',', '.'); ?> sem juros</option>
                                <option value="3">3x de R$ <?php echo number_format($course['price'] / 3, 2, ',', '.'); ?> sem juros</option>
                                <option value="6">6x de R$ <?php echo number_format($course['price'] / 6, 2, ',', '.'); ?> sem juros</option>
                                <option value="12">12x de R$ <?php echo number_format($course['price'] / 12, 2, ',', '.'); ?> sem juros</option>
                            </select>
                        </div>
                    </div>

                    <!-- Boleto Form View -->
                    <div class="hidden flex flex-col gap-6" id="form-boleto">
                        <div class="p-4 bg-primary/5 border border-primary/20 rounded-lg flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-xl mt-0.5 font-bold">info</span>
                            <p class="text-[11px] text-[#EAEAEA] leading-relaxed font-medium">
                                O boleto bancário pode levar de 1 a 2 dias úteis para compensar após o pagamento na rede bancária ou lotéricas.
                            </p>
                        </div>
                        <div class="floating-input-container">
                            <input class="floating-input" id="boletoCpf" placeholder="000.000.000-00" type="text">
                            <label class="floating-label" for="boletoCpf">CPF do Titular</label>
                        </div>
                    </div>

                    <!-- Submit Area -->
                    <div class="flex flex-col gap-2 mt-4">
                        <button class="w-full h-14 bg-primary text-obsidian rounded-xl font-bold text-sm uppercase tracking-[0.2em] transition-all hover:brightness-110 hover:shadow-[0_0_30px_rgba(242,201,76,0.3)] active:scale-[0.98] flex items-center justify-center gap-3" type="submit" id="submitBtn">
                            <span class="material-symbols-outlined font-bold">shield</span>
                            <span id="btnText">Finalizar Compra Segura</span>
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-black hidden" id="loadingSpinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                        <div class="flex items-center justify-center gap-2 mt-2 opacity-60">
                            <span class="material-symbols-outlined text-[16px]">lock</span>
                            <span class="text-[11px] uppercase tracking-widest">Processamento Criptografado SSL</span>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <!-- Footer Security -->
    <footer class="w-full py-8 px-6 flex flex-wrap justify-center items-center gap-8 lg:gap-16 opacity-40 grayscale hover:grayscale-0 transition-all duration-500 border-t border-white/5">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined">security</span>
            <span class="text-xs font-bold uppercase tracking-widest">PCI Compliance</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined">verified</span>
            <span class="text-xs font-bold uppercase tracking-widest">GT Secure</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined">gpp_good</span>
            <span class="text-xs font-bold uppercase tracking-widest">Safe Checkout</span>
        </div>
    </footer>

    <!-- Custom Checkout Scripts -->
    <script>
        const coursePrice = <?php echo (float)$course['price']; ?>;
        let finalPrice = coursePrice;
        let activeCoupon = "";

        // Simple input masks
        const cardNumber = document.getElementById('cardNumber');
        if (cardNumber) {
            cardNumber.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.match(/.{1,4}/g)?.join(' ') || '';
                e.target.value = value.substring(0, 19);
            });
        }

        const cardExpiry = document.getElementById('cardExpiry');
        if (cardExpiry) {
            cardExpiry.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        }

        const cardCvc = document.getElementById('cardCvc');
        if (cardCvc) {
            cardCvc.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            });
        }

        const boletoCpf = document.getElementById('boletoCpf');
        if (boletoCpf) {
            boletoCpf.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 9) {
                    value = value.replace(/(\D|^)(\d{3})(\d{3})(\d{3})(\d{2})/, "$2.$3.$4-$5");
                }
                e.target.value = value.substring(0, 14);
            });
        }

        // Apply coupon promo logic (dynamic fetch integration)
        async function applyCoupon() {
            const couponInput = document.getElementById('couponInput').value.toUpperCase().trim();
            if (couponInput === "") return;

            try {
                const response = await fetch(`api/checkout/validate_coupon.php?code=${couponInput}&course_id=<?php echo $courseId; ?>`);
                const res = await response.json();

                if (!response.ok || !res.success) {
                    alert(res.error || "Cupom de desconto inválido ou expirado.");
                    return;
                }

                const discount = res.discount;
                finalPrice = res.final_price;
                activeCoupon = res.code;

                // Update UI elements
                document.getElementById('selectedCoupon').value = activeCoupon;
                document.getElementById('couponCodeDisplay').innerText = activeCoupon;
                document.getElementById('discountValue').innerText = "- R$ " + discount.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                document.getElementById('discountRow').classList.remove('hidden');
                document.getElementById('discountRow').classList.add('flex');
                
                document.getElementById('totalPrice').innerText = "R$ " + finalPrice.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                document.getElementById('installmentPrice').innerText = "R$ " + (finalPrice / 12).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

                // Update installment select dropdown options dynamically
                const select = document.getElementById('installmentsSelect');
                if (select) {
                    select.innerHTML = `
                        <option value="1">1x de R$ ${finalPrice.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} sem juros</option>
                        <option value="2">2x de R$ ${(finalPrice / 2).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} sem juros</option>
                        <option value="3">3x de R$ ${(finalPrice / 3).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} sem juros</option>
                        <option value="6">6x de R$ ${(finalPrice / 6).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} sem juros</option>
                        <option value="12">12x de R$ ${(finalPrice / 12).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} sem juros</option>
                    `;
                }
                
                alert("Cupom promocional aplicado com sucesso!");
            } catch (err) {
                alert("Erro ao validar cupom: " + err.message);
            }
        }

        // Select Payment Method tab
        function selectPaymentMethod(method) {
            document.getElementById('selectedMethod').value = method;

            // Remove selected classes
            const btns = ['pix', 'credit_card', 'boleto'];
            btns.forEach(b => {
                const button = document.getElementById(`btn-${b}`);
                const icon = button.querySelector('span');
                const label = button.querySelectorAll('span')[1];

                button.className = "payment-card flex flex-col items-center justify-center p-4 rounded-xl border border-white/10 bg-white/5 hover:border-primary/50 transition-all text-center";
                icon.className = "material-symbols-outlined mb-2 text-3xl text-muted";
                label.className = "text-[10px] font-bold uppercase tracking-wider text-muted";
            });

            // Add selected to active
            const activeBtn = document.getElementById(`btn-${method}`);
            const activeIcon = activeBtn.querySelector('span');
            const activeLabel = activeBtn.querySelectorAll('span')[1];

            activeBtn.className = "payment-card selected flex flex-col items-center justify-center p-4 rounded-xl border border-primary bg-primary/10 transition-all text-center";
            activeIcon.className = "material-symbols-outlined mb-2 text-3xl text-primary";
            activeLabel.className = "text-[10px] font-bold uppercase tracking-wider text-white";

            // Hide/Show Forms
            document.getElementById('form-pix').classList.add('hidden');
            document.getElementById('form-credit_card').classList.add('hidden');
            document.getElementById('form-boleto').classList.add('hidden');

            document.getElementById(`form-${method}`).classList.remove('hidden');
        }

        // Form Submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const method = document.getElementById('selectedMethod').value;
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const loadingSpinner = document.getElementById('loadingSpinner');

            submitBtn.disabled = true;
            btnText.textContent = 'Processando Pagamento Seguro...';
            loadingSpinner.classList.remove('hidden');

            const scheduleSelect = document.getElementById('scheduleTimeSelect');
            let payload = {
                course_id: <?php echo $courseId; ?>,
                payment_method: method,
                payment_method_id: method === 'pix' ? 'pix' : (method === 'boleto' ? 'bolbradesco' : 'visa'),
                coupon: activeCoupon,
                schedule_time: scheduleSelect ? scheduleSelect.value : null,
                csrf_token: "<?php echo $csrfToken; ?>"
            };

            // Se visitante (não logado): inclui dados de registro no payload
            const guestName     = document.getElementById('guestName');
            const guestEmail    = document.getElementById('guestEmail');
            const guestPassword = document.getElementById('guestPassword');
            if (guestName && guestEmail && guestPassword) {
                if (!guestName.value.trim() || !guestEmail.value.trim() || !guestPassword.value.trim()) {
                    alert('Preencha seu nome, e-mail e senha para continuar.');
                    submitBtn.disabled = false;
                    btnText.textContent = 'Finalizar Compra Segura';
                    loadingSpinner.classList.add('hidden');
                    return;
                }
                payload.guest_name     = guestName.value.trim();
                payload.guest_email    = guestEmail.value.trim();
                payload.guest_password = guestPassword.value.trim();
            }

            if (method === 'credit_card') {
                payload.token = 'mock_mercado_pago_card_token_992211';
                payload.installments = parseInt(document.getElementById('installmentsSelect').value);
            }

            fetch('api/checkout/process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => {
                return res.json().then(data => {
                    if (!res.ok) {
                        throw new Error(data.error || 'Ocorreu uma falha técnica ao processar o checkout.');
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.data.redirect;
                }
            })
            .catch(error => {
                alert(error.message);
                submitBtn.disabled = false;
                btnText.textContent = 'Finalizar Compra Segura';
                loadingSpinner.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
