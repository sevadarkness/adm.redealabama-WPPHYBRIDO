# WhatsHybrid Lite - Core Infrastructure

## 📋 Visão Geral

Este documento descreve a infraestrutura core implementada no PR #1 da extensão WhatsHybrid Lite.

## 🏗️ Sistemas Implementados

### 1. Sistema de Cache Inteligente (`SmartCache`)

Cache com TTL (Time To Live) para melhorar performance e reduzir chamadas repetidas.

**Uso:**
```javascript
// Cache global
const whlCache = new SmartCache();

// Armazenar com TTL customizado
whlCache.set('key', value, 5000); // 5 segundos

// Recuperar
const cached = whlCache.get('key'); // null se expirado

// Verificar existência
if (whlCache.has('key')) { ... }

// Limpar
whlCache.delete('key');
whlCache.clear(); // limpar tudo
```

**Caches atuais:**
- Settings: 5 segundos
- Respostas IA: 30 segundos
- Cleanup automático: a cada 2 minutos

### 2. Sistema de Seletores com Fallback (`WA_SELECTORS`)

Sistema robusto para encontrar elementos do WhatsApp Web, resistente a mudanças de DOM.

**Uso:**
```javascript
// Encontrar um elemento (retorna primeiro visível)
const composer = findElement('composer');

// Encontrar múltiplos elementos
const results = findElements('searchResults');

// Encontrar com retry
const button = await findElementWithRetry('sendButton', 10, 300);
```

**Seletores disponíveis:**
- `composer` - Caixa de mensagem
- `sendButton` - Botão enviar
- `attachButton` - Botão de anexo
- `fileInput` - Input de arquivo
- `searchBox` - Busca de chats
- `searchResults` - Resultados da busca
- `mediaDialog` - Preview de mídia
- `mediaSendButton` - Botão enviar mídia
- `mediaCaptionBox` - Campo de legenda
- `chatHeader` - Título do chat
- `messagesContainer` - Container de mensagens
- `errorIndicators` - Indicadores de erro

### 3. Sistema de Persistência de Campanhas

Salva estado de campanhas em `chrome.storage.local` para não perder progresso.

**Estrutura de dados:**
```javascript
const CampaignState = {
  id: 'camp_123456',
  status: 'running' | 'paused' | 'completed' | 'failed',
  createdAt: '2025-12-16T10:00:00Z',
  updatedAt: '2025-12-16T10:30:00Z',
  
  config: {
    message: 'Olá {{nome}}...',
    media: { name, type, base64 } | null,
    delayMin: 8,
    delayMax: 15,
    mode: 'dom' | 'api'
  },
  
  contacts: [
    { number: '+5511999999999', name: 'João', status: 'pending' },
    { number: '+5511988888888', name: 'Maria', status: 'sent' },
    { number: '+5511977777777', name: 'Pedro', status: 'failed', error: 'Chat não abriu' }
  ],
  
  progress: {
    total: 100,
    sent: 45,
    failed: 2,
    pending: 53,
    currentIndex: 47
  },
  
  errors: [
    { contact: '+5511977777777', error: 'Chat não abriu', at: '...' }
  ]
};
```

**Uso:**
```javascript
// Salvar estado
await saveCampaignState(state);

// Carregar estado
const state = await loadCampaignState();

// Limpar estado ativo
await clearCampaignState();

// Adicionar ao histórico
await saveCampaignToHistory(campaign);
```

### 4. Modo Stealth (Comportamento Humano)

Simula comportamento humano para evitar detecção.

**Configurações (`STEALTH_CONFIG`):**
```javascript
{
  typingDelayMin: 30,              // ms entre caracteres (mín)
  typingDelayMax: 120,             // ms entre caracteres (máx)
  beforeSendDelayMin: 200,         // ms antes de enviar
  beforeSendDelayMax: 800,
  delayVariation: 0.3,             // ±30% variação
  humanHoursStart: 7,              // Horário inicial (7h)
  humanHoursEnd: 22,               // Horário final (22h)
  maxMessagesPerHour: 30,          // Rate limit
  randomLongPauseChance: 0.05,     // 5% chance pausa longa
  randomLongPauseMin: 30000,       // 30s
  randomLongPauseMax: 120000,      // 2min
  thinkingWhileTypingChance: 0.02  // 2% pausa durante digitação
}
```

**Funções:**
```javascript
// Digitação humanizada
await humanType(element, text);

// Delay randomizado
const delay = randomizedDelay(baseDelayMs);

// Verificar horário humano
if (isHumanHour()) { ... }

// Verificar rate limit
if (checkRateLimit()) { ... }

// Registrar envio
recordMessageSent();

// Pausa aleatória longa
await maybeRandomLongPause();
```

**Integração:**
```javascript
// Inserir texto com stealth
await insertIntoComposer(text, useStealthMode = true);

// Enviar com stealth
await clickSend(useStealthMode = true);

// Enviar mídia com stealth
await attachMediaAndSend(payload, caption, useStealthMode = true);
```

## 📊 Constantes de Configuração

Todas constantes estão no objeto `CONFIG`:

```javascript
const CONFIG = {
  CAMPAIGN_HISTORY_LIMIT: 20,          // Máx campanhas no histórico
  AI_CACHE_TRANSCRIPT_LENGTH: 500,     // Tamanho transcript para cache
  SETTINGS_CACHE_TTL: 5000,            // TTL cache settings (5s)
  AI_CACHE_TTL: 30000,                 // TTL cache IA (30s)
  CACHE_CLEANUP_INTERVAL: 120000       // Intervalo cleanup (2min)
};
```

## 🚀 Modo DOM de Campanhas

**Status:** Desativado via feature flag (`DOM_MODE_ENABLED = false`)

O modo DOM está implementado com:
- ✅ Persistência de estado
- ✅ Modo stealth integrado
- ✅ Tratamento de erros robusto
- ✅ Verificação de horário humano
- ✅ Rate limiting
- ✅ Pausas aleatórias

**Para ativar:** Alterar `DOM_MODE_ENABLED = true` nos locais apropriados.

## 🔧 Manutenção

### Adicionar novo seletor

1. Adicionar no objeto `WA_SELECTORS`:
```javascript
const WA_SELECTORS = {
  // ...
  meuNovoSeletor: [
    'seletor-prioritário',
    'seletor-fallback-1',
    'seletor-fallback-2'
  ]
};
```

2. Usar com `findElement()`:
```javascript
const element = findElement('meuNovoSeletor');
```

### Adicionar nova constante

1. Adicionar no objeto `CONFIG` ou `STEALTH_CONFIG`:
```javascript
const CONFIG = {
  // ...
  MINHA_NOVA_CONSTANTE: 1000
};
```

2. Usar em vez de magic numbers:
```javascript
await sleep(CONFIG.MINHA_NOVA_CONSTANTE);
```

## 📝 Boas Práticas

1. **Sempre use seletores via `findElement()`** - não use `querySelector()` diretamente
2. **Use cache para dados custosos** - settings, respostas IA, etc.
3. **Extraia constantes** - nunca use magic numbers
4. **Use stealth mode em campanhas** - passar `useStealthMode = true`
5. **Salve estado frequentemente** - a cada iteração de campanha

## 🔒 Segurança

- ✅ 0 vulnerabilidades (CodeQL)
- ✅ Sanitização via `safeText()`
- ✅ Cache com TTL (sem vazamento de memória)
- ✅ Feature flags para funcionalidades sensíveis
- ✅ Rate limiting e horários humanos

## 📚 Referências

- Manifest: `manifest.json`
- Content Script: `content/content.js`
- Background: `background/serviceWorker.js`
