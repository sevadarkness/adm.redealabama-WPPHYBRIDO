# Sistema de Navegação Inteligente - Alabama CMS

## Visão Geral

Sistema de navegação moderno e inteligente para o painel administrativo com aproximadamente 45 páginas/abas. Implementa menu lateral colapsável, busca global (Ctrl+K), favoritos por usuário, badges de notificação e tema unificado.

## Funcionalidades Principais

### 1. Menu Lateral Colapsável por Categoria

#### Características:
- ✅ 10 categorias organizadas (Dashboard, CRM, Vendas, Estoque, Marketing, WhatsApp, Automação, IA, Logística, Configurações)
- ✅ ~45 páginas catalogadas e organizadas
- ✅ Categorias expansíveis/colapsáveis (clique para expandir/recolher)
- ✅ Estado salvo no localStorage (persiste após refresh)
- ✅ Modo mini (apenas ícones, 70px de largura)
- ✅ Indicador visual da categoria/página ativa
- ✅ Responsivo com drawer em mobile

#### Como usar:
```html
<!-- Já incluído automaticamente ao usar menu_navegacao.php -->
<?php include __DIR__ . '/menu_navegacao.php'; ?>
```

#### Estrutura de Categorias:
- **📊 Dashboard**: Painéis administrativos e relatórios
- **👥 CRM**: Leads, clientes, agenda, atendimento
- **💰 Vendas**: Nova venda, top vendas, prejuízos
- **📦 Estoque**: Estoque, catálogo, produtos
- **📢 Marketing**: Campanhas, remarketing
- **💬 WhatsApp**: Conversas, bot IA, fluxos
- **⚡ Automação**: Regras, jobs, matching
- **🤖 IA & Analytics**: LLM, insights, analytics
- **🚚 Logística**: Frete
- **⚙️ Configurações**: Config, auditoria

### 2. Busca Global (Ctrl+K)

#### Características:
- ✅ Abre com `Ctrl+K` ou `Cmd+K` (Mac)
- ✅ Clique no campo de busca no menu lateral
- ✅ Filtragem em tempo real
- ✅ Mostra ícone e categoria de cada resultado
- ✅ Navegação por teclado (↑↓ Enter ESC)
- ✅ Histórico de buscas recentes (localStorage)

#### Atalhos de Teclado:
| Tecla | Ação |
|-------|------|
| `Ctrl+K` / `Cmd+K` | Abrir busca |
| `ESC` | Fechar busca |
| `↑` / `↓` | Navegar resultados |
| `Enter` | Abrir página selecionada |

#### Como funciona:
```javascript
// Array de páginas definido em includes/global_search.php
window.alAllPages = [
    { url: 'leads.php', label: 'Leads', category: 'CRM', icon: 'fa-user-plus' },
    // ... todas as páginas
];
```

### 3. Badges de Status

#### Tipos de Badges:
| Badge Type | Query | Cor |
|------------|-------|-----|
| `new_leads` | Leads novos hoje | Roxo (--al-primary) |
| `unread_messages` | Mensagens não lidas | Vermelho (--al-danger) |
| `active_campaigns` | Campanhas ativas | Azul (--al-info) |
| `pending_tasks` | Tarefas pendentes | Amarelo (--al-warning) |
| `sales_today` | Vendas de hoje | Verde (--al-success) |

#### Atualização:
- Automaticamente via AJAX a cada 30 segundos
- Endpoint: `api/menu_badges.php`

#### Adicionar Badge a um Item:
```php
[
    'url' => 'leads.php',
    'label' => 'Leads',
    'icon' => 'fa-user-plus',
    'badge_type' => 'new_leads' // ← Adicionar esta linha
]
```

### 4. Favoritos Personalizados

#### Características:
- ✅ Marcar páginas como favoritas por usuário
- ✅ Seção "⭐ Favoritos" no topo do menu
- ✅ Persistência no banco de dados
- ✅ Suporte a reordenação (preparado para drag & drop)

#### Tabela MySQL:
```sql
CREATE TABLE user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    page_label VARCHAR(255) NOT NULL,
    page_icon VARCHAR(100) DEFAULT 'fa-star',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_page (user_id, page_url),
    INDEX idx_user (user_id)
);
```

#### API Endpoints:
- `GET api/favorites.php` - Lista favoritos do usuário
- `POST api/favorites.php` com `action=add` - Adiciona favorito
- `POST api/favorites.php` com `action=remove` - Remove favorito
- `POST api/favorites.php` com `action=reorder` - Reordena favoritos

