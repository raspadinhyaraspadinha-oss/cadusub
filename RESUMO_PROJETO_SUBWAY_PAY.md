# Resumo do Projeto Subway Pay – Onboarding para IA

Este documento é um estudo profundo do projeto **Subway Pay**: uma aplicação web no estilo “Subway Surfers” onde o usuário (lead) entra, se cadastra, deposita via PIX, joga uma corrida com apostas em dinheiro real e, se atingir a meta no jogo, pode sacar o ganho. O backend não define probabilidade de vitória; a vitória/derrota é determinada pelo desempenho do jogador no cliente (correr sem bater).

---

## 1. Visão geral da stack e estrutura

- **Backend:** PHP (sem framework; scripts por pasta). Compatível com PHP 8.x (cPanel/.htaccess).
- **Banco de dados:** MySQL. Conexão via `mysqli` e em alguns fluxos PDO.
- **Frontend:** HTML/CSS/JS (jQuery em partes), assets estilo Webflow. Jogo em JS (bundle tipo Subway Surfers).
- **Configuração de banco:** Raiz do projeto em `conectarbanco.php` (array `$config`: `db_host`, `db_user`, `db_pass`, `db_name`). Admin usa `adm/config/bd.php`, que inclui `conectarbanco.php` e adiciona validação de sessão admin e referer.
- **Document root:** O app roda na raiz do projeto (não há pasta `public/` separada). Entrada principal: `index.php`.

**Pastas principais:**

| Pasta / arquivo | Função |
|-----------------|--------|
| `index.php` | Landing; define `realBetPage` no localStorage para controle de acesso ao jogo real. |
| `conectarbanco.php` | Configuração única do banco (host, user, pass, db_name). |
| `cadastrar/` | Cadastro de lead (com suporte a `?aff=` para afiliado). |
| `login/` | Login; define `$_SESSION["email"]` e redireciona para `/deposito`. |
| `deposito/` | Fluxo de depósito PIX: formulário, geração de QR/código, `pix.php`, consulta de pagamento. |
| `webhook/` | Callback do gateway PIX (SuitPay): `webhook/pix.php` é o ativo. |
| `painel/` | Dashboard logado: saldo, botões de aposta (R$ 1, 2, 5), link para jogar ou demo. |
| `jogar/` | Jogo com dinheiro real (runner Subway Surfers). Parâmetros na URL. |
| `demo/` | Modo demonstração (sem saldo real). |
| `gameover/` | Páginas de resultado: `win.php` (credita saldo), `loss.php` (debita aposta + comissão afiliado). |
| `enddemo/` | Telas de fim do demo (win/loss). |
| `auth/` | API usada pelo jogo: win (`auth/index.php`) e loss (`auth/percas.php`) para atualizar saldo/GGR. |
| `saque/` | Saque do usuário (PIX). |
| `saque-afiliado/` | Saque de comissão de afiliado (PIX). |
| `adm/` | Painel administrativo: config, usuários, gateway, GGR, depósitos, saques, planos, pixels. |
| `afiliate/` | Área do afiliado (link, estatísticas, saque). |
| `influencer/`, `presell/` | Fluxos alternativos (landing, jogo teste). |
| `legal/`, `obrigado/` | Termos e página de agradecimento pós-depósito. |

---

## 2. Fluxo do lead/usuário (entrada → jogo → depósito → saque)

1. **Entrada:** Acesso ao site → `index.php` (landing). Pode vir com `?aff=ID` para atribuir a um afiliado.
2. **Cadastro:** `cadastrar/index.php` — coleta nome, email, senha, telefone; grava em `appconfig` com `lead_aff` (afiliado); define `$_SESSION["email"]` e `$_SESSION["user_id"]`; redireciona para `/deposito`.
3. **Login:** `login/index.php` — email + senha; valida em `appconfig`; mesma sessão; redireciona para `/deposito`.
4. **Depósito:** Usuário em `/deposito` preenche nome, CPF e valor; sistema gera PIX (SuitPay) e redireciona para `deposito/pix.php` (QR/código). Quando o pagamento é confirmado pelo webhook, o saldo é creditado (detalhes em “Gateways de pagamento”).
5. **Painel:** Em `/painel` o usuário vê saldo e escolhe aposta (R$ 1, 2 ou 5) e clica em “Jogar”. O painel redireciona para:
   - **Jogo real:** `/jogar/?jogarsubway=1BC|2BC|3BC&SbS{B1C2|B1C3|B1C4}` (aposta + dificuldade).
   - **Demo:** POST para `../demo` (sem usar saldo).
