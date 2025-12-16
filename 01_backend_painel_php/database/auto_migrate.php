<?php
declare(strict_types=1);

/**
 * Auto-migration: executa migrations pendentes automaticamente.
 * Seguro para rodar múltiplas vezes (idempotente).
 */

/**
 * Executa todas as migrations pendentes.
 * 
 * @param PDO $pdo Conexão PDO com o banco de dados
 * @return array<int, array<string, mixed>> Resultados das migrations executadas
 */
function auto_migrate_run(PDO $pdo): array {
    $results = [];
    
    // Migration: remarketing_campanhas
    if (!auto_migrate_table_exists($pdo, 'remarketing_campanhas')) {
        $sql = "CREATE TABLE IF NOT EXISTS remarketing_campanhas (
            id VARCHAR(32) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            ativo TINYINT(1) DEFAULT 1,
            config_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL,
            INDEX idx_ativo (ativo),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($sql);
            $results[] = ['table' => 'remarketing_campanhas', 'status' => 'created'];
        } catch (Throwable $e) {
            $results[] = ['table' => 'remarketing_campanhas', 'status' => 'error', 'message' => $e->getMessage()];
        }
    } else {
        $results[] = ['table' => 'remarketing_campanhas', 'status' => 'exists'];
    }
    
    // Migration: user_favorites
    if (!auto_migrate_table_exists($pdo, 'user_favorites')) {
        // Check if usuarios table exists first (for foreign key)
        $usuariosExists = auto_migrate_table_exists($pdo, 'usuarios');
        
        $sql = "CREATE TABLE IF NOT EXISTS user_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            page_url VARCHAR(255) NOT NULL,
            page_label VARCHAR(255) NOT NULL,
            page_icon VARCHAR(100) DEFAULT 'fa-star',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_page (user_id, page_url),
            INDEX idx_user (user_id),
            INDEX idx_sort_order (sort_order)" .
            ($usuariosExists ? ",\n            CONSTRAINT fk_user_favorites_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE" : "") . "
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($sql);
            $results[] = ['table' => 'user_favorites', 'status' => 'created'];
        } catch (Throwable $e) {
            $results[] = ['table' => 'user_favorites', 'status' => 'error', 'message' => $e->getMessage()];
        }
    } else {
        $results[] = ['table' => 'user_favorites', 'status' => 'exists'];
    }
    
    return $results;
}

/**
 * Verifica se uma tabela existe no banco de dados.
 * 
 * @param PDO $pdo Conexão PDO com o banco de dados
 * @param string $table Nome da tabela a verificar
 * @return bool True se a tabela existe, false caso contrário
 */
function auto_migrate_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $table]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}
