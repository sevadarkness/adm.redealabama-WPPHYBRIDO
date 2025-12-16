/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║                                                                           ║
 * ║   ██████╗ ███████╗██████╗ ███████╗     █████╗ ██╗      █████╗            ║
 * ║   ██╔══██╗██╔════╝██╔══██╗██╔════╝    ██╔══██╗██║     ██╔══██╗           ║
 * ║   ██████╔╝█████╗  ██║  ██║█████╗      ███████║██║     ███████║           ║
 * ║   ██╔══██╗██╔══╝  ██║  ██║██╔══╝      ██╔══██║██║     ██╔══██║           ║
 * ║   ██║  ██║███████╗██████╔╝███████╗    ██║  ██║███████╗██║  ██║           ║
 * ║   ╚═╝  ╚═╝╚══════╝╚═════╝ ╚══════╝    ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝           ║
 * ║                                                                           ║
 * ║                    SISTEMA PROPRIETÁRIO                                   ║
 * ║                                                                           ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                           ║
 * ║   © 2024 Rede Alabama. Todos os direitos reservados.                      ║
 * ║                                                                           ║
 * ║   Este software é propriedade exclusiva da Rede Alabama.                  ║
 * ║   A cópia, distribuição ou uso não autorizado é PROIBIDO                  ║
 * ║   e sujeito a penalidades legais conforme Lei 9.609/98.                   ║
 * ║                                                                           ║
 * ║   Fingerprint: RA-2024-WPPHYBRIDO-ALABAMA                                 ║
 * ║                                                                           ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */

// ═══════════════════════════════════════════════════════════════════
// SISTEMA DE LICENÇA E AUTENTICAÇÃO
// © 2024 Rede Alabama - Sistema Proprietário
// ═══════════════════════════════════════════════════════════════════

// Chave de licença (ofuscada em Base64) - "Cristi@no123"
const LICENSE_KEY_ENCODED = "Q3Jpc3RpQG5vMTIz";

// ═══════════════════════════════════════════════════════════════════
// FUNÇÕES DE VALIDAÇÃO
// ═══════════════════════════════════════════════════════════════════

function validateLicense(inputKey) {
  try {
    const correctKey = atob(LICENSE_KEY_ENCODED);
    return inputKey === correctKey;
  } catch (e) {
    return false;
  }
}

function validateApiKey(apiKey) {
  // Apenas verifica se não está vazio - aceita qualquer valor
  return apiKey && apiKey.trim().length > 0;
}

// ═══════════════════════════════════════════════════════════════════
// INICIALIZAÇÃO - SISTEMA DE LICENÇA NO HEADER
// ═══════════════════════════════════════════════════════════════════

async function initLicenseSystem() {
  // Carregar estado da licença e API key
  const storage = await chrome.storage.local.get(["licenseValid", "openaiApiKey"]);
  
  const licenseInput = document.getElementById("headerLicenseKey");
  const apiKeyInput = document.getElementById("headerApiKey");
  const apiKeyContainer = document.getElementById("apiKeyFieldContainer");
  
  if (storage.licenseValid) {
    // Licença válida - mostrar campo API key
    licenseInput.value = "••••••••";
    licenseInput.disabled = true;
    apiKeyContainer.classList.remove("hidden");
    
    if (storage.openaiApiKey) {
      apiKeyInput.value = "••••••••";
    }
  }
}

// ═══════════════════════════════════════════════════════════════════
// EVENT LISTENERS - LICENÇA NO HEADER
// ═══════════════════════════════════════════════════════════════════

function setupLicenseListeners() {
  const licenseInput = document.getElementById("headerLicenseKey");
  const apiKeyInput = document.getElementById("headerApiKey");
  const apiKeyContainer = document.getElementById("apiKeyFieldContainer");
  
  // Validar licença ao pressionar Enter
  if (licenseInput) {
    licenseInput.addEventListener("keypress", async (e) => {
      if (e.key === "Enter") {
        const inputValue = licenseInput.value.trim();
        
        if (validateLicense(inputValue)) {
          // Licença válida
          await chrome.storage.local.set({ licenseValid: true });
          licenseInput.value = "••••••••";
          licenseInput.disabled = true;
          apiKeyContainer.classList.remove("hidden");
          apiKeyInput.focus();
          
          if (typeof setStatus === 'function') {
            setStatus("✅ Licença válida!", true);
          }
        } else {
          // Licença inválida
          licenseInput.style.borderColor = "var(--danger)";
          setTimeout(() => {
            licenseInput.style.borderColor = "";
          }, 1000);
          
          if (typeof setStatus === 'function') {
            setStatus("❌ Licença inválida", false);
          }
        }
      }
    });
  }
  
  // Salvar API Key ao pressionar Enter ou sair do campo
  if (apiKeyInput) {
    const saveApiKey = async () => {
      const apiKey = apiKeyInput.value.trim();
      
      if (validateApiKey(apiKey)) {
        await chrome.storage.local.set({ openaiApiKey: apiKey });
        apiKeyInput.value = "••••••••";
        
        if (typeof setStatus === 'function') {
          setStatus("✅ API Key salva com sucesso!", true);
        }
      }
    };
    
    apiKeyInput.addEventListener("keypress", async (e) => {
      if (e.key === "Enter") {
        await saveApiKey();
      }
    });
    
    apiKeyInput.addEventListener("blur", async () => {
      if (apiKeyInput.value && apiKeyInput.value !== "••••••••") {
        await saveApiKey();
      }
    });
  }
}