### 5. Tema Unificado

#### Design System:
- Alabama Design System Premium v2.0
- Paleta: Roxo (#8b5cf6), Azul (#3b82f6), Preto profundo
- Dark mode nativo
- Efeitos glassmorphism e gradientes

#### Variáveis CSS Principais:
```css
--al-primary: #8b5cf6;
--al-accent: #3b82f6;
--al-bg-base: #0a0a1a;
--al-bg-surface: #12122a;
--al-text-primary: #e5e7eb;
```

## Arquitetura de Arquivos

### Estrutura de Diretórios:
```
01_backend_painel_php/
├── includes/
│   ├── sidebar_menu.php         # Menu lateral com categorias
│   ├── global_search.php        # Modal de busca global
│   ├── layout_header.php        # Header padrão (opcional)
│   └── layout_footer.php        # Footer padrão (opcional)
├── api/
│   ├── menu_badges.php          # API de badges
│   └── favorites.php            # API de favoritos
├── assets/
│   ├── css/
│   │   └── alabama-navigation.css  # Estilos do sistema de navegação
│   └── js/
│       └── navigation.js        # JavaScript do sistema
├── database/
│   └── migrations/
│       └── 2025_12_16_150000_create_user_favorites.sql
└── menu_navegacao.php           # Integração principal
```

### Componentes JavaScript:

#### navigation.js
- `initSidebarToggle()` - Gerencia colapso/expansão do sidebar
- `initCategoryState()` - Salva estado de categorias no localStorage
- `initGlobalSearch()` - Implementa busca global com Ctrl+K
- `updateBadges()` - Atualiza badges via AJAX a cada 30s
- `initFavorites()` - Gerencia favoritar/desfavoritar páginas

## Como Usar

### Para Novas Páginas

#### Opção 1: Usar o Menu Existente (Recomendado)
```php
<?php
require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente', 'Vendedor'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Minha Página</title>
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
</head>
<body class="al-body">

<?php include __DIR__ . '/menu_navegacao.php'; ?>

<div class="container-fluid my-4">
    <!-- Seu conteúdo aqui -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

#### Opção 2: Usar Layout Header/Footer
```php
<?php
$pageTitle = 'Minha Página';
require_once __DIR__ . '/includes/layout_header.php';
?>

<!-- Seu conteúdo aqui -->

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
```

### Adicionar Nova Página ao Menu

Edite `includes/sidebar_menu.php` e adicione o item à categoria apropriada:

```php
$menuCategories = [
    'crm' => [
        'icon' => 'fa-users',
        'label' => '👥 CRM',
        'items' => [
            // ... itens existentes ...
            [
                'url' => 'minha_pagina.php',
                'label' => 'Minha Página',
                'icon' => 'fa-rocket',
                'badge_type' => 'optional_badge', // Opcional
                'roles' => ['Administrador'] // Opcional: restrição de acesso
            ],
        ]
    ],
];
```

Também adicione a página ao array de busca em `includes/global_search.php`:

```javascript
window.alAllPages = [
    // ... páginas existentes ...
    { url: 'minha_pagina.php', label: 'Minha Página', category: 'CRM', icon: 'fa-rocket' },
];
```

## Responsividade

### Breakpoints:
- **Desktop (> 992px)**: Sidebar fixo, 280px de largura
- **Tablet (768px - 992px)**: Sidebar colapsável com overlay
- **Mobile (< 768px)**: Menu hambúrguer com drawer

### Comportamento Mobile:
1. Sidebar fica escondido por padrão (translateX(-100%))
2. Botão hambúrguer aparece no canto superior esquerdo
3. Clique abre o drawer com overlay escuro
4. Clique no overlay ou item do menu fecha o drawer

## Personalização

### Cores de Badge Personalizadas:
```css
/* Em seu CSS customizado */
.al-badge[data-badge="meu_badge"] {
    background: #ff6b6b;
}
```

### Categorias Adicionais:
```php
// Em includes/sidebar_menu.php
$menuCategories['nova_categoria'] = [
    'icon' => 'fa-icone',
    'label' => '🎯 Nova Categoria',
    'items' => [
        // seus itens aqui
    ]
];
```

### Desabilitar Funcionalidade Específica:
```javascript
// Desabilitar atualização automática de badges
// Comente a linha em assets/js/navigation.js:
// setInterval(updateBadges, 30000);
```

## Migração de Banco de Dados

### Executar Migração:
```bash
# Via navegador
http://seu-dominio/migrate.php

# Ou executar SQL diretamente
mysql -u usuario -p database < database/migrations/2025_12_16_150000_create_user_favorites.sql
```

### Auto-migrate:
A tabela `user_favorites` é criada automaticamente na primeira execução do sistema graças ao `database/auto_migrate.php`.

## Testes

### Checklist de Funcionalidades:
- [ ] Menu lateral abre/fecha ao clicar no botão toggle
- [ ] Estado do menu persiste após refresh da página
- [ ] Categorias expandem/recolhem corretamente
- [ ] Ctrl+K abre o modal de busca
- [ ] Busca filtra páginas em tempo real
- [ ] Setas ↑↓ navegam pelos resultados da busca
- [ ] Enter abre a página selecionada
- [ ] ESC fecha o modal de busca
- [ ] Badges aparecem nos itens corretos
- [ ] Badges atualizam automaticamente
- [ ] Estrela de favoritos aparece ao passar o mouse
- [ ] Favoritar/desfavoritar funciona corretamente
- [ ] Seção de favoritos aparece no topo do menu
- [ ] Menu mobile abre/fecha com o botão hambúrguer
- [ ] Overlay fecha o menu mobile ao clicar
- [ ] Tema dark mode está consistente em todas as páginas

### Página de Demonstração:
Acesse `navigation_demo.php` para ver todas as funcionalidades em ação.

## Troubleshooting

### Menu não aparece:
1. Verifique se `menu_navegacao.php` está incluído
2. Verifique se a classe `al-body` está no elemento `<body>`
3. Verifique se o CSS está carregado: `assets/css/alabama-navigation.css`

### Busca não abre com Ctrl+K:
1. Verifique se `assets/js/navigation.js` está carregado
2. Verifique o console do navegador por erros JavaScript
3. Certifique-se de que `global_search.php` está incluído

### Badges não aparecem:
1. Verifique se a API `api/menu_badges.php` está acessível
2. Verifique as tabelas do banco de dados (leads, whatsapp_conversas, etc.)
3. Abra o console do navegador e veja requisições à API

### Favoritos não salvam:
1. Verifique se a tabela `user_favorites` existe no banco
2. Execute a migração se necessário
3. Verifique se a API `api/favorites.php` está acessível
4. Verifique permissões de sessão/autenticação

## Performance

### Otimizações Implementadas:
- ✅ CSS carregado uma única vez no head
- ✅ JavaScript com event delegation para favoritos
- ✅ Badges atualizados a cada 30s (não em tempo real)
- ✅ LocalStorage para estado do menu (evita requisições)
- ✅ Busca client-side (sem chamadas ao servidor)

### Métricas Esperadas:
- Tempo de abertura do menu: < 50ms
- Tempo de busca (Ctrl+K): < 100ms
- Tamanho do CSS: ~13KB
- Tamanho do JS: ~14KB
- Requisições AJAX: 1 a cada 30s (badges)

## Suporte a Navegadores

### Compatibilidade:
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile browsers (iOS Safari, Chrome Android)

### Funcionalidades Degradadas em Navegadores Antigos:
- localStorage: Categorias não persistem (mas continuam funcionando)
- backdrop-filter: Efeito de blur pode não aparecer
- CSS Grid: Layout pode ter pequenas diferenças

## Contribuindo

Para adicionar novas funcionalidades:

1. **Adicionar nova categoria**: Edite `includes/sidebar_menu.php`
2. **Adicionar novo badge**: Edite `api/menu_badges.php` e adicione query
3. **Modificar estilos**: Edite `assets/css/alabama-navigation.css`
4. **Adicionar funcionalidade JS**: Edite `assets/js/navigation.js`

## Changelog

### v1.0.0 (2025-12-16)
- ✅ Sistema de navegação lateral implementado
- ✅ 10 categorias organizadas com ~45 páginas
- ✅ Busca global (Ctrl+K) funcional
- ✅ Sistema de favoritos com banco de dados
- ✅ Badges de notificação dinâmicos
- ✅ Tema unificado dark mode
- ✅ Responsividade mobile completa

## Créditos

**Desenvolvido para:** Rede Alabama  
**Design System:** Alabama Design System Premium v2.0  
**Framework:** Bootstrap 5.3 + Custom CSS/JS  
**Ícones:** Font Awesome 6.4  
**Fontes:** Inter (Google Fonts)

---

Para suporte ou dúvidas, consulte a página de demonstração: `navigation_demo.php`
