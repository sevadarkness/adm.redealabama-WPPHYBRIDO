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
  return apiKey && apiKey.startsWith("sk-") && apiKey.length > 20;
}

// ═══════════════════════════════════════════════════════════════════
// FUNÇÕES DE NAVEGAÇÃO ENTRE TELAS
// ═══════════════════════════════════════════════════════════════════

async function showScreen(screenId) {
  // Esconder todas as telas
  document.querySelectorAll(".screen").forEach(s => s.classList.remove("active"));
  
  // Mostrar tela específica
  const screen = document.getElementById(screenId);
  if (screen) {
    screen.classList.add("active");
  }
  
  // Mostrar/esconder botão de reconfig
  const btnReconfig = document.getElementById("btnReconfig");
  if (btnReconfig) {
    if (screenId === "screenMain") {
      btnReconfig.classList.remove("hidden");
      // Setup event listeners for main screen
      if (typeof setupMainListeners === 'function') {
        setupMainListeners();
      }
      // Carregar configurações quando entrar na tela principal
      if (typeof load === 'function') {
        await load().catch((e) => {
          if (typeof setStatus === 'function') {
            setStatus(String(e?.message || e), false);
          }
        });
      }
    } else {
      btnReconfig.classList.add("hidden");
    }
  }
}

// ═══════════════════════════════════════════════════════════════════
// INICIALIZAÇÃO - VERIFICAR ESTADO
// ═══════════════════════════════════════════════════════════════════

async function initLicenseSystem() {
  const storage = await chrome.storage.local.get(["licenseValid", "openaiApiKey"]);
  
  if (!storage.licenseValid) {
    // Estado 1: Sem licença
    showScreen("screenLicense");
  } else if (!storage.openaiApiKey) {
    // Estado 2: Licença OK, sem API Key
    showScreen("screenApiKey");
  } else {
    // Estado 3: Tudo configurado
    showScreen("screenMain");
  }
}

// ═══════════════════════════════════════════════════════════════════
// EVENT LISTENERS - LICENÇA
// ═══════════════════════════════════════════════════════════════════

