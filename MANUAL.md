# Manual do Dashboard TI — GLPI

> Documentação técnica completa do projeto. Atualizado em 05/06/2026.

---

## Visão Geral

O Dashboard TI é uma aplicação de monitoramento em tempo real dos chamados do GLPI. Ele é composto por dois arquivos que trabalham em conjunto:

- **`api.php`** — backend em PHP que consulta o banco MySQL do GLPI e devolve os dados em JSON
- **`dashboard_live.html`** — frontend HTML/JS que consome a API e exibe os dados em um painel visual rotativo

O fluxo é simples: o HTML faz chamadas HTTP para a API, recebe JSON, e renderiza os gráficos e cards na tela. A tela se atualiza automaticamente a cada 5 minutos e alterna entre 4 visões diferentes a cada 45 segundos.

```
Navegador → dashboard_live.html
                    │
                    │  3 requisições HTTP simultâneas (mês atual, mês anterior, ano)
                    ▼
             api.php (PHP)
                    │
                    │  SELECT no banco
                    ▼
         MySQL do GLPI (banco: glpi)
```

---

## 1. api.php — A API de Dados

### O que ela faz

Conecta ao banco MySQL do GLPI, executa 7 consultas SQL e retorna tudo em um único JSON. Ela é chamada três vezes a cada ciclo de atualização do dashboard (uma para o mês atual, uma para o mês anterior, e uma para o ano inteiro).

### Configuração

As credenciais do banco são lidas de um arquivo `.env` no mesmo diretório da API:

```ini
DB_HOST=ip_servidor
DB_PORT=porta
DB_NAME=nome_do_banco
DB_USER=[USUARIO_DB]
DB_PASS=sua_senha_aqui
```

Se o `.env` não existir ou estiver inválido, a API retorna HTTP 500 com mensagem de erro.

### Parâmetros aceitos pela URL

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `data_inicio` | `YYYY-MM-DD` | Início do período (usa em par com `data_fim`) |
| `data_fim` | `YYYY-MM-DD` | Fim do período |
| `meses` | `1`, `3`, `6`, `12` | Atalho de período — usado como fallback se as datas não forem informadas |
| `categoria` | inteiro | ID da categoria no GLPI para filtrar os dados. `0` = sem filtro |

O filtro de categoria é inteligente: ele inclui a categoria selecionada **e todas as suas subcategorias**, navegando pela hierarquia através do campo `completename` da tabela `glpi_itilcategories`.

### Dados retornados (estrutura do JSON)

```json
{
  "gerado_em": "05/06/2026 14:30:00",
  "periodo_meses": null,
  "categoria_id": 73,
  "data_inicio": "2026-06-01",
  "data_fim": "2026-06-05",
  "kpis": { ... },
  "volume_mensal": [ ... ],
  "top_categorias": [ ... ],
  "tecnicos": [ ... ],
  "satisfacao": { ... },
  "comentarios": [ ... ],
  "chamados_criticos": [ ... ],
  "aguardando_aprovacao_list": [ ... ]
}
```

### Detalhamento de cada seção do JSON

#### `kpis`

Um único objeto com os indicadores gerais do período filtrado:

| Campo | Descrição |
|-------|-----------|
| `total_chamados` | Total de tickets no período |
| `em_aberto` | Tickets com status diferente de Resolvido (5) e Fechado (6) |
| `fechados` | Tickets com status 5 ou 6 |
| `abertos_hoje` | Tickets abertos na data atual (independente do filtro de período) |
| `tmr_horas` | Tempo Médio de Resolução em horas, calculado só para tickets fechados com `solvedate` preenchido |
| `aguardando_aprovacao` | Contagem de tickets com status = 5 (resolvido, aguardando confirmação do usuário) |
| `sla_violado` | Tickets onde `solvedate > time_to_resolve` |
| `sla_cumprido` | Tickets onde `solvedate <= time_to_resolve` |
| `pct_sla` | Percentual de SLA cumprido — calculado apenas sobre os tickets que têm ambos os campos preenchidos |

#### `volume_mensal`

Array com uma entrada por mês no período, agrupado por `DATE_FORMAT(date, '%Y-%m')`. Cada item:

| Campo | Descrição |
|-------|-----------|
| `mes` | Ex.: `"Jun/26"` — rótulo para exibição |
| `mes_ordem` | Ex.: `"2026-06"` — usado para ordenar cronologicamente |
| `abertos` | Total de tickets abertos no mês |
| `fechados` | Tickets fechados no mês |
| `sla_ok` | Tickets resolvidos dentro do prazo naquele mês |
| `sla_total` | Total de tickets com SLA calculável naquele mês |

#### `top_categorias`

Top 8 categorias com mais chamados no período. Cada item:

