<?php
declare(strict_types=1);

/**
 * Layout Header Padrão - Rede Alabama
 * Header unificado para todas as páginas do painel
 */

// Define título padrão se não foi definido
$pageTitle = $pageTitle ?? 'Rede Alabama';
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Vendor CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alabama Design System -->
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="<?= $basePath ?>alabama-theme.css">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/alabama-navigation.css">
    
    <!-- Page specific CSS can be added here -->
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="al-body al-sidebar-expanded">
    <!-- Sidebar Menu -->
    <?php require_once __DIR__ . '/sidebar_menu.php'; ?>
    
    <!-- Main Content Wrapper -->
    <div class="al-main-wrapper">
        <!-- Global Search Modal -->
        <?php require_once __DIR__ . '/global_search.php'; ?>
