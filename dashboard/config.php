<?php
/**
 * Configuración Global - API Tictac Comunicación
 * Archivo central con todas las configuraciones del sistema
 */

// ============================================
// AUTENTICACIÓN - Verificación de sesión CRM
// ============================================
// mod_rewrite NO está activo en este servidor, así que el .htaccess no funciona.
// La protección se hace aquí directamente desde PHP.
// Esta función se ejecuta ANTES de cualquier otra cosa.

function _verificarSesionCRM() {
    $loginUrl = 'https://gestion-tictac-comunicacion.es/index.php/signin';
    $crmHome = 'https://gestion-tictac-comunicacion.es/index.php';

    // Si no hay cookie ci_session → no hay sesión, al login
    if (!isset($_COOKIE['ci_session']) || trim($_COOKIE['ci_session']) === '') {
        header('Location: ' . $loginUrl);
        exit;
    }

    // Pasa la cookie ci_session al CRM y ve qué responde
    $cookies = 'ci_session=' . $_COOKIE['ci_session'];
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookies .= '; PHPSESSID=' . $_COOKIE['PHPSESSID'];
    }

    $ch = curl_init($crmHome);
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    curl_setopt($ch, CURLOPT_NOBODY, true);              // Solo headers (HEAD), sin descargar cuerpo
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);     // No seguir redirects, queremos ver el 302
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0');

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $curlError = curl_errno($ch);
    curl_close($ch);

    // 200 → logueado, sesión válida ✅
    if ($httpCode === 200) {
        return;
    }

    // 302 o 301 → ver a dónde redirige
    if ($httpCode === 302 || $httpCode === 301) {
        if ($redirectUrl && (strpos($redirectUrl, '/signin') !== false || strpos($redirectUrl, '/login') !== false)) {
            // Redirige al login → sesión caducada ❌
            header('Location: ' . $loginUrl);
            exit;
        }
        // Redirect a otra URL del CRM → sesión válida ✅
        return;
    }

    // 401 o 403 → no autenticado ❌
    if ($httpCode === 401 || $httpCode === 403) {
        header('Location: ' . $loginUrl);
        exit;
    }

    // Error de curl (timeout, DNS, etc.) → dejamos pasar para no bloquear
    // en caso de caída temporal del servidor del CRM
    if ($curlError !== 0 || $httpCode === 0) {
        return;
    }

    // Cualquier otro código inesperado → bloquear por precaución
    header('Location: ' . $loginUrl);
    exit;
}

// Ejecutar verificación INMEDIATAMENTE
_verificarSesionCRM();

// ============================================
// CONFIGURACIÓN DEL CRM
// ============================================
define('CRM_URL', 'https://gestion-tictac-comunicacion.es/index.php/dashboard');
define('API_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJ1c2VyIjoicHJvZHVjY2lvbkB0aWN0YWMtY29tdW5pY2FjaW9uLmVzIiwibmFtZSI6IlByb2R1Y2Npb24iLCJBUElfVElNRSI6MTc2OTUwMjU3MX0.4RoKiYv6z8sBE5MdchSE8iQ7wJnXGOIAlW52Mjn5oZvdRJsAWG3l-VmVIMlj3DawwtDl21e26_twU77usBjuGw');

// ============================================
// RUTAS DEL SISTEMA
// ============================================
define('BASE_PATH', dirname(__FILE__));
define('DATA_PATH', BASE_PATH . '/data');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URLs para navegación
define('BASE_URL', 'https://gestion-tictac-comunicacion.es/dashboard');
define('CLIENTES_URL', BASE_URL . '/clientes');
define('AUDITORIA_URL', BASE_URL . '/auditoria');
define('PRESUPUESTOS_URL', BASE_URL . '/presupuestos');
define('FACTURAS_URL', BASE_URL . '/facturas');

// ============================================
// CONFIGURACIÓN DE MARCA
// ============================================
define('COMPANY_NAME', 'Tictac Comunicación');

// Logos (coloca logocolor.png y logoblanco.png en /assets/img/)
define('LOGO_COLOR', BASE_URL . '/assets/img/logocolor.png');
define('LOGO_BLANCO', BASE_URL . '/assets/img/logoblanco.png');

// Colores corporativos
define('BRAND_COLOR', '#E91E8C');
define('BRAND_COLOR_DARK', '#C91E82');
define('ACCENT_COLOR', '#C6D617');