| Campo | Descrição |
|-------|-----------|
| `cat` | Nome da categoria (ou `"Sem categoria"` se nula) |
| `total` | Volume de chamados |

#### `tecnicos`

Top 15 técnicos com pelo menos 3 chamados no período, ordenados por chamados resolvidos. Cada item:

| Campo | Descrição |
|-------|-----------|
| `nome` | Nome completo (`firstname + realname`) |
| `total` | Total de chamados distintos atribuídos |
| `resolvidos` | Chamados com status 5 ou 6 |
| `abertos` | Chamados ainda em aberto |
| `tmr` | Tempo Médio de Resolução em horas |
| `sla` | % de SLA cumprido do técnico |

O vínculo técnico↔ticket usa `glpi_tickets_users` com `type = 2` (técnico atribuído).

#### `satisfacao`

Dados da pesquisa de satisfação (`glpi_ticketsatisfactions`), filtrados pelo período:

| Campo | Descrição |
|-------|-----------|
| `total_enviadas` | Pesquisas enviadas |
| `respondidas` | Pesquisas com resposta |
| `media` | Nota média (escala 1–5) |
| `nota_1` a `nota_5` | Contagem por nota |
| `taxa_resposta` | % de pesquisas respondidas |

#### `comentarios`

Até 5 comentários mais recentes das pesquisas respondidas, com `comment` não nulo:

| Campo | Descrição |
|-------|-----------|
| `nota` | Nota dada pelo usuário |
| `comment` | Texto do comentário |
| `data` | Data da resposta formatada em `DD/MM/YYYY` |

#### `chamados_criticos`

Até 10 chamados em aberto no momento (sem filtro de data), ordenados por: SLA violado primeiro, depois prioridade decrescente, depois data de abertura crescente:

| Campo | Descrição |
|-------|-----------|
| `id` | ID do ticket no GLPI |
| `titulo` | Título do chamado |
| `priority` | Código de prioridade (1 a 6) |
| `categoria` | Nome da categoria |
| `tecnico` | Nome do técnico atribuído |
| `abertura` | Data/hora de abertura formatada |
| `prazo_sla` | Timestamp do prazo SLA |
| `sla_violado` | `1` se `NOW() > time_to_resolve`, `0` caso contrário |
| `horas_aberto` | Horas desde a abertura até agora |

#### `aguardando_aprovacao_list`

Até 7 chamados com status = 5 (resolvidos pela equipe, aguardando confirmação do usuário), ordenados pelo mais antigo primeiro:

| Campo | Descrição |
|-------|-----------|
| `id` | ID do ticket |
| `titulo` | Título |
| `priority` | Prioridade |
| `categoria` | Categoria |
| `tecnico` | Técnico responsável |
| `solucao` | Data da resolução |
| `horas_aguardando` | Horas desde a resolução até agora |

### Tabelas GLPI utilizadas

| Tabela | Uso |
|--------|-----|
| `glpi_tickets` | Tabela principal — todos os filtros e métricas |
| `glpi_tickets_users` | Vínculo ticket↔técnico (`type = 2`) |
| `glpi_users` | Nome dos técnicos |
| `glpi_itilcategories` | Nome e hierarquia das categorias |
| `glpi_ticketsatisfactions` | Pesquisas de satisfação |

### Códigos de status do GLPI

| Código | Significado |
|--------|-------------|
| 1 | Novo |
| 2 | Em Andamento (atribuído) |
| 3 | Em Andamento (planejado) |
| 4 | Em Espera |
| 5 | Resolvido (aguardando aprovação do usuário) |
| 6 | Fechado |

---

## 2. dashboard_live.html — O Painel Visual

### O que ele faz

Exibe os dados da API em um painel de TV/monitor, com 4 telas que se alternam automaticamente a cada 45 segundos. Os dados são atualizados a cada 5 minutos sem precisar recarregar a página.

### Configuração inicial (variáveis no topo do script)

```javascript
const API_URL      = '/dashboard/api.php'; // Caminho para a API
const CATEGORIA_ID = 73;                   // ID da categoria no GLPI (0 = sem filtro)
const SCREEN_TIME  = 45000;               // Tempo em cada tela (milissegundos)
const REFRESH_TIME = 300000;              // Intervalo de atualização dos dados (milissegundos)
```

### Como os dados são buscados

A cada ciclo de atualização, o dashboard faz **3 requisições simultâneas** à API:

| Requisição | Período | Finalidade |
|-----------|---------|-----------|
| `mes` | 1º dia do mês atual → hoje | Dados das Telas 1, 2 e 4 |
| `anterior` | 1º dia do mês anterior → último dia do mês anterior | Cálculo de deltas (variação vs mês anterior) |
| `ano` | 1º de janeiro do ano atual → hoje | Dados da Tela 3 |

