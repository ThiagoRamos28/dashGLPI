# Dashboard TI — GLPI

## Visão Geral

Dashboard de gestão de chamados e desempenho da equipe de TI, integrado ao banco de dados do GLPI via API PHP. Exibe KPIs, gráficos e tabela de técnicos com dados em tempo real.

## Estrutura do Projeto

```
dashGLPI/
├── api.php              # API PHP — consulta o MySQL do GLPI e retorna JSON
├── dashboard_live.html  # Frontend — consome a API e renderiza os gráficos
└── CLAUDE.md            # Este arquivo
```

## api.php

### Configuração

| Constante | Valor atual | Descrição |
|-----------|-------------|-----------|
| `DB_HOST` | `[IP_SERVIDOR]` | IP do servidor MySQL |
| `DB_PORT` | `3306` | Porta MySQL |
| `DB_NAME` | `glpi` | Nome do banco |
| `DB_USER` | `[USUARIO_DB]` | Usuário (somente leitura) |
| `DB_PASS` | *(configurar)* | Senha — substituir `SUA_SENHA_AQUI` |

### Parâmetros de Query

- `?meses=` — filtra o período: `1`, `3`, `6` ou `12` (padrão: `12`)

### Endpoints / Estrutura do JSON retornado

```json
{
  "gerado_em": "01/06/2026 10:30:00",
  "periodo_meses": 12,
  "data_inicio": "2025-06-01",
  "kpis": { ... },
  "volume_mensal": [ ... ],
  "por_status": [ ... ],
  "por_prioridade": [ ... ],
  "por_tipo": [ ... ],
  "top_categorias": [ ... ],
  "tmr_prioridade": [ ... ],
  "tecnicos": [ ... ]
}
```

### Seções de dados

| Chave | O que retorna |
|-------|--------------|
| `kpis` | Total de chamados, em aberto, fechados, abertos hoje, TMR médio, SLA violado/cumprido/% |
| `volume_mensal` | Abertos e fechados por mês + dados de SLA mensal |
| `por_status` | Contagem por status (Novo, Em Andamento, Em Espera, Resolvido, Fechado) |
| `por_prioridade` | Contagem por prioridade (Muito Baixa → Maior) |
| `por_tipo` | Incidente vs Requisição |
| `top_categorias` | Top 8 categorias por volume de chamados |
| `tmr_prioridade` | Tempo Médio de Resolução (horas) por prioridade |
| `tecnicos` | Top 15 técnicos com total, resolvidos, abertos, SLA% e TMR (mín. 3 chamados) |

### Tabelas GLPI utilizadas

- `glpi_tickets` — tabela principal
- `glpi_tickets_users` — vínculo ticket↔usuário (type=2 = técnico atribuído)
- `glpi_users` — dados dos técnicos
- `glpi_itilcategories` — categorias dos chamados

### Status dos tickets no GLPI

| Código | Label |
|--------|-------|
| 1 | Novo |
| 2, 3 | Em Andamento |
| 4 | Em Espera |
| 5 | Resolvido |
| 6 | Fechado |

---

## dashboard_live.html

### Configuração

No início do bloco `<script>`, ajuste a variável:

```js
const API_URL = '/dashboard/api.php';
// Exemplo com IP: const API_URL = 'http://[IP_SERVIDOR]/dashboard/api.php';
```

### Funcionalidades

- **Filtro de período** — 1, 3, 6 ou 12 meses (dropdown no header)
- **Auto-refresh** — atualiza automaticamente a cada 5 minutos
- **Status visual** — indicador no header mostra se a API está OK, carregando ou com erro
- **Ordenação** — colunas da tabela de técnicos são clicáveis para ordenar

### Seções visuais

| Seção | Tipo | Dados |
|-------|------|-------|
| KPIs | Cards numéricos | Total, abertos, resolvidos, SLA violado, TMR, abertos hoje |
| Volume Mensal | Bar + Line chart | Chamados abertos (barras) + fechados (linha) |
| % SLA | Line chart | Evolução mensal do SLA com linha de meta em 90% |
| Por Status | Doughnut chart | Distribuição de status atual |
| Por Prioridade | Barra horizontal | Volume por nível de prioridade |
| Incidente vs Requisição | Doughnut chart | Tipo dos chamados |
| Top Categorias | Barra horizontal | 8 categorias mais frequentes |
| TMR por Prioridade | Bar chart | Horas médias de resolução por prioridade |
| Técnicos | Tabela | Ranking com SLA%, TMR e barra de carga |

### Biblioteca

- **Chart.js 4.5.1** — via CDN (jsdelivr com integrity hash)

---

## Deploy

1. Copiar `api.php` para `/var/www/html/dashboard/api.php` no servidor web
2. Editar `api.php`: substituir `SUA_SENHA_AQUI` pela senha real do usuário `[USUARIO_DB]`
3. Garantir que o usuário MySQL `[USUARIO_DB]` tenha permissão `SELECT` nas tabelas do banco `glpi`
4. Abrir `dashboard_live.html` no navegador (pode ser servido como arquivo estático ou via servidor web)
5. Ajustar `API_URL` no HTML se necessário

## Observações

- `Access-Control-Allow-Origin: *` está habilitado na API — restringir ao domínio correto em produção
- O timeout de conexão com o banco está configurado para 5 segundos
- Técnicos com menos de 3 chamados são excluídos da tabela de desempenho
- SLA é calculado apenas quando `time_to_resolve` e `solvedate` estão preenchidos

## Instruções para o Claude

- **Nunca salvar ou memorizar endereços de servidor** (IPs, hostnames, URLs internas). Sempre que precisar referenciar um endereço em exemplos, documentação ou código gerado, usar placeholders genéricos como `[IP_SERVIDOR]`, `192.168.x.x`, `servidor.local` ou `[HOST]`.
- Credenciais, senhas e tokens também nunca devem ser memorizados — usar `[SENHA]`, `SUA_SENHA_AQUI` etc.
