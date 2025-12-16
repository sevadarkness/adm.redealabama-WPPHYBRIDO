<?php
declare(strict_types=1);

/**
 * automation_runner.php
 *
 * Runner simples para processar eventos de automação.
 * Pode ser chamado via cron:
 *
 *   php automation_runner.php
 *
 * ou, em último caso, via HTTP (apenas Admin/Gerente).
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/automation_core.php';

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    // Execução via CLI (cron)
    try {
        automation_run_pending($pdo, 100);
        echo "[automation_runner] Execução concluída." . PHP_EOL;
    } catch (Throwable $e) {
        echo "[automation_runner] Erro: " . $e->getMessage() . PHP_EOL;
        log_app_error('automation_runner', 'cli_error', [
            'error' => $e->getMessage(),
        ]);
    }
    return;
}

// Execução via HTTP – protege com RBAC
require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Runner de Automação - Rede Alabama</title>
    <link rel="stylesheet" href="alabama-theme.css">
</head>
<body class="alabama-body">
<div class="container mt-4">
    <h1>Runner de Automação</h1>
    <p class="text-muted">Processa eventos pendentes na fila de automação (automation_events).</p>

    <div class="card">
        <div class="card-body">
            <?php
            try {
                automation_run_pending($pdo, 50);
                echo '<div class="alert alert-success">Execução concluída. Verifique os logs para detalhes.</div>';
            } catch (Throwable $e) {
                echo '<div class="alert alert-danger">Erro ao processar automação: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
                log_app_error('automation_runner', 'http_error', [
                    'error' => $e->getMessage(),
                ]);
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
