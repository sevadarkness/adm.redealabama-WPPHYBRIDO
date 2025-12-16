<?php
/**
 * Runner de jobs agendados.
 *
 * Pode ser chamado via cron:
 *   php jobs_runner.php
 * ou via HTTP (Admin/Gerente).
 */

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_once __DIR__ . '/rbac.php';
    require_role(['Administrador', 'Gerente']);
}

// Lê jobs pendentes cujo agendado_para é nulo ou <= agora
$sql = "SELECT * FROM jobs_agendados
        WHERE status = 'pendente'
          AND (agendado_para IS NULL OR agendado_para <= NOW())
        ORDER BY id ASC
        LIMIT 20";
$stmt = $pdo->query($sql);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function runner_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    } else {
        echo '<div>' . htmlspecialchars('[' . date('Y-m-d H:i:s') . '] ' . $msg) . '</div>';
    }
}

if (!$jobs) {
    runner_log('Nenhum job pendente.');
    exit;
}

foreach ($jobs as $job) {
    $id         = (int)$job['id'];
    $tipo       = $job['tipo'];
    $payloadRaw = $job['payload'] ?? null;
    $tentativas = (int)$job['tentativas'];
    $maxTent    = (int)$job['max_tentativas'];

    runner_log("Executando job #$id tipo={$tipo} tentativa=" . ($tentativas+1));

    $ok    = false;
    $erro  = null;
    $msgOk = null;

    try {
        $payload = $payloadRaw ? json_decode($payloadRaw, true) : [];

        switch ($tipo) {
            case 'remarketing_disparo_batch':
                // Exemplo: integração futura com api/v2/remarketing_disparos.php
                // Por enquanto, apenas marcamos como "executado" sem ação externa.
                $ok = true;
                $msgOk = 'Job de disparo remarketing marcado como executado (stub).';
                break;

            case 'lembrete_agenda_whatsapp':
                // Exemplo: ler agenda_compromissos e criar mensagens via WhatsApp.
                $ok = true;
                $msgOk = 'Job de lembrete de agenda executado (stub).';
                break;

            default:
                $erro = 'Tipo de job não reconhecido: ' . $tipo;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }

    if ($ok) {
        $stmtUp = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_2292();
        $stmtUp->execute([':id' => $id]);

        $stmtLog = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_2505();
        $stmtLog->execute([
            ':job_id'   => $id,
            ':tipo'     => $tipo,
            ':mensagem' => $msgOk ?: 'Job concluído.',
        ]);

        log_app_event('jobs_runner', 'job_sucesso', ['job_id' => $id, 'tipo' => $tipo]);
        runner_log("Job #$id concluído com sucesso.");
    } else {
        $tentativas++;
        $status = ($tentativas >= $maxTent) ? 'falhou' : 'pendente';

        $stmtUp = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_3056();
        $stmtUp->execute([
            ':status'     => $status,
            ':tentativas' => $tentativas,
            ':erro'       => $erro,
            ':id'         => $id,
        ]);

        $stmtLog = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_3388();
        $stmtLog->execute([
            ':job_id'   => $id,
            ':tipo'     => $tipo,
            ':status'   => $status,
            ':mensagem' => $erro ?: 'Erro desconhecido',
        ]);

        log_app_event('jobs_runner', 'job_erro', ['job_id' => $id, 'tipo' => $tipo, 'erro' => $erro]);
        runner_log("Job #$id terminou com erro: " . ($erro ?: 'erro desconhecido'));
    }
}
