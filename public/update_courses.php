<?php
/**
 * Script público temporário para atualização e migração das categorias e cursos técnicos
 * alinhados ao portfólio da GT Serv Tec na produção (HostGator).
 */
use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Config/AppConfig.php';

// Inicia sessão
AppConfig::startSession();

// Define o token de segurança
$securityToken = "gt_servtec_2026";
$providedToken = $_GET['token'] ?? '';

// Layout Obsidian Gold do Terminal de Migração
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Sincronização de Catálogo — GT Cursos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&family=Plus+Jakarta+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #060608;
            color: #F5F5F7;
        }
        h1 {
            font-family: 'Clash Display', sans-serif;
        }
        .glass-card {
            background: rgba(14, 14, 18, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 201, 76, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-cover bg-center" style="background-image: linear-gradient(rgba(6, 6, 8, 0.96), rgba(6, 6, 8, 0.98)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop');">
    <div class="w-full max-w-2xl glass-card rounded-2xl p-8 md:p-10">
        <div class="border-b border-white/5 pb-6 mb-6 flex items-center gap-4">
            <div class="h-12 w-12 rounded bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center text-yellow-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.2" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold uppercase tracking-wider text-white">Sincronizador de Cursos</h1>
                <p class="text-xs text-yellow-500 uppercase font-semibold tracking-widest mt-1">Alinhamento Institucional com GT Serv Tec</p>
            </div>
        </div>

        <div class="space-y-4 text-sm leading-relaxed mb-8">
            <?php if ($providedToken !== $securityToken): ?>
                <div class="p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">✗ Acesso Negado</h3>
                    <p class="text-xs">Token de segurança ausente ou inválido na requisição. Para executar este script, adicione o parâmetro de segurança correto na URL.</p>
                </div>
            <?php else: ?>
                <div class="p-4 bg-white/[0.02] border border-white/5 rounded-xl space-y-3">
                    <h3 class="text-xs font-bold text-yellow-500 uppercase tracking-wider">⚡ Executando Migração de Banco de Dados...</h3>
                    
                    <?php
                    try {
                        $dbInstance = Database::getInstance();
                        $db = $dbInstance->getConnection();

                        // Desativa temporariamente validações de chaves estrangeiras
                        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

                        // Limpa tabelas antigas para evitar duplicidade de dados e manter o catálogo limpo
                        $db->exec("TRUNCATE TABLE `categories`");
                        $db->exec("TRUNCATE TABLE `courses`");
                        $db->exec("TRUNCATE TABLE `modules`");
                        $db->exec("TRUNCATE TABLE `subjects`");
                        $db->exec("TRUNCATE TABLE `lessons`");

                        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Tabelas de cursos, categorias e aulas limpas com sucesso.</p>";

                        // Insere as novas Categorias técnicas
                        $categories = [
                            ['name' => 'Segurança Eletrônica', 'slug' => 'seguranca-eletronica', 'sort_order' => 1],
                            ['name' => 'Automação Smart Home', 'slug' => 'automacao', 'sort_order' => 2],
                            ['name' => 'Infraestrutura de TI', 'slug' => 'infraestrutura-ti', 'sort_order' => 3],
                            ['name' => 'Assistência Técnica', 'slug' => 'assistencia-tecnica', 'sort_order' => 4]
                        ];

                        $catIds = [];
                        $insCat = $db->prepare("INSERT INTO `categories` (name, slug, sort_order) VALUES (:name, :slug, :sort_order)");
                        foreach ($categories as $cat) {
                            $insCat->execute([
                                ':name' => $cat['name'],
                                ':slug' => $cat['slug'],
                                ':sort_order' => $cat['sort_order']
                            ]);
                            $catIds[$cat['slug']] = $db->lastInsertId();
                        }
                        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ 4 Novas categorias inseridas com sucesso.</p>";

                        // Cursos correspondentes aos serviços da GT Serv Tec
                        $courses = [
                            [
                                'id' => 1,
                                'title' => 'Instalador de Sistemas de Segurança Eletrônica',
                                'description' => 'Domine a instalação, configuração e manutenção de centrais de alarme residenciais e comerciais, cercas elétricas, interfonia física e sistemas integrados de CFTV com monitoramento por aplicativo móvel.',
                                'thumbnail_url' => 'https://images.unsplash.com/photo-1557597774-9d273605dfa9?q=80&w=1470&auto=format&fit=crop',
                                'type' => 'hybrid',
                                'price' => 890.00,
                                'category_id' => $catIds['seguranca-eletronica'],
                                'available_hours' => '08:00 às 10:00,10:00 às 12:00,14:00 às 16:00,19:00 às 21:00'
                            ],
                            [
                                'id' => 2,
                                'title' => 'Especialista em Automação Residencial e Smart Home',
                                'description' => 'Aprenda a planejar e instalar sistemas inteligentes de automação residencial. Integração completa de módulos de iluminação, automação de portões, fechaduras eletrônicas inteligentes e sensores integrados com controle via Alexa ou Google Assistant.',
                                'thumbnail_url' => 'https://images.unsplash.com/photo-1558002038-1055907df827?q=80&w=1470&auto=format&fit=crop',
                                'type' => 'hybrid',
                                'price' => 990.00,
                                'category_id' => $catIds['automacao'],
                                'available_hours' => '08:00 às 10:00,14:00 às 16:00,19:00 às 21:00'
                            ],
                            [
                                'id' => 3,
                                'title' => 'Instalador de Redes, Cabeamento Estruturado e Infraestrutura de TI',
                                'description' => 'Capacitação teórica e prática para infraestrutura de TI corporativa e residencial. Aprenda organização de racks, crimpagem profissional RJ45/Keystones, roteadores Wi-Fi de alta performance, e cabeamento estruturado seguindo normas técnicas.',
                                'thumbnail_url' => 'https://images.unsplash.com/photo-1544256718-3bcf237f3974?q=80&w=1471&auto=format&fit=crop',
                                'type' => 'hybrid',
                                'price' => 1190.00,
                                'category_id' => $catIds['infraestrutura-ti'],
                                'available_hours' => '10:00 às 12:00,14:00 às 16:00,19:00 às 21:00'
                            ],
                            [
                                'id' => 4,
                                'title' => 'Manutenção de Hardware: PCs, Notebooks e Smartphones',
                                'description' => 'Torne-se um profissional completo em assistência técnica. Diagnóstico de falhas de hardware, troca de telas de celulares, limpeza e troca de pasta térmica, formatação, recuperação e otimização de sistemas operacionais.',
                                'thumbnail_url' => 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?q=80&w=1470&auto=format&fit=crop',
                                'type' => 'online',
                                'price' => 790.00,
                                'category_id' => $catIds['assistencia-tecnica'],
                                'available_hours' => NULL
                            ]
                        ];

                        $insCourse = $db->prepare("
                            INSERT INTO `courses` (id, title, description, thumbnail_url, type, price, category_id, available_hours, status) 
                            VALUES (:id, :title, :description, :thumbnail_url, :type, :price, :category_id, :available_hours, 'active')
                        ");

                        foreach ($courses as $c) {
                            $insCourse->execute([
                                ':id' => $c['id'],
                                ':title' => $c['title'],
                                ':description' => $c['description'],
                                ':thumbnail_url' => $c['thumbnail_url'],
                                ':type' => $c['type'],
                                ':price' => $c['price'],
                                ':category_id' => $c['category_id'],
                                ':available_hours' => $c['available_hours']
                            ]);
                        }
                        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ 4 Cursos profissionais baseados na GT Serv Tec inseridos.</p>";

                        // Insere os Módulos e Aulas de exemplo no Curso 1 para testes
                        $db->exec("INSERT INTO `modules` (`id`, `course_id`, `title`, `description`, `sort_order`) VALUES 
                            (1, 1, 'Módulo 1: Fundamentos de Segurança Eletrônica', 'Conceitos iniciais e principais equipamentos usados em segurança eletrônica.', 1),
                            (2, 1, 'Módulo 2: Instalação Física de Dispositivos', 'Práticas de passagem de cabo, fixação e fontes de alimentação.', 2)
                        ");

                        $db->exec("INSERT INTO `subjects` (`id`, `module_id`, `title`, `sort_order`) VALUES 
                            (1, 1, 'Teoria e Componentes', 1),
                            (2, 2, 'Instalações de Câmeras e Sensores', 2)
                        ");

                        $db->exec("INSERT INTO `lessons` (`id`, `subject_id`, `title`, `description`, `video_provider`, `video_url`, `duration`, `sort_order`) VALUES 
                            (1, 1, '1. Introdução à Segurança e CFTV', 'Conceito de imagens analógicas e digitais e tipos de câmeras no mercado.', 'bunny', '7750362074564546823', 600, 1),
                            (2, 2, '2. Prática: Crimpagem e Alimentação 12V', 'Aprenda a crimpar conectores BNC e P4 de alimentação de forma profissional.', 'bunny', '8004146441738874927', 900, 2)
                        ");

                        echo "<p class='text-xs text-emerald-400 font-medium ml-4'>✓ Grade curricular pedagógica de testes criada com sucesso.</p>";

                        // Reativa chaves estrangeiras
                        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

                        echo "<h2 class='text-base font-bold text-emerald-400 uppercase mt-4 flex items-center gap-2'>
                                <span class='w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping'></span>
                                Banco de Dados Atualizado com Sucesso!
                              </h2>";

                    } catch (\Exception $e) {
                        echo "<p class='text-xs text-red-400 ml-4'>✗ ERRO: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="flex justify-end gap-4 border-t border-white/5 pt-6">
            <a href="index.php" class="px-6 py-3 rounded-lg bg-yellow-500 text-black font-bold text-xs uppercase tracking-widest hover:bg-yellow-400 transition-all">Ir para a Home</a>
        </div>
    </div>
</body>
</html>
