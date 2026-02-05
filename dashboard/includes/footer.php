    <div style="text-align: center; padding: 40px 20px; color: #666; margin-top: 60px;">
        <p><strong><?php echo COMPANY_NAME; ?></strong> - Sistema de Gestión</p>
        <p style="font-size: 12px; margin-top: 10px;">
            Versión <?php echo SYSTEM_VERSION; ?> - Última actualización: <?php echo date('d/m/Y H:i:s'); ?>
        </p>
    </div>
    
    <?php if (isset($additionalScripts)) echo $additionalScripts; ?>
</body>
</html>
