<?php
/**
 * Redirector de Propuestas CRM → Dashboard
 * 
 * Este script intercepta las peticiones de edición de propuestas del CRM
 * y las redirige al editor del dashboard.
 * 
 * USO:
 * 1. Instalar en: /dashboard/presupuestos/redirect_proposal.php
 * 2. Configurar en el CRM para que use esta URL como editor de propuestas
 */

require_once '../config.php';

// Obtener el ID de la propuesta del CRM
$crm_proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
$crm_proposal_id_alt = isset($_GET['id']) ? intval($_GET['id']) : 0;

$proposal_id = $crm_proposal_id > 0 ? $crm_proposal_id : $crm_proposal_id_alt;

if ($proposal_id <= 0) {
    die('❌ Error: ID de propuesta no válido');
}

// Buscar el presupuesto local que corresponde a esta propuesta del CRM
$presupuestosFile = DATA_PATH . '/presupuestos.json';
$presupuestoLocal = null;

if (file_exists($presupuestosFile)) {
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    
    if (is_array($presupuestos)) {
        foreach ($presupuestos as $p) {
            if (isset($p['crm_proposal_id']) && $p['crm_proposal_id'] == $proposal_id) {
                $presupuestoLocal = $p;
                break;
            }
        }
    }
}

// Si encontramos el presupuesto local, redirigir al editor
if ($presupuestoLocal) {
    $localId = $presupuestoLocal['id'];
    header('Location: editor.php?id=' . urlencode($localId));
    exit;
}

// Si no existe localmente, crear uno nuevo desde los datos del CRM
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importando Propuesta...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f5f5;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: ' . BRAND_COLOR . ';
            margin-bottom: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid ' . BRAND_COLOR . ';
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            color: #666;
            margin-top: 20px;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: ' . BRAND_COLOR . ';
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
        }
        .btn:hover {
            background: ' . BRAND_COLOR_DARK . ';
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Importando Propuesta</h1>
        <div class="spinner"></div>
        <div class="message">
            <p>Esta propuesta (ID: ' . $proposal_id . ') fue creada directamente en el CRM.</p>
            <p>Estamos importándola al sistema de presupuestos del dashboard...</p>
        </div>
        <div id="status"></div>
    </div>
    
    <script>
        // Intentar importar la propuesta del CRM
        setTimeout(function() {
            fetch("import_proposal.php?proposal_id=' . $proposal_id . '")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = "editor.php?id=" + encodeURIComponent(data.local_id);
                    } else {
                        document.getElementById("status").innerHTML = 
                            \'<p class="error">❌ Error: \' + (data.message || "No se pudo importar") + \'</p>\' +
                            \'<a href="index.php" class="btn">← Volver a Presupuestos</a>\';
                        document.querySelector(".spinner").style.display = "none";
                    }
                })
                .catch(error => {
                    document.getElementById("status").innerHTML = 
                        \'<p class="error">❌ Error de conexión</p>\' +
                        \'<a href="index.php" class="btn">← Volver a Presupuestos</a>\';
                    document.querySelector(".spinner").style.display = "none";
                });
        }, 1000);
    </script>
</body>
</html>';
?>