<?php
/**
 * Script para atualização e migração das categorias e cursos técnicos da GT Cursos
 * alinhado com o portfólio de serviços da empresa GT Serv Tec.
 */
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

echo "=== INICIANDO MIGRAÇÃO DO BANCO DE DADOS (GT SERV TEC ALIGNMENT) ===\n";

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    // Desativa temporariamente validações de chaves estrangeiras
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Limpa categorias e cursos antigos para evitar conflitos de IDs e manter o catálogo limpo
    $db->exec("TRUNCATE TABLE `categories`");
    $db->exec("TRUNCATE TABLE `courses`");
    $db->exec("TRUNCATE TABLE `modules`");
    $db->exec("TRUNCATE TABLE `subjects`");
    $db->exec("TRUNCATE TABLE `lessons`");

    echo "[+] Tabelas antigas limpas com sucesso.\n";

    // 2. Insere as novas Categorias técnicas
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
    echo "[+] Categorias inseridas com sucesso.\n";

    // 3. Insere os novos Cursos correspondentes aos serviços da GT Serv Tec
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
    echo "[+] Cursos inseridos com sucesso.\n";

    // 4. Insere alguns Módulos e Aulas de exemplo no Curso 1 para testes da sala de aula
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

    echo "[+] Estrutura de aulas de exemplo inserida no Curso 1.\n";

    // Reativa validações de chaves estrangeiras
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "=== MIGRAÇÃO FINALIZADA COM SUCESSO! ===\n";

} catch (\Exception $e) {
    echo "[-] ERRO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
}
