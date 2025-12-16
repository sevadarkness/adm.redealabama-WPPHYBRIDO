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

const el = (id) => document.getElementById(id);

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

function normalizePath(p, fallback) {
  const s = String(p || "").trim();
  if (!s) return fallback;
  return s.startsWith("/") ? s : `/${s}`;
}

async function load() {
  const resp = await send("GET_SETTINGS", {});
  if (!resp?.ok) throw new Error(resp?.error || "Falha ao carregar settings.");

  const st = resp?.settings || {};

  el("provider").value = st.provider || "openai";
  el("openaiApiKey").value = st.openaiApiKey || "";
  el("openaiModel").value = st.openaiModel || "gpt-4o-mini";
  el("backendUrl").value = st.backendUrl || "";
  el("backendSecret").value = st.backendSecret || "";
  el("backendAiPath").value = st.backendAiPath || "/ai/chat.php";
  el("backendCampaignPath").value = st.backendCampaignPath || "/api/campaigns.php";

  el("memoryServerUrl").value = st.memoryServerUrl || "";
  el("memoryWorkspaceKey").value = st.memoryWorkspaceKey || "";
  el("memorySyncEnabled").checked = Boolean(st.memorySyncEnabled);

  el("temperature").value = typeof st.temperature === "number" ? st.temperature : 0.7;
  el("maxTokens").value = typeof st.maxTokens === "number" ? st.maxTokens : 450;

  el("persona").value = st.persona || "";
  el("businessContext").value = st.businessContext || "";
  el("autoSuggest").checked = Boolean(st.autoSuggest);
  el("autoMemory").checked = Boolean(st.autoMemory);
}

el("toggleKey").addEventListener("click", () => {
  const i = el("openaiApiKey");
  i.type = i.type === "password" ? "text" : "password";
});

// Copilot Mode Functions
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

el("save").addEventListener("click", async () => {
  setStatus("Salvando…", true);

  const settings = {
    provider: el("provider").value,
    openaiApiKey: el("openaiApiKey").value,
    openaiModel: el("openaiModel").value,

    backendUrl: el("backendUrl").value,
    backendSecret: el("backendSecret").value,
    backendAiPath: normalizePath(el("backendAiPath").value, "/ai/chat.php"),
    backendCampaignPath: normalizePath(el("backendCampaignPath").value, "/api/campaigns.php"),

    memoryServerUrl: el("memoryServerUrl").value,
    memoryWorkspaceKey: el("memoryWorkspaceKey").value,
    memorySyncEnabled: el("memorySyncEnabled").checked,

    temperature: Number(el("temperature").value || 0.7),
    maxTokens: Number(el("maxTokens").value || 450),

    persona: el("persona").value,
    businessContext: el("businessContext").value,
    autoSuggest: el("autoSuggest").checked,
    autoMemory: el("autoMemory").checked,
  };

  const resp = await send("SAVE_SETTINGS", { settings });
  if (resp?.ok) setStatus("Salvo ✅", true);
  else setStatus(resp?.error || "Falha ao salvar", false);
});

load().catch((e) => setStatus(String(e?.message || e), false));
loadCopilotData().catch(console.error);
