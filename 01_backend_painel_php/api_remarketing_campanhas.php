<?php
/**
 * api_remarketing_campanhas.php
 *
 * Endpoint legacy (compat) para campanhas de remarketing.
 *
 * Observação:
 * - Este arquivo existe para manter compatibilidade com o wrapper
 *   /api/v2/remarketing_campanhas.php.
 * - Atualmente o módulo de Campanhas do painel salva no navegador (localStorage).
 * - Aqui entregamos um CRUD mínimo com persistência em arquivo (JSON) como fallback.
 *   Em produção, recomenda-se persistir em banco (MySQL) e/ou integrar com o motor
 *   de disparos/fluxos.
 */

declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Storage em arquivo (fallback).
// Por padrão usamos sys_get_temp_dir() (fora do webroot) para evitar exposição acidental por HTTP.
// Se quiser persistência em disco, defina ALABAMA_STORAGE_DIR para um diretório gravável.
$baseDir = rtrim((string)(getenv('ALABAMA_STORAGE_DIR') ?: ''), '/');
if ($baseDir === '' || !is_dir($baseDir) || !is_writable($baseDir)) {
    $baseDir = rtrim(sys_get_temp_dir(), '/');
}

$storeFile = $baseDir . '/alabama_remarketing_campanhas.json';

/**
 * @return array<int,array<string,mixed>>
 */
function rm_load_campaigns(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    // Normaliza para lista
    if (isset($data['campanhas']) && is_array($data['campanhas'])) {
        $data = $data['campanhas'];
    }
    // Reindex
    return array_values(array_filter($data, fn($x) => is_array($x)));
}

/**
 * @param array<int,array<string,mixed>> $campanhas
 */
function rm_save_campaigns(string $file, array $campanhas): bool
{
    $payload = json_encode([
        'campanhas'   => array_values($campanhas),
        'updated_at'  => date('c'),
        'schema'      => 1,
        'storage'     => 'file',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if (!is_string($payload)) {
        return false;
    }

    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

/**
 * @return array<string,mixed>
 */
function rm_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$campanhas = rm_load_campaigns($storeFile);

// GET: lista
if ($method === 'GET') {
    echo json_encode([
        'success'   => true,
        'campanhas' => $campanhas,
        'storage'   => [
            'type' => 'file',
            'path' => basename($storeFile),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: cria/atualiza
if ($method === 'POST') {
    $body = rm_read_json_body();
    if (!is_array($body)) {
        $body = [];
    }

    $camp = $body['campanha'] ?? $body;
    if (!is_array($camp)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Payload inválido. Envie JSON com objeto "campanha".',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = $camp['id'] ?? null;
    if (!is_string($id) || trim($id) === '') {
        // id simples (não-UUID) – suficiente para uso interno.
        $id = 'rm_' . bin2hex(random_bytes(6));
        $camp['id'] = $id;
    }

    // Normalizações
    $camp['nome'] = isset($camp['nome']) && is_string($camp['nome']) ? trim($camp['nome']) : 'Campanha';
    $camp['ativo'] = isset($camp['ativo']) ? (bool)$camp['ativo'] : true;
    $camp['updated_at'] = date('c');
    if (!isset($camp['created_at'])) {
        $camp['created_at'] = date('c');
    }

    $found = false;
    foreach ($campanhas as $i => $existing) {
        if (is_array($existing) && isset($existing['id']) && $existing['id'] === $id) {
            $campanhas[$i] = array_merge($existing, $camp);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $campanhas[] = $camp;
    }

    if (!rm_save_campaigns($storeFile, $campanhas)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Falha ao persistir campanhas no servidor (storage file).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'campanha'  => $camp,
        'campanhas' => $campanhas,
        'message'   => $found ? 'Campanha atualizada.' : 'Campanha criada.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// DELETE: remove (?id=...)
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!is_string($id) || trim($id) === '') {
        echo json_encode([
            'success' => false,
            'error'   => 'Informe o parâmetro id.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $before = count($campanhas);
    $campanhas = array_values(array_filter($campanhas, function ($c) use ($id) {
        return !(is_array($c) && isset($c['id']) && $c['id'] === $id);
    }));

    if ($before === count($campanhas)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Campanha não encontrada.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!rm_save_campaigns($storeFile, $campanhas)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Falha ao persistir campanhas no servidor (storage file).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'campanhas' => $campanhas,
        'message'   => 'Campanha removida.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error'   => 'Método não suportado.',
], JSON_UNESCAPED_UNICODE);