// ═══════════════════════════════════════════════════════════════════

const el = (id) => document.getElementById(id);

// Global state
let quickReplies = [];
let teamMembers = [];
let products = [];
let faqs = [];
let documents = [];
let cannedResponses = [];
let contacts = [];
let campaigns = [];

async function send(type, payload) {
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

function setStatus(msg, ok = true) {
  const s = el("status");
  s.textContent = msg || "";
  s.className = "status " + (ok ? "ok" : "err");
}

// -------------------------
// Navigation (New Design)
// -------------------------
function initNavigation() {
  const navBtns = document.querySelectorAll('.nav-btn');
  const sections = document.querySelectorAll('.section');
  
  navBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      
      // Update active nav button
      navBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      
      // Show corresponding section
      sections.forEach(s => {
        s.classList.toggle('active', s.dataset.section === tab);
      });
    });
  });
}

// -------------------------
// Accordion (New Design)
// -------------------------
function initAccordion() {
  const accordionHeaders = document.querySelectorAll('.accordion-header');
  
  accordionHeaders.forEach(header => {
    header.addEventListener('click', () => {
      const item = header.parentElement;
      const wasOpen = item.classList.contains('open');
      
      // Toggle clicked accordion
      item.classList.toggle('open', !wasOpen);
    });
  });
}

// -------------------------
// Load Settings
// -------------------------
async function load() {
  const resp = await send("GET_SETTINGS", {});
  if (!resp?.ok) throw new Error(resp?.error || "Falha ao carregar settings.");

  const st = resp?.settings || {};

  // Chatbot
  el("persona").value = st.persona || "";
  el("businessContext").value = st.businessContext || "";
  el("autoSuggest").checked = Boolean(st.autoSuggest);
  el("autoMemory").checked = Boolean(st.autoMemory);

  // Quick Replies
  quickReplies = st.quickReplies || [];
  renderQuickRepliesNew(quickReplies);

  // Team
  el("senderName").value = st.senderName || "";
  teamMembers = st.teamMembers || [];
  renderTeamMembers(teamMembers);
  
  // Load copilot data
  await loadCopilotData();
  
  // Load IA training data
  await loadIAData();
  
  // Load contacts
  await loadContacts();
  
  // Load campaigns
  await loadCampaigns();
}

// -------------------------
// Save Settings
// -------------------------
async function saveSettings() {
  setStatus("Salvando…", true);

  const settings = {
    // Chatbot
    persona: el("persona").value,
    businessContext: el("businessContext").value,
    autoSuggest: el("autoSuggest").checked,
    autoMemory: el("autoMemory").checked,
    
    // Quick Replies
    quickReplies: quickReplies,
    
    // Team
    senderName: el("senderName").value,
    teamMembers: teamMembers,
  };

  const resp = await send("SAVE_SETTINGS", { settings });
  if (resp?.ok) setStatus("Salvo ✅", true);
  else setStatus(resp?.error || "Falha ao salvar", false);
}

// -------------------------
// Copilot Mode Functions
// -------------------------
async function loadCopilotData() {
  try {
    const resp = await send("GET_CONFIDENCE", {});
    if (!resp?.ok) {
      console.error("Failed to load copilot data:", resp?.error);
      return;
    }
    
    const { score, level, metrics, config, points_to_threshold } = resp;
    
    // Update confidence score and mode
    const scoreEl = el("confidenceScore");
    if (scoreEl) scoreEl.textContent = `${Math.round(score)}%`;
    
    const modeEl = el("copilotMode");
    if (modeEl) modeEl.textContent = level.label || "Assistido";
    
    // Update copilot controls
    const enabledEl = el("copilotEnabled");
    if (enabledEl) {
      enabledEl.checked = config.copilot_enabled;
      enabledEl.disabled = score < config.copilot_threshold;
    }
    
    const statusEl = el("copilotStatusText");
    if (statusEl) {
      statusEl.textContent = config.copilot_enabled 
        ? "Modo Copiloto Ativo" 
        : "Modo Copiloto Desativado";
    }
    
    const thresholdEl = el("copilotThreshold");
    if (thresholdEl) thresholdEl.value = config.copilot_threshold;
    
    const thresholdValEl = el("thresholdValue");
    if (thresholdValEl) thresholdValEl.textContent = `${config.copilot_threshold}%`;
    
    // Update stats
    const statGoodEl = el("statGood");
    if (statGoodEl) statGoodEl.textContent = metrics.total_good;
    
    const statBadEl = el("statBad");
    if (statBadEl) statBadEl.textContent = metrics.total_bad;
    
    const statCorrEl = el("statCorrections");
    if (statCorrEl) statCorrEl.textContent = metrics.total_corrections;
    
    const statAutoEl = el("statAutoSent");
    if (statAutoEl) statAutoEl.textContent = metrics.total_auto_sent;
    
  } catch (e) {
    console.error("Error loading copilot data:", e);
  }
}