6. **Jogo:** Em `jogar/index.php` é verificado `localStorage.realBetPage === 'true'` (setado pelo painel ao clicar em Jogar); caso contrário, redireciona para o painel. O jogo roda no cliente; ao fim:
   - **Vitória:** formulário POST para `../gameover/win.php` com `msg` = valor acumulado (em reais).
   - **Derrota:** POST para `../gameover/loss.php` com valor da aposta; saldo é debitado e comissão do afiliado é creditada, se houver.
7. **Saque:** Em `/saque` o usuário informa valor, nome e chave PIX (CPF); `saque/saque.php` valida saldo, debita e registra em `saques`; pode chamar API interna de pagamento (`adm/saques/payment_auto.php`).

**Sessão:** Uso consistente de `session_start()` e `$_SESSION["email"]` nas áreas de usuário; admin usa `$_SESSION['emailadm']`.

---

## 3. Gateways de pagamento

### 3.1 Visão geral

- **Único gateway de pagamento implementado:** **SuitPay** (PIX).
- Credenciais ficam na tabela `gateway`: `client_id`, `client_secret`. Configuráveis pelo admin em `adm/gateway/`.
- Não há integração com Mercado Pago ou outros gateways no código analisado.

### 3.2 Depósito (entrada de dinheiro)

- **Endpoint SuitPay:** `https://ws.suitpay.app/api/v1/gateway/request-qrcode` (POST, JSON).
- **Headers:** `Content-Type: application/json`, `ci: {client_id}`, `cs: {client_secret}`.
- **Fluxo:**
  1. **deposito/index.php:** Usuário logado envia nome, CPF e valor. Valida valor mínimo (`app.deposito_min`). Monta payload com `amount` (base64 decode de "YW1vdW50" no código), `requestNumber`, `dueDate`, `client` (name, document, email), `callbackUrl` = `{baseUrl}/webhook/pix.php`. Chama SuitPay; recebe `idTransaction` e `paymentCode`.
  2. Insere em `confirmar_deposito`: `email`, `valor`, `externalreference` = `idTransaction`, `status` = `WAITING_FOR_APPROVAL`, `data`.
  3. Redireciona para `deposito/pix.php?pix_key={paymentCode}&token={idTransaction}` para exibir QR/código PIX.
- **Webhook (confirmação):** `webhook/pix.php` — só aceita POST; lê JSON do body; exige `typeTransaction === "PIX"` e `statusTransaction === "PAID_OUT"`. Busca em `confirmar_deposito` por `externalreference`; evita processar duas vezes (idempotência). Atualiza `confirmar_deposito.status` para `PAID_OUT`; soma valor em `appconfig.depositou` e em `appconfig.saldo`; aplica lógica de **primeiro depósito e CPA afiliado** (ver “Afiliados e CPA”).
- **Observação:** Há `webhook/index.php` legado (usa sessão e tabela `pix_deposito`); o fluxo principal é `webhook/pix.php` com `confirmar_deposito`.

### 3.3 Saque (saída de dinheiro)

- **Usuário:** `saque/index.php` exibe formulário (saldo, mínimo de `app.saques_min`). `saque/saque.php` recebe nome, CPF (chave PIX), valor; valida saldo; debita `appconfig.saldo`; insere em `saques` (email, externalreference, destino, chavepix, data, valor, status). Opcionalmente chama `https://{dominio}/adm/saques/payment_auto.php` (POST com chavepix, valor, id) para efetivar o PIX; se a resposta for “Pagamento realizado com sucesso”, mantém o registro.
- **Afiliado:** `saque-afiliado/index.php` — saque a partir de `appconfig.saldo_comissao`; registro em `saque_afiliado`. Valor mínimo também vem de `app.saques_min`.
- **Admin:** Listagem e alteração de status em `adm/saques/` e `adm/saques-afiliados/`.

---

## 4. Jogo e “chances” de vitória

### 4.1 Natureza do jogo

- Jogo no estilo **runner** (Subway Surfers): o personagem corre; o jogador desvia de obstáculos e coleta moedas. **Não há RNG (sorte) no servidor.** Vitória ou derrota é definida no **cliente**:
  - **Derrota:** colisão com obstáculo (game over).
  - **Vitória:** atingir a “meta” de valor acumulado (em reais) antes de bater.

### 4.2 Parâmetros da partida (URL)

