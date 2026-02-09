<?php
/**
 * Enviar emails usando la configuración Gmail API del CRM
 */

function enviarEmailGmailAPI($to, $subject, $htmlBody, $attachments = []) {
    
    // Cargar la configuración del CRM
    $crm_path = realpath(__DIR__ . '/../../');
    
    // Intentar incluir el autoloader del CRM
    if (file_exists($crm_path . '/vendor/autoload.php')) {
        require_once $crm_path . '/vendor/autoload.php';
    } else {
        error_log("Gmail API: No se encontró autoloader del CRM");
        return false;
    }
    
    // Incluir helpers del CRM para email
    if (file_exists($crm_path . '/app/Helpers/email_helper.php')) {
        require_once $crm_path . '/app/Helpers/email_helper.php';
    }
    
    try {
        // Usar la función del CRM para enviar email
        $email_config = array(
            "to" => $to,
            "subject" => $subject,
            "message" => $htmlBody,
            "attachments" => $attachments
        );
        
        // Llamar a la función de envío del CRM
        if (function_exists('send_app_mail')) {
            $result = send_app_mail($to, $subject, $htmlBody, array("attachments" => $attachments));
            return $result;
        } else {
            error_log("Gmail API: Función send_app_mail no encontrada");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error enviando email con Gmail API: " . $e->getMessage());
        return false;
    }
}
?>