# Dashboard TV 4K — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refatorar `dashboard_live.html` para exibição em TV 4K com zona fixa de KPIs e zona rotativa de 4 telas, sem controles interativos.

**Architecture:** Layout em 3 camadas — header compacto (logo + status), zona KPI fixa (5 cards sempre visíveis), zona rotativa (4 telas × 45s). Arquivo único `dashboard_live.html`; `api.php` não é alterado.

**Tech Stack:** HTML5, CSS Grid/Flexbox, Chart.js 4.5.1, Vanilla JS

**Spec:** `docs/superpowers/specs/2026-06-02-dashboard-tv-4k-design.md`

---

### Task 1: Atualizar constantes e remover funções obsoletas do JS

**Files:**
- Modify: `dashboard_live.html` — bloco `<script>`, seção CONFIG e funções obsoletas

- [ ] **Step 1: Atualizar constantes**

Localizar a seção `// ── CONFIG ──` e substituir por:

```js
const API_URL            = '/dashboard/api.php';
const SCREEN_TIME        = 45000;
const SLA_WARN_THRESHOLD = 5;
const SLA_CRIT_THRESHOLD = 15;
```

Remover também a linha: `const SCREEN_NAMES = ['Visão Geral', 'Distribuição', 'Equipe', 'Satisfação'];`

- [ ] **Step 2: Remover o IIFE initFromURL**

Localizar e apagar o bloco inteiro:

```js
(function initFromURL() {
  const p = new URLSearchParams(window.location.search);
  if (p.get('categoria')) document.getElementById('f-categoria').value = p.get('categoria');
  if (p.get('meses'))     document.getElementById('f-periodo').value   = p.get('meses');
})();
```

- [ ] **Step 3: Remover funções mortas**

Apagar inteiramente as seguintes funções (serão substituídas ou não têm mais uso):

- `populateCategorias(categorias, cid)` — filtro de categoria removido
- `togglePause()` — botão de pausa removido
- `renderStatus(data)` — tela de distribuição removida
- `renderPrioridade(data)` — tela de distribuição removida
- `renderTipo(data)` — tela de distribuição removida
- `renderTMR(data)` — tela de distribuição removida

- [ ] **Step 4: Verificar no browser**

Abrir `dashboard_live.html`. O console não deve ter erros de sintaxe. Erros de rede (`api.php` inacessível) são esperados e normais.

- [ ] **Step 5: Commit**

```bash
git add dashboard_live.html
git commit -m "refactor: remove funções de modo desktop — filtros, pausa, distribuição"
```

---

### Task 2: Simplificar o header HTML — remover controles interativos

**Files:**
- Modify: `dashboard_live.html` — HTML do `<header>` e CSS correspondente

- [ ] **Step 1: Substituir o conteúdo do `<header>`**

Localizar `<header>` e substituir TODO o conteúdo interno por:

```html
  <div class="h-left">
    <div class="h-logo">
      <div class="h-logo-icon">🖥</div>
      <div>
        <div class="h-title">Dashboard TI — GLPI</div>
        <div class="h-sub">Central de chamados · tempo real</div>
      </div>
    </div>
  </div>
  <div class="h-right">
    <div class="status-row">
      <div class="sdot" id="sdot"></div>
      <span id="stxt" style="font-size:11px;color:var(--muted)">—</span>
    </div>
  </div>
```

- [ ] **Step 2: Remover regras CSS mortas do header**

No bloco `<style>`, apagar as regras que não têm mais elemento correspondente:

```css
/* REMOVER estas regras: */
.sep { ... }
.h-filters { ... }
.f-label { ... }
.f-select { ... }
.f-select:hover, .f-select:focus { ... }
.f-select option { ... }
.nav-dots { ... }
.dot-btn { ... }
.dot-btn.active { ... }
.screen-badge { ... }
.btn-ctrl { ... }
.btn-ctrl:hover { ... }
```

- [ ] **Step 3: Verificar no browser**

Recarregar. Header deve mostrar apenas logo, título e indicador de status. Sem dropdowns, sem botões.

