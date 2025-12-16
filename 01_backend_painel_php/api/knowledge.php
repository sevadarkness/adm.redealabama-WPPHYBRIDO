<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║   REDE ALABAMA - API de Conhecimento/Treinamento de IA                    ║
 * ║   © 2024 Rede Alabama. Todos os direitos reservados.                      ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 * 
 * Endpoints:
 * - GET  /api/knowledge.php                    → Buscar conhecimento
 * - POST /api/knowledge.php (action=save)      → Salvar conhecimento
 * - POST /api/knowledge.php (action=merge)     → Merge local + servidor
 * - POST /api/knowledge.php (action=sync)      → Sincronização completa
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Alabama-Proxy-Key, X-Workspace-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir configuração do banco
require_once __DIR__ . '/../db_config.php';

// Verificar autenticação (opcional - pode usar X-Alabama-Proxy-Key)
$proxyKey = $_SERVER['HTTP_X_ALABAMA_PROXY_KEY'] ?? '';
$workspaceKey = $_SERVER['HTTP_X_WORKSPACE_KEY'] ?? 'default';

// Função para responder JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para obter conexão PDO
function getDB() {
    global $pdo;
    if (isset($pdo)) return $pdo;
    
    // Fallback se não houver conexão global
    try {
        $pdo = new PDO(
            'mysql:host=' . (defined('DB_HOST') ? DB_HOST : 'localhost') . ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'alabama_db') . ';charset=utf8mb4',
            defined('DB_USER') ? DB_USER : 'root',
            defined('DB_PASS') ? DB_PASS : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Database connection failed'], 500);
    }
}

