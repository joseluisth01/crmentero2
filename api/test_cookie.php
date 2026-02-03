<?php
/**
 * TEST - Ver cookies y limpiarlas
 * Subir a /api/, usar para prueba, ELIMINAR después.
 * 
 * Este archivo NO incluye config.php para que no ejecute la verificación de sesión.
 */

// Si viene con ?clear=1, elimina las cookies
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    // Eliminar todas las cookies conocidas del CRM
    $cookiesToClear = [
        'ci_session',
        'PHPSESSID', 
        'rise_csrf_cookie',
        'cookie_hash_of_store',
        'chatbox_open',
        'active_chat_id',
        'left_menu_minimized'
    ];
    
    foreach ($cookiesToClear as $cookie) {
        setcookie($cookie, '', time() - 3600, '/');
        setcookie($cookie, '', time() - 3600, '/', 'gestion-tictac-comunicacion.es');
    }
    
    // También eliminar todas las cookies que empiezan con user_41_ y selected_
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'user_41_') === 0 || strpos($name, 'selected_') === 0) {
            setcookie($name, '', time() - 3600, '/');
            setcookie($name, '', time() - 3600, '/', 'gestion-tictac-comunicacion.es');
        }
    }
    
    header('Location: test_cookie.php?cleared=1');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Cookies</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
        .box { background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #E91E8C; }
        .btn { display: inline-block; padding: 12px 30px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px; margin-top: 15px; }
        .btn:hover { background: #c82333; }
        .btn-green { background: #28a745; }
        .btn-green:hover { background: #218838; }
        .ok { color: #28a745; font-weight: bold; }
        .ko { color: #dc3545; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        td:first-child { font-weight: bold; color: #666; width: 250px; }
        .highlight { background: #fff3cd; }
        .steps { background: #d1ecf1; padding: 20px; border-radius: 8px; margin-top: 15px; }
        .steps ol { margin: 0; padding-left: 20px; }
        .steps li { margin-bottom: 8px; }
    </style>
</head>
<body>

<div class="box">
    <h1>🍪 Test de Cookies</h1>
    
    <?php if (isset($_GET['cleared']) && $_GET['cleared'] === '1'): ?>
        <p class="ok">✓ Cookies eliminadas. Ahora sigue los pasos de abajo.</p>
    <?php endif; ?>

    <div class="steps">
        <strong>Pasos para la prueba:</strong>
        <ol>
            <li>Haz clic en <strong>"Eliminar todas las cookies"</strong> de abajo</li>
            <li>Cierra esta pestaña</li>
            <li>Abre una nueva pestaña y ve a <code>https://gestion-tictac-comunicacion.es/api/</code></li>
            <li>Si te manda al login → <span class="ok">FUNCIONA ✓</span></li>
            <li>Si te muestra el dashboard → <span class="ko">NO FUNCIONA ✗</span> (mándame screenshot)</li>
        </ol>
    </div>

    <a href="test_cookie.php?clear=1" class="btn">🗑️ Eliminar todas las cookies</a>
</div>

<div class="box">
    <h2>Cookies actuales en el navegador</h2>
    <?php
    if (empty($_COOKIE)) {
        echo '<p class="ok">No hay cookies. ✓</p>';
    } else {
        echo '<table>';
        foreach ($_COOKIE as $name => $value) {
            $isCI = ($name === 'ci_session');
            $class = $isCI ? ' class="highlight"' : '';
            echo "<tr{$class}><td>{$name}</td><td><code>" . htmlspecialchars(substr($value, 0, 60)) . "</code></td></tr>";
        }
        echo '</table>';
    }
    ?>
</div>

</body>
</html>