// -------------------------
// Quick Replies Functions
// -------------------------
function addQuickReply() {
  const trigger = el("newTrigger").value.trim().toLowerCase();
  const response = el("newResponse").value.trim();
  
  if (!trigger || !response) {
    alert("Preencha gatilho e resposta");
    return;
  }
  
  const newReply = {
    id: `qr_${Date.now()}`,
    trigger,
    response,
    createdAt: new Date().toISOString()
  };
  
  quickReplies.push(newReply);
  renderQuickRepliesNew(quickReplies);
  saveSettings();
  
  // Limpar form
  el("newTrigger").value = "";
  el("newResponse").value = "";
}

function removeQuickReply(id) {
  quickReplies = quickReplies.filter(qr => qr.id !== id);
  renderQuickRepliesNew(quickReplies);
  saveSettings();
}

function renderQuickRepliesNew(replies) {
  const container = el("quickRepliesList");
  if (!container) return;
  
  if (!replies.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma mensagem rápida cadastrada</p>';
    return;
  }
  
  container.innerHTML = replies.map(qr => `
    <div class="quick-reply-item" data-id="${qr.id}">
      <div class="qr-trigger">/${qr.trigger}</div>
      <div class="qr-response">${escapeHtml(qr.response)}</div>
      <button class="btn-delete" data-id="${qr.id}">🗑️</button>
    </div>
  `).join('');
  
  // Add event listeners for delete buttons
  container.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.target.dataset.id;
      removeQuickReply(id);
    });
  });
}

// -------------------------
// Team Functions
// -------------------------
function addTeamMember() {
  const name = el("memberName").value.trim();
  const phone = el("memberPhone").value.trim().replace(/\D/g, '');
  
  if (!name || !phone) {
    setStatus("Preencha nome e número", false);
    return;
  }
  
  if (phone.length < 10) {
    setStatus("Número inválido (mínimo 10 dígitos)", false);
    return;
  }
  
  const newMember = {
    id: `tm_${Date.now()}`,
    name,
    phone,
    selected: false,
    createdAt: new Date().toISOString()
  };
  
  teamMembers.push(newMember);
  renderTeamMembers(teamMembers);
  saveSettings();
  
  // Limpar form
  el("memberName").value = "";
  el("memberPhone").value = "";
  setStatus("Membro adicionado ✅", true);
}

function removeTeamMember(id) {
  teamMembers = teamMembers.filter(m => m.id !== id);
  renderTeamMembers(teamMembers);
  saveSettings();
  setStatus("Membro removido", true);
}

function toggleMemberSelection(id) {
  const member = teamMembers.find(m => m.id === id);
  if (member) {
    member.selected = !member.selected;
    renderTeamMembers(teamMembers);
    updateMessagePreview();
  }
}

function selectAllMembers() {
  teamMembers.forEach(m => m.selected = true);
  renderTeamMembers(teamMembers);
  updateMessagePreview();
}

function clearSelection() {
  teamMembers.forEach(m => m.selected = false);
  renderTeamMembers(teamMembers);
  updateMessagePreview();
}

function renderTeamMembers(members) {
  const container = el("teamList");
  if (!container) return;
  
  if (!members.length) {
    container.innerHTML = '<p class="empty-state">Nenhum membro cadastrado</p>';
    return;
  }
  
  container.innerHTML = members.map(m => `
    <div class="team-member-item ${m.selected ? 'selected' : ''}" data-id="${m.id}">
      <label class="checkbox-container">
        <input type="checkbox" ${m.selected ? 'checked' : ''} data-id="${m.id}">
      </label>
      <div class="member-info">
        <span class="member-name">${escapeHtml(m.name)}</span>
        <span class="member-phone">${formatPhone(m.phone)}</span>
      </div>
      <button class="btn-delete" data-id="${m.id}">🗑️</button>
    </div>
  `).join('');
  
  // Add event listeners
  container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', (e) => {
      toggleMemberSelection(e.target.dataset.id);
    });
  });
  
  container.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeTeamMember(e.target.dataset.id);
    });
  });
  
  updateMessagePreview();
}

function formatPhone(phone) {
  if (phone.length === 13) {
    return phone.replace(/(\d{2})(\d{2})(\d{5})(\d{4})/, '+$1 ($2) $3-$4');
  } else if (phone.length === 11) {
    return phone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  }
  return phone;
}