function setupLicenseListeners() {
  // Validar Licença
  const btnValidate = document.getElementById("btnValidateLicense");
  if (btnValidate) {
    btnValidate.addEventListener("click", async () => {
      const licenseInput = document.getElementById("licenseKey");
      const errorEl = document.getElementById("licenseError");
      const inputValue = licenseInput.value.trim();
      
      if (validateLicense(inputValue)) {
        // Licença válida
        await chrome.storage.local.set({ licenseValid: true });
        errorEl.classList.add("hidden");
        await showScreen("screenApiKey");
      } else {
        // Licença inválida
        errorEl.classList.remove("hidden");
        licenseInput.classList.add("shake");
        setTimeout(() => {
          licenseInput.classList.remove("shake");
        }, 500);
      }
    });
  }

  // Salvar API Key
  const btnSaveApi = document.getElementById("btnSaveApiKey");
  if (btnSaveApi) {
    btnSaveApi.addEventListener("click", async () => {
      const apiKeyInput = document.getElementById("openaiApiKey");
      const errorEl = document.getElementById("apiKeyError");
      const apiKey = apiKeyInput.value.trim();
      
      if (validateApiKey(apiKey)) {
        // API Key válida
        await chrome.storage.local.set({ openaiApiKey: apiKey });
        errorEl.classList.add("hidden");
        await showScreen("screenMain");
        
        // Mostrar status de sucesso se a função existir
        if (typeof setStatus === 'function') {
          setStatus("✅ API Key salva com sucesso!", true);
        }
      } else {
        // API Key inválida
        errorEl.classList.remove("hidden");
        apiKeyInput.classList.add("shake");
        setTimeout(() => {
          apiKeyInput.classList.remove("shake");
        }, 500);
      }
    });
  }

  // Toggle mostrar/ocultar senha - Licença
  const toggleLicense = document.getElementById("toggleLicenseKey");
  if (toggleLicense) {
    toggleLicense.addEventListener("click", () => {
      const input = document.getElementById("licenseKey");
      input.type = input.type === "password" ? "text" : "password";
    });
  }

  // Toggle mostrar/ocultar senha - API Key
  const toggleApi = document.getElementById("toggleApiKey");
  if (toggleApi) {
    toggleApi.addEventListener("click", () => {
      const input = document.getElementById("openaiApiKey");
      input.type = input.type === "password" ? "text" : "password";
    });
  }

  // Enter para validar licença
  const licenseKeyInput = document.getElementById("licenseKey");
  if (licenseKeyInput) {
    licenseKeyInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        document.getElementById("btnValidateLicense").click();
      }
    });
  }

  // Enter para salvar API Key
  const apiKeyInput = document.getElementById("openaiApiKey");
  if (apiKeyInput) {
    apiKeyInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        document.getElementById("btnSaveApiKey").click();
      }
    });
  }

  // Botão Reconfigurar (🔐)
  const btnReconfig = document.getElementById("btnReconfig");
  if (btnReconfig) {
    btnReconfig.addEventListener("click", () => {
      const modal = document.getElementById("modalReconfig");
      modal.classList.remove("hidden");
      const reconfigInput = document.getElementById("reconfigLicenseKey");
      reconfigInput.value = "";
      reconfigInput.focus();
    });
  }

  // Cancelar Reconfig
  const btnCancel = document.getElementById("btnCancelReconfig");
  if (btnCancel) {
    btnCancel.addEventListener("click", () => {
      document.getElementById("modalReconfig").classList.add("hidden");
      document.getElementById("reconfigError").classList.add("hidden");
    });
  }

  // Confirmar Reconfig
  const btnConfirm = document.getElementById("btnConfirmReconfig");
  if (btnConfirm) {
    btnConfirm.addEventListener("click", async () => {
      const licenseInput = document.getElementById("reconfigLicenseKey");
      const errorEl = document.getElementById("reconfigError");
      const inputValue = licenseInput.value.trim();
      
      if (validateLicense(inputValue)) {
        document.getElementById("modalReconfig").classList.add("hidden");
        errorEl.classList.add("hidden");
        await showScreen("screenApiKey");
        
        // Preencher com API Key atual se existir
        const data = await chrome.storage.local.get(["openaiApiKey"]);
        if (data.openaiApiKey) {
          document.getElementById("openaiApiKey").value = data.openaiApiKey;
        }
      } else {
        errorEl.classList.remove("hidden");
        licenseInput.classList.add("shake");
        setTimeout(() => {
          licenseInput.classList.remove("shake");
        }, 500);
      }
    });
  }

  // Fechar modal clicando no backdrop
  const backdrop = document.querySelector(".modal-backdrop");
  if (backdrop) {
    backdrop.addEventListener("click", () => {
      document.getElementById("modalReconfig").classList.add("hidden");
      document.getElementById("reconfigError").classList.add("hidden");
    });
  }
  
  // Enter no modal de reconfig
  const reconfigInput = document.getElementById("reconfigLicenseKey");
  if (reconfigInput) {
    reconfigInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        document.getElementById("btnConfirmReconfig").click();
      }
    });
  }
}

// ═══════════════════════════════════════════════════════════════════

const el = (id) => document.getElementById(id);

// Global state
let quickReplies = [];
let teamMembers = [];

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
// Tab Navigation
// -------------------------
function setupTabNavigation() {
  document.querySelectorAll('.popup-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      // Remover active de todas as abas
      document.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.popup-tab-content').forEach(c => c.classList.remove('active'));
      
      // Ativar aba clicada
      tab.classList.add('active');
      const tabId = `tab-${tab.dataset.tab}`;
      document.getElementById(tabId).classList.add('active');
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
  renderQuickReplies(quickReplies);

  // Team
  el("senderName").value = st.senderName || "";
  teamMembers = st.teamMembers || [];
  renderTeamMembers(teamMembers);
  
  // Load copilot data
  await loadCopilotData();
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
    
    // Update confidence bar and percentage
    el("confidencePercent").textContent = `${Math.round(score)}%`;
    const fillEl = el("confidenceFill");
    fillEl.style.width = `${score}%`;
    fillEl.setAttribute("data-level", level.level);
    
    // Update confidence level
    el("confidenceLevel").innerHTML = `
      <span class="level-emoji">${level.emoji}</span>
      <span class="level-label">${level.label}</span>
      <span class="level-desc">${level.description}</span>
    `;
    
    // Update threshold indicator
    el("confidenceThreshold").style.left = `${config.copilot_threshold}%`;
    
    // Update copilot controls
    el("copilotEnabled").checked = config.copilot_enabled;
    el("copilotEnabled").disabled = score < config.copilot_threshold;
    el("copilotStatusText").textContent = config.copilot_enabled 
      ? "Modo Copiloto Ativo" 
      : "Modo Copiloto Desativado";
    
    el("copilotThreshold").value = config.copilot_threshold;
    el("thresholdValue").textContent = `${config.copilot_threshold}%`;
    
    // Update stats
    el("statGood").textContent = metrics.total_good;
    el("statBad").textContent = metrics.total_bad;
    el("statCorrections").textContent = metrics.total_corrections;
    el("statAutoSent").textContent = metrics.total_auto_sent;
    
    // Update goal
    const goalEl = el("copilotGoal");
    if (score >= config.copilot_threshold) {
      goalEl.innerHTML = "🎉 <strong>Meta Atingida!</strong> Modo Copiloto disponível";
      goalEl.classList.add("achieved");
    } else {
      goalEl.innerHTML = `🎯 <strong>Faltam <span id="pointsToGoal">${Math.round(points_to_threshold)}</span> pontos</strong> para o Modo Copiloto`;
      goalEl.classList.remove("achieved");
    }
  } catch (e) {
    console.error("Error loading copilot data:", e);
  }
}

