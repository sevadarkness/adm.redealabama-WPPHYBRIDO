/**
 * Alabama Navigation System JavaScript
 * Handles sidebar collapse, global search (Ctrl+K), badges, and favorites
 */

(function() {
    'use strict';
    
    // ========================================
    // SIDEBAR COLLAPSE/EXPAND
    // ========================================
    
    const initSidebarToggle = () => {
        const sidebarToggle = document.getElementById('alSidebarToggle');
        const body = document.body;
        const overlay = document.getElementById('alSidebarOverlay');
        
        // Load saved state from localStorage
        const savedState = localStorage.getItem('al-sidebar-collapsed');
        if (savedState === 'true') {
            body.classList.add('al-sidebar-collapsed');
        }
        
        // Toggle sidebar
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                body.classList.toggle('al-sidebar-collapsed');
                const isCollapsed = body.classList.contains('al-sidebar-collapsed');
                localStorage.setItem('al-sidebar-collapsed', isCollapsed);
            });
        }
        
        // Mobile overlay
        if (overlay) {
            overlay.addEventListener('click', () => {
                body.classList.remove('al-sidebar-mobile-open');
            });
        }
        
        // Mobile menu button
        const mobileMenuBtn = document.getElementById('alMobileMenuBtn');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                body.classList.toggle('al-sidebar-mobile-open');
            });
        }
        
        // Alternative mobile menu button selector
        const altMobileBtn = document.querySelector('.al-mobile-menu-btn');
        if (altMobileBtn && altMobileBtn.id !== 'alMobileMenuBtn') {
            altMobileBtn.addEventListener('click', () => {
                body.classList.toggle('al-sidebar-mobile-open');
            });
        }
    };
    
    // ========================================
    // CATEGORY COLLAPSE STATE
    // ========================================
    
    const initCategoryState = () => {
        const categories = document.querySelectorAll('.al-sidebar-category');
        
        categories.forEach(category => {
            const categoryKey = category.dataset.category;
            const collapseEl = category.querySelector('.collapse');
            const headerBtn = category.querySelector('.al-sidebar-category-header');
            
            if (!collapseEl || !headerBtn) return;
            
            // Load saved state
            const savedState = localStorage.getItem(`al-category-${categoryKey}`);
            
            // Set initial state
            if (savedState === 'collapsed') {
                collapseEl.classList.remove('show');
                headerBtn.setAttribute('aria-expanded', 'false');
            } else if (savedState === 'expanded' || collapseEl.classList.contains('show')) {
                collapseEl.classList.add('show');
                headerBtn.setAttribute('aria-expanded', 'true');
            }
            
            // Save state on toggle
            collapseEl.addEventListener('shown.bs.collapse', () => {
                localStorage.setItem(`al-category-${categoryKey}`, 'expanded');
                headerBtn.setAttribute('aria-expanded', 'true');
            });
            
            collapseEl.addEventListener('hidden.bs.collapse', () => {
                localStorage.setItem(`al-category-${categoryKey}`, 'collapsed');
                headerBtn.setAttribute('aria-expanded', 'false');
            });
        });
    };
    
    // ========================================
    // GLOBAL SEARCH (Ctrl+K)
    // ========================================
    
    const initGlobalSearch = () => {
        const modal = document.getElementById('globalSearchModal');
        const input = document.getElementById('globalSearchInput');
        const results = document.getElementById('globalSearchResults');
        const searchTrigger = document.getElementById('alSearchTrigger');
        const closeBtn = document.getElementById('globalSearchClose');
        
        if (!modal || !input || !results) return;
        
        let selectedIndex = -1;
        let filteredPages = [];
        
        // Open search modal
        const openSearch = () => {
            modal.classList.add('active');
            input.value = '';
            input.focus();
            selectedIndex = -1;
            showAllPages();
        };
        
        // Close search modal
        const closeSearch = () => {
            modal.classList.remove('active');
            input.value = '';
            results.innerHTML = '<div class="al-search-empty"><i class="fas fa-search"></i><p>Digite para buscar páginas</p></div>';
        };
        
        // Show all pages as initial results
        const showAllPages = () => {
            if (!window.alAllPages || window.alAllPages.length === 0) {
                results.innerHTML = '<div class="al-search-empty"><i class="fas fa-search"></i><p>Nenhuma página disponível</p></div>';
                return;
            }
            
            filteredPages = window.alAllPages;
            renderResults(filteredPages);
        };
        
        // Filter pages based on search query
        const filterPages = (query) => {
            if (!query.trim()) {
                showAllPages();
                return;
            }
            
            const lowerQuery = query.toLowerCase();
            filteredPages = window.alAllPages.filter(page => {
                return page.label.toLowerCase().includes(lowerQuery) ||
                       page.category.toLowerCase().includes(lowerQuery) ||
                       page.url.toLowerCase().includes(lowerQuery);
            });
            
            if (filteredPages.length === 0) {
                results.innerHTML = '<div class="al-search-empty"><i class="fas fa-search"></i><p>Nenhuma página encontrada</p></div>';
            } else {
                renderResults(filteredPages);
            }
            
            selectedIndex = filteredPages.length > 0 ? 0 : -1;
        };
        
        // Render search results
        const renderResults = (pages) => {
            const html = pages.map((page, index) => `
                <a href="${page.url}" class="al-search-item" data-index="${index}">
                    <div class="al-search-item-icon">
                        <i class="fas ${page.icon}"></i>
                    </div>
                    <div class="al-search-item-content">
                        <div class="al-search-item-label">${page.label}</div>
                        <div class="al-search-item-category">${page.category}</div>
                    </div>
                </a>
            `).join('');
            
            results.innerHTML = html;
            
            // Add click handlers
            results.querySelectorAll('.al-search-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    // Save to recent searches
                    const page = pages[parseInt(item.dataset.index)];
                    saveRecentSearch(page);
                });
            });
        };
        
        // Navigate results with keyboard
        const selectResult = (index) => {
            const items = results.querySelectorAll('.al-search-item');
            items.forEach(item => item.classList.remove('selected'));
            
            if (index >= 0 && index < items.length) {
                items[index].classList.add('selected');
                items[index].scrollIntoView({ block: 'nearest' });
            }
        };
        
        // Save recent search
        const saveRecentSearch = (page) => {
            let recent = JSON.parse(localStorage.getItem('al-recent-searches') || '[]');
            recent = recent.filter(p => p.url !== page.url);
            recent.unshift(page);
            recent = recent.slice(0, 5); // Keep only 5 recent
            localStorage.setItem('al-recent-searches', JSON.stringify(recent));
        };
        
        // Event listeners
        if (searchTrigger) {
            searchTrigger.addEventListener('click', openSearch);
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSearch);
        }
        
        // Ctrl+K or Cmd+K to open
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (!modal.classList.contains('active')) {
                    openSearch();
                }
            }
            
            // ESC to close
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeSearch();
            }
            
            // Arrow navigation
            if (modal.classList.contains('active')) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, filteredPages.length - 1);
                    selectResult(selectedIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    selectResult(selectedIndex);
                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                    e.preventDefault();
                    const items = results.querySelectorAll('.al-search-item');
                    if (items[selectedIndex]) {
                        items[selectedIndex].click();
                    }
                }
            }
        });
        
        // Search input
        if (input) {
            input.addEventListener('input', (e) => {
                filterPages(e.target.value);
            });
        }
        
        // Click backdrop to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('al-search-backdrop')) {
                closeSearch();
            }
        });
    };
    
    // ========================================
    // BADGES UPDATE
    // ========================================
    
    const updateBadges = async () => {
        try {
            const response = await fetch('api/menu_badges.php');
            if (!response.ok) return;
            
            const data = await response.json();
            if (!data.ok || !data.badges) return;
            
            // Update all badges
            Object.keys(data.badges).forEach(badgeType => {
                const count = data.badges[badgeType];
                const badges = document.querySelectorAll(`[data-badge="${badgeType}"]`);
                
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.textContent = '';
                        badge.style.display = 'none';
                    }
                });
            });
        } catch (error) {
            console.warn('Failed to update badges:', error);
        }
    };
    
    const initBadges = () => {
        // Update immediately
        updateBadges();
        
        // Update every 30 seconds
        setInterval(updateBadges, 30000);
    };
    
    // ========================================
    // FAVORITES
    // ========================================
    
    const initFavorites = () => {
        const favBtns = document.querySelectorAll('.al-favorite-btn');
        
        favBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const url = btn.dataset.url;
                const label = btn.dataset.label;
                const icon = btn.dataset.icon || 'fa-star';
                const isActive = btn.classList.contains('active');
                
                try {
                    const action = isActive ? 'remove' : 'add';
                    const response = await fetch('api/favorites.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action,
                            page_url: url,
                            page_label: label,
                            page_icon: icon
                        })
                    });
                    
                    const data = await response.json();
                    if (data.ok) {
                        // Update button state
                        if (isActive) {
                            btn.classList.remove('active');
                            btn.querySelector('i').classList.remove('fas');
                            btn.querySelector('i').classList.add('far');
                            btn.title = 'Adicionar aos favoritos';
                        } else {
                            btn.classList.add('active');
                            btn.querySelector('i').classList.remove('far');
                            btn.querySelector('i').classList.add('fas');
                            btn.title = 'Remover dos favoritos';
                        }
                        
                        // Update all favorite buttons for this page
                        const allBtns = document.querySelectorAll(`[data-url="${url}"]`);
                        allBtns.forEach(b => {
                            if (isActive) {
                                b.classList.remove('active');
                                b.querySelector('i').classList.remove('fas');
                                b.querySelector('i').classList.add('far');
                                b.title = 'Adicionar aos favoritos';
                            } else {
                                b.classList.add('active');
                                b.querySelector('i').classList.remove('far');
                                b.querySelector('i').classList.add('fas');
                                b.title = 'Remover dos favoritos';
                            }
                        });
                        
                        // Show a success message (optional - could use toast notification)
                        console.log(isActive ? 'Removido dos favoritos' : 'Adicionado aos favoritos');
                        
                        // Note: Favorites section update requires page reload
                        // Future enhancement: dynamically update favorites section without reload
                    }
                } catch (error) {
                    console.error('Failed to toggle favorite:', error);
                }
            });
        });
    };
    
    // ========================================
    // INITIALIZATION
    // ========================================
    
    const init = () => {
        initSidebarToggle();
        initCategoryState();
        initGlobalSearch();
        initBadges();
        initFavorites();
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
