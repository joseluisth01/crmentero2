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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, <?php echo BRAND_COLOR; ?> 0%, <?php echo BRAND_COLOR_DARK; ?> 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .logo {
            margin-bottom: 10px;
        }

        .logo img {
            height: 80px;
            width: auto;
            max-width: 300px;
            object-fit: contain;
        }

        /* Fallback si no hay imagen */
        .logo-text {
            font-size: 48px;
            font-weight: 300;
            letter-spacing: 8px;
        }

        .tagline {
            font-size: 14px;
            letter-spacing: 2px;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .welcome {
            text-align: center;
            margin-bottom: 50px;
        }

        .welcome h1 {
            color: <?php echo BRAND_COLOR; ?>;
            font-weight: 300;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #666;
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card.active {
            border: 3px solid <?php echo ACCENT_COLOR; ?>;
        }

        .dashboard-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .dashboard-card.disabled:hover {
            transform: none;
        }

        .card-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 24px;
            font-weight: 600;
            color: <?php echo BRAND_COLOR; ?>;
            margin-bottom: 15px;
        }

        .card-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .badge {
            display: inline-block;
            background: <?php echo ACCENT_COLOR; ?>;
            color: #333;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 15px;
        }

        .badge-disabled {
            background: #ccc;
        }

        .footer {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            margin-top: 60px;
        }

        .footer-logo {
            margin-bottom: 10px;
        }

        .footer-logo img {
            height: 50px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
        }

        /* Fallback si no hay imagen */
        .footer-logo-text {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 4px;
            color: <?php echo BRAND_COLOR; ?>;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .logo {
                font-size: 36px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            <?php if (defined('LOGO_BLANCO') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/api/assets/img/logoblanco.png')): ?>
                <img src="<?php echo LOGO_BLANCO; ?>" alt="<?php echo COMPANY_NAME; ?>">
            <?php else: ?>
                <span class="logo-text">t/ctac</span>
            <?php endif; ?>
        </div>
        <div class="tagline">COMUNICACIÓN - PANEL DE GESTIÓN</div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>Bienvenido al Dashboard</h1>
            <p>Gestiona clientes, presupuestos y facturas desde un solo lugar</p>
        </div>

        <div class="dashboard-grid">
            <!-- Clientes -->
            <a href="<?php echo CLIENTES_URL; ?>" class="dashboard-card active">
                <div class="card-icon">👥</div>
                <div class="card-title">Clientes</div>
                <div class="card-description">
                    Visualiza, filtra y gestiona todos tus clientes del CRM.
                    Distingue entre activos e inactivos.
                </div>
                <span class="badge">Disponible</span>
            </a>

            <!-- Auditoría -->
            <a href="<?php echo AUDITORIA_URL; ?>" class="dashboard-card active">
                <div class="card-icon">📊</div>
                <div class="card-title">Auditoría</div>
                <div class="card-description">
                    Registro completo de emails automáticos enviados y
                    todas las acciones del sistema.
                </div>
                <span class="badge">Disponible</span>
            </a>

            <!-- Preparar Presupuesto -->
            <a href="<?php echo PRESUPUESTOS_URL; ?>" class="dashboard-card active">
                <div class="card-icon">📝</div>
                <div class="card-title">Preparar Presupuesto</div>
                <div class="card-description">
                    Crea y genera presupuestos profesionales para tus clientes
                    de forma rápida y sencilla.
                </div>
                <span class="badge">Disponible</span>
            </a>

            <!-- Preparar Factura -->
            <a href="#" class="dashboard-card disabled" onclick="return false;">
                <div class="card-icon">🧾</div>
                <div class="card-title">Preparar Factura</div>
                <div class="card-description">
                    Genera facturas profesionales y envíalas directamente
                    a tus clientes desde el sistema.
                </div>
                <span class="badge badge-disabled">Próximamente</span>
            </a>
        </div>
    </div>

    <div class="footer">
        <div class="footer-logo">
            <?php if (defined('LOGO_COLOR') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/api/assets/img/logocolor.png')): ?>
                <img src="<?php echo LOGO_COLOR; ?>" alt="<?php echo COMPANY_NAME; ?>">
            <?php else: ?>
                <span class="footer-logo-text">t/ctac</span>
            <?php endif; ?>
        </div>
        <p><strong><?php echo COMPANY_NAME; ?></strong> - Agencia de Marketing Digital</p>
        <p style="font-size: 12px; margin-top: 10px; color: #999;">
            Sistema de Gestión v<?php echo SYSTEM_VERSION; ?> - <?php echo date('Y'); ?>
        </p>
    </div>
</body>

</html>