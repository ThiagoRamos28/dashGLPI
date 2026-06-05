# Painel Gerencial TI — Power BI

Dashboard gerencial do departamento de TI, conectado ao banco MariaDB do GLPI.

---

## Visão geral

Painel de uso interno para gestão do time de TI. Foco em decisão gerencial: desempenho por técnico, cumprimento de SLA, satisfação dos usuários e riscos operacionais. Não é painel de TV — é ferramenta de análise.

**Arquivo:** `Painel Gerencial TI.pbix`
**Ferramenta:** Power BI Desktop
**Refresh:** Manual

---

## Fonte de dados

| Campo | Valor |
|-------|-------|
| Host | `[IP_SERVIDOR_DB]` |
| Porta | `3306` |
| Banco | `glpi` |
| Usuário | `[USUARIO_DB]` |
| Tipo | MariaDB (via conector MySQL) |

> O usuário de banco tem permissão somente leitura (`SELECT`) nas tabelas do GLPI.

**Pré-requisito:** instalar o [MySQL ODBC Connector 64-bit](https://dev.mysql.com/downloads/connector/odbc/) antes de abrir o arquivo no Power BI Desktop.

---

## Tabelas do modelo

| Nome no modelo | Tabela de origem | Tipo | Descrição |
|----------------|-----------------|------|-----------|
| `f_Chamados` | `glpi_tickets` | Fato | Chamados — tabela principal |
| `f_ChamadosTecnicos` | `glpi_tickets_users` | Fato auxiliar | Vínculo chamado↔técnico (type=2) |
| `d_Tecnicos` | `glpi_users` | Dimensão | Dados dos técnicos |
| `d_Categorias` | `glpi_itilcategories` | Dimensão | Categorias com hierarquia de 3 níveis |
| `f_Satisfacao` | `glpi_ticketsatisfactions` | Fato | Avaliações de satisfação dos chamados |
| `d_Calendario` | *(calculada no DAX)* | Dimensão de datas | Calendário de 01/01/2026 até hoje |

---

## Transformações no Power Query

### f_Chamados (glpi_tickets)
- Colunas mantidas: `id`, `Titulo`, `Data_Abertura`, `Data_Resolução`, `status`, `type`, `priority`, `Sla_Prazo`, `itilcategories_id`, `is_deleted`
- Filtro aplicado: `is_deleted = 0`
- Renomeamentos:

| Original | Renomeado |
|----------|-----------|
| `name` | `Titulo` |
| `date` | `Data_Abertura` |
| `solvedate` | `Data_Resolução` |
| `time_to_resolve` | `Sla_Prazo` |

### f_ChamadosTecnicos (glpi_tickets_users)
- Filtro aplicado: `type = 2` (somente técnicos atribuídos)
- Colunas mantidas: `tickets_id`, `users_id`

### d_Tecnicos (glpi_users)
- Colunas mantidas: `id`, `firstname`, `realname`, `name`
- Coluna personalizada adicionada:
  ```
  Nome_Completo = [firstname] & " " & [realname]
  ```

### d_Categorias (glpi_itilcategories)
- Colunas mantidas: `id`, `name`, `completename`
- Três colunas de hierarquia adicionadas via **Coluna Personalizada**:

**Nivel1**
```
Text.BeforeDelimiter([completename], " > ")
```

**Nivel2**
```
let p = Text.Split([completename], " > ") in if List.Count(p) >= 2 then p{1} else null
```

**Nivel3**
```
let p = Text.Split([completename], " > ") in if List.Count(p) >= 3 then p{2} else null
```

### f_Satisfacao (glpi_ticketsatisfactions)
- Colunas mantidas: `tickets_id`, `satisfaction`, `date_answered`, `comment`

---

## Modelo de dados

### Tabela calculada DAX — d_Calendario

Criada em **Ferramentas de Tabela → Nova Tabela**:

```dax
d_Calendario =
ADDCOLUMNS(
    CALENDAR(DATE(2026, 1, 1), TODAY()),
    "Ano",          YEAR([Date]),
    "Mês Num",      MONTH([Date]),
    "Mês Nome",     FORMAT([Date], "MMMM", "pt-BR"),
    "Mês Abrev",    FORMAT([Date], "MMM", "pt-BR"),
    "Ano-Mês",      FORMAT([Date], "YYYY-MM"),
    "Trimestre",    "T" & QUARTER([Date]),
    "Semana",       WEEKNUM([Date]),
    "Dia Semana",   FORMAT([Date], "dddd", "pt-BR"),
    "É Fim Semana", IF(WEEKDAY([Date], 2) >= 6, 1, 0)
)
```

> Marcar a coluna `Date` como **tabela de datas** em Ferramentas de Tabela → Marcar como Tabela de Datas.

---

### Relacionamentos

| De | Para | Ativo? |
|----|------|--------|
| `d_Calendario[Date]` | `f_Chamados[Data_Abertura]` | ✅ Ativo |
| `d_Calendario[Date]` | `f_Chamados[Data_Resolução]` | ❌ Inativo |
| `f_Chamados[id]` | `f_ChamadosTecnicos[tickets_id]` | ✅ Ativo |
| `f_ChamadosTecnicos[users_id]` | `d_Tecnicos[id]` | ✅ Ativo |
| `f_Chamados[itilcategories_id]` | `d_Categorias[id]` | ✅ Ativo |
| `f_Chamados[id]` | `f_Satisfacao[tickets_id]` | ✅ Ativo |

> O relacionamento inativo com `Data_Resolução` permite medir throughput real (resolvidos no mês) via `USERELATIONSHIP`.

---

## Medidas DAX

```dax
Total Chamados = COUNTROWS(f_Chamados)

Abertos =
CALCULATE(
    COUNTROWS(f_Chamados),
    f_Chamados[status] IN {1, 2, 3, 4})

Fechados =
CALCULATE(
    COUNTROWS(f_Chamados),
    f_Chamados[status] IN {5, 6})

SLA % =
VAR ComSLA =
    CALCULATE(
        COUNTROWS(f_Chamados),
        f_Chamados[status] IN {5, 6},
        f_Chamados[Sla_Prazo] <> BLANK(),
        f_Chamados[Data_Resolução] <= f_Chamados[Sla_Prazo])
VAR TotalSLA =
    CALCULATE(
        COUNTROWS(f_Chamados),
        f_Chamados[status] IN {5, 6},
        f_Chamados[Sla_Prazo] <> BLANK())
RETURN DIVIDE(ComSLA, TotalSLA, 0) * 100

TMR Horas =
AVERAGEX(
    FILTER(f_Chamados,
        f_Chamados[status] IN {5, 6} &&
        f_Chamados[Data_Resolução] <> BLANK()),
    DATEDIFF(f_Chamados[Data_Abertura], f_Chamados[Data_Resolução], HOUR))

Satisfação Média = AVERAGE(f_Satisfacao[satisfaction])

Criados no Mês = COUNTROWS(f_Chamados)

Resolvidos no Mês =
CALCULATE(
    COUNTROWS(f_Chamados),
    USERELATIONSHIP(d_Calendario[Date], f_Chamados[Data_Resolução]),
    f_Chamados[Data_Resolução] <> BLANK())
```

---

## Estrutura do painel

### Página 1 — Visão Geral
| Visual | Tipo | Campos |
|--------|------|--------|
| KPIs | 5 cartões | Total Chamados, Abertos, Fechados, SLA %, TMR Horas |
| Volume mensal | Colunas + linha | Eixo X: `d_Calendario[Date]` por mês · Barras: `Criados no Mês` · Linha: `Resolvidos no Mês` |
| Filtro período | Segmentação | `d_Calendario[Date]` estilo Entre |

### Página 2 — Equipe
| Visual | Tipo | Campos |
|--------|------|--------|
| Performance | Tabela | Técnico, Total, Abertos, Fechados, SLA %, TMR Horas |
| Carga | Barras horizontais | Total por técnico |
| Filtros | Segmentações | Período + `d_Categorias[Nivel1]` |

### Página 3 — Operacional
| Visual | Tipo | Campos |
|--------|------|--------|
| Top categorias | Barras horizontais | `d_Categorias[Nivel2]` por Total |
| SLA por área | Barras | SLA % por `d_Categorias[Nivel1]` |
| Satisfação | Tabela | Técnico × Satisfação Média |
| Tickets parados | Tabela | Tickets com `status IN {1,2,3,4}` e `Data_Abertura < HOJE()-7` |

---

## Referências

### Status dos chamados

| Código | Label |
|--------|-------|
| 1 | Novo |
| 2 | Atribuído |
| 3 | Planejado |
| 4 | Em espera |
| 5 | Resolvido |
| 6 | Fechado |

### Prioridades

| Código | Label |
|--------|-------|
| 1 | Muito baixa |
| 2 | Baixa |
| 3 | Média |
| 4 | Alta |
| 5 | Muito alta |
| 6 | Maior |

---

## Índices MySQL recomendados

```sql
ALTER TABLE glpi_tickets
  ADD INDEX idx_dash_date_status (date, status, is_deleted);

ALTER TABLE glpi_tickets_users
  ADD INDEX idx_dash_ticket_type (tickets_id, type);
```

---

## Histórico de decisões

| Decisão | Motivo |
|---------|--------|
| Power BI Desktop (não Service) | Sem necessidade de publicação; refresh manual por enquanto |
| `d_Categorias` via Power Query | Hierarquia extraída com `Text.Split` diretamente na query — elimina dependência de tabela calculada DAX e simplifica o modelo |
| Relacionamento inativo com `Data_Resolução` | Permite medir throughput real (resolvidos no mês) via `USERELATIONSHIP` |
| `d_Calendario` a partir de 01/01/2026 | GLPI implantado em 2026 — dados anteriores não existem |
| Nomes com prefixo `f_` / `d_` | Convenção clara: `f_` = fato, `d_` = dimensão |