// ============================================
// CONFIGURACIÓN DEL SISTEMA
// ============================================
define('SYSTEM_VERSION', '1.0');
define('SYSTEM_TIMEZONE', 'Europe/Madrid');

// Establecer zona horaria
date_default_timezone_set(SYSTEM_TIMEZONE);

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
// Mismas credenciales que usa procesosautomaticos/funcionesbbdd.php
function conexionBBDD() {
    static $mysqli = null;
    if ($mysqli !== null) return $mysqli;

    $mysqli = new mysqli('localhost', 'gestiontictaccom_usercron', ']s+tER.&k{ew(!?^', 'gestiontictaccom_admin');
    if (mysqli_connect_errno()) {
        return null;
    }
    $mysqli->query("SET NAMES 'utf8'");
    return $mysqli;
}

// ============================================
// FUNCIONES GLOBALES
// ============================================

/**
 * Realizar llamada a la API del CRM (para endpoints que SÍ existen: clients, projects, etc.)
 */
function callCrmApi($endpoint, $method = 'GET', $data = null) {
    $url = CRM_URL . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'authtoken: ' . API_TOKEN,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Obtener artículos del catálogo directamente de la BBDD del CRM.
 * 
 * El plugin RestApi NO expone un endpoint /api/items.
 * Los artículos viven en la tabla `items` con campos:
 *   id, title, description, category_id, unit_type, rate, deleted
 * 
 * Este método es idéntico a lo que hace Items_model.php internamente.
 */
function getArticulosCRM() {
    $mysqli = conexionBBDD();
    if (!$mysqli) {
        return array();
    }

    $sql = "SELECT i.id, i.title, i.description, i.unit_type, i.rate, ic.title AS category_title
            FROM crm_items i
            LEFT JOIN crm_item_categories ic ON ic.id = i.category_id
            WHERE i.deleted = 0
            ORDER BY i.title ASC";

    $result = $mysqli->query($sql);
    if (!$result) {
        return array();
    }

    $articulos = array();
    while ($row = $result->fetch_assoc()) {
        $articulos[] = $row;
    }
    $result->free();

    return $articulos;
}

/**
 * Guardar registro en archivo JSON de auditoría
 */
function guardarAuditoria($tipo, $estado, $mensaje, $datos = array()) {
    $logFile = DATA_PATH . '/auditoria.json';
    
    $logs = array();
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true);
        if (!is_array($logs)) $logs = array();
    }
    
    $registro = array(
        'fecha' => date('Y-m-d H:i:s'),
        'tipo' => $tipo,
        'estado' => $estado,
        'mensaje' => $mensaje,
        'cliente_id' => isset($datos['cliente_id']) ? $datos['cliente_id'] : 0,
        'cliente_nombre' => isset($datos['cliente_nombre']) ? $datos['cliente_nombre'] : '',
        'email' => isset($datos['email']) ? $datos['email'] : ''
    );
    
    $logs[] = $registro;
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Formatear fecha en español
 */
function formatearFecha($fecha) {
    if (empty($fecha)) return 'N/A';
    
    $timestamp = strtotime($fecha);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Obtener tipo de cliente según sus grupos
 */
function getTipoCliente($cliente) {
    $clientGroups = isset($cliente['client_groups']) ? strtolower($cliente['client_groups']) : '';
    
    if (strpos($clientGroups, 'inactivo') !== false) {
        return 'inactivo';
    }
    
    if (!empty($clientGroups)) {
        if (strpos($clientGroups, 'sin') !== false && strpos($clientGroups, 'mantenimiento') !== false) {
            return 'sin_mantenimiento';
        }
        if (strpos($clientGroups, 'mantenimiento') !== false) {
            return 'con_mantenimiento';
        }
    }
    
    return 'sin_mantenimiento';
}

/**
 * Redireccionar a una URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Mostrar mensaje de error y terminar
 */
function mostrarError($mensaje) {
    http_response_code(500);
    echo json_encode(array('error' => true, 'mensaje' => $mensaje));
    exit;
}

/**
 * Mostrar mensaje de éxito
 */
function mostrarExito($mensaje, $datos = array()) {
    http_response_code(200);
    $response = array('error' => false, 'mensaje' => $mensaje);
    if (!empty($datos)) {
        $response = array_merge($response, $datos);
    }
    echo json_encode($response);
    exit;
}
?>