- **Aposta:** `jogarsubway=1BC|2BC|3BC` → valor numérico: 1BC = R$ 1, 2BC = R$ 2, 3BC = R$ 5.
- **Dificuldade:** `SbS=B1C2|B1C3|B1C4` (vem de `app.dificuldade_jogo`: `facil` → B1C2, `medio` → B1C3, `dificil` → B1C4). Afeta apenas a **velocidade base** do jogo (quanto mais difícil, mais rápido), tornando mais difícil jogar por tempo suficiente para atingir a meta. Não há “probabilidade de vitória” configurável no backend.

### 4.3 Meta de vitória e valor creditado (código)

- Em `jogar/js/dependencies.bundle.js`:
  - `aposta`: valor da aposta (1, 2 ou 5) conforme URL.
  - `xmeta = 7` → **meta = aposta * 7** (meta em reais para vencer).
  - No jogo, o valor acumulado em reais é calculado a partir das moedas: algo como `numberMoney = parseFloat(coins * 0.10 * multiplies).toFixed(2)` (com `multiplies` ligado ao tipo de aposta 1BC/2BC/3BC).
  - Quando **acumulado >= meta**, o cliente submete um form POST para `../gameover/win.php` com `msg` = valor acumulado (string).
- **Importante:** No painel está escrito “Sua meta(ganho) é 10x o valor apostado!”; no código da meta de vitória está **7x** (`xmeta = 7`). O valor efetivamente creditado é o que o cliente envia em `msg` (até que o servidor valide algo diferente; hoje `gameover/win.php` confia no valor recebido para crédito).

### 4.4 Backend – vitória e derrota

- **Vitória:**  
  - **gameover/win.php:** Requer sessão; se existir `$_POST["msg"]`, trata como valor ganho: atualiza `appconfig.saldo` e `appconfig.ganhos` (soma o valor). Não recalcula multiplicador no servidor. Define `realBetPage = 'false'` no front para evitar reentrada direta na página de aposta real.
  - **auth/index.php:** GET com `action=game&type=win` e `val={acumulado}`; atualiza `appconfig.saldo` e `appconfig.ganhos`. Pode ser usado pelo cliente em paralelo ou em fluxo alternativo.
- **Derrota:**  
  - **gameover/loss.php:** Recebe aposta (bet) via POST; debita `appconfig.saldo`; incrementa `appconfig.percas`; se o usuário tem `lead_aff`, credita comissão ao afiliado (campo `plano` em % sobre a aposta) em `appconfig.saldo_comissao`.
  - **auth/percas.php:** POST com `action=game&type=lose` e `bet`; atualiza tabela `ggr` (total_percas, ggr_total, debito_ggr, credito_ggr, ggr_pago, status_ggr). Não debita saldo do usuário (a débito é feita em `gameover/loss.php`).

Resumo: **não existe “chance de vitória” ou RNG no servidor.** A “chance” é 100% dependente da habilidade do jogador em não colidir e em acumular valor até a meta (7x da aposta no código atual). A dificuldade só altera a velocidade do jogo.

---

## 5. Depósitos – detalhes e regras de negócio

- **Mínimo:** `app.deposito_min` (ex.: 20 no seed). Usado na validação do formulário e na regra de CPA.
- **Fluxo:** Formulário (nome, CPF, valor) → SuitPay → `confirmar_deposito` → usuário paga PIX → webhook `PAID_OUT` → crédito em `appconfig.saldo` e `depositou`.
- **Primeiro depósito e CPA:** No `webhook/pix.php`, se já existir pelo menos um depósito confirmado para o email e o usuário tiver `afiliado` e valor >= `app.deposito_min_cpa`, é aplicada a lógica de CPA: `rand(0, 100) <= app.chance_afiliado`; se verdadeiro, credita CPA no afiliado (`appconfig.saldo_cpa` do afiliado). Valor do CPA pode vir do plano do afiliado (`appconfig.cpa`) ou de `app.cpa`. `chance_afiliado` (ex.: 80) é a probabilidade percentual de o afiliado ganhar esse CPA no primeiro depósito qualificado.

---

## 6. Saques – limites e taxa

- **Mínimo:** `app.saques_min` (ex.: 100 no seed).
- **Rollover e taxa:** `app.rollover_saque` e `app.taxa_saque` existem no schema; verificar uso em `saque/saque.php` e telas (podem ser apenas configuráveis no admin).
- **Fluxo:** Usuário informa valor, nome e chave PIX (CPF); backend valida saldo, debita e registra; opcionalmente chama `payment_auto.php` para enviar o PIX.

---

## 7. Banco de dados (principais tabelas)

