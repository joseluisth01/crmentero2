<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . COMPANY_NAME : COMPANY_NAME; ?></title>
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
            padding: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
        }
        
        /* Fallback si no hay imagen */
        .logo-text {
            font-size: 36px;
            font-weight: 300;
            letter-spacing: 8px;
        }
        
        .back-button {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .page-header h1 {
            color: <?php echo BRAND_COLOR; ?>;
            font-weight: 300;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
    <?php if (isset($additionalStyles)) echo $additionalStyles; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <?php if (defined('LOGO_BLANCO') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/api/assets/img/logoblanco.png')): ?>
                    <img src="<?php echo LOGO_BLANCO; ?>" alt="<?php echo COMPANY_NAME; ?>">
                <?php else: ?>
                    <span class="logo-text">t/ctac</span>
                <?php endif; ?>
            </div>
            <?php if (isset($showBackButton) && $showBackButton): ?>
                <a href="<?php echo BASE_URL; ?>" class="back-button">← Volver al Dashboard</a>
            <?php endif; ?>
        </div>
    </div>