- [ ] **Step 4: Commit**

```bash
git add dashboard_live.html
git commit -m "refactor: header TV — remove filtros e controles interativos"
```

---

### Task 3: Adicionar zona KPI fixa ao HTML

**Files:**
- Modify: `dashboard_live.html` — HTML entre `</header>` e `<!-- ERROR -->`

- [ ] **Step 1: Inserir HTML da tv-kpi-zone**

Logo após `</header>` e antes de `<!-- ERROR -->`, inserir:

```html
<!-- TV KPI ZONE -->
<div class="tv-kpi-zone">
  <div class="kpi" id="kpi-total">
    <div class="kpi-lbl">Total de Chamados</div>
    <div class="kpi-val" id="k-total">—</div>
    <div class="kpi-sub" id="k-periodo">—</div>
  </div>
  <div class="kpi g" id="kpi-fechados">
    <div class="kpi-lbl">Resolvidos</div>
    <div class="kpi-val" id="k-fechados">—</div>
    <div class="kpi-sub">no período</div>
  </div>
  <div class="kpi r" id="kpi-abertos">
    <div class="kpi-lbl">Em Aberto</div>
    <div class="kpi-val" id="k-abertos">—</div>
    <div class="kpi-sub">agora</div>
  </div>
  <div class="kpi g" id="kpi-hoje">
    <div class="kpi-lbl">Abertos Hoje</div>
    <div class="kpi-val" id="k-hoje">—</div>
    <div class="kpi-sub">novos</div>
  </div>
  <div class="kpi o" id="kpi-sla">
    <div class="kpi-lbl">SLA Violado</div>
    <div class="kpi-val" id="k-slav">—</div>
    <div class="kpi-sub" id="k-slasub">no período</div>
  </div>
</div>
```

- [ ] **Step 2: Verificar no browser**

Recarregar. Deve aparecer uma faixa com 5 cards KPI entre o header e o conteúdo rotativo. Valores mostram "—" (sem dados ainda).

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: zona KPI fixa com 5 cards (Total, Resolvidos, Em Aberto, Hoje, SLA)"
```

---

### Task 4: CSS para layout TV — zonas e estados de alerta SLA

**Files:**
- Modify: `dashboard_live.html` — bloco `<style>`

- [ ] **Step 1: Adicionar CSS da tv-kpi-zone**

No final do bloco `<style>` (antes de `</style>`), adicionar:

```css
/* ── TV KPI ZONE ─────────────────────────────────────── */
.tv-kpi-zone {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: var(--gap);
  padding: 12px 16px;
  background: var(--s1);
  border-bottom: 2px solid rgba(88,166,255,0.1);
}

/* ── SLA ALERT STATES ────────────────────────────────── */
.kpi.sla-warn {
  border: 1px solid rgba(227,179,65,0.35);
  border-top-color: var(--orange);
}
.kpi.sla-warn .kpi-val { color: var(--orange); }

.kpi.sla-crit {
  background: rgba(248,81,73,0.08);
  border: 1px solid rgba(248,81,73,0.4);
  border-top-color: var(--red);
}
.kpi.sla-crit .kpi-val {
  color: var(--red);
  text-shadow: 0 0 20px rgba(248,81,73,0.5);
}
.kpi.sla-crit .kpi-sub::before {
  content: '⚠ CRÍTICO · ';
  color: var(--red);
}
```

- [ ] **Step 2: Remover .kpi-grid**

Localizar e apagar a regra `.kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); ... }` — os KPIs agora vivem na `.tv-kpi-zone`.

- [ ] **Step 3: Verificar no browser**

Recarregar. Os 5 KPI cards devem aparecer lado a lado com bordas coloridas. Forçar teste do alerta: alterar temporariamente `SLA_WARN_THRESHOLD = -1` para ver o card SLA ficar laranja, depois `SLA_CRIT_THRESHOLD = -1` para ver o estado crítico com glow vermelho. Restaurar valores após o teste.

- [ ] **Step 4: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: CSS zona KPI fixa + estados de alerta SLA (warn/crit)"
```