- **app:** Configuração global (uma linha): `deposito_min`, `saques_min`, `aposta_min`, `aposta_max`, `dificuldade_jogo`, `rollover_saque`, `taxa_saque`, `chance_afiliado`, `cpa`, `deposito_min_cpa`, `revenue_share`, `nome_unico`, `nome_um`, `nome_dois`, tags Google/Facebook, etc.
- **appconfig:** Usuários/leads: `id`, `nome`, `email`, `senha`, `cpf`, `telefone`, `saldo`, `depositou`, `sacou`, `percas`, `ganhos`, `lead_aff`, `linkafiliado`, `afiliado`, `saldo_comissao`, `plano`, `cpa`, `status_primeiro_deposito`, `data_cadastro`, etc.
- **gateway:** `id`, `client_id`, `client_secret` (SuitPay).
- **confirmar_deposito:** `email`, `externalreference`, `valor`, `status`, `data`.
- **saques:** `email`, `externalreference`, `destino`, `chavepix`, `data`, `valor`, `status`.
- **saque_afiliado:** `email`, `nome`, `pix`, `valor`, `status`.
- **ggr:** Métricas de GGR (total_percas, ggr_total, debito_ggr, credito_ggr, ggr_pago, status_ggr, etc.).
- **game:** `email`, `entry_value`, `out_value` (registro de partidas).
- **planos:** Planos de afiliado (cpa, rev, indicacao, valor_saque_maximo, saque_diario, etc.).
- **admlogin**, **pix**, **pix_deposito**, **token:** Suporte a login admin, PIX legado e tokens.

Schema completo e seeds em `sql_subway.sql`.

---

## 8. Afiliados e CPA

- Lead pode entrar com `?aff=ID`; `ID` é gravado em `appconfig.lead_aff` e associado ao `afiliado` (id do afiliado em `appconfig`).
- **Revenue share (perda):** Em `gameover/loss.php`, se há `lead_aff`, o campo `plano` do afiliado (percentual) é aplicado sobre o valor da aposta e creditado em `appconfig.saldo_comissao` do afiliado.
- **CPA (primeiro depósito):** No webhook PIX, na primeira vez que o depósito qualificado é confirmado, com `chance_afiliado` (rand 0–100) decide se o afiliado recebe CPA em `saldo_cpa` (ou equivalente). Valores e limites em `app` e no plano.
- Área do afiliado: `afiliate/`; saque de comissão: `saque-afiliado/`.

---

## 9. Pontos de atenção para outra IA

1. **Segurança:** Uso de concatenação de SQL em vários pontos (ex.: `webhook/pix.php`, consultas com `$email`); ideal migrar para prepared statements em todos os fluxos. `auth/index.php` usa interpolação direta em `$acumulado` na query.
2. **Consistência:** Meta no jogo é 7x no código; texto no painel fala em 10x. Decidir regra (7x ou 10x) e alinhar código e copy.
3. **Webhook:** `webhook/pix.php` grava `file_put_contents("teste.txt", $payload)` — remover em produção e garantir que o webhook não dependa de sessão.
4. **Saque:** Em `saque/index.php` há `include "conectarbanco.php"` (sem `../`); depende do contexto de execução (pasta `saque/`) para resolver; pode quebrar se o include path mudar.
5. **Config:** `conectarbanco.php` na raiz é o único ponto de credenciais do banco para a aplicação principal; `adm/config/bd.php` inclui esse arquivo e adiciona checagens de admin e rede.
6. **Vitória:** O valor creditado na vitória vem do cliente (`msg`). Para evitar fraude, seria necessário o servidor validar o valor (ex.: limitar a um múltiplo máximo da aposta ou recalcular a partir de regras fixas).

---

## 10. Resumo em uma frase

**Subway Pay** é um site PHP/MySQL onde o lead se cadastra (com opcional link de afiliado), deposita via PIX (SuitPay), joga um runner no estilo Subway Surfers com apostas de R$ 1, 2 ou 5; a vitória é por habilidade (atingir 7x a aposta em valor acumulado no jogo, sem RNG no servidor); em caso de vitória o saldo é creditado e o usuário pode sacar por PIX; em caso de derrota o valor da aposta é debitado e parte vira comissão do afiliado; depósitos são confirmados por webhook e o primeiro depósito qualificado pode gerar CPA ao afiliado com uma chance configurável (`chance_afiliado`).

Este resumo cobre gateways de pagamento (SuitPay PIX), funcionamento do jogo, “chances” de vitória (determinísticas no cliente), depósitos, saques e fluxo geral para permitir que outra IA entenda e modifique o projeto com precisão.
