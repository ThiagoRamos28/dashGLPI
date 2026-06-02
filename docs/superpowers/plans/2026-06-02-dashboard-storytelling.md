# Dashboard Storytelling — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reescrever `dashboard_live.html` como scorecard editorial Dark Premium com 3 telas rotativas (Mês Atual / Radar Operacional / Desempenho do Ano), fixado na categoria Tecnologia, sem filtros interativos.

**Architecture:** O frontend faz 3 chamadas paralelas à API (mês atual, mês anterior, ano) e calcula deltas no cliente. A API ganha uma nova query `chamados_criticos` que retorna tickets abertos sem filtro de data. O HTML é uma reescrita completa — o arquivo existente é substituído inteiramente.

**Tech Stack:** HTML5 + CSS3 + JavaScript ES2020 (sem frameworks), PHP 8+ PDO, Chart.js removido (substituído por barras CSS puras), Google Fonts (Barlow Condensed, DM Sans, JetBrains Mono).

**Spec:** `docs/superpowers/specs/2026-06-02-dashboard-storytelling-redesign.md`

---

## Mapa de arquivos

| Arquivo | Ação | Responsabilidade |
|---------|------|-----------------|
| `api.php` | Modificar | Adicionar `chamados_criticos`; remover queries não usadas (`por_status`, `por_prioridade`, `por_tipo`, `tmr_prioridade`, `categorias`) |
| `dashboard_live.html` | Reescrever | Todo HTML, CSS e JS do novo design |

---

## Task 1: api.php — Limpar queries e adicionar chamados_criticos

**Files:**
- Modify: `api.php`

### Por que esta ordem

A API precisa estar pronta antes de o frontend poder ser testado com dados reais. Esta task não quebra nada — apenas remove seções do JSON que o frontend antigo consumia e adiciona uma nova.

- [ ] **Step 1: Remover as 5 queries não usadas e seus blocos SQL**

Em `api.php`, apague completamente os blocos das seguintes queries (incluindo os comentários de seção):

```
// 3. POR STATUS          (linhas ~126–142)
// 4. POR PRIORIDADE      (linhas ~147–164)
// 5. POR TIPO            (linhas ~169–176)
// 7. TMR POR PRIORIDADE  (linhas ~196–209)
// 11. CATEGORIAS PRINCIPAIS (linhas ~280–292)
```

Após remover, a numeração das seções restantes será: 1 KPIs, 2 Volume Mensal, 3 Top Categorias, 4 Técnicos, 5 Satisfação, 6 Comentários.

- [ ] **Step 2: Adicionar a query chamados_criticos após a seção de técnicos**

Cole o bloco abaixo imediatamente após o `$tecnicos = query(...)` existente (antes da seção de satisfação):

```php
// ============================================================
// CHAMADOS CRÍTICOS (abertos agora — sem filtro de data)
// ============================================================
$chamados_criticos = query($pdo, "
    SELECT
        t.id,
        t.name                                                              AS titulo,
        ic.name                                                             AS categoria,
        DATE_FORMAT(t.date, '%d/%m/%Y %H:%i')                              AS abertura,
        t.time_to_resolve                                                   AS prazo_sla,
        CASE WHEN t.time_to_resolve IS NOT NULL
              AND NOW() > t.time_to_resolve THEN 1 ELSE 0 END              AS sla_violado,
        TIMESTAMPDIFF(HOUR, t.date, NOW())                                  AS horas_aberto
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    WHERE t.is_deleted = 0
      AND t.status NOT IN (5, 6)
      $gf
    ORDER BY sla_violado DESC, t.date ASC
    LIMIT 10
", []);
```

**Atenção:** esta query passa `[]` como segundo argumento (sem bind de datas), mas usa `$gf` interpolado diretamente na string SQL. Isso é seguro porque `$gf` é construído a partir de `(int)$_GET['categoria']`.

- [ ] **Step 3: Atualizar o json_encode final**

Substitua o bloco `echo json_encode([...])` pelo seguinte (remove as 5 chaves antigas, adiciona `chamados_criticos`):

```php
echo json_encode([
    'gerado_em'         => date('d/m/Y H:i:s'),
    'periodo_meses'     => $meses,
    'categoria_id'      => $categoria_id,
    'data_inicio'       => $data_inicio,
    'data_fim'          => $data_fim,
    'kpis'              => $kpis[0] ?? [],
    'volume_mensal'     => $volume_mensal,
    'top_categorias'    => $top_categorias,
    'tecnicos'          => $tecnicos,
    'satisfacao'        => $satisfacao[0] ?? [],
    'comentarios'       => $comentarios,
    'chamados_criticos' => $chamados_criticos,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
```

- [ ] **Step 4: Verificar a API no browser**

