<?php
/**
 * VERSIÓN SIMPLIFICADA: Enviar emails usando mail() de PHP
 * Con configuración del remitente del CRM
 */

function enviarEmailGmailAPI($to, $subject, $htmlBody, $attachments = []) {
    
    // Log simple
    $logMsg = "[" . date('Y-m-d H:i:s') . "] Intentando enviar email a: $to\n";
    error_log($logMsg);
    
    // Ruta al CRM
    $crm_path = realpath(__DIR__ . '/../../');
    
    // Leer configuración de Email del CRM
    $emailConfigPath = $crm_path . '/app/Config/Email.php';
    
    $fromEmail = 'hola@tictac-comunicacion.es';
    $fromName = 'Tictac Comunicación';
    
    if (file_exists($emailConfigPath)) {
        $configContent = file_get_contents($emailConfigPath);
        
        // Extraer fromEmail
        if (preg_match('/public\s+\$fromEmail\s*=\s*[\'"]([^\'"]+)[\'"]/', $configContent, $match)) {
            $fromEmail = $match[1];
            error_log("FromEmail encontrado: $fromEmail");
        }
        
        // Extraer fromName
        if (preg_match('/public\s+\$fromName\s*=\s*[\'"]([^\'"]+)[\'"]/', $configContent, $match)) {
            $fromName = $match[1];
            error_log("FromName encontrado: $fromName");
        }
    } else {
        error_log("No se encontró Email.php, usando valores por defecto");
    }
    
    // Preparar headers
    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Si hay adjuntos, usar multipart
    if (!empty($attachments)) {
        $boundary = md5(time() . rand());
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        // Construir cuerpo con adjuntos
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n";
        
        // Añadir cada adjunto
        foreach ($attachments as $att) {
            if (isset($att['file_path']) && file_exists($att['file_path'])) {
                $filename = basename($att['file_path']);
                $fileContent = file_get_contents($att['file_path']);
                $encodedContent = chunk_split(base64_encode($fileContent));
                
                $body .= "--$boundary\r\n";
                $body .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
                $body .= $encodedContent . "\r\n";
                
                error_log("Adjunto agregado: $filename");
            }
        }
        
        $body .= "--$boundary--";
        
    } else {
        // Sin adjuntos, solo HTML
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body = $htmlBody;
    }
    
    // Enviar email
    error_log("Enviando email...");
    $resultado = mail($to, $subject, $body, $headers);
    
    if ($resultado) {
        error_log("✅ Email enviado correctamente a $to");
        return true;
    } else {
        error_log("❌ Error al enviar email a $to");
        return false;
    }
}
?>