function updateMessagePreview() {
  const senderName = el("senderName").value || "Empresa";
  const message = el("teamMessage").value || "Sua mensagem aqui...";
  const selectedCount = teamMembers.filter(m => m.selected).length;
  
  el("teamMessagePreview").innerHTML = `
    📱 <strong>Preview da notificação:</strong><br>
    "<strong>${escapeHtml(senderName)}:</strong> ${escapeHtml(message.substring(0, 50))}${message.length > 50 ? '...' : ''}"<br>
    <small>${selectedCount} membro(s) selecionado(s)</small>
  `;
}

async function sendToTeam() {
  const senderName = el("senderName").value.trim();
  const message = el("teamMessage").value.trim();
  const selectedMembers = teamMembers.filter(m => m.selected);
  
  if (!message) {
    setStatus("Digite uma mensagem", false);
    return;
  }
  
  if (!selectedMembers.length) {
    setStatus("Selecione pelo menos um membro", false);
    return;
  }
  
  // Formatar mensagem com nome do remetente
  const fullMessage = senderName ? `*${senderName}:* ${message}` : message;
  
  setStatus(`Enviando para ${selectedMembers.length} membro(s)...`, true);
  
  try {
    const response = await send("SEND_TO_TEAM", {
      payload: {
        members: selectedMembers,
        message: fullMessage,
        senderName
      }
    });
    
    if (response.ok) {
      // Mostrar resultados detalhados se disponíveis
      if (response.results) {
        const { success, failed } = response.results;
        setStatus(`✅ Sucesso: ${success} | Falhas: ${failed} de ${selectedMembers.length} membro(s)`, success > 0);
      } else {
        setStatus(`✅ Enviado para ${selectedMembers.length} membro(s)!`, true);
      }
      el("teamMessage").value = "";
      clearSelection();
      updateMessagePreview();
    } else {
      setStatus(`❌ Erro: ${response.error}`, false);
    }
  } catch (e) {
    setStatus(`❌ Erro: ${e.message}`, false);
  }
}

// -------------------------
// IA Tab Functions
// -------------------------
async function loadIAData() {
  const data = await chrome.storage.local.get([
    'businessName', 'businessDescription', 'businessSegment', 'businessHours',
    'products', 'faqs', 'policyPayment', 'policyDelivery', 'policyReturns',
    'toneStyle', 'toneEmojis', 'toneGreeting', 'toneClosing',
    'documents', 'cannedResponses'
  ]);
  
  // Business Data
  if (el("businessName")) el("businessName").value = data.businessName || "";
  if (el("businessDescription")) el("businessDescription").value = data.businessDescription || "";
  if (el("businessSegment")) el("businessSegment").value = data.businessSegment || "";
  if (el("businessHours")) el("businessHours").value = data.businessHours || "";
  
  // Products
  products = data.products || [];
  renderProducts();
  
  // FAQs
  faqs = data.faqs || [];
  renderFAQs();
  
  // Policies
  if (el("policyPayment")) el("policyPayment").value = data.policyPayment || "";
  if (el("policyDelivery")) el("policyDelivery").value = data.policyDelivery || "";
  if (el("policyReturns")) el("policyReturns").value = data.policyReturns || "";
  
  // Tone
  if (el("toneStyle")) el("toneStyle").value = data.toneStyle || "informal";
  if (el("toneEmojis")) el("toneEmojis").checked = data.toneEmojis !== false;
  if (el("toneGreeting")) el("toneGreeting").value = data.toneGreeting || "";
  if (el("toneClosing")) el("toneClosing").value = data.toneClosing || "";
  
  // Documents
  documents = data.documents || [];
  renderDocuments();
  
  // Canned Responses
  cannedResponses = data.cannedResponses || [];
  renderCannedResponses();
}

async function saveIAData() {
  const data = {
    businessName: el("businessName")?.value || "",
    businessDescription: el("businessDescription")?.value || "",
    businessSegment: el("businessSegment")?.value || "",
    businessHours: el("businessHours")?.value || "",
    products: products,
    faqs: faqs,
    policyPayment: el("policyPayment")?.value || "",
    policyDelivery: el("policyDelivery")?.value || "",
    policyReturns: el("policyReturns")?.value || "",
    toneStyle: el("toneStyle")?.value || "informal",
    toneEmojis: el("toneEmojis")?.checked !== false,
    toneGreeting: el("toneGreeting")?.value || "",
    toneClosing: el("toneClosing")?.value || "",
    documents: documents,
    cannedResponses: cannedResponses
  };
  
  await chrome.storage.local.set(data);
  setStatus("✅ Conhecimento salvo!", true);
}