Abra `http://[IP_SERVIDOR]/dashboard/api.php?categoria=0` (ou o IP do seu servidor) e confirme:

- JSON retorna sem erros PHP
- Chave `chamados_criticos` presente como array
- Chaves `por_status`, `por_prioridade`, `por_tipo`, `tmr_prioridade`, `categorias` **ausentes**
- Cada item de `chamados_criticos` tem: `id`, `titulo`, `categoria`, `abertura`, `sla_violado`, `horas_aberto`

- [ ] **Step 5: Commit**

```bash
git add api.php
git commit -m "feat: api — chamados_criticos + remove queries não usadas"
```

---

## Task 2: dashboard_live.html — HTML + CSS completo (sem JS)

**Files:**
- Rewrite: `dashboard_live.html`

Esta task substitui todo o arquivo com o novo HTML e CSS. O `<script>` fica vazio por enquanto — as Tasks 3–7 preenchem o JS. Ao abrir no browser, o layout deve ser visível com dados estáticos nos placeholders.

- [ ] **Step 1: Substituir dashboard_live.html pelo novo esqueleto HTML + CSS**

Crie o arquivo com o seguinte conteúdo completo:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard TI — GLPI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <style>
    /* ── RESET ── */
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; overflow: hidden; }

    /* ── TOKENS ── */
    :root {
      --bg:      #0a0c10;
      --card:    rgba(255,255,255,0.02);
      --border:  rgba(255,255,255,0.07);
      --text:    #f0f2f5;
      --muted:   rgba(255,255,255,0.28);
      --dim:     rgba(255,255,255,0.12);
      --green:   #6bcb85;
      --red:     #f87171;
      --amber:   #fbbf24;
      --r:       8px;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      flex-direction: column;
      font-size: 14px;
    }

    /* ── LOADING OVERLAY ── */
    #overlay {
      position: fixed; inset: 0;
      background: rgba(10,12,16,0.96);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 14px; z-index: 999;
    }
    #overlay.hidden { display: none; }
    .spinner {
      width: 32px; height: 32px;
      border: 2px solid rgba(255,255,255,0.08);
      border-top-color: rgba(255,255,255,0.4);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    #overlay p { font-size: 12px; color: var(--muted); letter-spacing: 0.5px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── HEADER ── */
    header {
      flex-shrink: 0;
      padding: 10px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      background: rgba(10,12,16,0.95);
      backdrop-filter: blur(20px);
      z-index: 10;
    }
    .h-dept  { font-size: 9px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; }
    .h-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 22px; font-weight: 700;
      color: var(--text); line-height: 1.1;
    }
    .h-right { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
    .h-indicator { font-size: 9px; color: var(--muted); letter-spacing: 1px; }
    .status { display: flex; align-items: center; gap: 6px; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }
    .status-dot.ok      { background: var(--green); }
    .status-dot.loading { background: var(--amber); }
    .status-dot.err     { background: var(--red); }
    .status-txt { font-size: 9px; color: var(--muted); }

    /* ── PROGRESS BAR ── */
    .prog-bar { height: 2px; background: rgba(255,255,255,0.04); flex-shrink: 0; }
    #prog-fill { height: 100%; background: rgba(255,255,255,0.28); width: 0%; }

    /* ── MAIN / SCREENS ── */
    main { flex: 1; position: relative; overflow: hidden; }
    .screen {
      position: absolute; inset: 0;
      padding: 16px 24px;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.6s ease-in-out;
    }
    .screen.active { opacity: 1; pointer-events: auto; }

    /* ── LAYOUT HELPERS ── */
    .s-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      height: 100%;
    }
    .s-col { display: flex; flex-direction: column; gap: 12px; }

    /* ── CARD BASE ── */
    .card {
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 14px 16px;
      background: var(--card);
    }
    .sec-title {
      font-size: 8px; font-weight: 600;
      letter-spacing: 2px; text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 10px;
    }

    /* ── KPI CARDS ── */
    .kpi-grid { display: flex; flex-direction: column; gap: 10px; }
    .kpi-card {
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 12px 14px;
    }
    .kpi-lbl {
      font-size: 9px; font-weight: 500;
      letter-spacing: 1.5px; text-transform: uppercase;
      color: var(--muted); margin-bottom: 4px;
    }
    .kpi-val {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 42px; font-weight: 700;
      color: var(--text); line-height: 1;
      letter-spacing: -1px;
    }
    .kpi-val-sm { font-size: 26px; letter-spacing: -0.5px; }
    .kpi-delta { font-size: 11px; font-weight: 600; margin-top: 4px; min-height: 16px; }
    .delta-good { color: var(--green); }
    .delta-bad  { color: var(--red); }
    .kpi-bar { height: 2px; background: rgba(255,255,255,0.05); border-radius: 1px; margin-top: 8px; }
    .kpi-bar-fill { height: 100%; background: rgba(255,255,255,0.25); border-radius: 1px; transition: width 0.5s ease; }
    .kpi-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .kpi-sub { font-size: 10px; color: var(--muted); margin-top: 3px; }

    /* ── CSAT ── */
    .csat-val {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 30px; font-weight: 700;
      color: var(--amber); margin: 4px 0 2px;
    }
    .csat-sub { font-size: 10px; color: var(--muted); }

    /* ── TÉCNICOS ── */
    .tec-list { display: flex; flex-direction: column; gap: 6px; }
    .tec-row {
      display: flex; align-items: center; gap: 8px;
      padding: 5px 0;
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .tec-row:last-child { border-bottom: none; }
    .tec-rank {
      font-size: 10px; color: var(--dim); width: 16px; flex-shrink: 0;
      font-family: 'JetBrains Mono', monospace;
    }
    .tec-nome { font-size: 13px; color: rgba(255,255,255,0.6); flex: 1; }
    .tec-stats {
      font-size: 11px; color: var(--muted);
      font-family: 'JetBrains Mono', monospace;
    }
    .sla-good { color: var(--green); font-weight: 600; }
    .sla-warn { color: var(--amber); font-weight: 600; }
    .sla-bad  { color: var(--red);   font-weight: 600; }

    /* ── ALERT LIST (chamados críticos) ── */
    .alert-list { display: flex; flex-direction: column; gap: 5px; }
    .alert-row {
      display: flex; align-items: center; gap: 8px;
      padding: 6px 10px; border-radius: 6px;
      border: 1px solid rgba(251,191,36,0.2);
      background: rgba(251,191,36,0.04);
    }
    .alert-row.sla-viol {
      border-color: rgba(248,113,113,0.25);
      background: rgba(248,113,113,0.05);
    }
    .alert-id {
      font-size: 9px; color: var(--muted);
      font-family: 'JetBrains Mono', monospace; flex-shrink: 0;
    }
    .alert-titulo {
      font-size: 12px; color: rgba(255,255,255,0.65);
      flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .alert-tempo {
      font-size: 11px; font-weight: 600;
      font-family: 'JetBrains Mono', monospace; flex-shrink: 0;
      color: var(--amber);
    }
    .alert-row.sla-viol .alert-tempo { color: var(--red); }
    .empty-state { font-size: 12px; color: var(--muted); padding: 8px 0; }

    /* ── CATEGORY BARS ── */
    .cat-list { display: flex; flex-direction: column; gap: 9px; }
    .cat-row { display: flex; align-items: center; gap: 8px; }
    .cat-name {
      font-size: 12px; color: rgba(255,255,255,0.55);
      width: 120px; flex-shrink: 0;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cat-bar-wrap { flex: 1; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; }
    .cat-bar-fill {
      height: 100%; background: rgba(255,255,255,0.3);
      border-radius: 2px; width: 0%;
      transition: width 0.8s ease;
    }
    .cat-num {
      font-size: 11px; color: var(--muted);
      width: 32px; text-align: right;
      font-family: 'JetBrains Mono', monospace;
    }

    /* ── TELA 3: YEAR LAYOUT ── */
    .s-body-year { display: flex; flex-direction: column; gap: 10px; height: 100%; }
    .year-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

    .melhor-mes {
      display: flex; align-items: center; gap: 10px;
      background: rgba(107,203,133,0.04);
      border-color: rgba(107,203,133,0.18);
    }
    .melhor-label { font-size: 8px; letter-spacing: 2px; color: var(--green); text-transform: uppercase; margin-bottom: 2px; }
    .melhor-val { font-size: 13px; font-weight: 500; color: var(--text); }

    /* ── CHART BARS (CSS puro) ── */
    .chart-bars {
      display: flex; align-items: flex-end; gap: 6px;
      height: 80px; padding-top: 8px;
    }
    .bar-wrap {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; gap: 3px; height: 100%; justify-content: flex-end;
    }
    .bar-col {
      width: 100%; background: rgba(255,255,255,0.14);
      border-radius: 2px 2px 0 0; min-height: 2px;
      transition: height 0.6s ease;
    }
    .bar-col.best { background: var(--green); }
    .bar-lbl { font-size: 8px; color: var(--muted); }
    .bar-pct { font-size: 8px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
    .bar-pct.best { color: var(--green); font-weight: 600; }

    /* ── TELA 3 BOTTOM GRID ── */
    .year-bottom { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    /* ── ANIMATIONS ── */
    @keyframes kpi-in {
      from { opacity: 0; transform: translateY(5px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .kpi-card:nth-child(1) { animation: kpi-in 0.4s ease 0ms   both; }
    .kpi-card:nth-child(2) { animation: kpi-in 0.4s ease 60ms  both; }
    .kpi-card:nth-child(3) { animation: kpi-in 0.4s ease 120ms both; }

    @media (prefers-reduced-motion: reduce) {
      .screen     { transition: none; }
      .cat-bar-fill { transition: none; }
      .bar-col    { transition: none; }
      .kpi-card   { animation: none; }
      .spinner    { animation: none; }
      #prog-fill  { transition: none !important; }
    }
  </style>
</head>
<body>

<!-- Overlay de loading -->
<div id="overlay">
  <div class="spinner"></div>
  <p>Conectando ao GLPI...</p>
</div>

<!-- Header -->
<header>
  <div>
    <div class="h-dept">Departamento de TI · Tecnologia</div>
    <div class="h-title" id="h-title">Carregando...</div>
  </div>
  <div class="h-right">
    <div class="h-indicator" id="h-indicator">1 / 3</div>
    <div class="status">
      <div class="status-dot loading" id="status-dot"></div>
      <span class="status-txt" id="status-txt">Conectando...</span>
    </div>
  </div>
</header>

<div class="prog-bar"></div>

<main>

  <!-- ════ TELA 1 — MÊS ATUAL ════ -->
  <div id="s1" class="screen active">
    <div class="s-grid-2">

      <!-- Coluna esquerda: KPIs -->
      <div class="s-col">
        <div class="kpi-grid">
          <div class="kpi-card" id="k1-sla-card">
            <div class="kpi-lbl">SLA Cumprido</div>
            <div class="kpi-val" id="k1-sla-val">—</div>
            <div class="kpi-delta" id="k1-sla-delta"></div>
            <div class="kpi-bar"><div class="kpi-bar-fill" id="k1-sla-bar"></div></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-lbl">Resolvidos no Mês</div>
            <div class="kpi-val" id="k1-res-val">—</div>
            <div class="kpi-delta" id="k1-res-delta"></div>
          </div>
          <div class="kpi-card kpi-2col">
            <div>
              <div class="kpi-lbl">TMR Médio</div>
              <div class="kpi-val kpi-val-sm" id="k1-tmr-val">—</div>
              <div class="kpi-delta" id="k1-tmr-delta"></div>
            </div>
            <div>
              <div class="kpi-lbl">Em Aberto</div>
              <div class="kpi-val kpi-val-sm" id="k1-aberto-val">—</div>
              <div class="kpi-sub" id="k1-aberto-sub"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Coluna direita: CSAT + técnicos -->
      <div class="s-col">
        <div class="card">
          <div class="sec-title">Satisfação dos Usuários</div>
          <div class="csat-val" id="k1-csat-val">—</div>
          <div class="csat-sub" id="k1-csat-sub"></div>
        </div>
        <div class="card" style="flex:1">
          <div class="sec-title">Destaques do Mês</div>
          <div class="tec-list" id="k1-tec-list"></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ════ TELA 2 — RADAR OPERACIONAL ════ -->
  <div id="s2" class="screen">
    <div class="s-grid-2">
      <div class="card" style="overflow:auto">
        <div class="sec-title">⚠ Aguardando / SLA Extrapolado</div>
        <div class="alert-list" id="s2-criticos"></div>
      </div>
      <div class="card">
        <div class="sec-title">Top Categorias — Mês Atual</div>
        <div class="cat-list" id="s2-cats"></div>
      </div>
    </div>
  </div>

  <!-- ════ TELA 3 — DESEMPENHO DO ANO ════ -->
  <div id="s3" class="screen">
    <div class="s-body-year">
      <div class="year-kpis">
        <div class="kpi-card">
          <div class="kpi-lbl">Total no Ano</div>
          <div class="kpi-val kpi-val-sm" id="k3-total">—</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-lbl">SLA Médio</div>
          <div class="kpi-val kpi-val-sm" id="k3-sla">—</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-lbl">Satisfação Média</div>
          <div class="kpi-val kpi-val-sm" id="k3-csat">—</div>
        </div>
      </div>
      <div class="card melhor-mes" id="k3-melhor">
        <div>
          <div class="melhor-label">Melhor mês</div>
          <div class="melhor-val" id="k3-melhor-val">Carregando...</div>
        </div>
      </div>
      <div class="year-bottom">
        <div class="card">
          <div class="sec-title">SLA % por Mês</div>
          <div class="chart-bars" id="k3-chart"></div>
        </div>
        <div class="card">
          <div class="sec-title">Ranking do Ano</div>
          <div class="tec-list" id="k3-ranking"></div>
        </div>
      </div>
    </div>
  </div>

</main>

<div class="prog-bar">
  <div id="prog-fill"></div>
</div>

<script>
// JS adicionado nas Tasks 3–7
</script>
</body>
</html>
```

- [ ] **Step 2: Verificar layout no browser**

Abra `dashboard_live.html` direto no browser (arquivo local). Confirme:
- Fundo `#0a0c10` (carvão escuro)
- Header com "Departamento de TI · Tecnologia" visível
- Tela 1 visível com 3 KPI cards placeholders ("—")
- Telas 2 e 3 **não visíveis** (opacity: 0)
- Barra de progresso no rodapé (2px, cor cinza escuro)
- Overlay de loading visível (spinner animado)

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: html+css — estrutura Dark Premium 3 telas"
```

---

## Task 3: JS — Configuração, helpers, datas e fetch da API

**Files:**
- Modify: `dashboard_live.html` (bloco `<script>`)

Substitua o comentário `// JS adicionado nas Tasks 3–7` pelo seguinte código completo desta task:

- [ ] **Step 1: Adicionar constantes e helpers ao script**

```js
// ── CONFIGURAÇÃO ──────────────────────────────────────────────
const API_URL      = '/dashboard/api.php';
const CATEGORIA_ID = 0;       // Preencher com ID da categoria Tecnologia no GLPI
const SCREEN_TIME  = 45000;   // 45s por tela
const REFRESH_TIME = 300000;  // 5min entre re-fetches

const MESES_PT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const MESES_SHORT = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

// ── HELPERS ───────────────────────────────────────────────────
function fmt(n) {
  return n == null ? '—' : Number(n).toLocaleString('pt-BR');
}

function fmtH(h) {
  if (h == null || h === '') return '—';
  const hh = parseFloat(h);
  if (hh < 24) return hh.toFixed(1) + 'h';
  const d = Math.floor(hh / 24), r = Math.round(hh % 24);
  return r > 0 ? `${d}d ${r}h` : `${d}d`;
}

function calcDelta(curr, prev) {
  if (prev == null || curr == null || parseFloat(prev) === 0) return null;
  return ((parseFloat(curr) - parseFloat(prev)) / parseFloat(prev)) * 100;
}

function fmtDelta(delta, inverse = false) {
  if (delta == null) return '';
  const pct   = Math.abs(delta).toFixed(1);
  const good  = inverse ? delta < 0 : delta > 0;
  const arrow = delta > 0 ? '▲ +' : '▼ −';
  const cls   = good ? 'delta-good' : 'delta-bad';
  return `<span class="${cls}">${arrow}${pct}%</span>`;
}

function slaClass(pct) {
  const n = parseFloat(pct);
  if (n >= 90) return 'sla-good';
  if (n >= 75) return 'sla-warn';
  return 'sla-bad';
}

// ── UTILITÁRIOS DE DATA ───────────────────────────────────────
function toYMD(d) { return d.toISOString().split('T')[0]; }

function firstDayOfMonth(d)     { return toYMD(new Date(d.getFullYear(), d.getMonth(), 1)); }
function lastDayOfPrevMonth(d)  { return toYMD(new Date(d.getFullYear(), d.getMonth(), 0)); }
function firstDayOfPrevMonth(d) { return toYMD(new Date(d.getFullYear(), d.getMonth() - 1, 1)); }
function firstDayOfYear(d)      { return toYMD(new Date(d.getFullYear(), 0, 1)); }

// ── CACHE DE DADOS ────────────────────────────────────────────
let cache = { mes: null, anterior: null, ano: null };

// ── FETCH ─────────────────────────────────────────────────────
async function fetchAll() {
  setStatus('loading', 'Atualizando...');
  const hoje = new Date();
  const cat  = CATEGORIA_ID;

  function buildUrl(inicio, fim) {
    return `${API_URL}?${new URLSearchParams({
      data_inicio: inicio,
      data_fim: fim,
      categoria: cat,
      _: Date.now(),
    })}`;
  }

  try {
    const [mes, anterior, ano] = await Promise.all([
      fetch(buildUrl(firstDayOfMonth(hoje), toYMD(hoje))).then(r => r.json()),
      fetch(buildUrl(firstDayOfPrevMonth(hoje), lastDayOfPrevMonth(hoje))).then(r => r.json()),
      fetch(buildUrl(firstDayOfYear(hoje), toYMD(hoje))).then(r => r.json()),
    ]);

    if (mes.erro || anterior.erro || ano.erro) throw new Error(mes.erro || 'Erro na API');

    cache = { mes, anterior, ano };
    renderAll();
    setStatus('ok', 'Atualizado às ' + mes.gerado_em.split(' ')[1]);
    document.getElementById('overlay').classList.add('hidden');
  } catch (e) {
    setStatus('err', 'Erro na conexão');
    document.getElementById('overlay').classList.add('hidden');
  }
}

function setStatus(type, txt = '') {
  document.getElementById('status-dot').className = 'status-dot ' + type;
  if (txt) document.getElementById('status-txt').textContent = txt;
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val ?? '—';
}

function setHtml(id, val) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = val ?? '';
}
```

- [ ] **Step 2: Verificar no browser**

Abra o HTML localmente. No console do browser (F12) não deve haver erros de syntax. O overlay ainda fica visível (fetch vai falhar pois é arquivo local, mas não deve crashar o JS).

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: js — config, helpers, fetch paralelo"
```

---

## Task 4: JS — renderTela1() (Mês Atual)

**Files:**
- Modify: `dashboard_live.html` (adicionar ao final do `<script>`, antes do fechamento)

- [ ] **Step 1: Adicionar renderTela1 ao script**

```js
// ── RENDER TELA 1 — MÊS ATUAL ────────────────────────────────
function renderTela1() {
  const k  = cache.mes.kpis     || {};
  const kp = cache.anterior.kpis || {};

  // SLA
  const sla = parseFloat(k.pct_sla);
  setText('k1-sla-val', (k.pct_sla ?? '—') + '%');
  setHtml('k1-sla-delta', fmtDelta(calcDelta(k.pct_sla, kp.pct_sla)));
  const slaEl = document.getElementById('k1-sla-val');
  if (slaEl) slaEl.style.color = sla >= 90 ? 'var(--green)' : sla >= 75 ? 'var(--amber)' : 'var(--red)';
  const barEl = document.getElementById('k1-sla-bar');
  if (barEl) { barEl.style.width = '0%'; requestAnimationFrame(() => { barEl.style.width = (sla || 0) + '%'; }); }

  // Resolvidos
  setText('k1-res-val', fmt(k.fechados));
  setHtml('k1-res-delta', fmtDelta(calcDelta(k.fechados, kp.fechados)));

  // TMR (menor = melhor → inverse: true)
  setText('k1-tmr-val', fmtH(k.tmr_horas));
  setHtml('k1-tmr-delta', fmtDelta(calcDelta(k.tmr_horas, kp.tmr_horas), true));

  // Em aberto
  setText('k1-aberto-val', fmt(k.em_aberto));
  setText('k1-aberto-sub', (k.abertos_hoje ?? 0) + ' abertos hoje');

  // CSAT
  const csat  = cache.mes.satisfacao || {};
  const nota  = parseFloat(csat.media || 0);
  const stars = '★'.repeat(Math.max(0, Math.min(5, Math.round(nota)))) +
                '☆'.repeat(5 - Math.max(0, Math.min(5, Math.round(nota))));
  setText('k1-csat-val', (csat.media ?? '—') + ' ' + stars);
  setText('k1-csat-sub', fmt(csat.respondidas) + ' avaliações no mês');

  // Top técnicos
  const tecs = (cache.mes.tecnicos || []).slice(0, 3);
  document.getElementById('k1-tec-list').innerHTML = tecs.length
    ? tecs.map(t => `
        <div class="tec-row">
          <span class="tec-nome">${t.nome}</span>
          <span class="tec-stats">${fmt(t.resolvidos)} · <span class="${slaClass(t.sla)}">${t.sla ?? '—'}%</span></span>
        </div>`).join('')
    : '<div class="empty-state">Sem dados de técnicos</div>';
}
```

- [ ] **Step 2: Adicionar chamada temporária para testar isoladamente**

No final do script (antes de fechar `</script>`), adicione temporariamente:

```js
// Teste temporário — remover na Task 7
// fetchAll().then(() => renderTela1());
```

Descomente a linha, acesse o dashboard no servidor (com a API real), e verifique:
- SLA com cor correta (verde/âmbar/vermelho)
- Barras SLA animando de 0 ao valor real
- Deltas com ▲ verde ou ▼ vermelho
- TMR em formato `Xh` ou `Xd Yh`
- CSAT com estrelas Unicode
- Lista de top 3 técnicos

Após verificar, comente a linha novamente.

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: js — renderTela1 mês atual com deltas"
```

---

## Task 5: JS — renderTela2() (Radar Operacional)

**Files:**
- Modify: `dashboard_live.html` (adicionar ao `<script>`)

- [ ] **Step 1: Adicionar renderTela2 ao script**

```js
// ── RENDER TELA 2 — RADAR OPERACIONAL ────────────────────────
function renderTela2() {
  // Chamados críticos
  const criticos = cache.mes.chamados_criticos || [];
  document.getElementById('s2-criticos').innerHTML = criticos.length
    ? criticos.map(c => {
        const cls   = c.sla_violado == 1 ? 'sla-viol' : '';
        const tempo = fmtH(c.horas_aberto);
        const titulo = (c.titulo || 'Sem título').substring(0, 60);
        return `
          <div class="alert-row ${cls}">
            <span class="alert-id">#${c.id}</span>
            <span class="alert-titulo">${titulo}</span>
            <span class="alert-tempo">${tempo}</span>
          </div>`;
      }).join('')
    : '<div class="empty-state">Nenhum chamado crítico no momento</div>';

  // Top categorias (mês atual, máx 6)
  const cats = (cache.mes.top_categorias || []).slice(0, 6);
  const max  = cats[0]?.total || 1;
  document.getElementById('s2-cats').innerHTML = cats.length
    ? cats.map(c => `
        <div class="cat-row">
          <span class="cat-name">${c.cat || 'Sem categoria'}</span>
          <div class="cat-bar-wrap">
            <div class="cat-bar-fill" style="width:0%" data-w="${(c.total / max * 100).toFixed(1)}%"></div>
          </div>
          <span class="cat-num">${fmt(c.total)}</span>
        </div>`).join('')
    : '<div class="empty-state">Sem categorias no período</div>';

  // Animar barras após render (aguarda frame para transition funcionar)
  requestAnimationFrame(() => {
    document.querySelectorAll('#s2-cats .cat-bar-fill').forEach(el => {
      el.style.width = el.dataset.w || '0%';
    });
  });
}
```

- [ ] **Step 2: Verificar no servidor**

Com dados reais (descomentar teste temporário da Task 4 adaptado para `renderTela2()`), confirme:
- Chamados com SLA violado aparecem com fundo/borda vermelha, tempo em vermelho
- Chamados sem SLA violado aparecem com fundo âmbar
- Títulos longos são truncados com `...`
- Barras de categoria animam de 0 até o valor proporcional

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: js — renderTela2 radar operacional"
```

---

## Task 6: JS — renderTela3() (Desempenho do Ano)

**Files:**
- Modify: `dashboard_live.html` (adicionar ao `<script>`)

- [ ] **Step 1: Adicionar renderTela3 ao script**

```js
// ── RENDER TELA 3 — DESEMPENHO DO ANO ────────────────────────
function renderTela3() {
  const k    = cache.ano.kpis        || {};
  const meses = cache.ano.volume_mensal || [];
  const csat = cache.ano.satisfacao  || {};

  // KPIs anuais
  setText('k3-total', fmt(k.total_chamados));
  const slaAno = parseFloat(k.pct_sla);
  setText('k3-sla', (k.pct_sla ?? '—') + '%');
  const slaAnoEl = document.getElementById('k3-sla');
  if (slaAnoEl) slaAnoEl.style.color = slaAno >= 90 ? 'var(--green)' : slaAno >= 75 ? 'var(--amber)' : 'var(--red)';
  setText('k3-csat', (csat.media ?? '—') + '★');

  // Calcular melhor mês (maior pct_sla nos dados mensais)
  const melhor = meses.reduce((best, m) => {
    const pct = m.sla_total > 0 ? (m.sla_ok / m.sla_total * 100) : 0;
    return pct > (best.pct || 0) ? { ...m, pct } : best;
  }, { pct: 0 });
  document.getElementById('k3-melhor-val').textContent = melhor.mes
    ? `${melhor.mes} — ${melhor.pct.toFixed(0)}% SLA · ${fmt(melhor.abertos)} chamados`
    : 'Dados insuficientes';

  // Gráfico SLA por mês (últimos 6 meses)
  const last6 = meses.slice(-6);
  document.getElementById('k3-chart').innerHTML = last6.map(m => {
    const pct    = m.sla_total > 0 ? (m.sla_ok / m.sla_total * 100) : 0;
    const isBest = m.mes === melhor.mes;
    const lbl    = (m.mes || '').split('/')[0]; // "Jun" de "Jun/26"
    return `
      <div class="bar-wrap">
        <div class="bar-col ${isBest ? 'best' : ''}" style="height:${pct.toFixed(1)}%"></div>
        <div class="bar-lbl">${lbl}</div>
        <div class="bar-pct ${isBest ? 'best' : ''}">${pct.toFixed(0)}</div>
      </div>`;
  }).join('');

  // Ranking anual (top 5 por resolvidos)
  const tecs = (cache.ano.tecnicos || []).slice(0, 5);
  document.getElementById('k3-ranking').innerHTML = tecs.length
    ? tecs.map((t, i) => `
        <div class="tec-row">
          <span class="tec-rank">${i + 1}</span>
          <span class="tec-nome">${t.nome}</span>
          <span class="tec-stats">${fmt(t.resolvidos)} · <span class="${slaClass(t.sla)}">${t.sla ?? '—'}%</span></span>
        </div>`).join('')
    : '<div class="empty-state">Sem dados de técnicos</div>';
}
```

- [ ] **Step 2: Verificar no servidor**

- KPIs anuais mostram dados acumulados (não do mês)
- SLA com cor correta
- "Melhor mês" mostra o mês com maior percentual de SLA
- Barras do gráfico com altura proporcional ao % SLA (melhor mês em verde)
- Ranking com posições numeradas

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: js — renderTela3 desempenho anual"
```

---

## Task 7: JS — Rotação, progress bar, títulos e init

**Files:**
- Modify: `dashboard_live.html` (adicionar ao `<script>` e remover comentário de teste)

- [ ] **Step 1: Adicionar sistema de rotação e init ao script**

```js
// ── RENDER ALL ────────────────────────────────────────────────
function renderAll() {
  renderTela1();
  renderTela2();
  renderTela3();
}

// ── ROTAÇÃO + PROGRESS BAR ───────────────────────────────────
let currentScreen = 0;
let rotTimer      = null;
const SCREENS     = ['s1', 's2', 's3'];

function screenTitle(idx) {
  const hoje = new Date();
  const mes  = MESES_PT[hoje.getMonth()];
  const ano  = hoje.getFullYear();
  const mesAbrev = MESES_SHORT[hoje.getMonth()];
  const titles = [
    `${mes} ${ano} — Mês Atual`,
    `Radar Operacional — ${mes} ${ano}`,
    `${ano} — Acumulado até ${mesAbrev}`,
  ];
  return titles[idx] || '';
}

function showScreen(idx) {
  SCREENS.forEach((id, i) => {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('active', i === idx);
  });
  document.getElementById('h-title').textContent    = screenTitle(idx);
  document.getElementById('h-indicator').textContent = `${idx + 1} / ${SCREENS.length}`;
  startProgress();
}

function nextScreen() {
  currentScreen = (currentScreen + 1) % SCREENS.length;
  showScreen(currentScreen);
}

function startProgress() {
  const fill = document.getElementById('prog-fill');
  fill.style.transition = 'none';
  fill.style.width = '0%';
  requestAnimationFrame(() => {
    fill.style.transition = `width ${SCREEN_TIME}ms linear`;
    fill.style.width = '100%';
  });
}

function startRotation() {
  clearInterval(rotTimer);
  showScreen(0);
  rotTimer = setInterval(nextScreen, SCREEN_TIME);
}

// ── INIT ─────────────────────────────────────────────────────
async function init() {
  await fetchAll();
  startRotation();
  setInterval(fetchAll, REFRESH_TIME);
}

init();
```

- [ ] **Step 2: Verificar comportamento completo no servidor**

Abra o dashboard no servidor com a API ativa e confirme:
1. Overlay desaparece após dados carregarem
2. Tela 1 aparece com KPIs reais e deltas coloridos
3. Após 45s a Tela 2 aparece com fade
4. Título no header muda conforme a tela ("Junho 2026 — Mês Atual", "Radar Operacional", etc.)
5. Indicador "1 / 3", "2 / 3", "3 / 3" atualiza no canto direito
6. Progress bar no rodapé preenche de 0 a 100% em 45s e reseta
7. Após 3 telas, volta para a Tela 1
8. Status dot fica verde com "Atualizado às HH:MM:SS"

- [ ] **Step 3: Remover comentário de teste residual**

Certifique-se de que não há `// fetchAll().then(...)` descomentado no script.

- [ ] **Step 4: Commit e push**

```bash
git add dashboard_live.html
git commit -m "feat: js — rotação 3 telas, progress bar, títulos dinâmicos, init"
git push
```

---

## Self-review do plano

**Cobertura do spec:**

| Requisito do spec | Task |
|-------------------|------|
| chamados_criticos sem filtro de data | Task 1 |
| Remover queries não usadas | Task 1 |
| HTML/CSS Dark Premium | Task 2 |
| 3 telas com fade | Task 2 + Task 7 |
| Progress bar 0→100% | Task 7 |
| Indicador "N / 3" no header | Task 7 |
| Título dinâmico por tela | Task 7 |
| KPIs com deltas vs mês anterior | Task 3 + Task 4 |
| SLA cor condicional (verde/âmbar/vermelho) | Task 4 |
| TMR delta invertido | Task 4 |
| CSAT com estrelas | Task 4 |
| Top 3 técnicos mês | Task 4 |
| Lista chamados críticos (SLA violado + mais antigos) | Task 5 |
| Barras de categoria animadas | Task 5 |
| KPIs anuais YTD | Task 6 |
| Melhor mês destacado | Task 6 |
| Gráfico SLA por mês CSS | Task 6 |
| Ranking anual top 5 | Task 6 |
| prefers-reduced-motion | Task 2 (CSS) |
| CATEGORIA_ID configurável | Task 3 |
| Auto-refresh 5min | Task 7 |
| Status dot (ok/loading/err) | Task 3 + Task 7 |