// Criar tabela se não existir
function ensureTable() {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_knowledge (
            id INT AUTO_INCREMENT PRIMARY KEY,
            workspace_key VARCHAR(255) NOT NULL DEFAULT 'default',
            knowledge_type VARCHAR(50) NOT NULL,
            knowledge_data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_workspace_type (workspace_key, knowledge_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// GET - Buscar conhecimento
function getKnowledge($workspaceKey) {
    ensureTable();
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT knowledge_type, knowledge_data, updated_at 
        FROM ai_knowledge 
        WHERE workspace_key = ?
    ");
    $stmt->execute([$workspaceKey]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $knowledge = [
        'business' => ['name' => '', 'description' => '', 'segment' => '', 'hours' => ''],
        'policies' => ['payment' => '', 'delivery' => '', 'returns' => ''],
        'products' => [],
        'faq' => [],
        'cannedReplies' => [],
        'documents' => [],
        'tone' => ['style' => 'informal', 'useEmojis' => true, 'greeting' => '', 'closing' => '']
    ];
    
    $lastUpdated = null;
    
    foreach ($rows as $row) {
        $type = $row['knowledge_type'];
        $data = json_decode($row['knowledge_data'], true);
        
        if (isset($knowledge[$type])) {
            $knowledge[$type] = $data;
        }
        
        if (!$lastUpdated || $row['updated_at'] > $lastUpdated) {
            $lastUpdated = $row['updated_at'];
        }
    }
    
    return [
        'ok' => true,
        'knowledge' => $knowledge,
        'lastUpdated' => $lastUpdated,
        'source' => 'server'
    ];
}

// POST - Salvar conhecimento
function saveKnowledge($workspaceKey, $knowledge) {
    ensureTable();
    $db = getDB();
    
    $types = ['business', 'policies', 'products', 'faq', 'cannedReplies', 'documents', 'tone'];
    
    foreach ($types as $type) {
        if (!isset($knowledge[$type])) continue;
        
        $data = json_encode($knowledge[$type], JSON_UNESCAPED_UNICODE);
        
        $stmt = $db->prepare("
            INSERT INTO ai_knowledge (workspace_key, knowledge_type, knowledge_data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE knowledge_data = VALUES(knowledge_data), updated_at = NOW()
        ");
        $stmt->execute([$workspaceKey, $type, $data]);
    }
    
    return [
        'ok' => true,
        'message' => 'Conhecimento salvo com sucesso',
        'savedAt' => date('Y-m-d H:i:s')
    ];
}

// Helper: Merge arrays evitando duplicatas por chave
function mergeArrayByKey($serverArray, $localArray, $keyField, $isArrayKey = false) {
    $merged = [];
    $seen = [];
    
    // Adicionar itens do servidor primeiro
    foreach ($serverArray as $item) {
        $key = $isArrayKey ? json_encode($item[$keyField] ?? []) : ($item[$keyField] ?? '');
        if (!empty($key) && !isset($seen[$key])) {
            $merged[] = $item;
            $seen[$key] = true;
        }
    }
    
    // Adicionar itens locais que não existem no servidor
    foreach ($localArray as $item) {
        $key = $isArrayKey ? json_encode($item[$keyField] ?? []) : ($item[$keyField] ?? '');
        if (!empty($key) && !isset($seen[$key])) {
            $merged[] = $item;
            $seen[$key] = true;
        }
    }
    
    return $merged;
}

// POST - Merge conhecimento (combina local + servidor)
function mergeKnowledge($workspaceKey, $localKnowledge) {
    // Buscar conhecimento do servidor
    $serverData = getKnowledge($workspaceKey);
    $serverKnowledge = $serverData['knowledge'];
    
    $merged = [];
    
    // Business - preferir servidor se preenchido, senão local
    $merged['business'] = [];
    foreach (['name', 'description', 'segment', 'hours'] as $field) {
        $serverVal = $serverKnowledge['business'][$field] ?? '';
        $localVal = $localKnowledge['business'][$field] ?? '';
        $merged['business'][$field] = !empty($serverVal) ? $serverVal : $localVal;
    }
    
    // Policies - preferir servidor se preenchido
    $merged['policies'] = [];
    foreach (['payment', 'delivery', 'returns'] as $field) {
        $serverVal = $serverKnowledge['policies'][$field] ?? '';
        $localVal = $localKnowledge['policies'][$field] ?? '';
        $merged['policies'][$field] = !empty($serverVal) ? $serverVal : $localVal;
    }
    
    // Products - merge por nome (evitar duplicatas)
    $merged['products'] = mergeArrayByKey(
        $serverKnowledge['products'] ?? [],
        $localKnowledge['products'] ?? [],
        'name'
    );
    
    // FAQ - merge por pergunta
    $merged['faq'] = mergeArrayByKey(
        $serverKnowledge['faq'] ?? [],
        $localKnowledge['faq'] ?? [],
        'question'
    );
    
    // Canned Replies - merge por triggers
    $merged['cannedReplies'] = mergeArrayByKey(
        $serverKnowledge['cannedReplies'] ?? [],
        $localKnowledge['cannedReplies'] ?? [],
        'triggers',
        true // triggers é array
    );
    
    // Documents - merge por nome
    $merged['documents'] = mergeArrayByKey(
        $serverKnowledge['documents'] ?? [],
        $localKnowledge['documents'] ?? [],
        'name'
    );
    
    // Tone - preferir servidor
    $merged['tone'] = [
        'style' => $serverKnowledge['tone']['style'] ?? $localKnowledge['tone']['style'] ?? 'informal',
        'useEmojis' => $serverKnowledge['tone']['useEmojis'] ?? $localKnowledge['tone']['useEmojis'] ?? true,
        'greeting' => $serverKnowledge['tone']['greeting'] ?? $localKnowledge['tone']['greeting'] ?? '',
        'closing' => $serverKnowledge['tone']['closing'] ?? $localKnowledge['tone']['closing'] ?? ''
    ];
    
    // Salvar merge no servidor
    saveKnowledge($workspaceKey, $merged);
    
    return [
        'ok' => true,
        'knowledge' => $merged,
        'mergedAt' => date('Y-m-d H:i:s'),
        'stats' => [
            'products' => count($merged['products']),
            'faq' => count($merged['faq']),
            'cannedReplies' => count($merged['cannedReplies']),
            'documents' => count($merged['documents'])
        ]
    ];
}

// Roteamento
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Para POST, ler body JSON
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $action = $action ?: ($input['action'] ?? 'save');
}

try {
    switch ($method) {
        case 'GET':
            jsonResponse(getKnowledge($workspaceKey));
            break;
            
        case 'POST':
            switch ($action) {
                case 'save':
                    if (empty($input['knowledge'])) {
                        jsonResponse(['ok' => false, 'error' => 'Knowledge data required'], 400);
                    }
                    jsonResponse(saveKnowledge($workspaceKey, $input['knowledge']));
                    break;
                    
                case 'merge':
                    if (empty($input['knowledge'])) {
                        jsonResponse(['ok' => false, 'error' => 'Local knowledge required for merge'], 400);
                    }
                    jsonResponse(mergeKnowledge($workspaceKey, $input['knowledge']));
                    break;
                    
                case 'sync':
                    // Sync = merge + retornar dados atualizados
                    if (empty($input['knowledge'])) {
                        // Só buscar
                        jsonResponse(getKnowledge($workspaceKey));
                    } else {
                        // Merge e retornar
                        jsonResponse(mergeKnowledge($workspaceKey, $input['knowledge']));
                    }
                    break;
                    
                default:
                    jsonResponse(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);
            }
            break;
            
        default:
            jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
