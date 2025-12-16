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