---

### Task 5: Substituir as 4 telas rotativas pelo novo HTML TV

**Files:**
- Modify: `dashboard_live.html` — conteúdo interno de `<div class="screens-wrap">`

- [ ] **Step 1: Substituir TODO o conteúdo de `.screens-wrap`**

Localizar `<div class="screens-wrap">` e substituir o seu conteúdo inteiro (s0, s1, s2, s3 antigos) por:

```html
  <!-- S0 — GRÁFICOS ANALÍTICOS -->
  <div class="screen active" id="s0">
    <div class="screen-title">Gráficos Analíticos</div>
    <div class="row c2" style="flex:1">
      <div class="card" style="display:flex;flex-direction:column">
        <h3><span class="cdot"></span>Volume de Chamados por Mês</h3>
        <div class="chart-wrap" style="flex:1;min-height:200px"><canvas id="ch-volume"></canvas></div>
      </div>
      <div class="card" style="display:flex;flex-direction:column">
        <h3><span class="cdot" style="background:var(--green)"></span>% SLA Cumprido</h3>
        <div class="chart-wrap" style="flex:1;min-height:200px"><canvas id="ch-sla"></canvas></div>
      </div>
    </div>
  </div>

  <!-- S1 — TOP CATEGORIAS -->
  <div class="screen" id="s1">
    <div class="screen-title">Top Categorias</div>
    <div class="card" style="flex:1;display:flex;flex-direction:column">
      <h3><span class="cdot" style="background:var(--amber)"></span>Volume no Período</h3>
      <div id="cat-list" class="cat-list"></div>
    </div>
  </div>

  <!-- S2 — EQUIPE -->
  <div class="screen" id="s2">
    <div class="screen-title">Desempenho da Equipe</div>
    <div class="card" style="flex:1">
      <h3><span class="cdot" style="background:var(--cyan)"></span>Ranking de Técnicos</h3>
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th>#</th>
            <th>Técnico</th>
            <th>Total</th>
            <th>Resolvidos</th>
            <th>Em Aberto</th>
            <th>SLA %</th>
            <th>TMR (h)</th>
            <th>Carga</th>
          </tr></thead>
          <tbody id="tbl-body"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- S3 — SATISFAÇÃO -->
  <div class="screen" id="s3">
    <div class="screen-title">Satisfação dos Usuários</div>
    <div class="row c2" style="flex:1">
      <div class="card" style="display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div class="csat-num" id="csat-num">—</div>
        <div class="csat-stars" id="csat-stars">☆☆☆☆☆</div>
        <div class="csat-lbl">Nota Média · Escala 1 a 5</div>
        <div style="margin-top:8px;font-size:13px;color:var(--muted)">
          <span id="csat-taxa">—%</span> de resposta &nbsp;·&nbsp; <span id="csat-total">—</span> avaliações
        </div>
      </div>
      <div class="card" style="display:flex;flex-direction:column">
        <h3><span class="cdot" style="background:var(--orange)"></span>Distribuição de Notas</h3>
        <div class="rating-list">
          <div class="rating-row"><span class="rating-stars">⭐⭐⭐⭐⭐</span><div class="rating-track"><div class="rating-bar" id="rb5" style="width:0%;background:var(--green)"></div></div><span class="rating-n" id="rn5">0</span></div>
          <div class="rating-row"><span class="rating-stars">⭐⭐⭐⭐</span>  <div class="rating-track"><div class="rating-bar" id="rb4" style="width:0%;background:var(--green)"></div></div><span class="rating-n" id="rn4">0</span></div>
          <div class="rating-row"><span class="rating-stars">⭐⭐⭐</span>    <div class="rating-track"><div class="rating-bar" id="rb3" style="width:0%;background:var(--orange)"></div></div><span class="rating-n" id="rn3">0</span></div>
          <div class="rating-row"><span class="rating-stars">⭐⭐</span>      <div class="rating-track"><div class="rating-bar" id="rb2" style="width:0%;background:var(--amber)"></div></div><span class="rating-n" id="rn2">0</span></div>
          <div class="rating-row"><span class="rating-stars">⭐</span>        <div class="rating-track"><div class="rating-bar" id="rb1" style="width:0%;background:var(--red)"></div></div><span class="rating-n" id="rn1">0</span></div>
        </div>
        <div style="margin-top:auto;padding-top:12px">
          <h3><span class="cdot" style="background:var(--green)"></span>Comentário Recente</h3>
          <div class="comment-list" id="comment-list">
            <p style="color:var(--muted);font-size:12px">Nenhum comentário no período.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
```