Os resultados ficam em cache na variável `cache = { mes, anterior, ano }` e são reutilizados na renderização de todas as telas.

### Estrutura visual

O painel tem um header fixo no topo e 4 telas (`screens`) que se sobrepõem — apenas uma fica visível por vez, com transição de opacidade suave (0,7s). Uma barra de progresso abaixo do header mostra quanto tempo resta até a próxima tela.

---

### Tela 1 — Visão do Mês

**Título no header:** `[Mês] [Ano] — Visão do Mês`

Esta é a tela principal, exibida primeiro em cada ciclo. Resume o desempenho da equipe no mês atual.

**Layout:** duas colunas.

**Coluna esquerda:**

- **Hero SLA** — Card grande com o percentual de SLA do mês. A cor muda conforme o desempenho:
  - Verde (≥ 90%): meta cumprida
  - Âmbar (75–89%): atenção
  - Vermelho (< 75%): situação crítica
  - Inclui barra de progresso animada, frase explicativa e variação vs mês anterior (▲/▼ %)

- **3 Mini KPIs:**
  - **Resolvidos** — total de chamados fechados no mês, com variação vs mês anterior
  - **TMR Médio** — tempo médio de resolução em horas/dias, com comparação vs mês anterior
  - **Em Aberto** — chamados ainda não resolvidos, com contexto (quantos chegaram hoje)

**Coluna direita:**

- **Destaques da Equipe** — top 5 técnicos do mês, com ranking (ouro para 1º, prata para 2º), nome, chamados resolvidos, TMR e barra de carga colorida pelo SLA

- **Onde a Equipe Está Concentrada** — top 5 categorias do mês com barra de volume relativo

**Headline dinâmica** no topo da tela:
- Verde: `"A equipe está entregando — SLA de X% e Y chamados tratados no período."`
- Âmbar: `"Atenção: SLA em X%, abaixo da meta de 90%. Y chamados aguardam resolução."`
- Vermelho: `"Situação crítica — SLA em X% com Y chamados em aberto. Ação imediata necessária."`

---

### Tela 2 — Radar Operacional

**Título no header:** `Radar Operacional — [Mês] [Ano]`

Foco em chamados que precisam de atenção **agora** (tempo real, sem filtro de data).

**Layout:** duas colunas.

**Coluna esquerda — Chamados que Precisam de Atenção:**

Lista de até 7 chamados em aberto, priorizando os mais críticos. Cada item mostra:
- ID do ticket (com cor de alerta se violado)
- Título (truncado em 68 caracteres)
- Categoria e técnico responsável
- Badge de prioridade colorido (M.Baixa → Maior)
- Tempo em aberto (ex.: `3h`, `2d 4h`)

Código de cores das linhas:
- **Vermelho** (`viol`): SLA já violado
- **Âmbar** (`warn`): aberto há mais de 8h sem violação
- **Neutro**: dentro do prazo e tempo normal

**Coluna direita — Aguardando Aprovação:**

Lista de até 7 chamados com status Resolvido (5), aguardando o usuário confirmar o fechamento. Mostra ID, título, categoria, técnico e há quantas horas está aguardando.

**Headline dinâmica:**
- Verde: `"Tudo sob controle — nenhum chamado crítico no momento."`
- Vermelho: `"X chamados estão com SLA violado agora — ação necessária."`
- Âmbar: `"X chamados em aberto monitorados — nenhum com SLA violado até o momento."`

---

### Tela 3 — Desempenho do Ano

**Título no header:** `[Ano] — Desempenho Acumulado`

Visão consolidada do ano inteiro, de 1º de janeiro até hoje.

**Layout:** KPIs no topo + gráfico e ranking abaixo.

**3 KPIs anuais:**
- **Total no Ano** — total de chamados registrados no ano
- **SLA Médio Anual** — percentual de SLA do ano todo (cor verde/âmbar/vermelho)
- **TMR Médio Anual** — tempo médio de resolução no ano

**Gráfico de Volume Mensal (Chart.js):**

Gráfico combinado com dois eixos Y:
- **Barras azuis** — chamados abertos por mês
- **Barras verdes** — chamados fechados por mês
- **Linha âmbar** — % de SLA por mês (eixo direito, escala 0–100%)
- **Linha pontilhada âmbar** — marca a meta de 90% de SLA

O gráfico é destruído e recriado a cada atualização de dados para evitar acúmulo de instâncias.

**Ranking Anual:**

Top 5 técnicos do ano com mais chamados resolvidos, com destaque ouro/prata para os dois primeiros. Mostra nome, resolvidos, TMR e SLA%.

---

### Tela 4 — Voz do Cliente

