# Sistema Híbrido de Sincronização de Conhecimento/Treinamento de IA

## Visão Geral

O sistema agora implementa sincronização completa entre os dados de treinamento de IA salvos localmente no navegador (Chrome Extension) e o backend PHP, tornando o sistema verdadeiramente híbrido.

## Características Principais

### ✅ Sincronização Automática
- **Intervalo**: A cada 5 minutos
- **Merge Inteligente**: Combina dados locais e do servidor sem duplicatas
- **Offline-First**: Funciona sem conexão, sincroniza quando possível

### ✅ Dados Sincronizados
1. **Informações do Negócio** (nome, descrição, segmento, horário)
2. **Políticas** (pagamento, entrega, trocas)
3. **Produtos** (catálogo completo)
4. **FAQ** (perguntas e respostas)
5. **Respostas Prontas** (canned replies)
6. **Documentos**
7. **Tom de Voz** (estilo, emojis, saudação, despedida)

### ✅ Endpoints da API

#### Base URL
```
{backend_url}/api/knowledge.php
```

#### Headers
```
X-Alabama-Proxy-Key: {backendSecret}
X-Workspace-Key: {memoryWorkspaceKey ou "default"}
```

#### GET - Buscar Conhecimento
```http
GET /api/knowledge.php
```

**Resposta:**
```json
{
  "ok": true,
  "knowledge": {
    "business": {...},
    "policies": {...},
    "products": [...],
    "faq": [...],
    "cannedReplies": [...],
    "documents": [...],
    "tone": {...}
  },
  "lastUpdated": "2024-12-16 18:30:00",
  "source": "server"
}
```

#### POST - Salvar Conhecimento
```http
POST /api/knowledge.php
Content-Type: application/json

{
  "action": "save",
  "knowledge": {
    "business": {...},
    "policies": {...},
    ...
  }
}
```

**Resposta:**
```json
{
  "ok": true,
  "message": "Conhecimento salvo com sucesso",
  "savedAt": "2024-12-16 18:30:00"
}
```

#### POST - Merge/Sync
```http
POST /api/knowledge.php
Content-Type: application/json

{
  "action": "sync",
  "knowledge": {
    "business": {...},
    "policies": {...},
    ...
  }
}
```

**Resposta:**
```json
{
  "ok": true,
  "knowledge": {
    "business": {...},
    "policies": {...},
    ...
  },
  "mergedAt": "2024-12-16 18:30:00",
  "stats": {
    "products": 10,
    "faq": 5,
    "cannedReplies": 3,
    "documents": 2
  }
}
```

## Tabela do Banco de Dados

### `ai_knowledge`
```sql
CREATE TABLE ai_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workspace_key VARCHAR(255) NOT NULL DEFAULT 'default',
    knowledge_type VARCHAR(50) NOT NULL,
    knowledge_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_workspace_type (workspace_key, knowledge_type)
);
```

**Tipos de Conhecimento (`knowledge_type`):**
- `business`
- `policies`
- `products`
- `faq`
- `cannedReplies`
- `documents`
- `tone`

## Como Usar

### Na Extensão Chrome

1. **Salvar Conhecimento**
   - Clique em "💾 Salvar Conhecimento"
   - O sistema salva localmente E sincroniza com o servidor automaticamente
   - Mensagem de sucesso: "✅ Conhecimento salvo e sincronizado!"

2. **Sincronizar Manualmente**
   - Clique em "🔄 Sincronizar com Servidor"
   - O sistema faz merge dos dados locais com o servidor
   - Indicador mostra status: "Última sync: 18:30:00"

3. **Indicador de Status**
   - 🔄 Cinza: Aguardando sincronização
   - 🔄 Animado: Sincronizando
   - ✅ Verde: Sincronizado com sucesso
   - ❌ Vermelho: Erro na sincronização

### Lógica de Merge

#### Campos de Texto (Business, Policies, Tone)
- **Preferência**: Servidor > Local
- Se o campo do servidor estiver preenchido, usa o do servidor
- Senão, usa o valor local

