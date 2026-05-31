<?php
use Config\Database;
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
    <title>GT Cursos - Chamada Presencial</title>
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
            transform: translateY(-2px);
            border-color: rgba(242, 201, 76, 0.3);
            box-shadow: 0 12px 40px -12px rgba(0, 0, 0, 0.7);
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
        .input-glass {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #F5F5F7 !important;
            transition: all 0.3s ease;
        }
        .input-glass:focus {
            border-color: #f2c94c !important;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.15);
            outline: none;
        }
        /* Toggle switch styling */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: #8F8F9D;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: rgba(242, 201, 76, 0.1);
            border-color: rgba(242, 201, 76, 0.3);
        }
        input:checked + .slider:before {
            transform: translateX(20px);
            background-color: #f2c94c;
            box-shadow: 0 0 10px rgba(242, 201, 76, 0.5);
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

        /* Custom Premium Select Dropdown Style (Obsidian Gold) */
        select {
            background-color: #0d0d0f !important;
            color: #ffffff !important;
            border: 1px solid rgba(242, 201, 76, 0.15) !important;
            color-scheme: dark !important;
            outline: none !important;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        select:focus {
            border-color: rgba(242, 201, 76, 0.4) !important;
            box-shadow: 0 0 12px rgba(242, 201, 76, 0.15) !important;
        }
        select option {
            background-color: #0d0d0f !important;
            color: #ffffff !important;
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
        <img alt="GT Cursos Logo" onerror="this.style.display='none'" class="w-10 h-10 rounded-lg shadow-[0_0_15px_rgba(242,201,76,0.3)] object-contain bg-black p-1" src="../assets/images/logo.png">
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
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="categories.php">
            <span class="material-symbols-outlined">category</span>
            <span class="font-label-sm text-label-sm">Categorias</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:text-white hover:bg-white/5 transition-all duration-200" href="students.php">
            <span class="material-symbols-outlined">person</span>
            <span class="font-label-sm text-label-sm">Alunos</span>
        </a>
        <a class="flex items-center gap-4 px-4 py-3 rounded-lg border-l-2 border-primary bg-gradient-to-r from-primary/10 to-transparent text-primary font-bold shadow-[inset_1px_0_0_rgba(242,201,76,0.1)] transition-all duration-300" href="attendance.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">how_to_reg</span>
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
        <div class="flex-1 max-w-xl">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/50 group-focus-within:text-primary transition-colors">search</span>
                <input class="w-full bg-white/[0.03] border border-white/10 rounded-full py-2 pl-10 pr-4 text-white font-label-sm text-label-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all placeholder:text-white/20" placeholder="Buscar dados, relatórios..." type="text">
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex gap-4">
                <button class="material-symbols-outlined text-on-surface-variant hover:text-white hover:bg-white/10 p-2 rounded-full transition-all">notifications</button>
                <button class="material-symbols-outlined text-on-surface-variant hover:text-white hover:bg-white/10 p-2 rounded-full transition-all">mail</button>
            </div>
            <div class="flex items-center gap-3 pl-6 border-l border-white/10">
                <div class="text-right">
                    <p class="font-body-md text-white font-bold leading-none"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="font-caption text-[10px] text-on-surface-variant uppercase tracking-wider mt-1">Gerente Administrativo</p>
                </div>
                <img alt="User Avatar Admin" onerror="this.style.display='none'" class="w-10 h-10 rounded-full border-2 border-primary/20 object-cover shadow-lg bg-black" src="../assets/images/logo.png">
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-24 px-container-padding pb-stack-lg space-y-stack-lg">
        <!-- Header Operations -->
        <div class="glass-card rounded-xl p-6">
            <h1 class="text-xl font-bold tracking-tight text-white font-display">Controle de Frequência</h1>
            <p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mt-1">Realize a chamada física de presença para turmas híbridas e presenciais</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                <div>
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary mb-2">Selecione o Treinamento</label>
                    <select id="courseSelect" onchange="loadAttendanceList()" class="w-full px-4 py-3 rounded-lg input-glass text-xs">
                        <option value="">Carregando treinamentos...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-bold uppercase tracking-widest text-primary mb-2">Data da Aula</label>
                    <input type="date" id="dateSelect" onchange="loadAttendanceList()" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-3 rounded-lg input-glass text-xs" style="color-scheme: dark;">
                </div>
            </div>
        </div>

        <!-- Attendance List Card -->
        <div class="glass-card rounded-xl p-6 hidden" id="attendanceCard">
            <h3 class="text-xs font-bold text-white uppercase tracking-widest mb-6">Lista de Alunos Matriculados 📋</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/5 text-on-surface-variant font-bold uppercase tracking-wider pb-3">
                            <th class="pb-3">Aluno</th>
                            <th class="pb-3">E-mail</th>
                            <th class="pb-3">Horário Agendado (Checkout)</th>
                            <th class="pb-3">Horário da Aula/Liberação</th>
                            <th class="pb-3 text-right">Presença / Liberar Aula</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-white" id="studentsListTable">
                        <!-- Carregado via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Selection Placeholder -->
        <div class="glass-card rounded-xl p-12 text-center text-on-surface-variant text-xs font-bold uppercase tracking-wider border border-dashed border-white/10" id="placeholderCard">
            Selecione um treinamento acima para exibir a lista de presença física.
        </div>
    </main>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadCoursesSelect();
    });

    // 1. CARREGAR CURSOS NO DROPDOWN
    async function loadCoursesSelect() {
        try {
            const response = await fetch('../api/admin/courses.php');
            if (!response.ok) throw new Error('Não foi possível carregar a lista de cursos.');
            const courses = await response.json();
            
            const select = document.getElementById('courseSelect');
            select.innerHTML = '<option value="">-- Escolha um Treinamento --</option>';

            const hybridPresential = courses.filter(c => c.type === 'hybrid' || c.type === 'presencial');

            if (hybridPresential.length === 0) {
                select.innerHTML = '<option value="">Nenhum curso presencial/híbrido ativo.</option>';
                return;
            }

            hybridPresential.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.title} (${c.type === 'hybrid' ? 'Híbrido' : 'Presencial'})</option>`;
            });
        } catch (err) {
            showToast('Erro ao obter cursos: ' + err.message, 'error');
        }
    }

    // 2. RECUPERAR LISTA DE ALUNOS COM AS PRESENÇAS NA DATA ESPECIFICADA
    async function loadAttendanceList() {
        const courseId = document.getElementById('courseSelect').value;
        const date = document.getElementById('dateSelect').value;
        
        const placeholder = document.getElementById('placeholderCard');
        const card = document.getElementById('attendanceCard');
        const table = document.getElementById('studentsListTable');

        if (!courseId) {
            card.classList.add('hidden');
            placeholder.classList.remove('hidden');
            return;
        }

        placeholder.classList.add('hidden');
        card.classList.remove('hidden');
        table.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-on-surface-variant text-xs font-bold uppercase tracking-wider">Carregando lista...</td></tr>`;

        try {
            const response = await fetch(`../api/admin/attendance.php?course_id=${courseId}&date=${date}`);
            if (!response.ok) throw new Error('Falha ao buscar chamadas.');
            const res = await response.json();

            if (!res.success) throw new Error(res.error || 'Erro ao carregar.');

            table.innerHTML = '';

            if (res.students.length === 0) {
                table.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-on-surface-variant font-bold tracking-wider italic">Nenhum aluno com matrícula ativa neste treinamento.</td></tr>`;
                return;
            }

            res.students.forEach(s => {
                // Tenta extrair um horário padrão para o input de time_slot
                let defaultTime = s.time_slot || '';
                if (!defaultTime && s.schedule_time && s.schedule_time !== 'Não agendado') {
                    // Se for do tipo "08:00 às 10:00", tenta pegar o "08:00"
                    const match = s.schedule_time.match(/(\d{2}:\d{2})/);
                    if (match) {
                        defaultTime = match[1];
                    }
                }
                if (!defaultTime) {
                    const now = new Date();
                    defaultTime = now.toTimeString().substring(0, 5); // ex: "08:30"
                }

                table.innerHTML += `
                    <tr class="hover:bg-white/[0.01] transition-all border-b border-white/5">
                        <td class="py-4 font-bold text-white">${s.student_name}</td>
                        <td class="py-4 text-on-surface-variant">${s.student_email}</td>
                        <td class="py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">
                                <span class="material-symbols-outlined text-xs">schedule</span>
                                ${s.schedule_time}
                            </span>
                        </td>
                        <td class="py-4">
                            <input type="time" id="time_slot_${s.student_id}" value="${defaultTime}" class="px-2.5 py-1.5 rounded bg-black/40 border border-white/10 text-white text-xs outline-none focus:border-primary/50 transition-all" style="color-scheme: dark;">
                        </td>
                        <td class="py-4 text-right">
                            <label class="switch">
                                <input type="checkbox" ${s.attended ? 'checked' : ''} onchange="toggleAttendance(this, ${s.student_id}, 'time_slot_${s.student_id}')">
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>
                `;
            });

        } catch (err) {
            showToast(err.message, 'error');
            table.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-error font-bold tracking-wider">Erro ao processar chamada.</td></tr>`;
        }
    }

    async function toggleAttendance(checkbox, studentId, timeInputId = null) {
        const courseId = document.getElementById('courseSelect').value;
        const date = document.getElementById('dateSelect').value;
        const attended = checkbox.checked;
        const timeSlot = timeInputId ? document.getElementById(timeInputId).value : null;

        const payload = {
            course_id: parseInt(courseId),
            student_id: studentId,
            date: date,
            time_slot: timeSlot,
            attended: attended
        };

        checkbox.disabled = true;

        try {
            const response = await fetch('../api/admin/attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (!response.ok) throw new Error(res.error || 'Falha ao salvar presença.');

            showToast(res.message, 'success');
        } catch (err) {
            showToast(err.message, 'error');
            checkbox.checked = !attended;
        } finally {
            checkbox.disabled = false;
        }
    }

    // 4. HELPER DE NOTIFICAÇÃO TOAST
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const bgClass = type === 'success' ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-red-500/10 border-red-500/20 text-red-400';
        const icon = type === 'success' ? 'check_circle' : 'error';

        const toast = document.createElement('div');
        toast.className = `glass-card border rounded-lg px-4 py-3 flex items-center gap-3 text-xs font-semibold shadow-xl transition-all duration-300 transform translate-y-2 opacity-0 ${bgClass}`;
        toast.innerHTML = `
            <span class="material-symbols-outlined text-[18px]">${icon}</span>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        }, 50);

        setTimeout(() => {
            toast.classList.add('translate-y-2', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>
</body>
</html>