**Título no header:** `Voz do Cliente — Satisfação`

Dados da pesquisa de satisfação do mês atual.

**Layout:** duas colunas.

**Coluna esquerda:**

- **Hero Score** — nota média de satisfação em destaque (ex.: `4.3 /5`), com:
  - Estrelas visuais animadas
  - Barra de progresso proporcional à nota
  - Frase contextual sobre o resultado
  - Cores: verde (≥ 4,0), âmbar (3,0–3,9), vermelho (< 3,0)

- **2 Mini KPIs:**
  - **Avaliações** — quantidade de pesquisas respondidas (de X enviadas)
  - **Taxa Resposta** — % dos usuários que responderam a pesquisa

**Coluna direita:**

- **Distribuição das Notas** — barras horizontais mostrando a contagem de respostas por nota (5 a 1), com cores verde (4–5), âmbar (3) e vermelho (1–2)

- **O que os Usuários Disseram** — até 3 comentários mais recentes das pesquisas respondidas, com a nota em estrelas e a data da resposta

**Headline dinâmica:**
- Verde: `"Excelência confirmada — nota média X/5 em Y avaliações recebidas."`
- Verde moderado: `"Bom desempenho — usuários avaliaram o suporte com X/5 no período."`
- Âmbar: `"Satisfação moderada em X/5 — oportunidade de melhoria identificada."`
- Vermelho: `"Atenção: usuários insatisfeitos — nota média X/5. Revisão necessária."`

Se não houver nenhuma avaliação respondida, a tela exibe estado vazio.

---

## 3. Sistema de Storytelling

O dashboard não exibe apenas números — cada KPI vem acompanhado de uma frase em português que interpreta o dado em contexto. Isso é feito por funções JavaScript puras:

| Função | O que gera |
|--------|-----------|
| `slaStory(pct)` | Frase sobre o SLA atual ("Desempenho excepcional — 9 em cada 10...") |
| `headlineStory(sla, total, abertos)` | Frase para o título de cada tela |
| `tmrStory(h, prevH)` | Frase sobre o TMR comparando com mês anterior |
| `emAbertoStory(abertos, hoje, prev)` | Contexto para chamados em aberto |
| `satisfacaoHeadline(media, respondidas)` | Frase para a headline da Tela 4 |
| `satisfacaoStory(media, respondidas, total)` | Detalhamento da satisfação |

---

## 4. Deploy

### Pré-requisitos

- Servidor web com PHP 7.4+ e extensão PDO MySQL ativada
- Usuário MySQL `[USUARIO_DB]` com permissão `SELECT` nas tabelas do banco `glpi`
- Acesso de rede do servidor web ao MySQL (IP `[IP_SERVIDOR]`, porta `3306`)

### Passos

1. Copiar `api.php` para `/var/www/html/dashboard/api.php` no servidor
2. Criar o arquivo `/var/www/html/dashboard/.env` com as credenciais reais:
   ```ini
   DB_HOST=ip_servidor
   DB_PORT=porta
   DB_NAME=nome_do_banco
   DB_USER=[USUARIO_DB]
   DB_PASS=senha_real_aqui
   ```
3. Garantir que o `.env` não seja acessível via web (configure o servidor para bloquear acesso direto a arquivos `.env`)
4. Abrir `dashboard_live.html` no navegador — pode ser servido como arquivo estático ou via servidor web
5. Se necessário, ajustar `API_URL` e `CATEGORIA_ID` no início do bloco `<script>` do HTML

### Verificação

Acesse diretamente a URL da API no navegador:
```
http://[seu-servidor]/dashboard/api.php?data_inicio=2026-06-01&data_fim=2026-06-05
```

Deve retornar um JSON com os campos `kpis`, `tecnicos`, `satisfacao` etc. Se aparecer `{"erro": ...}`, o problema está na conexão com o banco ou no `.env`.

---

## 5. Observações de Manutenção

**Categoria fixa no código:** O valor `CATEGORIA_ID = 73` no HTML filtra todos os dados para uma categoria específica do GLPI. Para exibir dados de **todas** as categorias, altere para `0`.

**CORS:** A API está com `Access-Control-Allow-Origin: *` — adequado para ambiente interno. Em produção com acesso externo, restrinja ao domínio do dashboard.

**Timeout de banco:** Configurado para 5 segundos. Se o banco estiver lento, a API retornará erro e o dashboard mostrará "Erro na conexão com a API" no indicador de status.

**Mínimo de chamados para técnicos:** Técnicos com menos de 3 chamados no período não aparecem na tabela de desempenho.

**SLA calculado apenas quando completo:** Se um ticket não tem `time_to_resolve` ou `solvedate` preenchido, ele é excluído do cálculo de SLA (não penaliza nem contribui para o percentual).