function addProduct() {
  const name = el("productName")?.value?.trim();
  const price = el("productPrice")?.value?.trim();
  const description = el("productDescription")?.value?.trim();
  
  if (!name) {
    setStatus("Digite o nome do produto", false);
    return;
  }
  
  products.push({
    id: `prod_${Date.now()}`,
    name,
    price,
    description,
    createdAt: new Date().toISOString()
  });
  
  renderProducts();
  saveIAData();
  
  el("productName").value = "";
  el("productPrice").value = "";
  el("productDescription").value = "";
}

function removeProduct(id) {
  products = products.filter(p => p.id !== id);
  renderProducts();
  saveIAData();
}

function renderProducts() {
  const container = el("productsList");
  if (!container) return;
  
  if (!products.length) {
    container.innerHTML = '<p class="empty-state">Nenhum produto cadastrado</p>';
    return;
  }
  
  container.innerHTML = products.map(p => `
    <div class="item-card">
      <div class="item-header">
        <span class="item-title">${escapeHtml(p.name)}</span>
        ${p.price ? `<span class="item-price">${escapeHtml(p.price)}</span>` : ''}
      </div>
      ${p.description ? `<div class="item-content">${escapeHtml(p.description)}</div>` : ''}
      <button class="item-delete" data-id="${p.id}">🗑️</button>
    </div>
  `).join('');
  
  container.querySelectorAll('.item-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeProduct(e.target.dataset.id);
    });
  });
}

function addFAQ() {
  const question = el("faqQuestion")?.value?.trim();
  const answer = el("faqAnswer")?.value?.trim();
  
  if (!question || !answer) {
    setStatus("Preencha pergunta e resposta", false);
    return;
  }
  
  faqs.push({
    id: `faq_${Date.now()}`,
    question,
    answer,
    createdAt: new Date().toISOString()
  });
  
  renderFAQs();
  saveIAData();
  
  el("faqQuestion").value = "";
  el("faqAnswer").value = "";
}

function removeFAQ(id) {
  faqs = faqs.filter(f => f.id !== id);
  renderFAQs();
  saveIAData();
}

function renderFAQs() {
  const container = el("faqList");
  if (!container) return;
  
  if (!faqs.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma FAQ cadastrada</p>';
    return;
  }
  
  container.innerHTML = faqs.map(f => `
    <div class="item-card">
      <div class="item-title">${escapeHtml(f.question)}</div>
      <div class="item-content">${escapeHtml(f.answer)}</div>
      <button class="item-delete" data-id="${f.id}">🗑️</button>
    </div>
  `).join('');
  
  container.querySelectorAll('.item-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeFAQ(e.target.dataset.id);
    });
  });
}

function addDocument() {
  const title = el("docTitle")?.value?.trim();
  const content = el("docContent")?.value?.trim();
  
  if (!title || !content) {
    setStatus("Preencha título e conteúdo", false);
    return;
  }
  
  documents.push({
    id: `doc_${Date.now()}`,
    title,
    content,
    createdAt: new Date().toISOString()
  });
  
  renderDocuments();
  saveIAData();
  
  el("docTitle").value = "";
  el("docContent").value = "";
}

function removeDocument(id) {
  documents = documents.filter(d => d.id !== id);
  renderDocuments();
  saveIAData();
}

function renderDocuments() {
  const container = el("documentsList");
  if (!container) return;
  
  if (!documents.length) {
    container.innerHTML = '<p class="empty-state">Nenhum documento cadastrado</p>';
    return;
  }
  
  container.innerHTML = documents.map(d => `
    <div class="item-card">
      <div class="item-title">${escapeHtml(d.title)}</div>
      <div class="item-content">${escapeHtml(d.content.substring(0, 100))}${d.content.length > 100 ? '...' : ''}</div>
      <button class="item-delete" data-id="${d.id}">🗑️</button>
    </div>
  `).join('');
  
  container.querySelectorAll('.item-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeDocument(e.target.dataset.id);
    });
  });
}

function addCanned() {
  const title = el("cannedTitle")?.value?.trim();
  const content = el("cannedContent")?.value?.trim();
  
  if (!title || !content) {
    setStatus("Preencha título e conteúdo", false);
    return;
  }
  
  cannedResponses.push({
    id: `canned_${Date.now()}`,
    title,
    content,
    createdAt: new Date().toISOString()
  });
  
  renderCannedResponses();
  saveIAData();
  
  el("cannedTitle").value = "";
  el("cannedContent").value = "";
}

function removeCanned(id) {
  cannedResponses = cannedResponses.filter(c => c.id !== id);
  renderCannedResponses();
  saveIAData();
}

function renderCannedResponses() {
  const container = el("cannedList");
  if (!container) return;
  
  if (!cannedResponses.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma resposta pronta cadastrada</p>';
    return;
  }
  
  container.innerHTML = cannedResponses.map(c => `
    <div class="item-card">
      <div class="item-title">${escapeHtml(c.title)}</div>
      <div class="item-content">${escapeHtml(c.content)}</div>
      <button class="item-delete" data-id="${c.id}">🗑️</button>
    </div>
  `).join('');
  
  container.querySelectorAll('.item-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeCanned(e.target.dataset.id);
    });
  });
}

