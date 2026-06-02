# Dashboard TI GLPI — Redesign TV 4K

**Data:** 2026-06-02  
**Arquivo alvo:** `dashboard_live.html`  
**Abordagem:** Redesign TV dedicado (opção B)

---

## Contexto

O dashboard GLPI já existe com 4 telas rotativas, dark mode NOC e Chart.js. Ele será exibido em uma **TV 4K** no departamento de TI, visível também para o departamento financeiro e outros. O objetivo é torná-lo legível à distância (~4-5m), com informações críticas sempre visíveis e hierarquia visual clara.

---

## Estrutura geral — Layout híbrido

O layout é dividido em duas zonas verticais:

```
┌─ HEADER compacto ─────────────────────────────────────────┐
├─ ZONA FIXA (~28% da altura) ──────────────────────────────┤
│  5 KPI cards sempre visíveis                              │
├─ ZONA ROTATIVA (~68% da altura) ──────────────────────────┤
│  4 telas alternadas a cada 45s                            │
├─ BARRA DE PROGRESSO (3px) ────────────────────────────────┤
```

### Header

- Logo + título "Dashboard TI — GLPI" + subtítulo
- Lado direito: data/hora da última atualização + indicador de status (ponto verde/laranja/vermelho)
- **Sem filtros** — TV não tem interação humana
- **Sem botões de controle** (pausar, atualizar manual)

---

## Zona fixa — KPI cards

5 cards em grade horizontal (`grid-template-columns: repeat(5, 1fr)`), nesta ordem:

| Posição | Métrica | Cor da borda-topo | Valor padrão |
|---------|---------|-------------------|-------------|
| 1 | Total de Chamados | `--accent` (#58a6ff) | Número do período |
| 2 | Resolvidos | `--green` (#3fb950) | Número do período |
| 3 | Em Aberto | `--red` (#f85149) | Número atual |
| 4 | Abertos Hoje | `--green` (#3fb950) | Número do dia |
| 5 | SLA Violado | `--orange` (#e3b341) | Com estados de alerta |

### KPI card — anatomia

```
┌── borda-topo colorida (3px) ──────────────────────────────┐
│  LABEL (10px, uppercase, muted)                           │
│  VALOR (52px, Barlow Condensed 900, --text)               │
│  sub-label (11px, muted)                                  │
└───────────────────────────────────────────────────────────┘
```

### Estados de alerta — SLA Violado

| Faixa | Cor do número | Borda do card | Fundo |
|-------|--------------|---------------|-------|
| 0–5 (normal) | `--text` | `--orange` border-top | padrão |
| 6–15 (atenção) | `--orange` (#e3b341) | `--orange` border total (sutil) | padrão |
| >15 (crítico) | `--red` (#f85149) com `text-shadow` glow | `--red` border total (destacado) | `rgba(248,81,73,0.08)` + label "⚠ CRÍTICO" |

Os limiares (5 e 15) devem ser constantes configuráveis no topo do `<script>`.

---

## Zona rotativa — 4 telas

Rotação automática de 45s por tela, com fade de 0.6s entre transições. Ordem:

### Tela 1 — Gráficos Analíticos

Layout: 2 cards lado a lado (grid 1fr 1fr).

- **Esquerda:** Volume de Chamados por Mês (bar chart — barras azuis, Chart.js)
- **Direita:** % SLA Cumprido por Mês (bar chart com linha de meta em 90% tracejada verde)

Altura dos gráficos: ocupa 100% da zona rotativa disponível.

### Tela 2 — Top Categorias

Layout: 1 card full-width.

- Título: "Top Categorias — Volume no Período"
- Conteúdo: barras horizontais (grid 3 colunas: nome | barra | número)
- Dados: campo `top_categorias` da API (top 8 por volume total no período selecionado)
- **Nota:** A API atual não filtra por status=aberto nesse endpoint. Usar volume total é válido e não requer mudança na API.
- Barras em gradiente de azul (#58a6ff → #388bfd)
- Número à direita em destaque (`font-weight: 700`, `--text`)
- Fontes maiores que o atual para legibilidade 4K: categoria em 15px, número em 18px

### Tela 3 — Desempenho da Equipe

Layout: 1 card full-width com tabela.

Colunas: `#` | Técnico | Resolvidos | Em Aberto | SLA % | Carga (barra)

- Ranking por coluna `resolvidos` (padrão)
- Fonte da tabela: 15px (acima dos 13px atuais)
- Números em `JetBrains Mono`
- Badge SLA%: verde ≥90%, amarelo 75–89%, vermelho <75%
- Barra de carga proporcional ao maior valor de chamados abertos na equipe
- Top 10 técnicos (reduzido de 15 para caber bem sem scroll)

### Tela 4 — Satisfação (CSAT)

Layout: 2 cards lado a lado (grid 1fr 1fr).

- **Esquerda:** Nota média em 72px (Barlow Condensed 900), cor dinâmica (verde ≥4, amarelo 3–3.9, vermelho <3), estrelas em 22px, taxa de resposta e total de avaliações abaixo
- **Direita:** Distribuição de estrelas (barras horizontais) + último comentário recente

---

## Comportamento e configuração

### Temporização
```js
const SCREEN_TIME = 45000;   // 45s por tela rotativa
const REFRESH_TIME = 300000; // 5min para atualização de dados
const SLA_WARN_THRESHOLD  = 5;   // atenção
const SLA_CRIT_THRESHOLD  = 15;  // crítico
```

### Barra de progresso
- Faixa de 3px no rodapé (mantida do design atual)
- Preenche de 0% a 100% durante os 45s da tela atual
- Cor: gradiente `--accent` → `--cyan`
- Reseta ao trocar de tela

### Transição entre telas
- Fade de `opacity: 0 → 1` em 0.65s (mantém atual)
- Sem animação de slide — evita distração em TV

### Auto-refresh
- `fetch` da API a cada 5 minutos (mantém comportamento atual)
- Ao receber dados: atualiza KPIs fixos em tempo real (sem recarregar a tela rotativa atual)
- Indicador de status no header muda para laranja durante carregamento

---

## Tipografia 4K

| Elemento | Fonte | Tamanho | Peso |
|----------|-------|---------|------|
| KPI values | Barlow Condensed | 52px | 900 |
| CSAT nota | Barlow Condensed | 72px | 900 |
| Títulos de seção | Barlow Condensed | 14px | 700 |
| Nomes de técnicos | DM Sans | 15px | 500 |
| Labels de KPI | DM Sans | 11px | 600 |
| Números de tabela | JetBrains Mono | 14px | 400 |
| Categorias (barras) | DM Sans | 15px | 400 |

---

## O que é removido do layout atual

| Elemento | Motivo |
|----------|--------|
| Filtro de período (dropdown) | TV não tem interação |
| Filtro de categoria (dropdown) | TV não tem interação |
| Botão "Pausar" | TV não tem interação |
| Botão "↻ Atualizar" | Auto-refresh cobre isso |
| `nav-dots` (bolinhas de navegação) | TV não tem interação |
| `screen-badge` (label da tela atual) | Desnecessário sem controle |

---

## Arquivos afetados

| Arquivo | Mudança |
|---------|---------|
| `dashboard_live.html` | Refatoração de layout (CSS + HTML + JS) |
| `api.php` | Nenhuma — API permanece igual |

---

## Fora de escopo

- Modo desktop paralelo (não será implementado — apenas TV)
- Filtros interativos (removidos)
- Alertas sonoros
- Integração com outros sistemas além do GLPI
