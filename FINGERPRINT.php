<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  REDE ALABAMA - FINGERPRINT DE PROTEÇÃO                                   ║
 * ║  © 2024 Rede Alabama. Todos os direitos reservados.                       ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 * 
 * Este arquivo contém as informações de identificação e rastreamento do projeto.
 * A remoção ou alteração deste arquivo constitui violação de direitos autorais.
 */

// ═══════════════════════════════════════════════════════════════════════════
// FINGERPRINT DO PROJETO - NÃO REMOVER OU ALTERAR
// ═══════════════════════════════════════════════════════════════════════════

define('RA_PROJECT_FINGERPRINT', [
    // Identificação do Proprietário
    'owner'           => 'Rede Alabama',
    'owner_id'        => 'RA-2024',
    'copyright'       => '© 2024 Rede Alabama. Todos os direitos reservados.',
    
    // Identificação do Projeto
    'project_name'    => 'WhatsApp Híbrido - Sistema de Atendimento Inteligente',
    'project_code'    => 'WPPHYBRIDO',
    'version'         => '1.0.0',
    
    // Fingerprint Único (SHA-256)
    'fingerprint'     => 'RA-2024-WPPHYBRIDO-' . hash('sha256', 'REDE_ALABAMA_PROPRIETARY_2024'),
    
    // Datas
    'created_at'      => '2024-01-01',
    'registered_at'   => date('Y-m-d'),
    'build_timestamp' => time(),
    
    // Proteção Legal
    'legal_protection' => [
        'Lei 9.609/98 - Lei do Software',
        'Lei 9.610/98 - Lei de Direitos Autorais',
        'Convenção de Berna',
        'TRIPS Agreement'
    ],
    
    // Hash de Verificação
    'verification_hash' => hash('sha256', __FILE__ . filemtime(__FILE__))
]);

// ═══════════════════════════════════════════════════════════════════════════
// CÓDIGO DE RASTREAMENTO OCULTO
// ═══════════════════════════════════════════════════════════════════════════

$_RA_TRACE = base64_encode(json_encode([
    'fp' => 'RA-WPPHYBRIDO-2024',
    'ts' => time(),
    'id' => md5(uniqid('RA_', true) . microtime(true)),
    'owner' => 'redealabama',
    'sig' => hash_hmac('sha256', 'REDE_ALABAMA', 'PROPRIETARY_KEY_2024')
]));

// ═══════════════════════════════════════════════════════════════════════════
// FUNÇÕES DE VERIFICAÇÃO
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Verifica a integridade do fingerprint
 */
function ra_verify_fingerprint() {
    $fp = RA_PROJECT_FINGERPRINT;
    return isset($fp['fingerprint']) && 
           strpos($fp['fingerprint'], 'RA-2024-WPPHYBRIDO') === 0;
}

/**
 * Retorna informações de copyright para exibição
 */
function ra_get_copyright() {
    return RA_PROJECT_FINGERPRINT['copyright'];
}

/**
 * Retorna o fingerprint do projeto
 */
function ra_get_fingerprint() {
    return RA_PROJECT_FINGERPRINT['fingerprint'];
}

// Verificação automática na inclusão
if (!ra_verify_fingerprint()) {
    error_log('[ALABAMA SECURITY] Fingerprint verification failed - Possible tampering detected');
}