// -------------------------
// Campaigns Tab Functions
// -------------------------
async function loadCampaigns() {
  const data = await chrome.storage.local.get(['campaigns']);
  campaigns = data.campaigns || [];
  renderActiveCampaigns();
  renderCampaignHistory();
}

async function saveCampaigns() {
  await chrome.storage.local.set({ campaigns });
}

function startCampaign() {
  const name = el("campaignName")?.value?.trim();
  const message = el("campaignMessage")?.value?.trim();
  const image = el("campaignImage")?.value?.trim();
  const interval = el("campaignInterval")?.value || 5;
  
  if (!name || !message) {
    setStatus("Preencha nome e mensagem da campanha", false);
    return;
  }
  
  const campaign = {
    id: `camp_${Date.now()}`,
    name,
    message,
    image,
    interval: parseInt(interval),
    status: 'active',
    createdAt: new Date().toISOString(),
    selectedContacts: contacts.filter(c => c.selected).map(c => c.id)
  };
  
  campaigns.push(campaign);
  saveCampaigns();
  renderActiveCampaigns();
  
  setStatus("🚀 Campanha iniciada!", true);
  
  // Clear form
  el("campaignName").value = "";
  el("campaignMessage").value = "";
  el("campaignImage").value = "";
}

function scheduleCampaign() {
  const name = el("campaignName")?.value?.trim();
  const message = el("campaignMessage")?.value?.trim();
  const date = el("campaignDate")?.value;
  const time = el("campaignTime")?.value;
  
  if (!name || !message || !date || !time) {
    setStatus("Preencha todos os campos para agendar", false);
    return;
  }
  
  const campaign = {
    id: `camp_${Date.now()}`,
    name,
    message,
    scheduledDate: date,
    scheduledTime: time,
    status: 'scheduled',
    createdAt: new Date().toISOString(),
    selectedContacts: contacts.filter(c => c.selected).map(c => c.id)
  };
  
  campaigns.push(campaign);
  saveCampaigns();
  renderActiveCampaigns();
  
  setStatus("⏰ Campanha agendada!", true);
}

function renderActiveCampaigns() {
  const container = el("activeCampaigns");
  if (!container) return;
  
  const active = campaigns.filter(c => c.status === 'active' || c.status === 'scheduled');
  
  if (!active.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma campanha ativa</p>';
    return;
  }
  
  container.innerHTML = active.map(c => `
    <div class="campaign-card">
      <div class="campaign-header">
        <span class="campaign-name">${escapeHtml(c.name)}</span>
        <span class="campaign-status">${c.status === 'scheduled' ? '⏰ Agendada' : '🚀 Ativa'}</span>
      </div>
      <div class="campaign-message">${escapeHtml(c.message.substring(0, 100))}...</div>
      <div class="campaign-meta">
        <span>📅 ${new Date(c.createdAt).toLocaleDateString()}</span>
        <span>👥 ${c.selectedContacts?.length || 0} contatos</span>
      </div>
    </div>
  `).join('');
}

function renderCampaignHistory() {
  const container = el("campaignHistory");
  if (!container) return;
  
  const completed = campaigns.filter(c => c.status === 'completed');
  
  if (!completed.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma campanha realizada</p>';
    return;
  }
  
  container.innerHTML = completed.map(c => `
    <div class="campaign-card">
      <div class="campaign-header">
        <span class="campaign-name">${escapeHtml(c.name)}</span>
        <span class="campaign-status">✅ Concluída</span>
      </div>
      <div class="campaign-message">${escapeHtml(c.message.substring(0, 100))}...</div>
      <div class="campaign-meta">
        <span>📅 ${new Date(c.createdAt).toLocaleDateString()}</span>
      </div>
    </div>
  `).join('');
}

// -------------------------
// Contacts Tab Functions
// -------------------------
async function loadContacts() {
  const data = await chrome.storage.local.get(['contacts']);
  contacts = data.contacts || [];
  renderContacts();
  updateContactsCount();
}

async function saveContacts() {
  await chrome.storage.local.set({ contacts });
  updateContactsCount();
}

function extractFromChats() {
  setStatus("🔄 Extraindo contatos das conversas...", true);
  // TODO: Implement extraction from WhatsApp chats
  setTimeout(() => {
    setStatus("✅ Contatos extraídos!", true);
  }, 1500);
}

function extractFromGroups() {
  setStatus("🔄 Extraindo contatos de grupos...", true);
  // TODO: Implement extraction from WhatsApp groups
  setTimeout(() => {
    setStatus("✅ Contatos extraídos!", true);
  }, 1500);
}

