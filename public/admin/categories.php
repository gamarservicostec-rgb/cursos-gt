<?php
use Config\Database;
use Config\AppConfig;
use Middleware\AuthMiddleware;

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

// Requer privilégios de Admin
\Middleware\AuthMiddleware::requireAdmin();

$adminName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>GT Cursos - Gerenciar Categorias</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "error": "#ffb4ab",
                        "on-primary-container": "#1a1a1a",
                        "on-tertiary-container": "#005f79",
                        "surface-dim": "#0A0A0C",
                        "secondary-container": "#6f00be",
                        "secondary-fixed": "#f0dbff",
                        "on-primary-fixed": "#1a1a1a",
                        "surface-container-highest": "#1c1c1e",
                        "on-primary": "#1a1a1a",
                        "on-tertiary": "#003545",
                        "surface-container": "#111114",
                        "surface-container-low": "#0d0d0f",
                        "status-draft": "#FACC15",
                        "background-deep": "#070708",
                        "background": "#0A0A0C",
                        "on-secondary-fixed-variant": "#6900b3",
                        "surface-container-lowest": "#050505",
                        "outline": "rgba(242, 201, 76, 0.2)",
                        "surface": "#0A0A0C",
                        "tertiary-container": "#7cd9ff",
                        "inverse-primary": "#f2c94c",
                        "tertiary": "#cdefff",
                        "on-secondary-container": "#d6a9ff",
                        "on-secondary-fixed": "#2c0051",
                        "primary": "#f2c94c",
                        "inverse-surface": "#ffffff",
                        "on-error-container": "#ffdad6",
                        "on-surface-variant": "#a1a1a6",
                        "primary-fixed-dim": "#ebc246",
                        "on-secondary": "#ffffff",
                        "surface-bright": "#1c1c1e",
                        "border-subtle": "rgba(242, 201, 76, 0.2)",
                        "on-background": "#ffffff",
                        "on-surface": "#ffffff",
                        "tertiary-fixed": "#bce9ff",
                        "glow-primary": "rgba(242, 201, 76, 0.05)",
                        "tertiary-fixed-dim": "#75d2f8",
                        "surface-tint": "#f2c94c",
                        "on-primary-fixed-variant": "#584400",
                        "on-error": "#690005",
                        "inverse-on-surface": "#000000",
                        "secondary-fixed-dim": "#ddb7ff",
                        "status-published": "#34D399",
                        "secondary": "#ddb7ff",
                        "primary-container": "#f2c94c",
                        "error-container": "#93000a",
                        "on-tertiary-fixed": "#001f29",
                        "on-tertiary-fixed-variant": "#004d63",
                        "surface-container-high": "#161618",
                        "surface-glass": "rgba(10, 10, 12, 0.85)",
                        "surface-variant": "#1c1c1e",
                        "primary-fixed": "#f2c94c",
                        "outline-variant": "rgba(255, 255, 255, 0.1)"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "sidebar-width": "256px",
                        "gutter-grid": "1.5rem",
                        "container-padding": "2rem",
                        "stack-sm": "0.5rem",
                        "stack-md": "1rem",
                        "stack-lg": "2.5rem"
                    },
                    "fontFamily": {
                        "body-lg": ["Plus Jakarta Sans"],
                        "caption": ["Plus Jakarta Sans"],
                        "label-sm": ["Plus Jakarta Sans"],
                        "title-lg": ["Outfit"],
                        "headline-md": ["Outfit"],
                        "display-lg": ["Outfit"],
                        "body-md": ["Plus Jakarta Sans"]
                    },
                    "fontSize": {
                        "body-lg": ["18px", {"lineHeight": "1.5", "fontWeight": "700"}],
                        "caption": ["10px", {"lineHeight": "1.2", "letterSpacing": "0.1em", "fontWeight": "700"}],
                        "label-sm": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "600"}],
                        "title-lg": ["20px", {"lineHeight": "1.4", "fontWeight": "700"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                        "display-lg": ["36px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["14px", {"lineHeight": "1.6", "fontWeight": "500"}]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            background-color: #070708;
            color: #ffffff;
            overflow-x: hidden;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .glass-card {
            background: rgba(10, 10, 12, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(242, 201, 76, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            border-color: rgba(242, 201, 76, 0.3);
        }
        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            z-index: -1;
            opacity: 0.1;
        }
        .neon-text-gold {
            background: linear-gradient(135deg, #ffffff 0%, #f2c94c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 15px rgba(242, 201, 76, 0.1);
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #070708;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(242, 201, 76, 0.1);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(242, 201, 76, 0.2);
        }
    </style>
</head>
<body class="bg-background-deep font-body-md text-on-background" style="background: linear-gradient(rgba(7, 7, 8, 0.95), rgba(7, 7, 8, 0.98)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&amp;w=2070&amp;auto=format&amp;fit=crop'); background-size: cover; background-attachment: fixed;">

<!-- Atmospheric Glows -->
<div class="glow-orb bg-primary w-[500px] h-[500px] top-[-10%] right-[-5%]"></div>
<div class="glow-orb bg-secondary w-96 h-96 bottom-[10%] left-[-5%]"></div>

<!-- SideNavBar -->
<aside class="w-sidebar-width h-screen fixed left-0 top-0 border-r border-border-subtle backdrop-blur-2xl flex flex-col py-stack-lg z-50 glass-card" style="background: rgba(10, 10, 12, 0.92); height: 100vh;">
    <div class="px-6 mb-8 flex items-center gap-3">
        <img alt="GT Cursos Logo" class="w-10 h-10 rounded-lg shadow-[0_0_15px_rgba(242,201,76,0.3)] object-contain bg-black p-1" src="../assets/images/logo.png">
        <div>
            <h1 class="font-display-lg text-title-lg font-bold text-primary tracking-tighter leading-none">GT CURSOS</h1>
            <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-[0.2em] opacity-80 mt-1">Admin Terminal</p>
        </div>
    </div>
    <nav class="flex-1 flex flex-col gap-1 px-3 overflow-y-auto custom-scrollbar">
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="index.php">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-label-sm text-label-sm">Painel</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="courses.php">
            <span class="material-symbols-outlined">school</span>
            <span class="font-label-sm text-label-sm">Cursos</span>
        </a>
        <!-- Novo link Categorias ativo nesta página -->
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="categories.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">category</span>
            <span class="font-label-sm text-label-sm">Categorias</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="students.php">
            <span class="material-symbols-outlined">person</span>
            <span class="font-label-sm text-label-sm">Alunos</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="attendance.php">
            <span class="material-symbols-outlined">how_to_reg</span>
            <span class="font-label-sm text-label-sm">Presença</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="certificates.php">
            <span class="material-symbols-outlined">workspace_premium</span>
            <span class="font-label-sm text-label-sm">Certificados</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="support.php">
            <span class="material-symbols-outlined">support_agent</span>
            <span class="font-label-sm text-label-sm">Suporte & Chamados</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="coupons.php">
            <span class="material-symbols-outlined">redeem</span>
            <span class="font-label-sm text-label-sm">Cupons</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="email_whatsapp.php">
            <span class="material-symbols-outlined">mail</span>
            <span class="font-label-sm text-label-sm">E-mail & WhatsApp</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="financial.php">
            <span class="material-symbols-outlined">payments</span>
            <span class="font-label-sm text-label-sm">Financeiro</span>
        </a>
        
        <div class="mt-auto flex flex-col gap-1 border-t border-white/5 pt-4">
            <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-error/80 hover:text-error hover:bg-error/5 transition-all duration-200" href="../logout.php">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-label-sm text-label-sm">Sair</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Main Wrapper -->
<div class="ml-sidebar-width min-h-screen">
    <!-- TopAppBar -->
    <header class="fixed top-0 right-0 w-[calc(100%-256px)] h-16 z-40 flex justify-between items-center px-container-padding border-b border-white/5 backdrop-blur-xl glass-card" style="background: rgba(10, 10, 12, 0.8);">
        <div>
            <h2 class="font-title-lg text-title-lg font-bold text-white uppercase tracking-wider">Gestão de Categorias</h2>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3 pl-6">
                <div class="text-right">
                    <p class="font-label-sm text-label-sm font-semibold text-white leading-none"><?php echo htmlspecialchars($adminName); ?></p>
                    <p class="text-[10px] text-primary uppercase font-bold tracking-widest mt-1">Diretor Executivo</p>
                </div>
                <div class="w-10 h-10 rounded-full border border-primary/20 bg-primary/10 flex items-center justify-center font-bold text-primary font-heading shadow-glow">
                    DG
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-24 px-container-padding pb-stack-lg max-w-[1200px]">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-headline-md font-heading font-bold text-white uppercase">Categorias Ativas</h3>
                <p class="text-muted text-sm mt-1">Organize os cursos em grupos táticos para visualização dinâmica na Landing Page.</p>
            </div>
            <button onclick="openCreateModal()" class="flex items-center gap-2 rounded bg-primary text-background-deep font-bold px-5 py-3 text-xs uppercase tracking-widest hover:bg-gold-light hover:shadow-[0_0_20px_rgba(242,201,76,0.3)] transition-all duration-300">
                <span class="material-symbols-outlined" style="font-size: 18px; font-weight: bold;">add</span>
                Nova Categoria
            </button>
        </div>

        <!-- Tabela Obsidian Gold -->
        <div class="glass-card rounded-xl overflow-hidden shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/5 bg-white/[0.01]">
                            <th class="px-6 py-4 font-caption text-caption text-primary uppercase tracking-widest w-20">ID</th>
                            <th class="px-6 py-4 font-caption text-caption text-primary uppercase tracking-widest">Nome</th>
                            <th class="px-6 py-4 font-caption text-caption text-primary uppercase tracking-widest">Slug (Link)</th>
                            <th class="px-6 py-4 font-caption text-caption text-primary uppercase tracking-widest w-32">Ordem</th>
                            <th class="px-6 py-4 font-caption text-caption text-primary uppercase tracking-widest w-40 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="categories-table-body" class="divide-y divide-white/[0.03]">
                        <!-- Renderizado via JS -->
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-muted">Carregando categorias...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Obsidian Gold - Adicionar/Editar Categoria -->
<div id="category-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm hidden">
    <div class="w-full max-w-md p-6 glass-card rounded-xl shadow-2xl relative" style="background: #0d0d10;">
        <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-6">
            <h3 id="modal-title" class="font-heading text-title-lg font-bold text-white uppercase tracking-wider">Nova Categoria</h3>
            <button onclick="closeModal()" class="material-symbols-outlined text-muted hover:text-white transition-colors">close</button>
        </div>
        <form id="category-form" onsubmit="saveCategory(event)">
            <input type="hidden" id="category-id">
            
            <div class="flex flex-col gap-4 mb-6">
                <div>
                    <label class="block font-caption text-caption text-muted uppercase tracking-widest mb-2" for="category-name">Nome da Categoria</label>
                    <input class="w-full bg-white/[0.03] border border-white/10 rounded px-4 py-3 text-white focus:ring-1 focus:ring-primary focus:border-primary placeholder:text-white/20" id="category-name" required placeholder="Ex: Cibersegurança Avançada" type="text">
                </div>
                <div>
                    <label class="block font-caption text-caption text-muted uppercase tracking-widest mb-2" for="category-order">Ordem de Exibição</label>
                    <input class="w-full bg-white/[0.03] border border-white/10 rounded px-4 py-3 text-white focus:ring-1 focus:ring-primary focus:border-primary" id="category-order" required min="0" value="0" type="number">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-white/5">
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded border border-white/10 text-muted hover:text-white hover:bg-white/5 text-xs font-bold uppercase tracking-widest transition-all">Cancelar</button>
                <button type="submit" class="px-5 py-2.5 rounded bg-primary text-background-deep text-xs font-bold uppercase tracking-widest hover:bg-gold-light transition-all hover:shadow-glow">Salvar Categoria</button>
            </div>
        </form>
    </div>
</div>

<script>
    const API_URL = '../api/admin/categories.php';
    let categoriesList = [];

    // Carregar categorias ao iniciar
    document.addEventListener('DOMContentLoaded', loadCategories);

    async function loadCategories() {
        try {
            const response = await fetch(API_URL);
            const data = await response.json();
            
            if (data.success) {
                categoriesList = data.categories;
                renderCategories();
            } else {
                alert('Erro ao carregar categorias: ' + (data.error || 'Erro desconhecido.'));
            }
        } catch (error) {
            console.error('Erro de requisição:', error);
            alert('Falha ao conectar com o servidor para buscar categorias.');
        }
    }

    function renderCategories() {
        const body = document.getElementById('categories-table-body');
        if (categoriesList.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-muted">
                        <span class="material-symbols-outlined text-4xl block mb-2 opacity-50">category</span>
                        Nenhuma categoria cadastrada no momento.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = categoriesList.map(cat => `
            <tr class="hover:bg-white/[0.01] transition-colors">
                <td class="px-6 py-4 font-bold text-white/50">${cat.id}</td>
                <td class="px-6 py-4 font-bold text-white">${escapeHtml(cat.name)}</td>
                <td class="px-6 py-4 text-primary font-mono text-xs">${cat.slug}</td>
                <td class="px-6 py-4 text-white/70">${cat.sort_order}</td>
                <td class="px-6 py-4 text-right">
                    <div class="inline-flex gap-3 justify-end w-full">
                        <button onclick="openEditModal(${cat.id})" class="flex items-center gap-1 text-[11px] font-bold text-primary border border-primary/20 hover:border-primary hover:bg-primary/5 px-2.5 py-1.5 rounded uppercase tracking-wider transition-all">
                            <span class="material-symbols-outlined" style="font-size: 14px;">edit</span> Editar
                        </button>
                        <button onclick="deleteCategory(${cat.id})" class="flex items-center gap-1 text-[11px] font-bold text-error border border-error/20 hover:border-error hover:bg-error/5 px-2.5 py-1.5 rounded uppercase tracking-wider transition-all">
                            <span class="material-symbols-outlined" style="font-size: 14px;">delete</span> Excluir
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Modal Operations
    function openCreateModal() {
        document.getElementById('modal-title').textContent = 'Nova Categoria';
        document.getElementById('category-id').value = '';
        document.getElementById('category-name').value = '';
        document.getElementById('category-order').value = '0';
        document.getElementById('category-modal').classList.remove('hidden');
    }

    function openEditModal(id) {
        const cat = categoriesList.find(c => c.id === id);
        if (!cat) return;

        document.getElementById('modal-title').textContent = 'Editar Categoria';
        document.getElementById('category-id').value = cat.id;
        document.getElementById('category-name').value = cat.name;
        document.getElementById('category-order').value = cat.sort_order;
        document.getElementById('category-modal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('category-modal').classList.add('hidden');
    }

    async function saveCategory(event) {
        event.preventDefault();
        
        const id = document.getElementById('category-id').value;
        const name = document.getElementById('category-name').value.trim();
        const sort_order = document.getElementById('category-order').value;

        const payload = {
            name: name,
            sort_order: parseInt(sort_order)
        };

        if (id) {
            payload.id = parseInt(id);
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (data.success) {
                closeModal();
                loadCategories();
            } else {
                alert('Erro ao salvar categoria: ' + (data.error || 'Erro desconhecido.'));
            }
        } catch (error) {
            console.error('Erro de requisição:', error);
            alert('Falha ao conectar com o servidor para salvar a categoria.');
        }
    }

    async function deleteCategory(id) {
        if (!confirm('Atenção: Tem certeza de que deseja excluir esta categoria? Os cursos vinculados a ela não serão excluídos, apenas ficarão marcados como "Sem Categoria".')) {
            return;
        }

        try {
            const response = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            const data = await response.json();

            if (data.success) {
                loadCategories();
            } else {
                alert('Erro ao excluir categoria: ' + (data.error || 'Erro desconhecido.'));
            }
        } catch (error) {
            console.error('Erro de requisição:', error);
            alert('Falha ao conectar com o servidor para excluir a categoria.');
        }
    }

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(string));
        return div.innerHTML;
    }
</script>
</body>
</html>
