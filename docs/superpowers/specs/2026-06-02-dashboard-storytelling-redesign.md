# Dashboard TI GLPI — Redesign Storytelling

**Data:** 2026-06-02
**Arquivo alvo:** `dashboard_live.html` (reescrita completa) + `api.php` (novo endpoint)
**Abordagem:** Scorecard editorial, Dark Premium, 3 telas rotativas, sem filtros interativos

---

## Narrativa central

> "Estamos evoluindo — e os números provam."

O dashboard conta uma história em três capítulos, alternando a cada 45s:

1. **Como foi este mês** — KPIs atuais com comparativo vs mês anterior
2. **O que precisa de atenção agora** — chamados críticos e distribuição por categoria
3. **O quanto evoluímos no ano** — acumulado YTD com gráfico de progresso mês a mês

---

## Estilo visual — Dark Premium

| Propriedade | Valor |
|-------------|-------|
| Fundo | `#0a0c10` (carvão escuro) |
| Texto principal | `#f0f2f5` (quase branco) |
| Texto secundário | `rgba(255,255,255,0.28)` |
| Borda de card | `rgba(255,255,255,0.07)` |
| Positivo / Melhora | `#6bcb85` (verde suave) |
| Negativo / Alerta | `#f87171` (vermelho suave) |
| Aviso SLA | `#fbbf24` (âmbar) |
| Header border | `rgba(255,255,255,0.06)` |
| Progress bar | `rgba(255,255,255,0.25)` |

**Sem glow effects, sem neons, sem gradientes chamados.** O peso vem da tipografia e do espaço.

### Tipografia

| Elemento | Fonte | Tamanho | Peso |
|----------|-------|---------|------|
| Título da tela (mês/ano) | Barlow Condensed | 22px | 700 |
| KPI valor principal | Barlow Condensed | 42px | 700 |
| KPI valor secundário | Barlow Condensed | 22px | 700 |
| Delta (▲ / ▼) | DM Sans | 11px | 600 |
| Label do KPI | DM Sans | 9px | 500 — uppercase, letter-spacing 1.5px |
| Nome de técnico | DM Sans | 13px | 400 |
| Ticket crítico | DM Sans | 12px | 400 |
| Números de tabela | JetBrains Mono | 12px | 400 |
| Section title | DM Sans | 8px | 600 — uppercase, letter-spacing 2px |

---

## Estrutura geral

```
┌─ HEADER (fixo, compacto) ─────────────────────────────────────┐
│  [Departamento de TI · Tecnologia]   [● AO VIVO  10:32]       │
│  [Título da tela atual]                                        │
├─ PROGRESS BAR (2px, topo do conteúdo) ────────────────────────┤
│                                                                │
│  ZONA ROTATIVA — 3 TELAS (45s cada)                           │
│                                                                │
└─ PROGRESS BAR (2px, rodapé — fill 0→100% nos 45s) ───────────┘
```

Sem: filtros, botões, nav-dots, badges de tela. TV pura.

---

## Configuração (topo do script)

```js
const API_URL          = '/dashboard/api.php';
const CATEGORIA_ID     = 0;      // ID da categoria Tecnologia no GLPI (0 = todas)
const SCREEN_TIME      = 45000;  // ms por tela
const REFRESH_TIME     = 300000; // ms para re-fetch da API (5min)
```

O usuário deve preencher `CATEGORIA_ID` com o ID real da categoria "Tecnologia" consultando `glpi_itilcategories` no banco.

---

## Períodos das chamadas à API

O dashboard faz **3 chamadas** na inicialização e a cada `REFRESH_TIME`:

| Chamada | Parâmetros | Usado em |
|---------|-----------|----------|
| `mesAtual` | `data_inicio = 1º dia mês corrente`, `data_fim = hoje`, `categoria = CATEGORIA_ID` | Tela 1 + Tela 2 (categorias + chamados críticos) |
| `mesAnterior` | `data_inicio = 1º dia mês anterior`, `data_fim = último dia mês anterior`, `categoria = CATEGORIA_ID` | Deltas da Tela 1 |
| `anoAtual` | `data_inicio = 01/01/ano corrente`, `data_fim = hoje`, `categoria = CATEGORIA_ID` | Tela 3 |

Os deltas são calculados no frontend: `delta = ((atual - anterior) / anterior) * 100`.

---

## Tela 1 — Mês Atual

**Título:** `"Junho 2026 — Mês Atual"`
**Dados:** `mesAtual` + deltas calculados com `mesAnterior`

### Layout (grid 2 colunas: 55% | 45%)

**Coluna esquerda — KPIs com delta:**

| KPI | Valor | Delta |
|-----|-------|-------|
| SLA Cumprido | `pct_sla%` | vs mês anterior |
| Resolvidos | `fechados` | vs mês anterior |
| TMR Médio | `tmr_horas h` | vs mês anterior (▼ = bom) |
| Em Aberto | `em_aberto` | (sem delta — é snapshot) |

