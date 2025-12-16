<?php
declare(strict_types=1);

/**
 * Healthcheck do Rede Alabama (Railway/local).
 *
 * Requisitos:
 * - Não deve derrubar o deploy quando o DB não estiver configurado/disponível.
 * - Deve responder JSON consistente para observabilidade.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/bootstrap_autoload.php';

// Carrega .env cedo (melhor esforço)
try {
    if (class_exists(\RedeAlabama\Support\Env::class)) {
        \RedeAlabama\Support\Env::load();
    }
} catch (Throwable $e) {
    // ignore
}

$appEnv = (string) (getenv('APP_ENV') ?: getenv('ALABAMA_ENV') ?: 'prod');

$dbOk = null;
$dbError = null;

// Se não há nenhuma variável de DB, considera "degraded" mas sem tentar conectar
$hasDbConfig = (getenv('DB_HOST') !== false) || (getenv('DB_DATABASE') !== false) || (getenv('DB_USERNAME') !== false);

if ($hasDbConfig && class_exists(\RedeAlabama\Support\Config::class)) {
    try {
        $dsn  = \RedeAlabama\Support\Config::dbDsn();
        $user = \RedeAlabama\Support\Config::dbUsername();
        $pass = \RedeAlabama\Support\Config::dbPassword();

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);

        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        $dbOk = false;
        // Não expõe DSN/credenciais; retorna apenas categoria + mensagem curta
        $dbError = substr($e->getMessage(), 0, 180);
    }
} else {
    $dbOk = false;
    $dbError = 'db_not_configured';
}

$ok = true; // sempre 200 (Railway readiness), mas reporta status no payload
$status = ($dbOk === true) ? 'ok' : 'degraded';

http_response_code(200);

echo json_encode([
    'ok'        => $ok,
    'status'    => $status,
    'ts'        => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'app_env'   => $appEnv,
    'php'       => PHP_VERSION,
    'db'        => [
        'ok'    => $dbOk,
        'error' => $dbOk ? null : $dbError,
    ],
], JSON_UNESCAPED_UNICODE);