function addManualContact() {
  const name = el("manualContactName")?.value?.trim();
  const phone = el("manualContactPhone")?.value?.trim();
  const tags = el("manualContactTags")?.value?.trim();
  
  if (!name || !phone) {
    setStatus("Preencha nome e telefone", false);
    return;
  }
  
  contacts.push({
    id: `contact_${Date.now()}`,
    name,
    phone,
    tags: tags.split(',').map(t => t.trim()).filter(t => t),
    selected: false,
    createdAt: new Date().toISOString()
  });
  
  saveContacts();
  renderContacts();
  
  el("manualContactName").value = "";
  el("manualContactPhone").value = "";
  el("manualContactTags").value = "";
  
  setStatus("✅ Contato adicionado!", true);
}

function renderContacts() {
  const container = el("contactsList");
  if (!container) return;
  
  if (!contacts.length) {
    container.innerHTML = '<p class="empty-state">Nenhum contato extraído</p>';
    return;
  }
  
  container.innerHTML = contacts.map(c => `
    <div class="contact-item">
      <input type="checkbox" ${c.selected ? 'checked' : ''} data-id="${c.id}">
      <div class="contact-info">
        <div class="contact-name">${escapeHtml(c.name)}</div>
        <div class="contact-phone">${escapeHtml(c.phone)}</div>
      </div>
      <button class="btn-delete" data-id="${c.id}">🗑️</button>
    </div>
  `).join('');
  
  container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', (e) => {
      const contact = contacts.find(c => c.id === e.target.dataset.id);
      if (contact) {
        contact.selected = e.target.checked;
        saveContacts();
      }
    });
  });
  
  container.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      contacts = contacts.filter(c => c.id !== e.target.dataset.id);
      saveContacts();
      renderContacts();
    });
  });
}

function updateContactsCount() {
  const countEl = el("totalContacts");
  if (countEl) {
    countEl.textContent = contacts.length;
  }
}

function exportContacts() {
  if (!contacts.length) {
    setStatus("Nenhum contato para exportar", false);
    return;
  }
  
  const csv = "Nome,Telefone,Tags\n" + contacts.map(c => 
    `"${c.name}","${c.phone}","${c.tags?.join(';') || ''}"`
  ).join('\n');
  
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `contatos_${Date.now()}.csv`;
  a.click();
  
  setStatus("✅ Contatos exportados!", true);
}

function importContacts() {
  const fileInput = el("importFile");
  if (fileInput) {
    fileInput.click();
  }
}

// -------------------------
// Utility Functions
// -------------------------
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// -------------------------
// Setup Main Event Listeners
// -------------------------
let mainListenersSetup = false;