- [ ] **Step 2: Verificar no browser**

Recarregar. A tela s0 (Gráficos) deve aparecer ativa. Após 45s troca para s1. Console não deve ter erros de canvas.

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: 4 telas rotativas TV — Gráficos, Categorias, Equipe, Satisfação"
```

---

### Task 6: CSS para categorias, tipografia 4K e limpeza de regras mortas

**Files:**
- Modify: `dashboard_live.html` — bloco `<style>`

- [ ] **Step 1: Adicionar CSS da lista de categorias**

No final do bloco `<style>`, adicionar:

```css
/* ── CATEGORIAS TV ───────────────────────────────────── */
.cat-list { display: flex; flex-direction: column; gap: 14px; padding: 8px 0; flex: 1; justify-content: center; }
.cat-row  { display: grid; grid-template-columns: 200px 1fr 64px; gap: 12px; align-items: center; }
.cat-name { font-size: 15px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cat-track { height: 10px; background: rgba(255,255,255,0.05); border-radius: 5px; overflow: hidden; }
.cat-bar  { height: 100%; background: linear-gradient(90deg, #58a6ff, #388bfd); border-radius: 5px; transition: width .8s ease; }
.cat-num  { font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700; color: var(--text); text-align: right; }
```

- [ ] **Step 2: Aumentar fontes da tabela de técnicos para 4K**

Localizar `tbody td { padding: 10px 12px; font-size: 13px; }` e alterar para:

```css
tbody td { padding: 12px 14px; font-size: 15px; }
```

Localizar `.mono  { font-family: 'JetBrains Mono', monospace; font-size: 12px; }` e alterar para:

```css
.mono  { font-family: 'JetBrains Mono', monospace; font-size: 14px; }
```

- [ ] **Step 3: Remover regras CSS mortas**

Apagar as regras que não têm mais elemento correspondente no HTML:

```css
/* REMOVER — tela de satisfação antiga usava layout diferente */
.csat-hero { ... }
.kpi-stat { ... }
.kpi-stat .val { ... }
.kpi-stat .lbl { ... }
```

Manter: `.rating-list`, `.rating-row`, `.rating-stars`, `.rating-track`, `.rating-bar`, `.rating-n`, `.comment-list`, `.comment-item` — ainda usados em s3.

- [ ] **Step 4: Verificar no browser**

Recarregar. Com dados, tabela de técnicos deve ter fonte visivelmente maior. Navegar para s1 — categorias devem aparecer como barras horizontais.

- [ ] **Step 5: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: CSS 4K — tipografia maior, barras de categoria, limpeza"
```

---

### Task 7: Reescrever renderCategorias() como barras CSS

**Files:**
- Modify: `dashboard_live.html` — função `renderCategorias` no `<script>`

- [ ] **Step 1: Substituir a função renderCategorias()**

Localizar `function renderCategorias(data) {` (que atualmente usa `mkChart('ch-cat', ...)`) e substituir a função inteira por:

```js
function renderCategorias(data) {
  const maxVal = Math.max(...data.map(d => d.total), 1);
  document.getElementById('cat-list').innerHTML = data.slice(0, 8).map(d => {
    const pct = Math.round((d.total / maxVal) * 100);
    return `<div class="cat-row">
      <span class="cat-name">${esc(d.cat)}</span>
      <div class="cat-track"><div class="cat-bar" style="width:${pct}%"></div></div>
      <span class="cat-num">${fmt(d.total)}</span>
    </div>`;
  }).join('');
}
```

- [ ] **Step 2: Verificar no browser**

Navegar até s1. Deve exibir até 8 categorias como barras CSS horizontais, com o nome à esquerda e número à direita. A barra maior (100%) corresponde à categoria com mais chamados.

- [ ] **Step 3: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: categorias como barras CSS — remove dependência do Chart.js canvas"
```

---

### Task 8: Atualizar renderKPIs(), adicionar updateSLAAlert(), corrigir renderTecnicos()

**Files:**
- Modify: `dashboard_live.html` — funções `renderKPIs`, `renderTecnicos`, adição de `updateSLAAlert`

- [ ] **Step 1: Substituir renderKPIs()**

Localizar `function renderKPIs(d) {` e substituir a função inteira por:

```js
function renderKPIs(d) {
  const k = d.kpis, m = d.volume_mensal;
  const ini = m.length ? m[0].mes : '', fim = m.length ? m[m.length-1].mes : '';
  document.getElementById('k-total').textContent    = fmt(k.total_chamados);
  document.getElementById('k-fechados').textContent = fmt(k.fechados);
  document.getElementById('k-abertos').textContent  = fmt(k.em_aberto);
  document.getElementById('k-hoje').textContent     = fmt(k.abertos_hoje);
  document.getElementById('k-slav').textContent     = fmt(k.sla_violado);
  document.getElementById('k-periodo').textContent  = `${ini} – ${fim}`;
  document.getElementById('k-slasub').textContent   = `${k.pct_sla ?? '—'}% cumprido`;
  updateSLAAlert(k.sla_violado ?? 0);
}
```

- [ ] **Step 2: Adicionar updateSLAAlert() logo após renderKPIs()**

```js
function updateSLAAlert(n) {
  const card = document.getElementById('kpi-sla');
  card.classList.remove('sla-warn', 'sla-crit');
  if (n > SLA_CRIT_THRESHOLD) {
    card.classList.add('sla-crit');
  } else if (n > SLA_WARN_THRESHOLD) {
    card.classList.add('sla-warn');
  }
}
```

- [ ] **Step 3: Substituir renderTecnicos()**

Localizar `function renderTecnicos() {` e substituir a função inteira por:

```js
function renderTecnicos() {
  const rows = [...tecnicos]
    .sort((a, b) => (b.resolvidos ?? 0) - (a.resolvidos ?? 0))
    .slice(0, 10);
  const maxT = Math.max(...rows.map(r => r.total || 0), 1);
  document.getElementById('tbl-body').innerHTML = rows.map((t, i) => {
    const bc  = t.sla >= 90 ? 'b-g' : t.sla >= 75 ? 'b-y' : 'b-r';
    const pc  = t.sla >= 90 ? '#3fb950' : t.sla >= 75 ? '#e3b341' : '#f85149';
    const pct = Math.round((t.total / maxT) * 100);
    const rc  = i === 0 ? 'rank top1' : i === 1 ? 'rank top2' : i === 2 ? 'rank top3' : 'rank';
    return `<tr>
      <td><span class="${rc}">${i + 1}</span></td>
      <td class="tname">${t.nome}</td>
      <td class="mono">${fmt(t.total)}</td>
      <td class="mono" style="color:var(--green)">${fmt(t.resolvidos)}</td>
      <td class="mono" style="color:var(--red)">${fmt(t.abertos)}</td>
      <td><span class="badge ${bc}">${t.sla ?? '—'}%</span></td>
      <td class="mono">${t.tmr ?? '—'}h</td>
      <td style="min-width:90px"><div class="prog-bar"><div class="prog-fill" style="width:${pct}%;background:${pc}"></div></div></td>
    </tr>`;
  }).join('');
}
```

Obs: badge SLA agora usa 90%/75% como limiares (era 95%/80%) — alinhado com a meta de SLA do spec.

- [ ] **Step 4: Verificar no browser**

Com dados: KPIs fixos exibem valores corretos. Tabela mostra no máximo 10 técnicos. Forçar `sla_violado > 15` no JSON local ou ajustar `SLA_CRIT_THRESHOLD = -1` temporariamente para confirmar o estado crítico.

- [ ] **Step 5: Commit**

```bash
git add dashboard_live.html
git commit -m "feat: alertas SLA visuais, KPIs fixos populados, equipe top 10"
```

---

### Task 9: Finalizar — renderAll(), loadData(), goTo() e limpeza de estado

**Files:**
- Modify: `dashboard_live.html` — funções `renderAll`, `loadData`, `goTo`, bloco INIT, variáveis de estado

- [ ] **Step 1: Limpar variáveis de estado desnecessárias**

Localizar o bloco de declarações `let` e substituir por:

```js
let charts    = {};
let tecnicos  = [];
let current   = 0;
let rotTimer  = null;
let progAnim  = null;
let progStart = null;
```

Remover: `let sortCol = 'total', sortDir = -1;` e `let paused = false;`

- [ ] **Step 2: Simplificar goTo()**

Substituir a função `goTo(n)` por:

```js
function goTo(n) {
  document.getElementById('s' + current).classList.remove('active');
  current = n;
  document.getElementById('s' + current).classList.add('active');
  setTimeout(() => Object.values(charts).forEach(c => c.resize()), 80);
  restartProgress();
}
```

- [ ] **Step 3: Atualizar renderAll()**

Substituir `function renderAll(d) {` por:

```js
function renderAll(d) {
  renderKPIs(d);
  renderVolume(d.volume_mensal);
  renderSLA(d.volume_mensal);
  renderCategorias(d.top_categorias);
  tecnicos = d.tecnicos;
  renderTecnicos();
  renderSatisfacao(d.satisfacao, d.comentarios);
  setTimeout(() => Object.values(charts).forEach(c => c.resize()), 100);
}
```

- [ ] **Step 4: Simplificar loadData()**

Substituir `async function loadData() {` por:

```js
async function loadData() {
  setStatus('loading', 'Carregando...');
  try {
    const res = await fetch(`${API_URL}?meses=12&_=${Date.now()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const d = await res.json();
    if (d.erro) throw new Error(d.erro);
    hideError();
    document.getElementById('overlay').classList.add('hidden');
    renderAll(d);
    setStatus('ok', d.gerado_em);
    document.getElementById('dt-update').textContent = d.gerado_em;
  } catch(e) {
    showError(`Erro: ${e.message} — verifique se api.php está acessível em ${API_URL}`);
    setStatus('err', 'Erro na conexão');
    document.getElementById('overlay').classList.add('hidden');
  }
}
```

- [ ] **Step 5: Atualizar bloco INIT e event listeners**

Substituir o bloco final do script (após `restartProgress`) por:

```js
// ── INIT ─────────────────────────────────────────────────────
loadData();
startRotation();
setInterval(loadData, 5 * 60 * 1000);

document.addEventListener('keydown', e => {
  if (e.key === 'ArrowRight') { clearInterval(rotTimer); nextScreen(); startRotation(); }
  if (e.key === 'ArrowLeft')  { clearInterval(rotTimer); goTo((current + 3) % 4); startRotation(); }
});
```

- [ ] **Step 6: Verificar end-to-end no browser**

Abrir `dashboard_live.html` e verificar:
1. Header: só logo + status. Sem dropdowns ou botões
2. Zona KPI: 5 cards em linha (Total → Resolvidos → Em Aberto → Hoje → SLA Violado)
3. Tela s0 (Gráficos) ativa ao carregar
4. Barra de progresso no rodapé preenche em 45s
5. Após 45s → s1 (Categorias com barras CSS)
6. Após 45s → s2 (Equipe, tabela top 10)
7. Após 45s → s3 (Satisfação)
8. Teclas ← → navegam entre telas
9. Console sem erros de JS
10. Se conectado à API: KPIs fixos exibem valores reais e permanecem visíveis durante toda a rotação

- [ ] **Step 7: Commit final**

```bash
git add dashboard_live.html
git commit -m "feat: TV mode completo — layout híbrido, KPIs fixos, rotação 45s"
```
