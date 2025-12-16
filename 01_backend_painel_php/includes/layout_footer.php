<?php
declare(strict_types=1);

/**
 * Layout Footer Padrão - Rede Alabama
 * Footer unificado para todas as páginas do painel
 */
?>
    </div><!-- .al-main-wrapper -->
    
    <!-- Vendor JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Alabama Navigation System -->
    <script src="<?= $basePath ?? '' ?>assets/js/navigation.js"></script>
    
    <!-- Page specific JS can be added here -->
    <?php if (isset($additionalJS) && is_array($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- CSRF Token for JavaScript -->
    <?php if (function_exists('csrf_token')): ?>
    <script <?php echo function_exists('alabama_csp_nonce_attr') ? alabama_csp_nonce_attr() : ''; ?>>
        window.AL_BAMA_CSRF_TOKEN = "<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>";
    </script>
    <?php endif; ?>
</body>
</html>
