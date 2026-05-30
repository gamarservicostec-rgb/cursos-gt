<?php
use Config\AppConfig;
use Middleware\SecurityHeaders;

require_once __DIR__ . '/../src/Config/AppConfig.php';
require_once __DIR__ . '/../src/Middleware/SecurityHeaders.php';

// Inicia sessão segura
AppConfig::startSession();

// Se o usuário já estiver logado, redireciona automaticamente para sua área correspondente
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['user_role'] === 'admin') ? 'admin/index.php' : 'dashboard/index.php';
    header("Location: " . AppConfig::$APP_URL . "/" . $redirect);
    exit;
}

// Gera token CSRF para injeção no formulário
$csrfToken = \Middleware\SecurityHeaders::generateCSRFToken();
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Acesso à Plataforma — Cursos GT</title>
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
                    },
                    fontFamily: {
                        "display": ["Clash Display", "sans-serif"],
                        "sans": ["Satoshi", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "1rem",
                        "xl": "1.5rem",
                        "full": "9999px"
                    },
                    backgroundImage: {
                        'radial-glow': 'radial-gradient(circle at 50% 50%, rgba(241, 200, 75, 0.04) 0%, rgba(10, 10, 12, 1) 100%)',
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
            overflow: hidden;
        }

        h1, h2, h3 {
            font-family: 'Clash Display', sans-serif;
        }

        .glass-card {
            background: rgba(20, 20, 23, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);
        }

        .custom-input {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #F5F5F7 !important;
            padding: 0.85rem 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            border-radius: 0.375rem; /* Raio de borda discreto (6px) */
        }

        .custom-input:focus {
            outline: none;
            border-color: #f1c84b !important;
            box-shadow: 0 0 15px rgba(241, 200, 75, 0.15);
        }

        /* Corrige o preenchimento automático (autofill) do navegador que pinta o fundo de branco */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #0A0A0C inset !important;
            -webkit-text-fill-color: #F5F5F7 !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #f1c84b;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .btn-primary {
            background-color: #f1c84b;
            color: #0A0A0C;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border-radius: 0.375rem; /* Raio de borda discreto (6px) */
        }

        .btn-primary:hover {
            background-color: #f1c84b;
            transform: scale(1.018); /* Aumento discreto no hover */
            box-shadow: 0 4px 15px rgba(241, 200, 75, 0.2); /* Brilho leve e tátil */
        }

        .btn-primary:active {
            transform: scale(0.995);
        }
    </style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center bg-radial-glow h-screen select-none">
    <!-- Main Container -->
    <main class="w-full max-w-[440px] p-6 z-10 m-auto">
        <!-- Glassmorphic Card -->
        <div class="glass-card rounded-xl p-10">
            <!-- Logo / Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4 flex-col items-center">
                    <!-- Imagem do Logotipo com tratamento de fallback caso a imagem não exista física na pasta -->
                    <img src="assets/images/logo.png" alt="Logotipo GT Cursos" onerror="this.style.display='none'" class="h-24 w-auto mb-4 object-contain">
                    <span class="font-display text-3xl font-bold tracking-widest uppercase">
                        CURSOS <span class="text-primary">GT</span>
                    </span>
                </div>
                <p class="text-text-muted mt-2 text-xs font-semibold uppercase tracking-wider">Acesse sua conta para continuar</p>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" class="space-y-6">
                <!-- CSRF Token Input -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <!-- Email Input -->
                <div class="input-group">
                    <label class="input-label" for="email">E-mail</label>
                    <input autocomplete="email" class="custom-input" id="email" name="email" placeholder="nome@exemplo.com" required type="email">
                </div>
                
                <!-- Password Input -->
                <div class="input-group">
                    <label class="input-label" for="password">Senha</label>
                    <div class="relative">
                        <input class="custom-input pr-12" id="password" name="password" placeholder="••••••••" required type="password">
                        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-text-muted hover:text-primary transition-colors focus:outline-none" onclick="togglePassword()" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="visibility-icon">visibility</span>
                        </button>
                    </div>
                </div>
                
                <!-- Error State Warning -->
                <div class="hidden text-error text-xs font-semibold mt-2" id="error-message">
                    <span class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">error</span>
                        <span id="error-text">E-mail ou senha incorretos.</span>
                    </span>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center justify-end">
                    <a class="text-xs font-bold text-text-muted hover:text-primary transition-colors duration-200 uppercase tracking-widest" href="recover.php">
                        Esqueceu sua senha?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button class="w-full btn-primary font-bold py-4 rounded-lg uppercase tracking-[0.15em] text-[12px] mt-2 flex items-center justify-center gap-2" type="submit" id="submitBtn">
                    <span id="btnText">Entrar na Plataforma</span>
                    <!-- Loading Spinner -->
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-black hidden" id="loadingSpinner" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </form>
            
            <!-- Sign Up Link -->
            <div class="text-center mt-8">
                <p class="text-xs text-text-muted font-semibold uppercase tracking-wider">
                    Ainda não tem conta? 
                    <a class="text-primary hover:text-[#FFD700] font-bold transition-colors ml-1 uppercase tracking-widest" href="register.php">
                        Cadastre-se
                    </a>
                </p>
            </div>
        </div>
    </main>

    <script>
        // Alterna visualização de senha
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const visibilityIcon = document.getElementById('visibility-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                visibilityIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                visibilityIcon.textContent = 'visibility';
            }
        }

        // Intercepta e processa o envio assíncrono do formulário via Fetch
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorMsg = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Ativa estado de carregamento
            submitBtn.disabled = true;
            btnText.textContent = 'Autenticando...';
            loadingSpinner.classList.remove('hidden');
            errorMsg.classList.add('hidden');

            const payload = {
                email: email,
                password: password,
                csrf_token: "<?php echo $csrfToken; ?>"
            };

            fetch('api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                return response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Ocorreu um erro ao realizar a autenticação.');
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    // Redireciona para o painel apropriado do usuário
                    window.location.href = data.redirect;
                }
            })
            .catch(error => {
                // Exibe erro na tela
                errorText.textContent = error.message;
                errorMsg.classList.remove('hidden');
                
                // Restaura estado do botão
                submitBtn.disabled = false;
                btnText.textContent = 'Entrar na Plataforma';
                loadingSpinner.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
