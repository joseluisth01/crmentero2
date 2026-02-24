<?php
/**
 * Dashboard Principal - Sistema Tictac
 * Panel de control con acceso a todas las funcionalidades
 */
require_once 'config.php';
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . COMPANY_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* ── HEADER ───────────────────────────────────────── */
        .header {
            background: linear-gradient(135deg, <?php echo BRAND_COLOR; ?> 0%, <?php echo BRAND_COLOR_DARK; ?> 100%);
            color: white;
            padding: 40px 30px 0 30px;
            text-align: center;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .logo { margin-bottom: 10px; }
        .logo img { height: 80px; width: auto; max-width: 300px; object-fit: contain; }
        .logo-text { font-size: 48px; font-weight: 300; letter-spacing: 8px; }
        .tagline { font-size: 14px; letter-spacing: 2px; opacity: 0.9; margin-bottom: 15px; }

        .crm-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.15); color: white; text-decoration: none;
            padding: 8px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.6);
            font-size: 13px; font-weight: 600; letter-spacing: 1px;
            transition: all 0.2s; backdrop-filter: blur(5px); margin-bottom: 25px;
        }
        .crm-btn:hover { background: rgba(255,255,255,0.3); border-color: white; }

        /* ── TABS NAV ─────────────────────────────────────── */
        .tabs-nav {
            display: flex; justify-content: center;
            gap: 0; margin-top: 10px;
        }
        .tab-btn {
            background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.8);
            border: none; padding: 14px 28px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; border-radius: 12px 12px 0 0;
            margin: 0 3px; position: relative; top: 1px; white-space: nowrap;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.25); color: white; }
        .tab-btn.active { background: #f5f5f5; color: <?php echo BRAND_COLOR; ?>; }

        .tab-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: #dc3545; color: white; font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px; border-radius: 9px;
            padding: 0 5px; margin-left: 6px; vertical-align: middle;
        }

        /* ── TAB CONTENT ──────────────────────────────────── */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ── CONTAINER ────────────────────────────────────── */
        .container { max-width: 1200px; margin: 0 auto; padding: 50px 20px; }

        /* ── PESTAÑA INICIO ───────────────────────────────── */
        .welcome { text-align: center; margin-bottom: 50px; }
        .welcome h1 { color: <?php echo BRAND_COLOR; ?>; font-weight: 300; font-size: 32px; margin-bottom: 10px; }
        .welcome p { color: #666; font-size: 16px; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; margin-top: 40px;
        }
        .dashboard-card {
            background: white; border-radius: 15px; padding: 40px 30px;
            text-align: center; box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            transition: all 0.3s; cursor: pointer; text-decoration: none;
            color: inherit; display: block;
        }
        .dashboard-card:hover { transform: translateY(-10px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
        .dashboard-card.active { border: 3px solid <?php echo ACCENT_COLOR; ?>; }
        .dashboard-card.disabled { opacity: 0.5; cursor: not-allowed; }
        .dashboard-card.disabled:hover { transform: none; }
        .card-icon { font-size: 64px; margin-bottom: 20px; }
        .card-title { font-size: 24px; font-weight: 600; color: <?php echo BRAND_COLOR; ?>; margin-bottom: 15px; }
        .card-description { color: #666; font-size: 14px; line-height: 1.6; }
        .badge {
            display: inline-block; background: <?php echo ACCENT_COLOR; ?>; color: #333;
            padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 15px;
        }
        .badge-disabled { background: #ccc; }

        /* ── VENCIMIENTOS — COMPARTIDO ────────────────────── */
        .venc-header { text-align: center; margin-bottom: 35px; }
        .venc-header h2 { color: <?php echo BRAND_COLOR; ?>; font-weight: 300; font-size: 28px; margin-bottom: 8px; }
        .venc-header p { color: #666; font-size: 15px; }

        .venc-leyenda {
            display: flex; gap: 20px; justify-content: center;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .venc-leyenda span {
            display: flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 600; color: #444;
        }
        .dot { width: 13px; height: 13px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .venc-filtros {
            display: flex; gap: 10px; justify-content: center;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .btn-filtro-venc {
            padding: 8px 18px; border: 2px solid #ddd; background: white;
            border-radius: 25px; cursor: pointer; font-weight: 600;
            font-size: 13px; color: #666; transition: all 0.2s;
        }
        .btn-filtro-venc:hover,
        .btn-filtro-venc.active {
            border-color: <?php echo BRAND_COLOR; ?>;
            color: <?php echo BRAND_COLOR; ?>; background: #fff0f7;
        }
        .venc-contador {
            text-align: center; margin-bottom: 20px;
            font-size: 13px; color: #888; min-height: 20px;
        }

        .venc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .venc-card {
            background: white; border-radius: 12px; padding: 20px 24px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.07); border-left: 5px solid #ccc;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none; color: inherit; display: block;
        }
        .venc-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }

        .venc-card.urgente { border-left-color: #dc3545; }
        .venc-card.proximo { border-left-color: #fd7e14; }
        .venc-card.medio   { border-left-color: #ffc107; }
        .venc-card.largo   { border-left-color: #28a745; }

        .venc-card-inner { display: flex; justify-content: space-between; align-items: flex-start; }
        .venc-card-info { flex: 1; }

        .venc-tipo-pill {
            display: inline-block; padding: 2px 8px; border-radius: 8px;
            font-size: 10px; font-weight: 700; margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .venc-tipo-proyecto { background: #e8f0fe; color: #1a56db; }
        .venc-tipo-contrato { background: #f3e8ff; color: #7c3aed; }

        .venc-cliente {
            font-size: 11px; color: #999; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 4px;
        }
        .venc-titulo { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 12px; line-height: 1.3; }
        .venc-fecha  { font-size: 13px; color: #666; }

        .venc-dias-box { text-align: right; margin-left: 15px; flex-shrink: 0; }
        .venc-dias { font-size: 36px; font-weight: 700; line-height: 1; }

        .urgente .venc-dias { color: #dc3545; }
        .proximo .venc-dias { color: #fd7e14; }
        .medio   .venc-dias { color: #ffc107; }
        .largo   .venc-dias { color: #28a745; }

        .venc-dias-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; }

        .venc-pill {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 700; margin-top: 10px;
        }
        .urgente .venc-pill { background: #fde8ea; color: #dc3545; }
        .proximo .venc-pill { background: #fff0e6; color: #fd7e14; }
        .medio   .venc-pill { background: #fff8e1; color: #856404; }
        .largo   .venc-pill { background: #e8f5e9; color: #28a745; }

        .venc-empty {
            text-align: center; padding: 60px 20px; color: #999;
            background: white; border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.07);
        }

        /* ── FOOTER ───────────────────────────────────────── */
        .footer { text-align: center; padding: 40px 20px; color: #666; margin-top: 60px; }
        .footer-logo img { height: 50px; width: auto; max-width: 200px; object-fit: contain; margin-bottom: 10px; }
        .footer-logo-text { font-size: 24px; font-weight: 300; letter-spacing: 4px; color: <?php echo BRAND_COLOR; ?>; }

        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .venc-grid      { grid-template-columns: 1fr; }
            .tab-btn        { padding: 10px 14px; font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- ── HEADER ──────────────────────────────────────────────── -->
<div class="header">
    <div class="logo">
        <?php if (defined('LOGO_BLANCO') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/dashboard/assets/img/logoblanco.png')): ?>
            <img src="<?php echo LOGO_BLANCO; ?>" alt="<?php echo COMPANY_NAME; ?>">
        <?php else: ?>
            <span class="logo-text">t/ctac</span>
        <?php endif; ?>
    </div>
    <div class="tagline">COMUNICACIÓN - PANEL DE GESTIÓN</div>
    <div>
        <a href="https://gestion-tictac-comunicacion.es/index.php/dashboard" class="crm-btn">
            ← VOLVER AL CRM
        </a>
    </div>
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('inicio')" id="tab-btn-inicio">
            🏠 Inicio
        </button>
        <button class="tab-btn" onclick="switchTab('proyectos')" id="tab-btn-proyectos">
            📁 Venc. Proyectos
            <span class="tab-badge" id="badge-proyectos" style="display:none;"></span>
        </button>
        <button class="tab-btn" onclick="switchTab('contratos')" id="tab-btn-contratos">
            📄 Venc. Contratos
            <span class="tab-badge" id="badge-contratos" style="display:none;"></span>
        </button>
    </div>
</div>

<!-- ── PESTAÑA 1: INICIO ───────────────────────────────────── -->
<div id="tab-inicio" class="tab-content active">
    <div class="container">
        <div class="welcome">
            <h1>Bienvenido al Dashboard</h1>
            <p>Gestiona clientes, presupuestos y facturas desde un solo lugar</p>
        </div>
        <div class="dashboard-grid">

            <a href="<?php echo CLIENTES_URL; ?>" class="dashboard-card active">
                <div class="card-icon">👥</div>
                <div class="card-title">Clientes</div>
                <div class="card-description">Visualiza, filtra y gestiona todos tus clientes del CRM. Distingue entre activos e inactivos.</div>
                <span class="badge">Disponible</span>
            </a>

            <a href="<?php echo AUDITORIA_URL; ?>" class="dashboard-card active">
                <div class="card-icon">📊</div>
                <div class="card-title">Auditoría</div>
                <div class="card-description">Registro completo de emails automáticos enviados y todas las acciones del sistema.</div>
                <span class="badge">Disponible</span>
            </a>

            <a href="<?php echo PRESUPUESTOS_URL; ?>" class="dashboard-card active">
                <div class="card-icon">📝</div>
                <div class="card-title">Presupuestos</div>
                <div class="card-description">Crea y genera presupuestos profesionales para tus clientes de forma rápida y sencilla.</div>
                <span class="badge">Disponible</span>
            </a>

            <a href="<?php echo BASE_URL; ?>/contratos" class="dashboard-card active">
                <div class="card-icon">📄</div>
                <div class="card-title">Contratos</div>
                <div class="card-description">Genera contratos de servicios personalizados con todas las cláusulas legales. Envíalos firmados directamente al cliente.</div>
                <span class="badge">Disponible</span>
            </a>

            <a href="#" class="dashboard-card disabled" onclick="return false;">
                <div class="card-icon">🧾</div>
                <div class="card-title">Preparar Factura</div>
                <div class="card-description">Genera facturas profesionales y envíalas directamente a tus clientes desde el sistema.</div>
                <span class="badge badge-disabled">Próximamente</span>
            </a>

        </div>
    </div>
</div>

<!-- ── PESTAÑA 2: VENCIMIENTOS PROYECTOS ───────────────────── -->
<div id="tab-proyectos" class="tab-content">
    <div class="container">
        <div class="venc-header">
            <h2>📁 Vencimientos de Proyectos</h2>
            <p>Proyectos activos con fecha de fin próxima — gestiona renovaciones a tiempo</p>
        </div>
        <div class="venc-leyenda">
            <span><span class="dot" style="background:#dc3545;"></span> Menos de 30 días</span>
            <span><span class="dot" style="background:#fd7e14;"></span> 30 a 90 días</span>
            <span><span class="dot" style="background:#ffc107;"></span> 90 a 180 días</span>
            <span><span class="dot" style="background:#28a745;"></span> Más de 180 días</span>
        </div>
        <div class="venc-filtros" id="filtros-proyectos">
            <button onclick="filtrar('proyectos','todos')"   class="btn-filtro-venc active" data-f="todos">Todos</button>
            <button onclick="filtrar('proyectos','urgente')" class="btn-filtro-venc"        data-f="urgente">🔴 Urgentes (&lt;30d)</button>
            <button onclick="filtrar('proyectos','proximo')" class="btn-filtro-venc"        data-f="proximo">🟠 Próximos (30–90d)</button>
            <button onclick="filtrar('proyectos','medio')"   class="btn-filtro-venc"        data-f="medio">🟡 Medio plazo (90–180d)</button>
            <button onclick="filtrar('proyectos','largo')"   class="btn-filtro-venc"        data-f="largo">🟢 Largo plazo (+180d)</button>
        </div>
        <div class="venc-contador" id="contador-proyectos"></div>
        <div id="container-proyectos">
            <div class="venc-empty"><div style="font-size:48px;margin-bottom:12px;">⏳</div><strong>Cargando...</strong></div>
        </div>
    </div>
</div>

<!-- ── PESTAÑA 3: VENCIMIENTOS CONTRATOS ───────────────────── -->
<div id="tab-contratos" class="tab-content">
    <div class="container">
        <div class="venc-header">
            <h2>📄 Vencimientos de Contratos</h2>
            <p>Contratos activos con fecha de validez próxima — contacta con tus clientes para renovarlos</p>
        </div>
        <div class="venc-leyenda">
            <span><span class="dot" style="background:#dc3545;"></span> Menos de 30 días</span>
            <span><span class="dot" style="background:#fd7e14;"></span> 30 a 90 días</span>
            <span><span class="dot" style="background:#ffc107;"></span> 90 a 180 días</span>
            <span><span class="dot" style="background:#28a745;"></span> Más de 180 días</span>
        </div>
        <div class="venc-filtros" id="filtros-contratos">
            <button onclick="filtrar('contratos','todos')"   class="btn-filtro-venc active" data-f="todos">Todos</button>
            <button onclick="filtrar('contratos','urgente')" class="btn-filtro-venc"        data-f="urgente">🔴 Urgentes (&lt;30d)</button>
            <button onclick="filtrar('contratos','proximo')" class="btn-filtro-venc"        data-f="proximo">🟠 Próximos (30–90d)</button>
            <button onclick="filtrar('contratos','medio')"   class="btn-filtro-venc"        data-f="medio">🟡 Medio plazo (90–180d)</button>
            <button onclick="filtrar('contratos','largo')"   class="btn-filtro-venc"        data-f="largo">🟢 Largo plazo (+180d)</button>
        </div>
        <div class="venc-contador" id="contador-contratos"></div>
        <div id="container-contratos">
            <div class="venc-empty"><div style="font-size:48px;margin-bottom:12px;">⏳</div><strong>Cargando...</strong></div>
        </div>
    </div>
</div>

<!-- ── FOOTER ──────────────────────────────────────────────── -->
<div class="footer">
    <div class="footer-logo">
        <?php if (defined('LOGO_COLOR') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/dashboard/assets/img/logocolor.png')): ?>
            <img src="<?php echo LOGO_COLOR; ?>" alt="<?php echo COMPANY_NAME; ?>">
        <?php else: ?>
            <span class="footer-logo-text">t/ctac</span>
        <?php endif; ?>
    </div>
    <p><strong><?php echo COMPANY_NAME; ?></strong> - Agencia de Marketing Digital</p>
    <p style="font-size:12px;margin-top:10px;color:#999;">
        Sistema de Gestión v<?php echo SYSTEM_VERSION; ?> - <?php echo date('Y'); ?>
    </p>
</div>

<script>
/* ── ESTADO ─────────────────────────────────────────────────── */
const datos = { proyectos: [], contratos: [] };
const cargado = { proyectos: false, contratos: false };
let datosGlobalesCargados = false;

/* ── TABS ───────────────────────────────────────────────────── */
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('tab-btn-' + tab).classList.add('active');

    if ((tab === 'proyectos' || tab === 'contratos') && !cargado[tab]) {
        if (datosGlobalesCargados) {
            renderTab(tab, datos[tab]);
            cargado[tab] = true;
        }
        // Si aún no cargaron, se renderizan cuando llegue la respuesta fetch
    }
}

/* ── HELPERS ────────────────────────────────────────────────── */
function getCategoria(dias) {
    dias = parseInt(dias);
    if (dias < 30)  return 'urgente';
    if (dias < 90)  return 'proximo';
    if (dias < 180) return 'medio';
    return 'largo';
}

function getTexto(dias) {
    dias = parseInt(dias);
    if (dias === 0) return '¡Vence HOY!';
    if (dias === 1) return 'Vence mañana — URGENTE';
    if (dias < 30)  return 'Vence en ' + dias + ' días — URGENTE';
    if (dias < 90)  return 'Vence en ' + dias + ' días';
    const m = Math.round(dias / 30);
    return 'Vence en ' + m + ' ' + (m === 1 ? 'mes' : 'meses');
}

function esc(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

/* ── RENDER ─────────────────────────────────────────────────── */
function renderTab(tipo, items) {
    const cont   = document.getElementById('container-' + tipo);
    const contEl = document.getElementById('contador-' + tipo);
    const esContrato = tipo === 'contratos';

    if (!items.length) {
        cont.innerHTML = `<div class="venc-empty">
            <div style="font-size:48px;margin-bottom:12px;">✅</div>
            <strong>No hay vencimientos en este rango</strong><br>
            <span style="font-size:13px;margin-top:8px;display:block;">Prueba otro filtro.</span>
        </div>`;
        contEl.textContent = '';
        return;
    }

    const counts = { urgente:0, proximo:0, medio:0, largo:0 };
    items.forEach(p => counts[getCategoria(p.dias_restantes)]++);
    const partes = [];
    if (counts.urgente) partes.push('<span style="color:#dc3545;font-weight:700;">🔴 ' + counts.urgente + ' urgentes</span>');
    if (counts.proximo) partes.push('<span style="color:#fd7e14;font-weight:700;">🟠 ' + counts.proximo + ' próximos</span>');
    if (counts.medio)   partes.push('<span style="color:#856404;font-weight:700;">🟡 ' + counts.medio   + ' a medio plazo</span>');
    if (counts.largo)   partes.push('<span style="color:#28a745;font-weight:700;">🟢 ' + counts.largo   + ' a largo plazo</span>');
    contEl.innerHTML = 'Mostrando <strong>' + items.length + '</strong> — ' + partes.join(' · ');

    const baseUrl = esContrato
        ? 'https://gestion-tictac-comunicacion.es/index.php/contracts/view/'
        : 'https://gestion-tictac-comunicacion.es/index.php/projects/view/';

    const cards = items.map(p => {
        const dias  = parseInt(p.dias_restantes);
        const cat   = getCategoria(dias);
        const texto = getTexto(dias);
        const fecha = new Date(p.fecha_fin + 'T00:00:00').toLocaleDateString('es-ES', {
            day: '2-digit', month: 'long', year: 'numeric'
        });
        const tipoPillClass = esContrato ? 'venc-tipo-contrato' : 'venc-tipo-proyecto';
        const tipoLabel     = esContrato ? '📄 Contrato' : '📁 Proyecto';

        return `
        <a href="${baseUrl + p.id}" target="_blank" class="venc-card ${cat}">
            <div class="venc-card-inner">
                <div class="venc-card-info">
                    <div><span class="venc-tipo-pill ${tipoPillClass}">${tipoLabel}</span></div>
                    <div class="venc-cliente">${esc(p.company_name || 'Sin cliente')}</div>
                    <div class="venc-titulo">${esc(p.title)}</div>
                    <div class="venc-fecha">📅 ${fecha}</div>
                    <div><span class="venc-pill">${texto}</span></div>
                </div>
                <div class="venc-dias-box">
                    <div class="venc-dias">${dias}</div>
                    <div class="venc-dias-label">días</div>
                </div>
            </div>
        </a>`;
    }).join('');

    cont.innerHTML = '<div class="venc-grid">' + cards + '</div>';
}

/* ── FILTROS ────────────────────────────────────────────────── */
function filtrar(tipo, filtro) {
    document.querySelectorAll('#filtros-' + tipo + ' .btn-filtro-venc').forEach(b => {
        b.classList.toggle('active', b.getAttribute('data-f') === filtro);
    });
    const filtrados = filtro === 'todos'
        ? datos[tipo]
        : datos[tipo].filter(p => getCategoria(parseInt(p.dias_restantes)) === filtro);
    renderTab(tipo, filtrados);
}

/* ── CARGA INICIAL ──────────────────────────────────────────── */
fetch('api_vencimientos.php')
    .then(r => r.json())
    .then(response => {
        if (response.error) throw new Error(response.error);

        datos.proyectos = response.proyectos || [];
        datos.contratos = response.contratos || [];
        datosGlobalesCargados = true;

        // Badge proyectos urgentes
        const urgP = datos.proyectos.filter(p => parseInt(p.dias_restantes) < 30).length;
        if (urgP > 0) {
            const b = document.getElementById('badge-proyectos');
            b.textContent = urgP; b.style.display = 'inline-flex';
        }

        // Badge contratos urgentes
        const urgC = datos.contratos.filter(p => parseInt(p.dias_restantes) < 30).length;
        if (urgC > 0) {
            const b = document.getElementById('badge-contratos');
            b.textContent = urgC; b.style.display = 'inline-flex';
        }

        // Si alguna pestaña ya estaba activa antes de cargar, renderizarla ahora
        const tabActiva = document.querySelector('.tab-content.active');
        if (tabActiva && tabActiva.id === 'tab-proyectos' && !cargado.proyectos) {
            renderTab('proyectos', datos.proyectos);
            cargado.proyectos = true;
        }
        if (tabActiva && tabActiva.id === 'tab-contratos' && !cargado.contratos) {
            renderTab('contratos', datos.contratos);
            cargado.contratos = true;
        }
    })
    .catch(err => {
        ['proyectos','contratos'].forEach(t => {
            document.getElementById('container-' + t).innerHTML =
                '<div class="venc-empty">❌ Error: ' + err.message + '</div>';
        });
    });
</script>

</body>
</html>