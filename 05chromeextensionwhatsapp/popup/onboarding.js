// Tour de Onboarding do Popup
const PopupTour = {
  steps: [
    {
      step: 1,
      target: '.wrap',
      title: '🚀 Bem-vindo ao WhatsHybrid Lite!',
      content: 'Sua IA de atendimento no WhatsApp. Vamos fazer um tour rápido pelas funcionalidades.',
      position: 'center'
    },
    {
      step: 2,
      target: '.card:nth-child(1)',
      title: '🔌 Conexão',
      content: 'Configure sua API Key da OpenAI ou conecte ao seu Backend para usar a IA.',
      position: 'bottom'
    },
    {
      step: 3,
      target: '.card:nth-child(2)',
      title: '🖥️ Backend (opcional)',
      content: 'Se você tem um servidor próprio, configure a URL aqui para centralizar dados e campanhas.',
      position: 'bottom'
    },
    {
      step: 4,
      target: '.card:nth-child(3)',
      title: '🤖 Chatbot',
      content: 'Defina a persona da IA e o contexto do seu negócio. Quanto mais detalhado, melhor as respostas!',
      position: 'bottom'
    },
    {
      step: 5,
      target: '.card:nth-child(4)',
      title: '🎯 Modo Copiloto',
      content: 'A IA aprende com você! Quando a confiança atingir 70%, ela pode responder automaticamente casos simples.',
      position: 'bottom',
      highlight: true
    },
    {
      step: 6,
      target: '.card:nth-child(5)',
      title: '🧠 Memória Híbrida',
      content: 'Sincronize memórias entre dispositivos. A IA lembra do contexto de cada cliente!',
      position: 'top'
    }
  ],
  currentStep: 0,
  overlay: null,
  tooltip: null,
  
  init() {
    // Verificar se é primeira vez
    chrome.storage.local.get(['onboarding_popup_completed'], (result) => {
      if (!result.onboarding_popup_completed) {
        // Pequeno delay para garantir que o DOM está pronto
        setTimeout(() => this.start(), 500);
      }
    });
  },
  
  start() {
    this.currentStep = 0;
    this.createOverlay();
    this.showStep(0);
  },
  
  createOverlay() {
    // Criar overlay escuro
    this.overlay = document.createElement('div');
    this.overlay.className = 'tour-overlay';
    document.body.appendChild(this.overlay);
    
    // Criar tooltip
    this.tooltip = document.createElement('div');
    this.tooltip.className = 'tour-tooltip';
    document.body.appendChild(this.tooltip);
  },
  
  showStep(index) {
    if (index < 0 || index >= this.steps.length) return;
    
    const step = this.steps[index];
    
    // Remover highlight anterior
    document.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    
    // Encontrar e destacar o elemento
    let target = null;
    if (step.target) {
      target = document.querySelector(step.target);
      if (target) {
        target.classList.add('tour-highlight');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
    
    // Posicionar tooltip
    if (target && step.position !== 'center') {
      this.positionTooltip(target, step.position);
    } else {
      // Centralizar tooltip
      this.tooltip.style.top = '50%';
      this.tooltip.style.left = '50%';
      this.tooltip.style.transform = 'translate(-50%, -50%)';
      this.tooltip.classList.remove('position-top', 'position-bottom', 'position-left', 'position-right');
      this.tooltip.classList.add('position-center');
    }
    
    // Renderizar tooltip
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
    const tooltipWidth = 280;
    const tooltipHeight = 240; // Approximate
    const gap = 16;
    
    // Remove position classes
    this.tooltip.classList.remove('position-top', 'position-bottom', 'position-left', 'position-right', 'position-center');
    this.tooltip.style.transform = 'none';
    
    switch (position) {
      case 'bottom':
        this.tooltip.style.top = `${rect.bottom + gap}px`;
        this.tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
        this.tooltip.classList.add('position-bottom');
        break;
      case 'top':
        this.tooltip.style.top = `${rect.top - tooltipHeight - gap}px`;
        this.tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
        this.tooltip.classList.add('position-top');
        break;
      case 'left':
        this.tooltip.style.top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
        this.tooltip.style.left = `${rect.left - tooltipWidth - gap}px`;
        this.tooltip.classList.add('position-left');
        break;
      case 'right':
        this.tooltip.style.top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
        this.tooltip.style.left = `${rect.right + gap}px`;
        this.tooltip.classList.add('position-right');
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
    if (confirm('Deseja pular o tour? Você pode revisitá-lo clicando no botão "?" no canto inferior direito.')) {
      this.cleanup();
      chrome.storage.local.set({ onboarding_popup_completed: true });
    }
  },
  
  complete() {
    this.cleanup();
    chrome.storage.local.set({ onboarding_popup_completed: true });
    this.showCompletionMessage();
  },
  
  cleanup() {
    if (this.overlay) this.overlay.remove();
    if (this.tooltip) this.tooltip.remove();
    document.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
  },
  
  restart() {
    chrome.storage.local.set({ onboarding_popup_completed: false }, () => {
      this.start();
    });
  },
  
  showCompletionMessage() {
    const modal = document.createElement('div');
    modal.className = 'tour-complete-modal';
    modal.innerHTML = `
      <div class="tour-complete-icon">✅</div>
      <h3 class="tour-complete-title">Tour Concluído!</h3>
      <p class="tour-complete-text">
        Agora você conhece todas as funcionalidades do popup. 
        Quando abrir o WhatsApp Web, clique no botão 🤖 para ver o tour do painel!
      </p>
      <button class="tour-btn tour-btn-primary tour-complete-close">Começar a usar</button>
    `;
    
    document.body.appendChild(modal);
    
    const closeBtn = modal.querySelector('.tour-complete-close');
    closeBtn.addEventListener('click', () => {
      modal.remove();
    });
    
    // Auto-fechar após 5 segundos
    setTimeout(() => {
      if (modal.parentElement) modal.remove();
    }, 5000);
  }
};

// Iniciar quando DOM estiver pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => PopupTour.init());
} else {
  PopupTour.init();
}