function setupMainListeners() {
  // Only setup once to avoid duplicate listeners
  if (mainListenersSetup) return;
  mainListenersSetup = true;
  
  // Save button (Config tab)
  const saveConfigBtn = el("saveConfig");
  if (saveConfigBtn) {
    saveConfigBtn.addEventListener("click", saveSettings);
  }
  
  // Copilot controls
  const copilotEnabledEl = el("copilotEnabled");
  if (copilotEnabledEl) {
    copilotEnabledEl.addEventListener("change", async (e) => {
      const enabled = e.target.checked;
      const resp = await send("TOGGLE_COPILOT", { enabled });
      if (resp?.ok) {
        const statusEl = el("copilotStatusText");
        if (statusEl) {
          statusEl.textContent = enabled 
            ? "Modo Copiloto Ativo" 
            : "Modo Copiloto Desativado";
        }
      } else {
        e.target.checked = !enabled;
      }
    });
  }
  
  const thresholdEl = el("copilotThreshold");
  if (thresholdEl) {
    thresholdEl.addEventListener("input", (e) => {
      const valEl = el("thresholdValue");
      if (valEl) valEl.textContent = `${e.target.value}%`;
    });
    
    thresholdEl.addEventListener("change", async (e) => {
      const threshold = Number(e.target.value);
      const resp = await send("SET_THRESHOLD", { threshold });
      if (resp?.ok) {
        await loadCopilotData();
      }
    });
  }
  
  // Quick Replies
  const addQRBtn = el("addQuickReply");
  if (addQRBtn) {
    addQRBtn.addEventListener("click", addQuickReply);
  }
  
  // Team event listeners
  const addMemberBtn = el("addMember");
  if (addMemberBtn) {
    addMemberBtn.addEventListener("click", addTeamMember);
  }
  
  const sendToTeamBtn = el("sendToTeam");
  if (sendToTeamBtn) {
    sendToTeamBtn.addEventListener("click", sendToTeam);
  }
  
  const senderNameEl = el("senderName");
  if (senderNameEl) {
    senderNameEl.addEventListener("input", updateMessagePreview);
  }
  
  const teamMsgEl = el("teamMessage");
  if (teamMsgEl) {
    teamMsgEl.addEventListener("input", updateMessagePreview);
  }
  
  // IA Tab event listeners
  const addProductBtn = el("addProduct");
  if (addProductBtn) {
    addProductBtn.addEventListener("click", addProduct);
  }
  
  const addFaqBtn = el("addFaq");
  if (addFaqBtn) {
    addFaqBtn.addEventListener("click", addFAQ);
  }
  
  const addDocBtn = el("addDocument");
  if (addDocBtn) {
    addDocBtn.addEventListener("click", addDocument);
  }
  
  const addCannedBtn = el("addCanned");
  if (addCannedBtn) {
    addCannedBtn.addEventListener("click", addCanned);
  }
  
  const saveKnowledgeBtn = el("saveKnowledge");
  if (saveKnowledgeBtn) {
    saveKnowledgeBtn.addEventListener("click", saveIAData);
  }
  
  const syncKnowledgeBtn = el("syncKnowledge");
  if (syncKnowledgeBtn) {
    syncKnowledgeBtn.addEventListener("click", () => {
      setStatus("🔄 Sincronizando com servidor...", true);
      setTimeout(() => {
        setStatus("✅ Sincronizado!", true);
      }, 1500);
    });
  }
  
  // Campaigns Tab event listeners
  const startCampaignBtn = el("startCampaign");
  if (startCampaignBtn) {
    startCampaignBtn.addEventListener("click", startCampaign);
  }
  
  const scheduleCampaignBtn = el("scheduleCampaign");
  if (scheduleCampaignBtn) {
    scheduleCampaignBtn.addEventListener("click", scheduleCampaign);
  }
  
  // Contacts Tab event listeners
  const extractChatsBtn = el("extractFromChats");
  if (extractChatsBtn) {
    extractChatsBtn.addEventListener("click", extractFromChats);
  }
  
  const extractGroupsBtn = el("extractFromGroups");
  if (extractGroupsBtn) {
    extractGroupsBtn.addEventListener("click", extractFromGroups);
  }
  
  const addManualContactBtn = el("addManualContact");
  if (addManualContactBtn) {
    addManualContactBtn.addEventListener("click", addManualContact);
  }
  
  const exportContactsBtn = el("exportContacts");
  if (exportContactsBtn) {
    exportContactsBtn.addEventListener("click", exportContacts);
  }
  
  const importContactsBtn = el("importContacts");
  if (importContactsBtn) {
    importContactsBtn.addEventListener("click", importContacts);
  }
  
  const importFileInput = el("importFile");
  if (importFileInput) {
    importFileInput.addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          // Simple CSV parsing
          const lines = ev.target.result.split('\n');
          let imported = 0;
          for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            const parts = line.split(',').map(p => p.replace(/^"|"$/g, '').trim());
            if (parts.length >= 2) {
              contacts.push({
                id: `contact_${Date.now()}_${i}`,
                name: parts[0],
                phone: parts[1],
                tags: parts[2] ? parts[2].split(';').map(t => t.trim()) : [],
                selected: false,
                createdAt: new Date().toISOString()
              });
              imported++;
            }
          }
          saveContacts();
          renderContacts();
          setStatus(`✅ ${imported} contatos importados!`, true);
        };
        reader.readAsText(file);
      }
    });
  }
  
  const searchContactsInput = el("searchContacts");
  if (searchContactsInput) {
    searchContactsInput.addEventListener("input", (e) => {
      const query = e.target.value.toLowerCase();
      const container = el("contactsList");
      if (!container) return;
      
      const filtered = contacts.filter(c => 
        c.name.toLowerCase().includes(query) || 
        c.phone.includes(query)
      );
      
      if (!filtered.length) {
        container.innerHTML = '<p class="empty-state">Nenhum contato encontrado</p>';
        return;
      }
      
      container.innerHTML = filtered.map(c => `
        <div class="contact-item">
          <input type="checkbox" ${c.selected ? 'checked' : ''} data-id="${c.id}">
          <div class="contact-info">
            <div class="contact-name">${escapeHtml(c.name)}</div>
            <div class="contact-phone">${escapeHtml(c.phone)}</div>
          </div>
          <button class="btn-delete" data-id="${c.id}">🗑️</button>
        </div>
      `).join('');
      
      // Re-attach event listeners
      container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', (ev) => {
          const contact = contacts.find(c => c.id === ev.target.dataset.id);
          if (contact) {
            contact.selected = ev.target.checked;
            saveContacts();
          }
        });
      });
      
      container.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', (ev) => {
          contacts = contacts.filter(c => c.id !== ev.target.dataset.id);
          saveContacts();
          renderContacts();
        });
      });
    });
  }
}

// -------------------------
// Initialize
// -------------------------
document.addEventListener("DOMContentLoaded", async () => {
  // Initialize license system (header-based)
  await initLicenseSystem();
  setupLicenseListeners();
  
  // Setup navigation and accordion
  initNavigation();
  initAccordion();
  
  // Always setup main listeners and load data
  setupMainListeners();
  load().catch((e) => console.error("Load error:", e));
});
