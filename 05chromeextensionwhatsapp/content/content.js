// content/content.js
// WhatsHybrid Lite (Alabama) — DOM Campaign ENABLED
// Stealth + Persistência + IA Orquestradora + Anti-Ban (2025)

(() => {
  'use strict';

  /* ================= META ================= */

  const EXT = { id: 'whl-root', name: 'WhatsHybrid Lite', version: '0.3.0' };

  /* ================= CONFIG ================= */

  const CONFIG = {
    MAX_PER_HOUR_BASE: 30,
    HUMAN_HOURS_START: 7,
    HUMAN_HOURS_END: 22,
    LONG_PAUSE_CHANCE: 0.05,
    LONG_PAUSE_MIN: 30_000,
    LONG_PAUSE_MAX: 120_000
  };

  /* ================= UTILS ================= */

  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  const safe = (v) => v == null ? '' : String(v);
  const now = () => Date.now();

  const rand = (min, max) =>
    Math.floor(Math.random() * (max - min + 1)) + min;

  const humanHour = () => {
    const h = new Date().getHours();
    return h >= CONFIG.HUMAN_HOURS_START && h < CONFIG.HUMAN_HOURS_END;
  };

  /* ================= STORAGE ================= */

  const store = {
    async get(k) {
      return new Promise(r => chrome.storage.local.get([k], v => r(v[k])));
    },
    async set(k, v) {
      return new Promise(r => chrome.storage.local.set({ [k]: v }, r));
    },
    async del(k) {
      return new Promise(r => chrome.storage.local.remove([k], r));
    }
  };

  /* ================= RATE LIMIT ================= */

  const sentTimestamps = [];

  function canSendNow() {
    const hourAgo = now() - 3600_000;
    while (sentTimestamps.length && sentTimestamps[0] < hourAgo) {
      sentTimestamps.shift();
    }
    return sentTimestamps.length < CONFIG.MAX_PER_HOUR_BASE;
  }

  function markSent() {
    sentTimestamps.push(now());
  }

  async function maybeLongPause() {
    if (Math.random() < CONFIG.LONG_PAUSE_CHANCE) {
      const p = rand(CONFIG.LONG_PAUSE_MIN, CONFIG.LONG_PAUSE_MAX);
      await sleep(p);
    }
  }

  /* ================= WHATSAPP DOM ================= */

  const WA = {
    composer: 'footer [contenteditable="true"]',
    send: 'footer button span[data-icon="send"]',
    search: '#pane-side [contenteditable="true"]',
    rows: '#pane-side [role="row"]'
  };

  const qs = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => [...r.querySelectorAll(s)];

  async function openChat(number) {
    const box = qs(WA.search);
    if (!box) throw new Error('Busca não encontrada');

    box.focus();
    document.execCommand('selectAll');
    document.execCommand('insertText', false, number);
    box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    await sleep(1200);

    const rows = qsa(WA.rows).filter(r => r.innerText.includes(number.slice(-6)));
    if (!rows.length) throw new Error('Contato não encontrado');
    rows[0].click();
    await sleep(600);
  }

  async function insert(text) {
    const box = qs(WA.composer);
    if (!box) throw new Error('Composer não encontrado');
    box.focus();
    document.execCommand('selectAll');
    document.execCommand('insertText', false, safe(text));
    box.dispatchEvent(new InputEvent('input', { bubbles: true }));
  }

  async function send() {
    const btn = qs(WA.send)?.closest('button');
    if (!btn) throw new Error('Botão enviar não encontrado');
    btn.click();
    markSent();
  }

  /* ================= IA ORQUESTRADORA ================= */

  async function orchestrateMessage(baseMsg, contact) {
    try {
      const resp = await chrome.runtime.sendMessage({
        type: 'AI_ORCHESTRATE',
        payload: {
          base: baseMsg,
          contact
        }
      });
      if (resp?.ok && resp.text) return resp.text;
    } catch {}
    return baseMsg
      .replace('{{nome}}', contact.name || '')
      .replace('{{numero}}', contact.number || '');
  }

  /* ================= CAMPANHA DOM ================= */

  const campRun = {
    running: false,
    paused: false,
    abort: false,
    index: 0,
    contacts: [],
    message: ''
  };

  async function saveState() {
    await store.set('whl_campaign_active', {
      ...campRun,
      savedAt: new Date().toISOString()
    });
  }

  async function loadState() {
    return await store.get('whl_campaign_active');
  }

  async function clearState() {
    await store.del('whl_campaign_active');
  }

  async function executeDomCampaign(contacts, message) {
    campRun.running = true;
    campRun.paused = false;
    campRun.abort = false;
    campRun.contacts = contacts;
    campRun.message = message;

    await saveState();

    for (; campRun.index < contacts.length; campRun.index++) {
      if (campRun.abort) break;

      while (campRun.paused) await sleep(300);

      if (!humanHour()) {
        campRun.paused = true;
        await saveState();
        continue;
      }

      if (!canSendNow()) {
        await sleep(5 * 60_000);
        continue;
      }

      const c = contacts[campRun.index];

      try {
        await maybeLongPause();
        await openChat(c.number.replace(/\D/g,''));
        const finalMsg = await orchestrateMessage(message, c);
        await insert(finalMsg);
        await sleep(rand(200, 600));
        await send();

        chrome.runtime.sendMessage({
          type: 'CAMPAIGN_EVENT',
          event: { type: 'sent', contact: c.number }
        });

      } catch (e) {
        chrome.runtime.sendMessage({
          type: 'CAMPAIGN_EVENT',
          event: { type: 'error', contact: c.number, error: e.message }
        });
      }

      await saveState();
      await sleep(rand(8_000, 18_000));
    }

    campRun.running = false;
    await clearState();
  }

  /* ================= UI ================= */

  function mount() {
    if (document.getElementById(EXT.id)) return;

    const host = document.createElement('div');
    host.id = EXT.id;
    host.innerHTML = `
      <div style="position:fixed;bottom:24px;right:24px;
                  width:52px;height:52px;border-radius:16px;
                  background:linear-gradient(135deg,#8b5cf6,#3b82f6);
                  display:flex;align-items:center;justify-content:center;
                  color:white;cursor:pointer;z-index:999999">🤖</div>
    `;
    document.body.appendChild(host);
  }

  async function boot() {
    const paused = await loadState();
    if (paused && paused.running) {
      Object.assign(campRun, paused);
      executeDomCampaign(paused.contacts, paused.message);
    }
    mount();
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot)
    : boot();

})();
