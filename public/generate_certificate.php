<?php
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/AppConfig.php';
require_once __DIR__ . '/../src/Config/Database.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$isEmbed = isset($_GET['embed']) ? true : false;

if (empty($code)) {
    die("<h1>Certificado não informado</h1><p>Por favor, informe o código hash de validação.</p>");
}

$dbInstance = Database::getInstance();
$db = $dbInstance->getConnection();

try {
    // 1. Busca os dados relacionais do certificado
    $query = "SELECT c.certificate_code, c.issued_at, u.name as student_name, co.id as course_id, co.title as course_title 
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN courses co ON c.course_id = co.id
              WHERE c.certificate_code = :code 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':code' => $code]);
    $certData = $stmt->fetch();

    if (!$certData) {
        die("<h1>Certificado inválido</h1><p>O código hash informado não corresponde a nenhum certificado emitido.</p>");
    }

    // 2. Busca o template de certificado associado ao curso
    $tStmt = $db->prepare("SELECT * FROM certificate_templates WHERE course_id = :course_id LIMIT 1");
    $tStmt->execute([':course_id' => $certData['course_id']]);
    $template = $tStmt->fetch();

    // Se não houver template no banco, usa os padrões equilibrados
    if (!$template) {
        $template = [
            'student_name_x' => 100,
            'student_name_y' => 200,
            'student_name_size' => 28,
            'student_name_color' => '#F5F5F7',
            'course_title_x' => 100,
            'course_title_y' => 260,
            'course_title_size' => 22,
            'course_title_color' => '#f2c94c',
            'date_x' => 100,
            'date_y' => 320,
            'date_size' => 12,
            'date_color' => '#8F8F9D',
            'code_x' => 100,
            'code_y' => 370,
            'code_size' => 10,
            'code_color' => '#8F8F9D',
            'background_url' => null
        ];
    }

} catch (\PDOException $e) {
    die("Erro interno ao recuperar credenciais.");
}