Cada KPI: label uppercase + valor grande + delta colorido (verde = melhora, vermelho = piora).
A lógica de melhora/piora é invertida para TMR (menor é melhor) e Em Aberto (menor é melhor).

**Coluna direita — Satisfação + Técnicos:**

- **Satisfação:** nota média em grande (`4.7★`), total de avaliações abaixo
- **Top 3 técnicos do mês:** nome | chamados resolvidos | SLA%

---

## Tela 2 — Radar Operacional

**Título:** `"Radar Operacional — Junho 2026"`
**Dados:** `mesAtual` para categorias; chamados críticos do novo endpoint `chamados_criticos`

### Layout (grid 2 colunas: 50% | 50%)

**Coluna esquerda — Chamados críticos:**

Lista com até 10 chamados, ordenada por:
1. SLA violado (NOW() > time_to_resolve) primeiro — badge vermelho
2. Há mais tempo em aberto (date ASC) segundo — badge âmbar

Cada linha: `#ID · nome do chamado` | `Xd Yh em aberto` | badge SLA

**Coluna direita — Top categorias do mês:**

Barras horizontais com: nome da categoria | barra proporcional | contagem
Máximo 6 categorias.

---

## Tela 3 — Desempenho do Ano

**Título:** `"2026 — Acumulado até Junho"`
**Dados:** `anoAtual`

### Layout (grid superior 3 colunas + grid inferior 2 colunas)

**Linha superior — KPIs anuais:**

| KPI | Valor |
|-----|-------|
| Total de chamados | acumulado YTD |
| SLA médio | `pct_sla` do período YTD (calculado sobre todos os chamados do ano) |
| Satisfação | média anual |

**Bloco "Melhor mês":** destaca o mês com maior `pct_sla` do `volume_mensal`.

**Linha inferior:**
- **Esquerda:** Gráfico de barras — SLA% por mês (6 últimos meses). Último mês sempre destacado em `#6bcb85`.
- **Direita:** Ranking anual — top 5 técnicos por resolvidos, com SLA%.

---

## Alterações em `api.php`

### Nova query — `chamados_criticos`

Adicionada após a query existente de técnicos. Não usa `:data_inicio`/`:data_fim` — retorna todos os chamados ABERTOS no momento, filtrados apenas por categoria.

```php
$chamados_criticos = query($pdo, "
    SELECT
        t.id,
        t.name                                                               AS titulo,
        ic.name                                                              AS categoria,
        DATE_FORMAT(t.date, '%d/%m/%Y %H:%i')                               AS abertura,
        t.time_to_resolve                                                    AS prazo_sla,
        CASE WHEN t.time_to_resolve IS NOT NULL
              AND NOW() > t.time_to_resolve THEN 1 ELSE 0 END               AS sla_violado,
        TIMESTAMPDIFF(HOUR, t.date, NOW())                                   AS horas_aberto
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    WHERE t.is_deleted = 0
      AND t.status NOT IN (5, 6)
      $gf
    ORDER BY sla_violado DESC, t.date ASC
    LIMIT 10
", []);
```

**Importante:** esta query usa `$gf` (filtro de categoria) mas **não** usa `$p` (parâmetros de data).

### JSON de resposta

Adicionar ao `json_encode` final:
```php
'chamados_criticos' => $chamados_criticos,
```

### Remover da resposta final

- `por_status` — não usado no novo design
- `por_prioridade` — não usado
- `por_tipo` — não usado
- `tmr_prioridade` — não usado
- `categorias` (lista para dropdown) — não usado (sem filtro)

---

## Comportamento

### Rotação
- 45s por tela, fade de 0.6s
- Progress bar no rodapé: preenche 0→100% em 45s, reseta ao trocar de tela
- Indicador no header mostra qual tela está ativa (ex: "1 / 3")

### Auto-refresh
- A cada 5min, refaz as 3 chamadas em paralelo (`Promise.all`)
- Durante fetch: indicador de status muda para âmbar
- KPIs e dados atualizam na próxima transição de tela (não interrompe a tela atual)
- Em erro: indicador vermelho, mantém dados anteriores

### Animações
- Fade in/out entre telas: `opacity 0→1` em 0.6s, `ease-in-out`
- Barras de categoria: largura anima de 0 ao valor real em 0.8s na entrada da tela
- KPIs: `opacity 0 + translateY(4px) → normal` em 0.4s com stagger de 60ms por card
- `prefers-reduced-motion`: remove todas as animações

---

## Arquivos afetados

| Arquivo | Mudança |
|---------|---------|
| `dashboard_live.html` | Reescrita completa — novo HTML, CSS e JS |
| `api.php` | Nova query `chamados_criticos` + remoção de queries não usadas |

---

## Fora de escopo

- Filtros interativos (removidos)
- Modo de impressão ou exportação
- Múltiplas categorias configuráveis
- Autenticação
