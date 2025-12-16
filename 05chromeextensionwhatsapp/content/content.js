// content/content.js
// WhatsHybrid Lite (Alabama) - Content Script (MV3)
// Estratégia 2024-2025:
// - NÃO depender de APIs internas do WhatsApp Web (window.Store / window.require).
// - Interagir principalmente via DOM (ler mensagens visíveis + simular digitação + clique enviar).
// - Fallback: apenas detectar se Store existe (via injected.js) para debug.
//
// Módulos:
// - Chatbot IA (OpenAI ou Backend)
// - Memória (Leão) por conversa + contexto global do negócio
// - Campanhas: Links (assistido) | DOM (assistido/auto com confirmação) | API (backend)
// - Extração de contatos: leitura de IDs/JIDs no DOM (quando possível) + título/headers

(() => {
  'use strict';

  const EXT = {
    id: 'whl-root',
    name: 'WhatsHybrid Lite',
    version: '0.2.0'
  };

  // -------------------------
  // Utils
  // -------------------------
  const log = (...args) => console.log('[WhatsHybrid Lite]', ...args);
  const warn = (...args) => console.warn('[WhatsHybrid Lite]', ...args);

  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  function safeText(x) {
    return (x === undefined || x === null) ? '' : String(x);
  }

  // -------------------------
  // Sistema de Cache Inteligente
  // -------------------------
  class SmartCache {
    constructor(defaultTTL = 60000) { // 1 minuto padrão
      this.cache = new Map();
      this.defaultTTL = defaultTTL;
    }
    
    set(key, value, ttl = this.defaultTTL) {
      this.cache.set(key, {
        value,
        expiresAt: Date.now() + ttl
      });
    }
    
    get(key) {
      const item = this.cache.get(key);
      if (!item) return null;
      if (Date.now() > item.expiresAt) {
        this.cache.delete(key);
        return null;
      }
      return item.value;
    }
    
    has(key) {
      return this.get(key) !== null;
    }
    
    delete(key) {
      this.cache.delete(key);
    }
    
    clear() {
      this.cache.clear();
    }
    
    // Limpar expirados periodicamente
    cleanup() {
      const now = Date.now();
      for (const [key, item] of this.cache.entries()) {
        if (now > item.expiresAt) {
          this.cache.delete(key);
        }
      }
    }
  }

  // Instância global de cache
  const whlCache = new SmartCache();

  // Cleanup automático a cada 2 minutos
  setInterval(() => whlCache.cleanup(), 120000);

  // Hash simples para cache keys
  function hashString(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return hash.toString(36);
  }

  function clamp(n, min, max) {
    n = Number(n);
    if (!Number.isFinite(n)) return min;
    return Math.max(min, Math.min(max, n));
  }

  function uniq(arr) {
    return Array.from(new Set(arr.filter(Boolean)));
  }

  function csvEscape(v) {
    const s = safeText(v);
    if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
    return s;
  }

  async function bg(type, payload) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type, ...(payload || {}) }, (resp) => {
          const err = chrome.runtime.lastError;
          if (err) return resolve({ ok: false, error: err.message || String(err) });
          resolve(resp);
        });
      } catch (e) {
        resolve({ ok: false, error: e?.message || String(e) });
      }
    });
  }

  async function getSettingsCached() {
    // Use SmartCache system with 5 second TTL
    const cached = whlCache.get('settings');
    if (cached) return cached;
    
    const resp = await bg('GET_SETTINGS', {});
    const settings = resp?.settings || {};
    whlCache.set('settings', settings, 5000);
    return settings;
  }

  // -------------------------
  // Inject (fallback detection)
  // -------------------------
  function injectMainWorld() {
    try {
      const s = document.createElement('script');
      s.src = chrome.runtime.getURL('content/injected.js');
      s.async = false;
      (document.head || document.documentElement).appendChild(s);
      s.remove();
    } catch (e) {
      // ignore
    }
  }

  const injectedStatus = { received: false, info: null };
  window.addEventListener('message', (ev) => {
    try {
      if (!ev?.data || ev.data.source !== 'WHL') return;
      if (ev.data.type === 'INJECTED_STATUS') {
        injectedStatus.received = true;
        injectedStatus.info = ev.data.info || null;
        log('Injected status:', injectedStatus.info);
      }
    } catch (_) {}
  });

  injectMainWorld();

  // -------------------------
  // Sistema de Seletores com Fallback
  // -------------------------
  const WA_SELECTORS = {
    // Caixa de composição de mensagem
    composer: [
      'footer [contenteditable="true"][role="textbox"]',
      '[data-testid="conversation-compose-box-input"]',
      'div[data-tab="10"][contenteditable="true"]',
      '#main footer [contenteditable="true"]',
      'footer div[contenteditable="true"]'
    ],
    
    // Botão enviar
    sendButton: [
      'footer button[data-testid="compose-btn-send"]',
      'footer button span[data-icon="send"]',
      'footer button span[data-icon="send-light"]',
      'footer button[aria-label*="Enviar"]',
      'footer button[aria-label*="Send"]'
    ],
    
    // Botão de anexo
    attachButton: [
      'footer button[aria-label*="Anexar"]',
      'footer button[title*="Anexar"]',
      'footer span[data-icon="attach-menu-plus"]',
      'footer span[data-icon="clip"]',
      'footer span[data-icon="attach"]',
      'footer button[aria-label*="Attach"]'
    ],
    
    // Input de arquivo
    fileInput: [
      'input[type="file"][accept*="image"]',
      'input[type="file"][accept*="video"]',
      'input[type="file"]'
    ],
    
    // Caixa de busca de chats
    searchBox: [
      '[data-testid="chat-list-search"] [contenteditable="true"]',
      '[data-testid="chat-list-search"] [role="textbox"]',
      '#pane-side [contenteditable="true"][role="textbox"]',
      'div[data-testid="search-container"] [contenteditable="true"]'
    ],
    
    // Resultados de busca
    searchResults: [
      '#pane-side [role="row"]',
      '[data-testid="chat-list"] [role="row"]',
      '[data-testid="chat-list"] [role="listitem"]',
      '#pane-side [role="listitem"]'
    ],
    
    // Preview de mídia (dialog)
    mediaDialog: [
      'div[role="dialog"]',
      '[data-testid="media-viewer"]',
      '[data-testid="popup"]'
    ],
    
    // Botão enviar na preview de mídia
    mediaSendButton: [
      'div[role="dialog"] button[aria-label*="Enviar"]',
      'div[role="dialog"] button span[data-icon="send"]',
      '[data-testid="media-viewer"] button[aria-label*="Send"]'
    ],
    
    // Caption na preview de mídia
    mediaCaptionBox: [
      'div[role="dialog"] [contenteditable="true"][role="textbox"]',
      'div[role="dialog"] div[contenteditable="true"][data-tab]'
    ],
    
    // Título do chat atual
    chatHeader: [
      'header span[title]',
      'header [title]',
      '#main header span[dir="auto"]'
    ],
    
    // Container de mensagens
    messagesContainer: [
      '[data-testid="conversation-panel-messages"]',
      '#main div[role="application"]',
      '#main'
    ],
    
    // Indicadores de erro/bloqueio
    errorIndicators: [
      '[data-testid="alert-notification"]',
      'div[data-animate-modal-popup="true"]',
      '[data-testid="popup-contents"]'
    ]
  };

  // Função para encontrar elemento com fallback
  function findElement(selectorKey, parent = document) {
    const selectors = WA_SELECTORS[selectorKey];
    if (!selectors) {
      warn(`Selector key não encontrado: ${selectorKey}`);
      return null;
    }
    
    for (const sel of selectors) {
      try {
        const el = parent.querySelector(sel);
        if (el && el.isConnected) {
          // Verificar se é visível
          if (el.offsetWidth || el.offsetHeight || el.getClientRects().length) {
            return el;
          }
        }
      } catch (e) {
        // Seletor inválido, tentar próximo
      }
    }
    return null;
  }

  // Função para encontrar múltiplos elementos
  function findElements(selectorKey, parent = document) {
    const selectors = WA_SELECTORS[selectorKey];
    if (!selectors) return [];
    
    for (const sel of selectors) {
      try {
        const els = Array.from(parent.querySelectorAll(sel))
          .filter(el => el && el.isConnected);
        if (els.length) return els;
      } catch (e) {}
    }
    return [];
  }

  // Função com retry
  async function findElementWithRetry(selectorKey, maxAttempts = 10, delayMs = 300) {
    for (let i = 0; i < maxAttempts; i++) {
      const el = findElement(selectorKey);
      if (el) return el;
      await sleep(delayMs);
    }
    return null;
  }

  // -------------------------
  // Modo Stealth Aprimorado
  // -------------------------
  const STEALTH_CONFIG = {
    // Delays entre caracteres (ms)
    typingDelayMin: 30,
    typingDelayMax: 120,
    
    // Pausa após "digitando..." aparecer
    thinkingPauseMin: 500,
    thinkingPauseMax: 2000,
    
    // Delay antes de clicar enviar
    beforeSendDelayMin: 200,
    beforeSendDelayMax: 800,
    
    // Variação no delay entre mensagens da campanha (%)
    delayVariation: 0.3, // ±30%
    
    // Horários "humanos" (evitar 3am-6am)
    humanHoursStart: 7,
    humanHoursEnd: 22,
    
    // Máximo de mensagens por hora (parecer natural)
    maxMessagesPerHour: 30,
    
    // Pausas aleatórias longas (simular distração)
    randomLongPauseChance: 0.05, // 5% de chance
    randomLongPauseMin: 30000, // 30s
    randomLongPauseMax: 120000 // 2min
  };

  // Helper para número aleatório
  function randomBetween(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  // Digitação humanizada
  async function humanType(element, text) {
    element.focus();
    
    // Limpar conteúdo existente
    document.execCommand('selectAll', false, null);
    await sleep(randomBetween(50, 150));
    
    // Digitar caractere por caractere
    for (let i = 0; i < text.length; i++) {
      const char = text[i];
      
      // Delay variável entre caracteres
      const delay = randomBetween(
        STEALTH_CONFIG.typingDelayMin,
        STEALTH_CONFIG.typingDelayMax
      );
      await sleep(delay);
      
      // Inserir caractere
      document.execCommand('insertText', false, char);
      
      // Ocasionalmente fazer uma pausa maior (como se pensando)
      if (Math.random() < 0.02) { // 2% chance
        await sleep(randomBetween(300, 800));
      }
    }
    
    // Disparar evento de input
    element.dispatchEvent(new InputEvent('input', { bubbles: true }));
  }

  // Delay com variação aleatória
  function randomizedDelay(baseDelayMs) {
    const variation = baseDelayMs * STEALTH_CONFIG.delayVariation;
    return baseDelayMs + randomBetween(-variation, variation);
  }

  // Verificar se está em horário "humano"
  function isHumanHour() {
    const hour = new Date().getHours();
    return hour >= STEALTH_CONFIG.humanHoursStart && 
           hour < STEALTH_CONFIG.humanHoursEnd;
  }

  // Verificar rate limit
  const messageTimestamps = [];
  function checkRateLimit() {
    const oneHourAgo = Date.now() - 3600000;
    // Limpar timestamps antigos
    while (messageTimestamps.length && messageTimestamps[0] < oneHourAgo) {
      messageTimestamps.shift();
    }
    return messageTimestamps.length < STEALTH_CONFIG.maxMessagesPerHour;
  }

  function recordMessageSent() {
    messageTimestamps.push(Date.now());
  }

  // Pausa aleatória longa (simular distração humana)
  async function maybeRandomLongPause() {
    if (Math.random() < STEALTH_CONFIG.randomLongPauseChance) {
      const pause = randomBetween(
        STEALTH_CONFIG.randomLongPauseMin,
        STEALTH_CONFIG.randomLongPauseMax
      );
      log(`Stealth: Pausa aleatória de ${Math.round(pause/1000)}s`);
      await sleep(pause);
      return true;
    }
    return false;
  }

  // -------------------------
  // WhatsApp DOM helpers
  // -------------------------
  function getChatTitle() {
    // best-effort: WhatsApp changes DOM often
    const header = document.querySelector('header');
    if (!header) return 'chat_desconhecido';
    const span = header.querySelector('span[title]') || header.querySelector('[title]');
    const title = span?.getAttribute('title') || span?.textContent || '';
    return title.trim() || 'chat_desconhecido';
  }

  function getVisibleTranscript(limit = 25) {
    // WhatsApp often uses data-pre-plain-text attribute in message nodes.
    const nodes = Array.from(document.querySelectorAll('div[data-pre-plain-text]'));
    const slice = nodes.slice(Math.max(0, nodes.length - limit));
    const lines = [];
    for (const node of slice) {
      const txt =
        node.querySelector('span.selectable-text')?.innerText ||
        node.querySelector('span[dir="ltr"]')?.innerText ||
        node.innerText ||
        '';
      const clean = safeText(txt).replace(/\s+\n/g, '\n').trim();
      if (!clean) continue;
      const who = node.closest('.message-out') ? 'EU' : (node.closest('.message-in') ? 'CONTATO' : 'MSG');
      lines.push(`${who}: ${clean}`);
    }
    return lines.join('\n');
  }

  function findComposer() {
    // Use novo sistema de seletores com fallback
    return findElement('composer');
  }

  async function insertIntoComposer(text) {
    const box = findComposer();
    if (!box) throw new Error('Não encontrei a caixa de mensagem do WhatsApp.');
    box.focus();

    const t = safeText(text);

    // Try execCommand first
    try {
      document.execCommand('selectAll', false, null);
      document.execCommand('insertText', false, t);
      return true;
    } catch (_) {}

    // Fallback
    box.textContent = t;
    box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    return true;
  }

  function findSendButton() {
    // Use novo sistema de seletores com fallback
    const btn = findElement('sendButton');
    if (btn) return btn;
    // Fallback adicional: tentar encontrar botão através de ícone
    const iconBtns = document.querySelectorAll('footer button span[data-icon="send"], footer button span[data-icon="send-light"]');
    for (const icon of iconBtns) {
      const parent = icon.closest('button');
      if (parent) return parent;
    }
    return null;
  }

  async function clickSend() {
    const btn = findSendButton();
    if (!btn) throw new Error('Não encontrei o botão ENVIAR.');
    btn.click();
    return true;
  }

  function b64ToBytes(b64) {
    const s = safeText(b64).replace(/\s+/g, '');
    const bin = atob(s);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes;
  }

  function findAttachButton() {
    // Use novo sistema de seletores com fallback
    const btn = findElement('attachButton');
    if (btn) return btn;
    // Fallback adicional: tentar encontrar botão através de ícone
    const icons = document.querySelectorAll('footer span[data-icon="attach-menu-plus"], footer span[data-icon="clip"], footer span[data-icon="attach"]');
    for (const icon of icons) {
      const parent = icon.closest('button');
      if (parent) return parent;
    }
    return null;
  }

  function findBestFileInput() {
    // Use novo sistema de seletores com fallback
    const inputs = findElements('fileInput');
    if (!inputs.length) return null;

    // Prefer image accept
    const img = inputs.find(i => safeText(i.accept).includes('image'));
    return img || inputs[0];
  }

  function findDialogRoot() {
    // Use novo sistema de seletores com fallback
    return findElement('mediaDialog');
  }

  function findMediaCaptionBox() {
    const dlg = findDialogRoot();
    if (!dlg) return null;

    // Use novo sistema de seletores dentro do dialog
    const box = findElement('mediaCaptionBox', dlg);
    if (box && box.closest('footer')) return null;
    return box;
  }

  function findMediaSendButton() {
    const dlg = findDialogRoot();
    if (!dlg) return null;

    // Use novo sistema de seletores dentro do dialog
    const btn = findElement('mediaSendButton', dlg);
    if (btn && btn.closest('footer')) return null;
    
    // Fallback adicional: tentar encontrar botão através de ícone
    if (!btn) {
      const icons = dlg.querySelectorAll('button span[data-icon="send"], button span[data-icon="send-light"]');
      for (const icon of icons) {
        const parent = icon.closest('button');
        if (parent && !parent.closest('footer')) return parent;
      }
    }
    
    return btn;
  }

  async function attachMediaAndSend(mediaPayload, captionText) {
    if (!mediaPayload?.base64) throw new Error('Mídia não carregada.');
    const attachBtn = findAttachButton();
    if (!attachBtn) throw new Error('Não encontrei o botão de anexo (📎).');

    attachBtn.click();
    await sleep(450);

    const input = findBestFileInput();
    if (!input) throw new Error('Não encontrei o input de arquivo do WhatsApp.');

    const bytes = b64ToBytes(mediaPayload.base64);
    const blob = new Blob([bytes], { type: mediaPayload.type || 'image/*' });
    const file = new File([blob], mediaPayload.name || 'image', { type: blob.type });

    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));

    // Wait preview/dialog
    let sendBtn = null;
    for (let i = 0; i < 40; i++) {
      await sleep(250);
      sendBtn = findMediaSendButton();
      if (sendBtn) break;
    }
    if (!sendBtn) throw new Error('Preview de mídia não apareceu (botão enviar não encontrado).');

    // Caption
    const cap = safeText(captionText).trim();
    if (cap) {
      const box = findMediaCaptionBox();
      if (box) {
        box.focus();
        try {
          document.execCommand('selectAll', false, null);
          document.execCommand('insertText', false, cap);
          box.dispatchEvent(new InputEvent('input', { bubbles: true }));
        } catch (_) {
          box.textContent = cap;
          box.dispatchEvent(new InputEvent('input', { bubbles: true }));
        }
      }
    }

    await sleep(120);
    sendBtn.click();
    await sleep(900);
    return true;
  }


  async function copyToClipboard(text) {
    const t = safeText(text);
    if (!t) return;
    try {
      await navigator.clipboard.writeText(t);
      return true;
    } catch (e) {
      // fallback
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      return true;
    }
  }

  function parseNumbersFromText(text) {
    const t = safeText(text);
    const nums = [];
    // +55 11 99999-9999 or 5511999999999 etc.
    const re = /(\+?\d[\d\s().-]{6,}\d)/g;
    for (const m of t.matchAll(re)) {
      const raw = m[1];
      let digits = raw.replace(/[^\d+]/g, '');
      if (!digits) continue;
      // normalize: keep leading + if present, else add +
      if (!digits.startsWith('+')) digits = '+' + digits;
      // minimal length
      if (digits.replace(/\D/g, '').length < 10) continue;
      nums.push(digits);
    }
    return nums;
  }

  function extractJidsFromDom() {
    // Try to extract phone numbers from JIDs present in attributes.
    // Common forms: 5511999999999@c.us , 5511999999999@s.whatsapp.net , true_5511999999999@s.whatsapp.net
    const found = [];
    const els = document.querySelectorAll('[data-id],[id],[href],[data-testid],[aria-label]');
    const attrs = ['data-id', 'id', 'href', 'data-testid', 'aria-label'];

    for (const el of els) {
      for (const a of attrs) {
        const v = el.getAttribute?.(a);
        if (!v) continue;
        const s = String(v);
        const m = s.match(/(\d{7,})@(?:c\.us|s\.whatsapp\.net)/);
        if (m && m[1]) {
          found.push('+' + m[1]);
        }
        // Some IDs have true_ prefix
        const m2 = s.match(/true_(\d{7,})@/);
        if (m2 && m2[1]) {
          found.push('+' + m2[1]);
        }
      }
    }
    return found;
  }

  async function openChatBySearch(query) {
    // Best-effort. Works inside SAME WhatsApp Web tab. No window.Store / no links.
    const q = safeText(query).replace(/[^\d+]/g, '');
    const digits = q.replace(/[^\d]/g, '');
    if (!digits) throw new Error('Número inválido para abrir chat.');

    // Use novo sistema de seletores com fallback
    const box = findElement('searchBox');
    if (!box) throw new Error('Não encontrei a busca de chats (WhatsApp).');

    // type into search
    box.focus();
    try {
      document.execCommand('selectAll', false, null);
      document.execCommand('insertText', false, digits);
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    } catch (_) {
      box.textContent = digits;
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    }

    await sleep(700);

    const isVisible = (el) => !!(el && el.isConnected && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));

    // Gather result rows usando novo sistema
    const rows = findElements('searchResults').filter(isVisible);

    const matchByDigits = (el) => {
      const t = safeText(el.innerText || '').replace(/\D/g, '');
      return t.includes(digits) || digits.includes(t);
    };

    // Prefer rows that contain the digits
    let target = rows.find(matchByDigits);

    // Some UIs show a "message number" / "new chat" entry; try to pick by text
    if (!target) {
      const candidates = rows.filter(r => {
        const tx = safeText(r.innerText || '').toLowerCase();
        return tx.includes(digits.slice(-6)) || tx.includes('mensag') || tx.includes('message');
      });
      target = candidates.find(matchByDigits) || candidates[0] || rows[0] || null;
    }

    if (!target) throw new Error('Nenhum resultado na busca do WhatsApp (para este número).');

    target.click();

    // Clear search so it doesn't interfere with next iterations
    try {
      await sleep(120);
      box.focus();
      document.execCommand('selectAll', false, null);
      document.execCommand('insertText', false, '');
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    } catch (_) {}

    // Wait for composer
    for (let i = 0; i < 30; i++) {
      await sleep(250);
      if (findComposer()) return true;
    }
    throw new Error('Não consegui abrir o chat (composer não apareceu).');
  }

  // -------------------------
  // Sistema de Fila com Persistência
  // -------------------------
  async function saveCampaignState(state) {
    await chrome.storage.local.set({ 'whl_campaign_active': state });
  }

  async function loadCampaignState() {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_campaign_active'], (result) => {
        resolve(result.whl_campaign_active || null);
      });
    });
  }

  async function clearCampaignState() {
    await chrome.storage.local.remove(['whl_campaign_active']);
  }

  // Histórico de campanhas (últimas 20)
  async function saveCampaignToHistory(campaign) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_campaign_history'], (result) => {
        const history = result.whl_campaign_history || [];
        history.unshift({
          id: campaign.id,
          createdAt: campaign.createdAt,
          completedAt: new Date().toISOString(),
          stats: campaign.progress,
          message: campaign.config.message.slice(0, 50) + '...'
        });
        // Manter apenas últimas 20
        chrome.storage.local.set({ 
          'whl_campaign_history': history.slice(0, 20) 
        }, () => resolve(true));
      });
    });
  }

  // -------------------------
  // Memory (Leão) store
  // -------------------------
  async function getMemory(chatKey) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_memories'], (res) => {
        const mems = res?.whl_memories || {};
        resolve(mems[chatKey] || null);
      });
    });
  }

  async function setMemory(chatKey, memoryObj) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_memories'], (res) => {
        const mems = res?.whl_memories || {};
        mems[chatKey] = { ...(memoryObj || {}), updatedAt: new Date().toISOString() };
        chrome.storage.local.set({ whl_memories: mems }, async () => {
          try {
            await bg('MEMORY_PUSH', { event: { type: 'chat_memory', chatTitle: chatKey, memory: mems[chatKey] } });
          } catch (e) {}
          resolve(true);
        });
      });
    });
  }

  // Training examples (few-shot)
  async function getExamples() {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_examples'], (res) => {
        resolve(Array.isArray(res?.whl_examples) ? res.whl_examples : []);
      });
    });
  }

  async function addExample(example) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_examples'], (res) => {
        const arr = Array.isArray(res?.whl_examples) ? res.whl_examples : [];
        arr.unshift({ ...example, at: new Date().toISOString() });
        const trimmed = arr.slice(0, 60);
        chrome.storage.local.set({ whl_examples: trimmed }, async () => {
          try {
            await bg('MEMORY_PUSH', { event: { type: 'example', example: trimmed[0] } });
          } catch (e) {}
          resolve(true);
        });
      });
    });
  }

  // -------------------------
  // AI prompting
  // -------------------------
  function buildSystemPrompt({ persona, businessContext }) {
    const base =
`Você é um assistente de atendimento no WhatsApp.
Objetivo: responder rápido, claro, profissional e humano, sem inventar informações.

Regras:
- Nunca invente dados (preços, prazos, políticas). Se não souber, pergunte ou diga "não tenho essa informação".
- Não peça dados sensíveis desnecessários.
- Seja direto e útil. Use linguagem natural em pt-BR.
- Se houver contexto do negócio, use como verdade.`;

    const p = safeText(persona).trim();
    const ctx = safeText(businessContext).trim();

    return [
      base,
      p ? `\nPERSONA (regras extras):\n${p}` : '',
      ctx ? `\nCONTEXTO DO NEGÓCIO (conhecimento):\n${ctx}` : '',
    ].filter(Boolean).join('\n');
  }

  function pickExamples(examples, transcript, max = 3) {
    // Very simple relevance: keyword overlap (lightweight, no embeddings).
    const t = transcript.toLowerCase();
    const scored = examples.map((ex) => {
      const u = safeText(ex?.user || '').toLowerCase();
      let score = 0;
      for (const w of u.split(/\W+/).filter(x => x.length >= 4).slice(0, 18)) {
        if (t.includes(w)) score += 1;
      }
      return { ex, score };
    }).sort((a,b) => b.score - a.score);
    return scored.filter(s => s.score > 0).slice(0, max).map(s => s.ex);
  }

  async function getHybridContext({ chatTitle, transcript }) {
    const settings = await getSettingsCached();
    const localMemory = await getMemory(chatTitle);
    const localExamples = await getExamples();

    if (settings?.memorySyncEnabled && settings?.memoryServerUrl && settings?.memoryWorkspaceKey) {
      try {
        const r = await bg('MEMORY_QUERY', { payload: { chatTitle, transcript, topK: 4 } });
        if (r?.ok && r?.data) {
          const d = r.data || {};
          const memory = d.memory || localMemory;
          const examples = Array.isArray(d.examples) ? d.examples : localExamples;
          const context = d.context || null;
          return { memory, examples, context, source: 'server' };
        }
      } catch (e) {}
    }

    return { memory: localMemory, examples: localExamples, context: null, source: 'local' };
  }

  async function aiChat({ mode, extraInstruction, transcript, memory, chatTitle, examplesOverride, contextOverride }) {
    // Cache para respostas da IA (30 segundos) - exceto modo train
    if (mode !== 'train') {
      const cacheKey = `ai_${mode}_${hashString(transcript.slice(-500))}`;
      const cached = whlCache.get(cacheKey);
      if (cached) {
        log('Cache hit para resposta IA');
        return cached;
      }
    }
    
    const settings = await getSettingsCached();
    const systemBase = buildSystemPrompt({ persona: settings.persona, businessContext: settings.businessContext });
    const system = systemBase + (contextOverride?.additions ? `\n\nCONTEXTO (Servidor):\n${safeText(contextOverride.additions)}` : '');

    const memText = memory?.summary ? `\n\nMEMÓRIA (Leão) deste contato:\n${memory.summary}` : '';
    const action =
      mode === 'summary' ? 'Resuma a conversa em tópicos curtos.' :
      mode === 'followup' ? 'Sugira próximos passos claros e objetivos.' :
      mode === 'train' ? 'Gere melhorias para o atendimento (ver instruções).' :
      'Escreva uma sugestão de resposta pronta para eu enviar, mantendo tom premium e humano.';

    let user = `CHAT: ${chatTitle}\n\nCONVERSA (mais recente por último):\n${transcript || '(não consegui ler mensagens)'}${memText}\n\nTAREFA:\n${action}\n`;

    if (mode === 'train') {
      user += `\nINSTRUÇÕES DO MODO TREINO:\n- Analise a conversa e proponha melhorias.\n- Retorne em JSON com chaves: knowledge_additions (array de strings), canned_replies (array de {trigger, reply}), questions_to_clarify (array), risks (array).\n- Não invente informações do negócio.\n`;
    } else {
      const extra = safeText(extraInstruction).trim();
      if (extra) user += `\nINSTRUÇÃO EXTRA:\n${extra}\n`;
      user += `\nResponda SOMENTE com o texto final pronto para enviar.`;
    }

    const examples = Array.isArray(examplesOverride) ? examplesOverride : await getExamples();
    const picked = pickExamples(examples, transcript, 3);

    const messages = [{ role: 'system', content: system }];

    for (const ex of picked) {
      if (safeText(ex?.user).trim() && safeText(ex?.assistant).trim()) {
        messages.push({ role: 'user', content: safeText(ex.user).trim() });
        messages.push({ role: 'assistant', content: safeText(ex.assistant).trim() });
      }
    }

    messages.push({ role: 'user', content: user });

    const contactPhone = (parseNumbersFromText(chatTitle)[0] || parseNumbersFromText(transcript || '')[0] || '').trim();

    const payload = {
      messages,
      model: settings.openaiModel,
      temperature: settings.temperature,
      max_tokens: settings.maxTokens,
      meta: {
        chatTitle,
        contactPhone,
        mode
      },
      transcript: transcript || ''
    };

    const resp = await bg('AI_CHAT', { messages, payload });
    if (!resp?.ok) throw new Error(resp?.error || 'Falha na IA');
    const result = safeText(resp.text || '').trim();
    
    // Armazenar em cache se não for modo train
    if (mode !== 'train') {
      const cacheKey = `ai_${mode}_${hashString(transcript.slice(-500))}`;
      whlCache.set(cacheKey, result, 30000);
    }
    
    return result;
  }

  async function aiMemoryFromTranscript(transcript) {
    const settings = await getSettingsCached();
    const system = buildSystemPrompt({ persona: settings.persona, businessContext: settings.businessContext }) +
      `\n\nVocê agora cria uma memória curta (perfil do contato + contexto) para futuras conversas.`;

    const user =
`A partir da conversa abaixo, gere uma memória estruturada em JSON com o formato:
{
  "profile": "resumo do contato em 1-3 linhas",
  "preferences": ["..."],
  "context": ["fatos relevantes confirmados"],
  "open_loops": ["pendências/perguntas em aberto"],
  "next_actions": ["próximos passos sugeridos"],
  "tone": "tom recomendado"
}

Regras:
- Não invente. Se algo não está claro, use "desconhecido".
- Evite dados sensíveis desnecessários.
- Retorne SOMENTE o JSON.

CONVERSA:
${transcript || '(não consegui ler mensagens)'}
`;

    const chatTitle = getChatTitle();
    const contactPhone = (parseNumbersFromText(chatTitle)[0] || parseNumbersFromText(transcript || '')[0] || '').trim();

    const messages = [
      { role: 'system', content: system },
      { role: 'user', content: user }
    ];

    const payload = {
      messages,
      model: settings.openaiModel,
      temperature: settings.temperature,
      max_tokens: settings.maxTokens,
      meta: { chatTitle, contactPhone, mode: 'memory' },
      transcript: transcript || ''
    };

    const resp = await bg('AI_CHAT', { messages, payload });
    if (!resp?.ok) throw new Error(resp?.error || 'Falha na IA');
    return safeText(resp.text || '').trim();
  }

  function tryParseJson(text) {
    const t = safeText(text).trim();
    if (!t) return null;
    try { return JSON.parse(t); } catch (_) {}
    // try extract JSON object from fenced code or extra text
    const m = t.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
    if (m && m[1]) {
      try { return JSON.parse(m[1]); } catch (_) {}
    }
    const m2 = t.match(/\{[\s\S]*\}/);
    if (m2) {
      try { return JSON.parse(m2[0]); } catch (_) {}
    }
    return null;
  }

  // -------------------------
  // UI mount
  // -------------------------
  function mount() {
    if (document.getElementById(EXT.id)) return;

    const host = document.createElement('div');
    host.id = EXT.id;

    const shadow = host.attachShadow({ mode: 'open' });

    const style = document.createElement('style');
    style.textContent = `
      :host{
        --bg: rgba(10, 12, 24, 0.88);
        --panel: rgba(13, 16, 32, 0.92);
        --stroke: rgba(255,255,255,.10);
        --stroke2: rgba(139,92,246,.35);
        --stroke3: rgba(59,130,246,.25);
        --text: rgba(240,243,255,.95);
        --muted: rgba(240,243,255,.70);
        --danger: #ff4d4f;
        --ok: rgba(120, 255, 190, .95);
        --accent: #8b5cf6;
        --accent2: #3b82f6;
        --shadow: 0 18px 60px rgba(0,0,0,.45);
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      }
      *{ box-sizing:border-box; }
      .fab{
        width: 52px; height: 52px; border-radius: 16px;
        display:flex; align-items:center; justify-content:center;
        cursor:pointer; user-select:none;
        background: linear-gradient(135deg, rgba(139,92,246,.95), rgba(59,130,246,.95));
        border: 1px solid rgba(255,255,255,.18);
        box-shadow: 0 16px 44px rgba(0,0,0,.45);
      }
      .fab span{ font-size: 20px; filter: drop-shadow(0 6px 12px rgba(0,0,0,.35)); }
      .badge{
        position:absolute;
        right: -2px;
        top: -2px;
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(255,255,255,.92);
        color: #0b1020;
        font-size: 11px;
        display:none;
        align-items:center;
        justify-content:center;
        border: 1px solid rgba(0,0,0,.06);
      }
      .badge.on{ display:flex; }

      .panel{
        position:absolute;
        right: 0;
        bottom: 62px;
        width: 388px;
        max-height: 74vh;
        overflow:auto;
        border-radius: 18px;
        background: radial-gradient(1200px 500px at 20% -10%, rgba(139,92,246,.20), transparent 50%),
                    radial-gradient(1000px 600px at 90% 0%, rgba(59,130,246,.16), transparent 55%),
                    var(--panel);
        border: 1px solid var(--stroke);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        display:none;
      }
      .panel.open{ display:block; }

      .hdr{
        padding: 12px 12px 10px;
        border-bottom: 1px solid rgba(255,255,255,.08);
        display:flex; align-items:center; justify-content:space-between; gap:10px;
      }
      .hdr h2{ margin:0; font-size: 13px; letter-spacing:.2px; }
      .hdr .sub{ font-size: 11px; color: var(--muted); margin-top:2px; }
      .hdr .right{ display:flex; gap:8px; align-items:center; }
      .pill{
        font-size: 11px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.10);
        background: rgba(255,255,255,.06);
        color: var(--muted);
      }
      button.icon{
        width: 34px; height: 34px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.06);
        color: var(--text);
        cursor:pointer;
      }
      button.icon:hover{ background: rgba(255,255,255,.10); }

      .tabs{
        display:flex;
        gap:8px;
        padding: 10px 12px 8px;
        border-bottom: 1px solid rgba(255,255,255,.08);
      }
      .tab{
        font-size: 12px;
        padding: 7px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(5,7,15,.35);
        color: var(--muted);
        cursor:pointer;
      }
      .tab.active{
        color: var(--text);
        border-color: rgba(139,92,246,.55);
        box-shadow: 0 0 0 4px rgba(139,92,246,.12);
        background: rgba(139,92,246,.18);
      }

      .sec{ padding: 10px 12px 14px; display:none; }
      .sec.active{ display:block; }

      .note{
        font-size: 11px;
        color: var(--muted);
        line-height: 1.35;
        background: rgba(5,7,15,.35);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        padding: 10px;
      }

      label{ display:block; font-size: 12px; margin: 10px 0 4px; color: rgba(240,243,255,.92); }

      textarea, input, select{
        width: 100%;
        font-size: 12px;
        padding: 9px 10px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(5,7,15,.55);
        color: var(--text);
        outline: none;
      }
      textarea{ min-height: 96px; resize: vertical; }
      input[type="file"]{
        padding: 10px;
        background: rgba(5,7,15,.35);
      }
      input[type="file"]::file-selector-button{
        margin-right: 10px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 12px;
        background: rgba(255,255,255,.06);
        color: var(--text);
        padding: 8px 10px;
        cursor: pointer;
      }
      input[type="file"]::file-selector-button:hover{
        background: rgba(255,255,255,.10);
      }

      textarea:focus, input:focus, select:focus{
        border-color: rgba(139,92,246,.55);
        box-shadow: 0 0 0 4px rgba(139,92,246,.14);
      }

      .row{ display:flex; gap:8px; align-items:flex-end; }
      .row > *{ flex:1; }

      .btns{ display:flex; gap:8px; margin-top: 10px; flex-wrap: wrap; }
      button{
        padding: 9px 10px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.06);
        color: var(--text);
        cursor:pointer;
        font-size: 12px;
      }
      button:hover{ background: rgba(255,255,255,.10); }
      button.primary{
        background: linear-gradient(135deg, rgba(139,92,246,.95), rgba(59,130,246,.95));
        border-color: rgba(255,255,255,.18);
        font-weight: 700;
      }
      button.danger{
        background: rgba(255,77,79,.14);
        border-color: rgba(255,77,79,.35);
      }
      button:disabled{ opacity: .6; cursor:not-allowed; }

      .status{
        font-size: 11px;
        margin-top: 8px;
        color: var(--muted);
        white-space: pre-wrap;
        line-height:1.35;
      }
      .status.ok{ color: var(--ok); }
      .status.err{ color: var(--danger); }

      .list a{
        display:block;
        font-size:12px;
        padding: 8px 10px;
        border:1px solid rgba(255,255,255,.10);
        border-radius: 14px;
        margin: 8px 0;
        text-decoration:none;
        color: var(--text);
        background: rgba(5,7,15,.35);
      }
      .list a:hover{ background: rgba(255,255,255,.06); }

      .split{
        display:flex;
        gap:8px;
        align-items:center;
        justify-content:space-between;
      }
      .checkline{
        display:flex;
        align-items:center;
        gap:8px;
        padding: 10px;
        border:1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        background: rgba(5,7,15,.35);
        margin-top: 8px;
        color: var(--muted);
        font-size: 11px;
      }
      .checkline input{ width:16px; height:16px; }
      .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    `;
    shadow.appendChild(style);

    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <div class="fab" title="WhatsHybrid Lite">
        <span>🤖</span>
        <div class="badge" id="badge">1</div>
      </div>

      <div class="panel" role="dialog" aria-label="WhatsHybrid Lite">
        <div class="hdr">
          <div>
            <h2>WhatsHybrid Lite</h2>
            <div class="sub">IA • Memória • Campanhas • Contatos</div>
          </div>
          <div class="right">
            <div class="pill" id="pillStatus">online</div>
            <button class="icon" id="closeBtn" title="Fechar">✕</button>
          </div>
        </div>

        <div class="tabs">
          <div class="tab active" data-tab="chat">Chatbot</div>
          <div class="tab" data-tab="camp">Campanhas</div>
          <div class="tab" data-tab="cont">Contatos</div>
        </div>

        <div class="sec active" data-sec="chat">
          <div class="note">
            <b>Modo seguro:</b> o chatbot gera texto. Você decide o que enviar.<br/>
            A IA usa <b>contexto do negócio</b> + <b>memória (Leão)</b> + <b>exemplos</b>.
          </div>

          <label>Instrução extra</label>
          <textarea id="chatPrompt" placeholder="Ex.: Responda curto, com tom premium e CTA."></textarea>

          <div class="row">
            <div>
              <label>Mensagens lidas</label>
              <input id="chatLimit" type="number" min="5" max="80" value="30" />
            </div>
            <div>
              <label>Ação</label>
              <select id="chatMode">
                <option value="reply">Sugerir resposta</option>
                <option value="summary">Resumir conversa</option>
                <option value="followup">Próximos passos</option>
                <option value="train">Treino (melhorias)</option>
              </select>
            </div>
          </div>

          <div class="btns">
            <button class="primary" id="genBtn">Gerar</button>
            <button id="memBtn">Atualizar Memória (Leão)</button>
            <button id="saveExampleBtn">Salvar como exemplo</button>
          </div>

          <label>Saída</label>
          <textarea id="chatOut" placeholder="Aqui aparece a resposta..."></textarea>

          <div class="btns">
            <button id="insertBtn">Inserir no WhatsApp</button>
            <button id="sendBtn">Inserir no WhatsApp (assistido)</button>
            <button id="copyBtn">Copiar</button>
          </div>

          <div class="status" id="chatStatus"></div>
          <div class="status mono" id="trainStatus" style="display:none;"></div>
        </div>

        <div class="sec" data-sec="camp">
          <div class="note">
            Campanhas com 2 modos:
            <b>Links</b> (assistido) e <b>API</b> (backend).
          </div>

          <div id="campResumeBox" style="display:none; margin-bottom:10px;">
            <div class="note" style="background: rgba(139,92,246,.12); border-color: rgba(139,92,246,.35);">
              ⚠️ Campanha pausada encontrada
            </div>
            <div class="btns">
              <button class="primary" id="campResumeBtn">Retomar Campanha</button>
              <button class="danger" id="campClearBtn">Descartar</button>
            </div>
            <div class="status" id="campResumeStatus"></div>
          </div>

          <label>Modo</label>
          <select id="campMode">
            <option value="links">Links (assistido)</option>
            <option value="dom" disabled>DOM (desativado)</option>
            <option value="api">API (backend)</option>
          </select>

          <label>Lista de números (1 por linha, com DDI) ou CSV: numero,nome</label>
          <textarea id="campNumbers" placeholder="+5511999999999,João&#10;+5511988888888,Maria"></textarea>

          <label>Mensagem ({{nome}} e {{numero}})</label>
          <textarea id="campMsg" placeholder="Olá {{nome}}, tudo bem?"></textarea>

          <div id="campDomBox" style="display:none;">
            <div class="row">
              <div>
                <label>Delay min (s)</label>
                <input id="campDelayMin" type="number" min="1" max="120" value="6" />
              </div>
              <div>
                <label>Delay max (s)</label>
                <input id="campDelayMax" type="number" min="1" max="240" value="12" />
              </div>
            </div>

            <label>Mídia (opcional)</label>
            <input id="campMedia" type="file" accept="image/*" />
            <div class="hint">A imagem será enviada como anexo. A mensagem acima vira a legenda (opcional).</div>

            <div class="status" id="campDomStatus"></div>

            <div class="note" style="margin-top:10px;">
              <b>Modo DOM:</b> desativado nesta build por segurança/boas práticas. Use Links (assistido) ou API (backend oficial).
            </div>

            <div class="btns">
              <button class="primary" id="campStartBtn">Iniciar (DOM) (desativado)</button>
              <button id="campPauseBtn">Pausar</button>
              <button class="danger" id="campStopBtn">Parar</button>
            </div>
          </div>

          <div id="campLinksBox">
            <div class="btns">
              <button class="primary" id="campBuildBtn">Gerar links</button>
              <button id="campCopyBtn">Copiar lista</button>
            </div>
            <div class="status" id="campStatus"></div>
            <div class="list" id="campLinks"></div>
          </div>

          <div id="campApiBox" style="display:none;">
            <div class="note" style="margin-top:10px;">
              API envia para o backend (ex.: WhatsApp Business API). Requer Backend URL configurado no popup.
            </div>
            <div class="row">
              <div>
                <label>Lote</label>
                <input id="campBatch" type="number" min="1" max="200" value="25" />
              </div>
              <div>
                <label>Intervalo (s)</label>
                <input id="campInterval" type="number" min="1" max="300" value="8" />
              </div>
            </div>
            <div class="btns">
              <button class="primary" id="campApiBtn">Enviar via API</button>
            </div>
            <div class="status" id="campApiStatus"></div>
          </div>
        </div>

        <div class="sec" data-sec="cont">
          <div class="note">
            Extração pega números visíveis (títulos, header e mensagens).
            Resultados dependem do WhatsApp Web.
          </div>

          <div class="btns">
            <button class="primary" id="extractBtn">Extrair números</button>
            <button id="downloadBtn">Baixar CSV</button>
          </div>

          <label>Números</label>
          <textarea id="contOut" placeholder="Saída..."></textarea>

          <div class="status" id="contStatus"></div>
        </div>
      </div>
    `;
    shadow.appendChild(wrap);

    document.documentElement.appendChild(host);

    // UI elements
    const fab = shadow.querySelector('.fab');
    const badge = shadow.getElementById('badge');
    const panel = shadow.querySelector('.panel');
    const closeBtn = shadow.getElementById('closeBtn');
    const pillStatus = shadow.getElementById('pillStatus');

    const tabs = Array.from(shadow.querySelectorAll('.tab'));
    const secs = Array.from(shadow.querySelectorAll('.sec'));

    function setTab(key) {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === key));
      secs.forEach(s => s.classList.toggle('active', s.dataset.sec === key));
    }

    fab.addEventListener('click', () => {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) {
        badge.classList.remove('on');
      }
    });
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));
    tabs.forEach(t => t.addEventListener('click', () => setTab(t.dataset.tab)));

    // Provider status indicator
    (async () => {
      try {
        const st = await getSettingsCached();
        pillStatus.textContent = st.provider === 'backend' ? 'backend' : 'openai';
      } catch (_) {
        pillStatus.textContent = 'offline';
      }
    })();

    // -------------------------
    // Chatbot wiring
    // -------------------------
    const chatPrompt = shadow.getElementById('chatPrompt');
    const chatOut = shadow.getElementById('chatOut');
    const chatLimit = shadow.getElementById('chatLimit');
    const chatMode = shadow.getElementById('chatMode');
    const chatStatus = shadow.getElementById('chatStatus');
    const trainStatus = shadow.getElementById('trainStatus');

    const genBtn = shadow.getElementById('genBtn');
    const memBtn = shadow.getElementById('memBtn');
    const saveExampleBtn = shadow.getElementById('saveExampleBtn');
    const insertBtn = shadow.getElementById('insertBtn');
    const sendBtn = shadow.getElementById('sendBtn');
    const copyBtn = shadow.getElementById('copyBtn');

    function setChatStatus(msg, kind) {
      chatStatus.textContent = msg || '';
      chatStatus.classList.remove('ok','err');
      if (kind === 'ok') chatStatus.classList.add('ok');
      if (kind === 'err') chatStatus.classList.add('err');
    }

    function showTrainStatus(txt) {
      const t = safeText(txt).trim();
      if (!t) {
        trainStatus.style.display = 'none';
        trainStatus.textContent = '';
        return;
      }
      trainStatus.style.display = 'block';
      trainStatus.textContent = t;
    }

    async function runChat() {
      setChatStatus('', null);
      showTrainStatus('');

      genBtn.disabled = true;
      try {
        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const chatTitle = getChatTitle();
        const hybrid = await getHybridContext({ chatTitle, transcript });
        const mem = hybrid.memory;
        const examplesOverride = hybrid.examples;
        const contextOverride = hybrid.context;

        const mode = chatMode.value || 'reply';
        const extra = safeText(chatPrompt.value);

        const text = await aiChat({ mode, extraInstruction: extra, transcript, memory: mem, chatTitle });

        if (mode === 'train') {
          // Training suggestions
          const json = tryParseJson(text);
          if (!json) {
            showTrainStatus(text);
            setChatStatus('Treino gerado (texto) ✅', 'ok');
          } else {
            showTrainStatus(JSON.stringify(json, null, 2));
            setChatStatus('Treino gerado (JSON) ✅', 'ok');
          }
          return;
        }

        chatOut.value = text;
        setChatStatus('OK ✅', 'ok');

        // Optional: auto memory update
        const st = await getSettingsCached();
        if (st.autoMemory) {
          try {
            await autoUpdateMemory(transcript, chatTitle);
          } catch (e) {
            warn('autoMemory falhou:', e);
          }
        }

      } catch (e) {
        setChatStatus(`Erro: ${e?.message || String(e)}`, 'err');
      } finally {
        genBtn.disabled = false;
      }
    }

    async function autoUpdateMemory(transcript, chatTitle) {
      // Lightweight debounce: only update if transcript has enough content
      const t = safeText(transcript).trim();
      if (t.length < 60) return;
      const raw = await aiMemoryFromTranscript(t);
      const json = tryParseJson(raw);
      const summary =
        json
          ? [
              `Perfil: ${safeText(json.profile)}`,
              json.tone ? `Tom: ${safeText(json.tone)}` : '',
              Array.isArray(json.preferences) && json.preferences.length ? `Preferências: ${json.preferences.join('; ')}` : '',
              Array.isArray(json.context) && json.context.length ? `Contexto: ${json.context.join('; ')}` : '',
              Array.isArray(json.open_loops) && json.open_loops.length ? `Pendências: ${json.open_loops.join('; ')}` : '',
              Array.isArray(json.next_actions) && json.next_actions.length ? `Próximos: ${json.next_actions.join('; ')}` : '',
            ].filter(Boolean).join('\n')
          : raw;

      await setMemory(chatTitle, { summary, json });
    }

    genBtn.addEventListener('click', runChat);

    memBtn.addEventListener('click', async () => {
      setChatStatus('', null);
      memBtn.disabled = true;
      try {
        const limit = clamp(chatLimit.value || 30, 10, 120);
        const transcript = getVisibleTranscript(limit);
        const chatTitle = getChatTitle();

        await autoUpdateMemory(transcript, chatTitle);
        setChatStatus('Memória atualizada ✅', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao atualizar memória: ${e?.message || String(e)}`, 'err');
      } finally {
        memBtn.disabled = false;
      }
    });

    saveExampleBtn.addEventListener('click', async () => {
      setChatStatus('', null);
      try {
        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const assistant = safeText(chatOut.value).trim();

        if (!transcript.trim()) throw new Error('Sem conversa visível para usar como exemplo.');
        if (!assistant) throw new Error('Gere uma resposta primeiro para salvar como exemplo.');

        // The "user" side example is: last inbound message or last few lines.
        const lines = transcript.split('\n').slice(-6).join('\n').trim();
        await addExample({ user: `Contexto:\n${lines}\n\nGere uma resposta:`, assistant });

        setChatStatus('Exemplo salvo ✅ (ajuda a IA a ficar mais consistente)', 'ok');
      } catch (e) {
        setChatStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    insertBtn.addEventListener('click', async () => {
      try {
        await insertIntoComposer(chatOut.value || '');
        setChatStatus('Inserido ✅ (confira e envie)', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao inserir: ${e?.message || String(e)}`, 'err');
      }
    });

    sendBtn.addEventListener('click', async () => {
      try {
        await insertIntoComposer(chatOut.value || '');
        setChatStatus('Inserido ✅ (envio assistido — clique em enviar no WhatsApp)', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao inserir: ${e?.message || String(e)}`, 'err');
      }
    });

    copyBtn.addEventListener('click', async () => {
      try {
        await copyToClipboard(chatOut.value || '');
        setChatStatus('Copiado ✅', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao copiar: ${e?.message || String(e)}`, 'err');
      }
    });

    // -------------------------
    // Auto-suggest (MutationObserver)
    // -------------------------
    let autoSuggestTimer = null;
    let lastSuggestFingerprint = '';
    async function maybeAutoSuggest() {
      try {
        const st = await getSettingsCached();
        if (!st.autoSuggest) return;

        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const fp = String(transcript).slice(-600); // cheap fingerprint
        if (!fp || fp === lastSuggestFingerprint) return;
        lastSuggestFingerprint = fp;

        badge.textContent = '!';
        badge.classList.add('on');

        // If panel is open, auto-fill output suggestion
        const chatTitle = getChatTitle();
        const hybrid = await getHybridContext({ chatTitle, transcript });
        const mem = hybrid.memory;
        const examplesOverride = hybrid.examples;
        const contextOverride = hybrid.context;
        const text = await aiChat({
          mode: 'reply',
          extraInstruction: safeText(chatPrompt.value),
          transcript,
          memory: mem,
          chatTitle
        });

        // Don't overwrite if user is editing
        if (!safeText(chatOut.value).trim()) {
          chatOut.value = text;
        }
        setChatStatus('Sugestão automática pronta ✅', 'ok');

      } catch (e) {
        warn('autoSuggest erro:', e);
      }
    }

    function hookMessageObserver() {
      // Observe message container changes usando novo sistema de seletores
      const container = findElement('messagesContainer');
      if (!container) return;

      const obs = new MutationObserver(() => {
        if (autoSuggestTimer) clearTimeout(autoSuggestTimer);
        autoSuggestTimer = setTimeout(() => {
          maybeAutoSuggest();
        }, 1200);
      });

      obs.observe(container, { childList: true, subtree: true });
    }

    hookMessageObserver();

    // -------------------------
    // Campaigns
    // -------------------------
    const campMode = shadow.getElementById('campMode');
    const campNumbers = shadow.getElementById('campNumbers');
    const campMsg = shadow.getElementById('campMsg');

    const campResumeBox = shadow.getElementById('campResumeBox');
    const campResumeBtn = shadow.getElementById('campResumeBtn');
    const campClearBtn = shadow.getElementById('campClearBtn');
    const campResumeStatus = shadow.getElementById('campResumeStatus');

    const campDomStatus = shadow.getElementById('campDomStatus');
    const campMedia = shadow.getElementById('campMedia');

    let campMediaPayload = null;

    // Verificar se existe campanha pausada ao carregar
    async function checkPausedCampaign() {
      try {
        const state = await loadCampaignState();
        if (state && (state.status === 'paused' || state.status === 'running')) {
          campResumeBox.style.display = 'block';
          campResumeStatus.textContent = `Campanha ${state.id}: ${state.progress.sent}/${state.progress.total} enviadas`;
          campResumeStatus.classList.add('ok');
        }
      } catch (e) {
        warn('Erro ao verificar campanha pausada:', e);
      }
    }

    campClearBtn.addEventListener('click', async () => {
      try {
        await clearCampaignState();
        campResumeBox.style.display = 'none';
        campResumeStatus.textContent = 'Campanha descartada';
        campResumeStatus.classList.remove('ok');
      } catch (e) {
        campResumeStatus.textContent = `Erro: ${e?.message || String(e)}`;
        campResumeStatus.classList.add('err');
      }
    });

    // Check for paused campaigns on load
    checkPausedCampaign();

    async function fileToPayload(file) {
      if (!file) return null;
      const maxBytes = 4 * 1024 * 1024; // 4MB (ajuste se quiser)
      if (file.size > maxBytes) throw new Error('Arquivo muito grande (máx 4MB).');
      const dataUrl = await new Promise((resolve, reject) => {
        const fr = new FileReader();
        fr.onerror = () => reject(new Error('Falha ao ler arquivo'));
        fr.onload = () => resolve(String(fr.result || ''));
        fr.readAsDataURL(file);
      });
      const m = dataUrl.match(/^data:(.+?);base64,(.+)$/);
      return {
        name: file.name || 'media',
        type: (m && m[1]) ? m[1] : (file.type || 'application/octet-stream'),
        base64: (m && m[2]) ? m[2] : ''
      };
    }

    function setCampDomStatus(msg, kind) {
      if (!campDomStatus) return;
      campDomStatus.textContent = msg || '';
      campDomStatus.classList.remove('ok','err');
      if (kind === 'ok') campDomStatus.classList.add('ok');
      if (kind === 'err') campDomStatus.classList.add('err');
    }

    if (campMedia) {
      campMedia.addEventListener('change', async () => {
        try {
          const f = campMedia.files && campMedia.files[0];
          campMediaPayload = f ? await fileToPayload(f) : null;
          if (campMediaPayload) setCampDomStatus(`Mídia pronta: ${campMediaPayload.name}`, 'ok');
          else setCampDomStatus('Sem mídia selecionada.', 'ok');
        } catch (e) {
          campMediaPayload = null;
          setCampDomStatus(e?.message || String(e), 'err');
        }
      });
    }


    const campBuildBtn = shadow.getElementById('campBuildBtn');
    const campCopyBtn = shadow.getElementById('campCopyBtn');
    const campStatus = shadow.getElementById('campStatus');
    const campLinks = shadow.getElementById('campLinks');

    const campLinksBox = shadow.getElementById('campLinksBox');
    const campDomBox = shadow.getElementById('campDomBox');
    const campApiBox = shadow.getElementById('campApiBox');

    const campDelayMin = shadow.getElementById('campDelayMin');
    const campDelayMax = shadow.getElementById('campDelayMax');
    const campStartBtn = shadow.getElementById('campStartBtn');
    const campPauseBtn = shadow.getElementById('campPauseBtn');
    const campStopBtn = shadow.getElementById('campStopBtn');

    const campBatch = shadow.getElementById('campBatch');
    const campInterval = shadow.getElementById('campInterval');
    const campApiBtn = shadow.getElementById('campApiBtn');
    const campApiStatus = shadow.getElementById('campApiStatus');

    const campRun = { running:false, paused:false, abort:false, cursor:0, total:0 };

    function setCampStatus(msg, kind) {
      campStatus.textContent = msg || '';
      campStatus.classList.remove('ok','err');
      if (kind === 'ok') campStatus.classList.add('ok');
      if (kind === 'err') campStatus.classList.add('err');
    }
    function setCampApiStatus(msg, kind) {
      campApiStatus.textContent = msg || '';
      campApiStatus.classList.remove('ok','err');
      if (kind === 'ok') campApiStatus.classList.add('ok');
      if (kind === 'err') campApiStatus.classList.add('err');
    }

    function parseCampaignLines(raw) {
      const lines = safeText(raw).split(/\r?\n/).map(l => l.trim()).filter(Boolean);
      const entries = [];
      for (const line of lines) {
        const parts = line.split(',').map(p => p.trim());
        const number = (parts[0] || '').replace(/[^\d+]/g, '');
        const name = parts[1] || '';
        if (!number) continue;
        const normalized = number.startsWith('+') ? number : '+' + number;
        entries.push({ number: normalized, name });
      }
      const map = new Map();
      for (const e of entries) map.set(e.number, e);
      return Array.from(map.values());
    }

    function applyVars(msg, entry) {
      let out = safeText(msg);
      out = out.replaceAll('{{nome}}', entry.name || '');
      out = out.replaceAll('{{numero}}', entry.number || '');
      return out;
    }

    function renderCampMode() {
      let m = campMode.value;
      if (m === 'dom') {
        // DOM mode disabled in this build
        m = 'api';
        campMode.value = 'api';
        try { setCampStatus('DOM desativado. Use Links (assistido) ou API (backend).', 'err'); } catch (_) {}
      }
      campLinksBox.style.display = (m === 'links') ? 'block' : 'none';
      campDomBox.style.display = (m === 'dom') ? 'block' : 'none';
      campApiBox.style.display = (m === 'api') ? 'block' : 'none';
    }
    campMode.addEventListener('change', renderCampMode);
    renderCampMode();

    // Links mode (assistido) - same behavior as lite v0.1
    campBuildBtn.addEventListener('click', () => {
      setCampStatus('', null);
      campLinks.innerHTML = '';
      try {
        const entries = parseCampaignLines(campNumbers.value);
        if (!entries.length) throw new Error('Cole pelo menos 1 número.');
        const msg = safeText(campMsg.value).trim();
        if (!msg) throw new Error('Digite a mensagem.');

        const max = Math.min(entries.length, 200);
        const used = entries.slice(0, max);

        for (const e of used) {
          const text = applyVars(msg, e);
          const phone = e.number.replace(/^\+/, '');
          const url = `https://web.whatsapp.com/send?phone=${encodeURIComponent(phone)}&text=${encodeURIComponent(text)}`;
          const a = document.createElement('a');
          a.href = url;
          a.target = '_blank';
          a.rel = 'noreferrer';
          a.textContent = `${e.number}${e.name ? ' - ' + e.name : ''}`;
          campLinks.appendChild(a);
        }

        setCampStatus(`Links gerados: ${used.length}.`, 'ok');
      } catch (e) {
        setCampStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    campCopyBtn.addEventListener('click', async () => {
      try {
        const entries = parseCampaignLines(campNumbers.value);
        const msg = safeText(campMsg.value).trim();
        const lines = entries.map((e) => `${e.number}${e.name ? ',' + e.name : ''}`).join('\n');
        await copyToClipboard(lines);
        setCampStatus('Lista copiada ✅', 'ok');
      } catch (e) {
        setCampStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    async function waitWhilePaused() {
      while (campRun.paused && !campRun.abort) {
        await sleep(250);
      }
    }

    async function executeDomCampaign(entries, msg) {
      throw new Error('Modo DOM de campanha desativado nesta build. Use Campanha (API) com WhatsApp oficial ou Links (assistido).');

      const dmin = clamp(campDelayMin.value || 6, 1, 120);
      const dmax = clamp(campDelayMax.value || 12, 1, 240);

      campRun.running = true;
      campRun.paused = false;
      campRun.abort = false;
      campRun.cursor = 0;
      campRun.total = entries.length;

      setCampDomStatus(`Iniciando IA executora: ${entries.length} contatos…`, 'ok');

      for (let i = 0; i < entries.length; i++) {
        if (campRun.abort) break;
        await waitWhilePaused();
        if (campRun.abort) break;

        const e = entries[i];
        const text = applyVars(msg || '', e).trim();
        const phoneDigits = e.number.replace(/[^\d]/g, '');

        try {
          setCampDomStatus(`(${i+1}/${entries.length}) Abrindo ${e.number}…`, 'ok');

          // Abre o chat dentro da mesma aba (busca lateral)
          await openChatBySearch(phoneDigits);
          await sleep(350);

          if (campMediaPayload) {
            // Envia mídia + legenda (mensagem)
            await attachMediaAndSend(campMediaPayload, text);
          } else {
            if (!text) throw new Error('Mensagem vazia (e sem mídia).');
            await insertIntoComposer(text);
            await sleep(120);
            await clickSend();
          }

          setCampDomStatus(`Enviado ✅ (${i+1}/${entries.length}) para ${e.number}`, 'ok');
        } catch (err) {
          setCampDomStatus(`Falha (${i+1}/${entries.length}) em ${e.number}: ${err?.message || String(err)}`, 'err');
        } finally {
          campRun.cursor = i + 1;
        }

        const delay = (Math.random() * (dmax - dmin) + dmin) * 1000;
        await sleep(delay);
      }

      campRun.running = false;
      campRun.paused = false;
      campPauseBtn.textContent = 'Pausar';

      if (campRun.abort) setCampDomStatus('Campanha interrompida.', 'err');
      else setCampDomStatus('Campanha concluída ✅', 'ok');
    }

    campStartBtn.addEventListener('click', async () => {
      setCampDomStatus('', null);
      try {
        const entries = parseCampaignLines(campNumbers.value);
        if (!entries.length) throw new Error('Cole pelo menos 1 número.');

        const msg = safeText(campMsg.value).trim();
        const hasMedia = Boolean(campMediaPayload && campMediaPayload.base64);
        if (!msg && !hasMedia) throw new Error('Digite a mensagem ou selecione uma mídia.');

        if (campRun.running) throw new Error('Já existe uma execução em andamento.');
        await executeDomCampaign(entries, msg);
      } catch (e) {
        setCampDomStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    campPauseBtn.addEventListener('click', () => {
      if (!campRun.running) return;
      campRun.paused = !campRun.paused;
      campPauseBtn.textContent = campRun.paused ? 'Retomar' : 'Pausar';
    });

    campStopBtn.addEventListener('click', () => {
      if (!campRun.running) return;
      campRun.abort = true;
      campRun.paused = false;
      campPauseBtn.textContent = 'Pausar';
    });

    // API mode (backend) - compatible with old backend /api/campaigns shape
    campApiBtn.addEventListener('click', async () => {
      setCampApiStatus('', null);
      try {
        const entries = parseCampaignLines(campNumbers.value);
        if (!entries.length) throw new Error('Cole pelo menos 1 número.');
        const msg = safeText(campMsg.value).trim();
        if (!msg) throw new Error('Digite a mensagem.');

        const batchSize = clamp(campBatch.value || 25, 1, 200);
        const intervalSeconds = clamp(campInterval.value || 8, 1, 300);

        // backend expects: { message, messages:[{phone, vars?}], batchSize, intervalSeconds }
        // We'll send phone without '+'
        const messages = entries.map((e) => ({
          phone: e.number.replace(/[^\d]/g, ''),
          vars: e.name ? { nome: e.name, numero: e.number } : { numero: e.number }
        }));

        const payload = {
          message: msg, // keep {{nome}} placeholders - backend may replace
          messages,
          batchSize,
          intervalSeconds
        };

        const resp = await bg('CAMPAIGN_API_CREATE', { payload });
        if (!resp?.ok) throw new Error(resp?.error || 'Falha na API');

        const id = resp?.data?.id || resp?.data?.campaignId || '';
        setCampApiStatus(`Enviado ao backend ✅ ${id ? `Campanha: ${id}` : ''}`, 'ok');
      } catch (e) {
        setCampApiStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    // -------------------------
    // Contacts extraction
    // -------------------------
    const extractBtn = shadow.getElementById('extractBtn');
    const downloadBtn = shadow.getElementById('downloadBtn');
    const contOut = shadow.getElementById('contOut');
    const contStatus = shadow.getElementById('contStatus');

    function setContStatus(msg, kind) {
      contStatus.textContent = msg || '';
      contStatus.classList.remove('ok','err');
      if (kind === 'ok') contStatus.classList.add('ok');
      if (kind === 'err') contStatus.classList.add('err');
    }

    function extractNumbersDeep() {
      const nums = [];

      // Titles in chat list / header
      const titled = Array.from(document.querySelectorAll('[title]')).slice(0, 1200);
      for (const el of titled) {
        const title = el.getAttribute('title');
        nums.push(...parseNumbersFromText(title));
      }

      // Header text
      nums.push(...parseNumbersFromText(getChatTitle()));

      // (Restrito) Não extrai IDs internos automaticamente.

      return uniq(nums);
    }

    extractBtn.addEventListener('click', async () => {
      setContStatus('', null);
      try {
        const nums = extractNumbersDeep();
        contOut.value = nums.join('\n');
        setContStatus(`Encontrados: ${nums.length}`, nums.length ? 'ok' : 'err');
      } catch (e) {
        setContStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    downloadBtn.addEventListener('click', () => {
      try {
        const nums = safeText(contOut.value).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        if (!nums.length) throw new Error('Nada para baixar.');
        const csv = ['numero', ...nums].map(csvEscape).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `contatos_whl_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        setContStatus('CSV baixado ✅', 'ok');
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      } catch (e) {
        setContStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });
  }

  // Mount when possible (document_start friendly)
  function boot() {
    try {
      mount();
    } catch (e) {
      warn('Falha ao montar painel:', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