// URL absoluta para o QR Code de validação
$validationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . "/verify_certificate.php?code=" . $certData['certificate_code'];
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Credencial Oficial — <?php echo htmlspecialchars($certData['student_name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.cdnfonts.com/css/clash-display" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/satoshi" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Biblioteca leve para geração do QR Code real -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2c94c",
                        "background": "#070708",
                        "surface": "rgba(20, 20, 23, 0.75)",
                        "text-main": "#F5F5F7",
                        "text-muted": "#8F8F9D",
                        "border-color": "rgba(255, 255, 255, 0.05)"
                    },
                    fontFamily: {
                        "display": ["Clash Display", "sans-serif"],
                        "sans": ["Satoshi", "sans-serif"]
                    }
                },
            },
        }
    </script>

    <style>
        body {
            font-family: 'Satoshi', sans-serif;
            background-color: #070708;
            color: #F5F5F7;
            overflow-x: hidden;
        }
        .glass-card {
            background: rgba(15, 15, 18, 0.85);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(242, 201, 76, 0.15);
            box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.8);
        }
        .glow-primary {
            box-shadow: 0 0 25px rgba(242, 201, 76, 0.2);
        }
        .glow-primary:hover {
            box-shadow: 0 0 35px rgba(242, 201, 76, 0.45);
            transform: translateY(-2px);
        }
        canvas {
            box-shadow: 0 25px 60px rgba(0,0,0,0.8);
            border: 1px solid rgba(242, 201, 76, 0.2);
            max-width: 100%;
            height: auto;
        }
        
        /* Oculta controles na Impressão do navegador */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .print-container {
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                height: 100vh !important;
            }
            canvas {
                box-shadow: none !important;
                border: none !important;
            }
        }
        <?php if ($isEmbed): ?>
        header.no-print, footer.no-print {
            display: none !important;
        }
        body {
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }
        main {
            padding: 0 !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            height: 100vh !important;
        }
        canvas {
            box-shadow: none !important;
            border-radius: 0 !important;
            border: none !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
        }
        <?php endif; ?>
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col justify-between py-10 print-container">

    <!-- Header / Controles (no-print) -->
    <header class="w-full max-w-4xl mx-auto px-6 mb-8 flex flex-col sm:flex-row items-center justify-between gap-4 no-print">
        <div class="flex items-center gap-3">
            <img alt="GT Logo" class="h-9 w-auto object-contain bg-black p-1 rounded-lg border border-primary/20" src="assets/images/logo.png">
            <div>
                <h1 class="text-base font-bold text-white uppercase tracking-wider font-display">GT Cursos</h1>
                <p class="text-[9px] text-text-muted font-bold uppercase tracking-widest leading-none mt-0.5">Credencial Verificada</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-5 py-2.5 rounded-lg border border-primary/20 bg-primary/5 hover:bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">print</span>
                Imprimir
            </button>
            <button onclick="downloadPNG()" class="px-5 py-2.5 rounded-lg bg-primary text-background text-xs font-bold uppercase tracking-wider transition-all glow-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Baixar Imagem (PNG)
            </button>
        </div>
    </header>

    <!-- Canvas Workspace -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div class="relative w-full max-w-3xl flex justify-center">
            <!-- Canvas de alta definição (proporção 650x450 escalada em 2x para 1300x900px para impressão profissional) -->
            <canvas id="certificateCanvas" width="1300" height="900" class="rounded-xl bg-[#0f0f12]"></canvas>
        </div>
    </main>

    <!-- Rodapé de segurança (no-print) -->
    <footer class="w-full max-w-4xl mx-auto px-6 mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-white/5 pt-6 text-[10px] text-text-muted font-bold uppercase tracking-wider no-print">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[16px] text-emerald-400">verified</span>
            <span>Assinatura Digital Auditada por Blockchain</span>
        </div>
        <div class="font-mono text-xs select-all text-primary">
            <?php echo $certData['certificate_code']; ?>
        </div>
    </footer>

    <!-- Elemento invisível temporário para gerar o QR Code real em imagem -->
    <div id="qrcode" class="hidden"></div>

    <!-- Engine de renderização de alta definição no lado do cliente -->
    <script>
        const canvas = document.getElementById('certificateCanvas');
        const ctx = canvas.getContext('2d');

        const t = <?php echo json_encode($template); ?>;
        const cert = <?php echo json_encode([
            'student_name' => htmlspecialchars($certData['student_name'], ENT_QUOTES, 'UTF-8'),
            'course_title' => htmlspecialchars($certData['course_title'], ENT_QUOTES, 'UTF-8'),
            'date' => date('d/m/Y', strtotime($certData['issued_at'])),
            'code' => $certData['certificate_code']
        ]); ?>;

        // Imagens globais para renderização assíncrona
        const bgImg = new Image();
        const logoImg = new Image();
        const sigImg = new Image();

        let bgLoaded = false;
        let logoLoaded = false;
        let sigLoaded = false;

        let imagesToLoad = 0;
        let imagesLoaded = 0;

        function onImageLoad() {
            imagesLoaded++;
            if (imagesLoaded === imagesToLoad) {
                renderCertificate();
            }
        }

        // Configura carregamento de ativos gráficos
        if (t.background_url) {
            imagesToLoad++;
            bgImg.onload = () => { bgLoaded = true; onImageLoad(); };
            bgImg.onerror = () => { onImageLoad(); };
            bgImg.src = t.background_url;
        }
        if (t.logo_url) {
            imagesToLoad++;
            logoImg.onload = () => { logoLoaded = true; onImageLoad(); };
            logoImg.onerror = () => { onImageLoad(); };
            logoImg.src = t.logo_url;
        }
        if (t.signature_url) {
            imagesToLoad++;
            sigImg.onload = () => { sigLoaded = true; onImageLoad(); };
            sigImg.onerror = () => { onImageLoad(); };
            sigImg.src = t.signature_url;
        }

        // Gera o QR Code real de forma assíncrona na imagem
        const qrcodeContainer = document.getElementById('qrcode');
        new QRCode(qrcodeContainer, {
            text: "<?php echo $validationUrl; ?>",
            width: 150,
            height: 150,
            colorDark : "#f2c94c",
            colorLight : "#0f0f12",
            correctLevel : QRCode.CorrectLevel.H
        });

        // Aguarda carregar as fontes do Google Fonts e ativos antes de renderizar
        document.fonts.ready.then(() => {
            if (imagesToLoad === 0) {
                renderCertificate();
            } else {
                // Fallback de renderização rápida caso carregamento demore muito
                setTimeout(() => {
                    if (imagesLoaded < imagesToLoad) {
                        renderCertificate();
                    }
                }, 2500);
            }
        });

        function renderCertificate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 1. DESENHA FUNDO CUSTOMIZADO OU PADRÃO OBSIDIAN GOLD
            if (bgLoaded && bgImg.src) {
                ctx.drawImage(bgImg, 0, 0, canvas.width, canvas.height);
            } else {
                // Fundo Escuro Padrão
                ctx.fillStyle = '#0f0f12';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Borda dourada decorativa (escala 2x)
                ctx.strokeStyle = '#f2c94c';
                ctx.lineWidth = 8;
                ctx.strokeRect(30, 30, canvas.width - 60, canvas.height - 60);

                ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
                ctx.lineWidth = 2;
                ctx.strokeRect(40, 40, canvas.width - 80, canvas.height - 80);

                // Selo decorativo (canto inferior direito)
                ctx.strokeStyle = '#f2c94c';
                ctx.fillStyle = 'rgba(242, 201, 76, 0.03)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(canvas.width - 160, canvas.height - 170, 60, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = '#f2c94c';
                ctx.font = 'bold 15px Satoshi, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('GT APPROVED', canvas.width - 160, canvas.height - 165);
            }

            // 2. CABEÇALHOS DO CERTIFICADO (Se não houver fundo customizado que já os contenha)
            if (!bgLoaded) {
                ctx.fillStyle = '#F5F5F7';
                ctx.font = 'bold 32px Clash Display, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('CERTIFICADO DE ELITE', canvas.width / 2, 120);

                ctx.fillStyle = '#8F8F9D';
                ctx.font = 'bold 20px Satoshi, sans-serif';
                ctx.fillText('ESTA CREDENCIAL CERTIFICA QUE', canvas.width / 2, 180);
            }

            // 3. DESENHA OS ELEMENTOS DINÂMICOS DO TEMPLATE (Coordenadas e escalas multiplicadas por 2 para Canvas HD)
            ctx.textAlign = 'left';

            // Elemento A: Nome do Aluno
            ctx.fillStyle = t.student_name_color;
            ctx.font = `bold ${t.student_name_size * 2}px Clash Display, sans-serif`;
            ctx.fillText(cert.student_name, t.student_name_x * 2, t.student_name_y * 2);

            // Elemento B: Título do Curso
            ctx.fillStyle = t.course_title_color;
            ctx.font = `bold ${t.course_title_size * 2}px Clash Display, sans-serif`;
            ctx.fillText(cert.course_title, t.course_title_x * 2, t.course_title_y * 2);

            // Elemento C: Data de Emissão
            ctx.fillStyle = t.date_color;
            ctx.font = `bold ${t.date_size * 2}px Satoshi, sans-serif`;
            ctx.fillText(`Emitido em ${cert.date}`, t.date_x * 2, t.date_y * 2);

            // Elemento D: Hash do Código
            ctx.fillStyle = t.code_color;
            ctx.font = `bold ${t.code_size * 2}px Satoshi, sans-serif`;
            ctx.fillText(`Autenticidade: ${cert.code}`, t.code_x * 2, t.code_y * 2);

            // 4. DESENHA O LOGO INSTITUCIONAL CUSTOMIZADO SE SALVO
            if (logoLoaded && logoImg.src) {
                ctx.drawImage(logoImg, t.logo_x * 2, t.logo_y * 2, t.logo_w * 2, t.logo_h * 2);
            }

            // 5. DESENHA A ASSINATURA DIGITALIZADA CUSTOMIZADA SE SALVA
            if (sigLoaded && sigImg.src) {
                ctx.drawImage(sigImg, t.signature_x * 2, t.signature_y * 2, t.signature_w * 2, t.signature_h * 2);
            }

            // 6. DESENHA O TEXTO CUSTOMIZADO SE SALVO
            if (t.custom_text) {
                ctx.fillStyle = t.custom_text_color;
                ctx.font = `bold ${t.custom_text_size * 2}px Clash Display, sans-serif`;
                ctx.fillText(t.custom_text, t.custom_text_x * 2, t.custom_text_y * 2);
            }

            // 7. DESENHA O QR CODE REAL DE VERIFICAÇÃO NO CANVAS
            setTimeout(() => {
                const qrImage = qrcodeContainer.querySelector('img');
                if (qrImage) {
                    const qrX = t.code_x * 2;
                    const qrY = (t.code_y * 2) + 20; // posicionado ligeiramente abaixo do texto do código
                    ctx.drawImage(qrImage, qrX, qrY, 130, 130);
                }
            }, 150);
        }

        // Função para download da imagem PNG em altíssima definição
        function downloadPNG() {
            const link = document.createElement('a');
            link.download = `Certificado_GT_${cert.student_name.replace(/\s+/g, '_')}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            link.click();
        }
    </script>
</body>
</html>