// -------------------------
// Quick Replies Functions
// -------------------------
function addQuickReply() {
  const trigger = el("qrTrigger").value.trim().toLowerCase();
  const response = el("qrResponse").value.trim();
  
  if (!trigger || !response) {
    setStatus("Preencha gatilho e resposta", false);
    return;
  }
  
  const newReply = {
    id: `qr_${Date.now()}`,
    trigger,
    response,
    createdAt: new Date().toISOString()
  };
  
  quickReplies.push(newReply);
  renderQuickReplies(quickReplies);
  saveSettings();
  
  // Limpar form
  el("qrTrigger").value = "";
  el("qrResponse").value = "";
  setStatus("Mensagem rápida adicionada ✅", true);
}

function removeQuickReply(id) {
  quickReplies = quickReplies.filter(qr => qr.id !== id);
  renderQuickReplies(quickReplies);
  saveSettings();
  setStatus("Mensagem rápida removida", true);
}

function renderQuickReplies(replies) {
  const container = el("quickReplyList");
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
  const container = el("teamMembersContainer");
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
      setStatus(`✅ Enviado para ${selectedMembers.length} membro(s)!`, true);
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
function setupMainListeners() {
  // Save button
  el("save").addEventListener("click", saveSettings);
  
  // Copilot controls
  el("copilotEnabled").addEventListener("change", async (e) => {
    const enabled = e.target.checked;
    const resp = await send("TOGGLE_COPILOT", { enabled });
    if (resp?.ok) {
      el("copilotStatusText").textContent = enabled 
        ? "Modo Copiloto Ativo" 
        : "Modo Copiloto Desativado";
    } else {
      e.target.checked = !enabled;
    }
  });
  
  el("copilotThreshold").addEventListener("input", (e) => {
    el("thresholdValue").textContent = `${e.target.value}%`;
  });
  
  el("copilotThreshold").addEventListener("change", async (e) => {
    const threshold = Number(e.target.value);
    const resp = await send("SET_THRESHOLD", { threshold });
    if (resp?.ok) {
      el("confidenceThreshold").style.left = `${threshold}%`;
      await loadCopilotData();
    }
  });
  
  // Quick Replies
  el("addQuickReply").addEventListener("click", addQuickReply);
  
  // Team event listeners
  el("addMember").addEventListener("click", addTeamMember);
  el("selectAllMembers").addEventListener("click", selectAllMembers);
  el("clearSelection").addEventListener("click", clearSelection);
  el("sendToTeam").addEventListener("click", sendToTeam);
  el("senderName").addEventListener("input", updateMessagePreview);
  el("teamMessage").addEventListener("input", updateMessagePreview);
}

// -------------------------
// Initialize
// -------------------------
// Inicializar sistema de licença quando DOM estiver pronto
document.addEventListener("DOMContentLoaded", async () => {
  await initLicenseSystem();
  setupLicenseListeners();
  setupTabNavigation();
  
  // Só carregar configurações se já tiver licença e API key (screenMain)
  const storage = await chrome.storage.local.get(["licenseValid", "openaiApiKey"]);
  if (storage.licenseValid && storage.openaiApiKey) {
    setupMainListeners();
    load().catch((e) => setStatus(String(e?.message || e), false));
  }
});