#### Arrays (Products, FAQ, Canned Replies, Documents)
- **Merge por Chave Única**:
  - Products: por `name`
  - FAQ: por `question`
  - Canned Replies: por `triggers` (array serializado)
  - Documents: por `name`
- **Sem Duplicatas**: Itens do servidor têm prioridade, depois adiciona itens locais únicos

## Configuração

### Requisitos
- PHP >= 8.1
- MySQL/MariaDB com suporte a JSON
- Backend URL configurado na extensão
- Backend Secret (opcional, mas recomendado)

### Variáveis de Ambiente
```env
# Configuração de CORS (opcional)
ALABAMA_CORS_ORIGINS=https://web.whatsapp.com

# Workspace Key para multi-tenancy (opcional)
ALABAMA_MEMORY_WORKSPACE_KEY=default
```

## Segurança

### Autenticação
- Header `X-Alabama-Proxy-Key`: Valida requisições da extensão
- Header `X-Workspace-Key`: Isola dados por workspace

### CORS
- Configurado para aceitar de qualquer origem (`*`)
- Em produção, considere restringir usando `ALABAMA_CORS_ORIGINS`

### Validação de Dados
- JSON encoding/decoding com validação
- Prepared statements para prevenir SQL injection
- Charset UTF-8 em todas as operações

## Troubleshooting

### Sincronização Não Funciona
1. Verificar se `backendUrl` está configurado na extensão
2. Verificar se o servidor está acessível
3. Verificar logs do console: `debugLog` mostra status de sync

### Dados Não Aparecem
1. Verificar se a tabela `ai_knowledge` existe
2. Verificar se o `workspace_key` está correto
3. Verificar permissões do banco de dados

### Merge Criando Duplicatas
1. Verificar se os itens têm a chave única preenchida
2. Produtos devem ter `name`
3. FAQ deve ter `question`
4. Respostas devem ter `triggers`

## Testes Manuais

### Teste 1: Salvar e Recuperar
```bash
# 1. Adicionar produto na extensão
# 2. Clicar em "Salvar"
# 3. Abrir em outro navegador/dispositivo
# 4. Verificar que o produto aparece após sync
```

### Teste 2: Merge de Dados
```bash
# 1. Adicionar produto "A" no navegador 1
# 2. Adicionar produto "B" no navegador 2
# 3. Sincronizar ambos
# 4. Verificar que ambos têm produtos A e B
```

### Teste 3: Offline
```bash
# 1. Desconectar da internet
# 2. Adicionar dados na extensão
# 3. Clicar em "Salvar" (deve salvar localmente)
# 4. Reconectar
# 5. Sincronizar (deve enviar para servidor)
```

## Monitoramento

### Logs de Debug
A extensão registra logs no console do navegador:
```
✅ Conhecimento carregado do servidor
✅ Conhecimento salvo no servidor
✅ Conhecimento sincronizado com servidor
📊 Stats: {products: 10, faq: 5, ...}
⚠️ Falha ao buscar conhecimento do servidor: HTTP 500
```

### Banco de Dados
```sql
-- Ver todos os conhecimentos
SELECT workspace_key, knowledge_type, updated_at 
FROM ai_knowledge 
ORDER BY updated_at DESC;

-- Ver um tipo específico
SELECT knowledge_data 
FROM ai_knowledge 
WHERE workspace_key = 'default' 
  AND knowledge_type = 'products';
```

## Próximas Melhorias

- [ ] Versionamento de conhecimento (histórico)
- [ ] Resolução de conflitos manual
- [ ] Import/Export em massa via API
- [ ] Webhooks para notificações de mudanças
- [ ] Cache no servidor (Redis)
- [ ] Compressão de dados grandes

## Suporte

Para problemas ou dúvidas:
1. Verificar logs do console (`F12` no Chrome)
2. Verificar logs do servidor PHP
3. Abrir issue no repositório
