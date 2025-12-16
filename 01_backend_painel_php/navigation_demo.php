<?php
declare(strict_types=1);

/**
 * Demonstração do Sistema de Navegação Inteligente
 * Página de teste para mostrar todas as funcionalidades do novo sistema
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente', 'Vendedor'));

$pageTitle = 'Sistema de Navegação Inteligente - Demo';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Vendor CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alabama Design System -->
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-navigation.css">
</head>
<body class="al-body">

<?php include __DIR__ . '/menu_navegacao.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-rocket text-primary"></i>
                        Sistema de Navegação Inteligente
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        Demonstração das novas funcionalidades de navegação do Alabama CMS
                    </p>
                </div>
                <div class="card-body">
                    
                    <!-- Feature Grid -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-bars fa-2x text-primary"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Menu Lateral Colapsável</h5>
                                    </div>
                                    <p class="card-text">
                                        Menu organizado em categorias expansíveis com suporte a modo mini (apenas ícones).
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Categorias colapsáveis</li>
                                        <li><i class="fas fa-check text-success"></i> Estado salvo no localStorage</li>
                                        <li><i class="fas fa-check text-success"></i> Modo compacto (70px)</li>
                                        <li><i class="fas fa-check text-success"></i> ~45 páginas organizadas</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Clique no botão <i class="fas fa-bars"></i> no topo do menu lateral
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-info">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-info bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-search fa-2x text-info"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Busca Global (Ctrl+K)</h5>
                                    </div>
                                    <p class="card-text">
                                        Modal de busca rápida que filtra todas as páginas do sistema em tempo real.
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Atalho Ctrl+K / Cmd+K</li>
                                        <li><i class="fas fa-check text-success"></i> Filtragem instantânea</li>
                                        <li><i class="fas fa-check text-success"></i> Navegação por teclado (↑↓)</li>
                                        <li><i class="fas fa-check text-success"></i> ESC para fechar</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Pressione <kbd class="bg-dark text-white px-2 py-1 rounded">Ctrl+K</kbd> agora!
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-warning bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-star fa-2x text-warning"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Favoritos Personalizados</h5>
                                    </div>
                                    <p class="card-text">
                                        Marque suas páginas mais usadas como favoritas para acesso rápido.
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Favoritar com 1 clique</li>
                                        <li><i class="fas fa-check text-success"></i> Seção dedicada no topo</li>
                                        <li><i class="fas fa-check text-success"></i> Persistência por usuário</li>
                                        <li><i class="fas fa-check text-success"></i> Reordenação drag & drop</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Passe o mouse sobre itens do menu e clique na estrela <i class="far fa-star"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-danger">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-danger bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-bell fa-2x text-danger"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Badges de Status</h5>
                                    </div>
                                    <p class="card-text">
                                        Contadores de notificações em tempo real nos itens do menu.
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Novos leads do dia</li>
                                        <li><i class="fas fa-check text-success"></i> Mensagens não lidas</li>
                                        <li><i class="fas fa-check text-success"></i> Campanhas ativas</li>
                                        <li><i class="fas fa-check text-success"></i> Atualização a cada 30s</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Veja os badges <span class="badge bg-danger">0</span> nos itens do menu
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-success">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-success bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-mobile-alt fa-2x text-success"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Responsivo Total</h5>
                                    </div>
                                    <p class="card-text">
                                        Menu adaptado para todos os tamanhos de tela com drawer para mobile.
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Desktop: Sidebar fixo</li>
                                        <li><i class="fas fa-check text-success"></i> Tablet: Drawer colapsável</li>
                                        <li><i class="fas fa-check text-success"></i> Mobile: Menu hambúrguer</li>
                                        <li><i class="fas fa-check text-success"></i> Overlay de fundo</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Redimensione a janela para ver o comportamento mobile
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-secondary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-secondary bg-opacity-10 rounded p-3 me-3">
                                            <i class="fas fa-palette fa-2x text-secondary"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Tema Unificado</h5>
                                    </div>
                                    <p class="card-text">
                                        Design consistente em todas as 45+ páginas do sistema.
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Alabama Design System</li>
                                        <li><i class="fas fa-check text-success"></i> Paleta roxo/azul/preto</li>
                                        <li><i class="fas fa-check text-success"></i> Dark mode nativo</li>
                                        <li><i class="fas fa-check text-success"></i> Efeitos glassmorphism</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <strong>Teste:</strong> Navegue entre as páginas e veja a consistência visual
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Technical Details -->
                    <div class="card bg-dark text-light">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-code"></i>
                                Detalhes Técnicos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Arquivos Criados:</h6>
                                    <ul class="list-unstyled font-monospace small">
                                        <li>✓ includes/sidebar_menu.php</li>
                                        <li>✓ includes/global_search.php</li>
                                        <li>✓ includes/layout_header.php</li>
                                        <li>✓ includes/layout_footer.php</li>
                                        <li>✓ api/menu_badges.php</li>
                                        <li>✓ api/favorites.php</li>
                                        <li>✓ assets/css/alabama-navigation.css</li>
                                        <li>✓ assets/js/navigation.js</li>
                                        <li>✓ database/migrations/..._create_user_favorites.sql</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-info">Funcionalidades Implementadas:</h6>
                                    <ul class="list-unstyled small">
                                        <li>✓ 10 categorias de menu organizadas</li>
                                        <li>✓ ~45 páginas catalogadas</li>
                                        <li>✓ Sistema de favoritos com banco de dados</li>
                                        <li>✓ Badges dinâmicos via AJAX</li>
                                        <li>✓ Busca global com Ctrl+K</li>
                                        <li>✓ localStorage para persistência</li>
                                        <li>✓ Responsivo mobile-first</li>
                                        <li>✓ Backward compatible com menu antigo</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Keyboard Shortcuts -->
                    <div class="card mt-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-keyboard"></i>
                                Atalhos de Teclado
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <kbd class="bg-dark text-white px-3 py-2 rounded me-3">Ctrl+K</kbd>
                                        <span>Abrir busca global</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <kbd class="bg-dark text-white px-3 py-2 rounded me-3">ESC</kbd>
                                        <span>Fechar busca</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <kbd class="bg-dark text-white px-3 py-2 rounded me-3">↑ ↓</kbd>
                                        <span>Navegar resultados</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <kbd class="bg-dark text-white px-3 py-2 rounded me-3">Enter</kbd>
                                        <span>Abrir página selecionada</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/navigation.js"></script>

</body>
</html>
