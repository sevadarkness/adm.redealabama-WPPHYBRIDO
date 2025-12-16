// Tour de Onboarding do WhatsApp Web
const WhatsAppTour = {
  steps: [
    {
      step: 7,
      target: '.fab',
      title: '🤖 Botão do Chatbot',
      content: 'Clique aqui para abrir o painel da IA. Ele aparece em qualquer conversa do WhatsApp.',
      position: 'left'
    },
    {
      step: 8,
      target: '.tab[data-tab="chat"]',
      title: '💬 Aba Chatbot',
      content: 'Aqui a IA lê a conversa e sugere respostas. Clique em "Gerar" para criar uma sugestão.',
      position: 'right'
    },
    {
      step: 9,
      target: '#chatMode',
      title: '⚡ Modos de Ação',
      content: 'Escolha o que a IA deve fazer: Sugerir resposta, Resumir conversa, Próximos passos ou Modo treino.',
      position: 'right'
    },
    {
      step: 10,
      target: '#genBtn',
      title: '👍 Gerar e Feedback',
      content: 'Clique em "Gerar" para criar sugestões. Depois, use os feedbacks para treinar a IA e aumentar o score de confiança!',
      position: 'right',
      highlight: true
    },
    {
      step: 11,
      target: '#memBtn',
      title: '🦁 Memória (Leão)',
      content: 'A IA salva o contexto de cada cliente automaticamente. Você pode atualizar as memórias aqui.',
      position: 'right'
    },
    {
      step: 12,
      target: '.tab[data-tab="camp"]',
      title: '📢 Aba Campanhas',
      content: 'Envie mensagens em massa via DOM (simulando cliques) ou via API oficial do WhatsApp.',
      position: 'right'
    },
    {
      step: 13,
      target: '#scheduleDateTime',
      title: '📅 Agendamento',
      content: 'Agende campanhas para serem enviadas automaticamente no horário que você definir.',
      position: 'right'
    },
    {
      step: 14,
      target: '.tab[data-tab="cont"]',
      title: '📋 Aba Contatos',
      content: 'Extraia números de telefone visíveis na tela do WhatsApp. Útil para criar listas de envio.',
      position: 'right'
    },
    {
      step: 15,
      target: '.tab[data-tab="training"]',
      title: '🧠 Aba Treinamento IA',
      content: 'Cadastre produtos, FAQs, respostas rápidas e exemplos de conversa. Isso melhora as respostas da IA!',
      position: 'right'
    }
  ],
  currentStep: 0,
  overlay: null,
  tooltip: null,
  shadowRoot: null,
  panelWasOpen: false,
  
  init(shadowRoot) {
    this.shadowRoot = shadowRoot;
    
    // Verificar se é primeira vez
    chrome.storage.local.get(['onboarding_whatsapp_completed'], (result) => {
      if (!result.onboarding_whatsapp_completed) {
        // Esperar o painel ser aberto pela primeira vez
        this.waitForPanelOpen();
      }
    });
  },
  
  waitForPanelOpen() {
    if (!this.shadowRoot) return;
    
    const panel = this.shadowRoot.querySelector('.panel');
    if (!panel) {
      setTimeout(() => this.waitForPanelOpen(), 500);
      return;
    }
    
    // Observer para detectar quando o painel abre
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === 'class') {
          const isOpen = panel.classList.contains('open');
          if (isOpen && !this.panelWasOpen) {
            this.panelWasOpen = true;
            observer.disconnect();
            setTimeout(() => this.start(), 500);
          }
        }
      });
    });
    
    observer.observe(panel, { attributes: true });
  },
  
  start() {
    if (!this.shadowRoot) return;
    
    this.currentStep = 0;
    this.createOverlay();
    this.showStep(0);
  },
  
  createOverlay() {
    // Criar overlay escuro
    this.overlay = document.createElement('div');
    this.overlay.className = 'tour-overlay';
    this.shadowRoot.appendChild(this.overlay);
    
    // Criar tooltip
    this.tooltip = document.createElement('div');
    this.tooltip.className = 'tour-tooltip';
    this.shadowRoot.appendChild(this.tooltip);
  },
  
  showStep(index) {
    if (index < 0 || index >= this.steps.length) return;
    
    const step = this.steps[index];
    
    // Remover highlight anterior
    this.shadowRoot.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    
    // Se for uma aba, ativar ela antes de mostrar o step
    if (step.target.includes('data-tab')) {
      const tab = this.shadowRoot.querySelector(step.target);
      if (tab) {
        // Simular clique na aba para ativá-la
        setTimeout(() => {
          tab.click();
        }, 100);
      }
    }
    
    // Encontrar e destacar o elemento
    let target = null;
    setTimeout(() => {
      if (step.target) {
        target = this.shadowRoot.querySelector(step.target);
        if (target) {
          target.classList.add('tour-highlight');
          // Scroll dentro do painel se necessário
          target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
      
      // Posicionar tooltip
      if (target && step.position !== 'center') {
        this.positionTooltip(target, step.position);
      } else {
        // Centralizar tooltip no painel
        const panel = this.shadowRoot.querySelector('.panel');
        if (panel) {
          const rect = panel.getBoundingClientRect();
          this.tooltip.style.top = `${rect.height / 2}px`;
          this.tooltip.style.left = `${rect.width / 2 - 140}px`;
        }
        this.tooltip.classList.remove('position-top', 'position-bottom', 'position-left', 'position-right');
        this.tooltip.classList.add('position-center');
      }
      
      // Renderizar tooltip
      this.renderTooltip(index, step);
    }, 200);
  },
  
  renderTooltip(index, step) {
    this.tooltip.innerHTML = `
      <div class="tour-header">
        <span class="tour-step-indicator">${index + 1}/${this.steps.length}</span>
        <button class="tour-close">✕</button>
      </div>
      <h3 class="tour-title">${step.title}</h3>
      <p class="tour-content">${step.content}</p>
      <div class="tour-progress">
        ${this.steps.map((_, i) => `<span class="tour-dot ${i === index ? 'active' : ''} ${i < index ? 'completed' : ''}"></span>`).join('')}
      </div>
      <div class="tour-actions">
        ${index > 0 ? '<button class="tour-btn tour-btn-secondary tour-prev">← Anterior</button>' : ''}
        ${index < this.steps.length - 1 
          ? '<button class="tour-btn tour-btn-primary tour-next">Próximo →</button>'
          : '<button class="tour-btn tour-btn-primary tour-complete">Concluir ✓</button>'
        }
      </div>
    `;
    
    // Adicionar event listeners
    const closeBtn = this.tooltip.querySelector('.tour-close');
    const prevBtn = this.tooltip.querySelector('.tour-prev');
    const nextBtn = this.tooltip.querySelector('.tour-next');
    const completeBtn = this.tooltip.querySelector('.tour-complete');
    
    if (closeBtn) closeBtn.addEventListener('click', () => this.skip());
    if (prevBtn) prevBtn.addEventListener('click', () => this.prev());
    if (nextBtn) nextBtn.addEventListener('click', () => this.next());
    if (completeBtn) completeBtn.addEventListener('click', () => this.complete());
  },
  
  positionTooltip(target, position) {
    const rect = target.getBoundingClientRect();
    const panel = this.shadowRoot.querySelector('.panel');
    const panelRect = panel ? panel.getBoundingClientRect() : { top: 0, left: 0 };
    
    const tooltipWidth = 280;
    const tooltipHeight = 240;
    const gap = 16;
    
    // Converter coordenadas globais para coordenadas relativas ao shadowRoot
    const relativeTop = rect.top - panelRect.top;
    const relativeLeft = rect.left - panelRect.left;
    
    // Remove position classes
    this.tooltip.classList.remove('position-top', 'position-bottom', 'position-left', 'position-right', 'position-center');
    
    switch (position) {
      case 'right':
        this.tooltip.style.top = `${relativeTop + (rect.height / 2) - (tooltipHeight / 2)}px`;
        this.tooltip.style.left = `${relativeLeft + rect.width + gap}px`;
        this.tooltip.classList.add('position-right');
        break;
      case 'left':
        this.tooltip.style.top = `${relativeTop + (rect.height / 2) - (tooltipHeight / 2)}px`;
        this.tooltip.style.left = `${relativeLeft - tooltipWidth - gap}px`;
        this.tooltip.classList.add('position-left');
        break;
      case 'bottom':
        this.tooltip.style.top = `${relativeTop + rect.height + gap}px`;
        this.tooltip.style.left = `${relativeLeft + (rect.width / 2) - (tooltipWidth / 2)}px`;
        this.tooltip.classList.add('position-bottom');
        break;
      case 'top':
        this.tooltip.style.top = `${relativeTop - tooltipHeight - gap}px`;
        this.tooltip.style.left = `${relativeLeft + (rect.width / 2) - (tooltipWidth / 2)}px`;
        this.tooltip.classList.add('position-top');
        break;
    }
  },
  
  next() {
    this.currentStep++;
    if (this.currentStep < this.steps.length) {
      this.showStep(this.currentStep);
    } else {
      this.complete();
    }
  },
  
  prev() {
    this.currentStep--;
    if (this.currentStep >= 0) {
      this.showStep(this.currentStep);
    }
  },
  
  skip() {
    this.cleanup();
    chrome.storage.local.set({ onboarding_whatsapp_completed: true });
  },
  
  complete() {
    this.cleanup();
    chrome.storage.local.set({ onboarding_whatsapp_completed: true });
    this.showCompletionMessage();
  },
  
  cleanup() {
    if (this.overlay) this.overlay.remove();
    if (this.tooltip) this.tooltip.remove();
    if (this.shadowRoot) {
      this.shadowRoot.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    }
  },
  
  restart() {
    chrome.storage.local.set({ onboarding_whatsapp_completed: false }, () => {
      this.start();
    });
  },
  
  showCompletionMessage() {
    const modal = document.createElement('div');
    modal.className = 'tour-complete-modal';
    modal.innerHTML = `
      <div class="tour-complete-icon">✅</div>
      <h3 class="tour-complete-title">Pronto!</h3>
      <p class="tour-complete-text">
        Você está preparado para usar o WhatsHybrid Lite! 
        Explore as abas e comece a usar a IA nos seus atendimentos.
      </p>
      <div class="tour-actions">
        <button class="tour-btn tour-btn-secondary tour-restart">Ver tour novamente</button>
        <button class="tour-btn tour-btn-primary tour-complete-close">Começar a usar</button>
      </div>
    `;
    
    this.shadowRoot.appendChild(modal);
    
    const closeBtn = modal.querySelector('.tour-complete-close');
    const restartBtn = modal.querySelector('.tour-restart');
    
    closeBtn.addEventListener('click', () => {
      modal.remove();
    });
    
    restartBtn.addEventListener('click', () => {
      modal.remove();
      this.restart();
    });
    
    // Auto-fechar após 8 segundos
    setTimeout(() => {
      if (modal.parentElement) modal.remove();
    }, 8000);
  },
  
  // Método para adicionar botão de ajuda ao painel
  addHelpButton() {
    if (!this.shadowRoot) return;
    
    const panel = this.shadowRoot.querySelector('.panel');
    if (!panel) return;
    
    // Verificar se já existe
    if (this.shadowRoot.querySelector('.tour-help-btn')) return;
    
    const helpBtn = document.createElement('button');
    helpBtn.className = 'tour-help-btn';
    helpBtn.innerHTML = '?';
    helpBtn.title = 'Ver tour novamente';
    helpBtn.addEventListener('click', () => this.restart());
    
    this.shadowRoot.appendChild(helpBtn);
  }
};

// Exportar para uso no content.js
if (typeof window !== 'undefined') {
  window.WhatsAppTour = WhatsAppTour;
}
