# GAC Leads

Aplicacao PHP para busca, gerenciamento e exportacao de leads B2B.

## Estrutura principal

- `public/`: endpoints HTTP e interfaces.
- `src/`: servicos e camada de dominio.
- `config/`: bootstrap da aplicacao, carregando variaveis de ambiente.
- `database/schema.sql`: definicao das tabelas MySQL.
- `tools/process_lead_jobs.php`: worker para processar buscas agendadas.

## Preparacao do ambiente (.env)

1. Copie o arquivo de exemplo: `cp .env.example .env`
2. Ajuste os valores conforme seu ambiente local.
3. Quando utilizar Docker, mantenha `DB_HOST=db`. Em producao defina o host real do banco.
4. Configure tambem as variaveis do Asaas:
   - `ASAAS_API_KEY` – token obtido no painel do Asaas.
   - `ASAAS_ENVIRONMENT` – `sandbox` ou `production`.
   - `ASAAS_WEBHOOK_TOKEN` – token secreto para validar o webhook.

> **Importante:** O arquivo `.env` nao deve ser commitado nem enviado ao GitHub.

## Ambiente local com Docker

1. Garanta que o Docker Desktop (ou equivalente) esteja em execucao.
2. Crie o arquivo `.env` conforme descrito acima.
3. Suba os containers:
   ```bash
   docker compose up --build
   ```
4. A aplicacao ficara acessivel em `http://localhost:8080`.
5. O banco MySQL exposto na porta `3306` utiliza os valores definidos em `.env`.
6. Importe o schema inicial:
   ```bash
   docker compose exec db mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} < database/schema.sql
   ```

Os volumes nomeados `mysql_data` e `storage_exports` preservam os dados entre recriacoes.

## Publicando no GitHub

1. Crie um repositorio vazio no GitHub.
2. Configure o remoto localmente (substitua `SEU_USUARIO` e `SEU_REPOSITORIO`):
   ```bash
   git remote add origin git@github.com:SEU_USUARIO/SEU_REPOSITORIO.git
   git branch -M main
   git push -u origin main
   ```
   Caso utilize HTTPS:
   ```bash
   git remote add origin https://github.com/SEU_USUARIO/SEU_REPOSITORIO.git
   git branch -M main
   git push -u origin main
   ```

## Deploy com Dokploy

O [Dokploy](https://dokploy.com/) orquestra deploys Docker a partir de repositorios Git.

1. No painel do Dokploy, crie uma nova aplicacao apontando para o repositorio GitHub recem-publicado.
2. Configure a build usando o `Dockerfile` da raiz (sem `docker compose`).
3. Defina as variaveis de ambiente obrigatorias (exemplo):
   - `APP_ENV=production`
   - `APP_TIMEZONE=America/Sao_Paulo`
   - `DB_HOST=<host do MySQL>`
   - `DB_NAME=<nome do banco>`
   - `DB_USER=<usuario>`
   - `DB_PASS=<senha>`
   - `CASA_DOS_DADOS_API_KEY=<token real>`
   - `ASAAS_API_KEY=<token do Asaas>`
   - `ASAAS_ENVIRONMENT=production`
   - `ASAAS_WEBHOOK_TOKEN=<token do webhook>`
   - `LEAD_VIEW_COST=<valor desejado>`
4. Na aba de volumes/persistencia, monte um volume em `/var/www/html/storage/exports` para manter as exportacoes geradas.
5. Se preferir reutilizar o `docker-compose.yml`, importe-o como stack no Dokploy e ajuste os secrets diretamente por la.
6. Agende o worker:
   - Opcao A: configure um segundo serviço/stack usando a mesma imagem com o comando `php /var/www/html/tools/process_lead_jobs.php`.
   - Opcao B: utilize o agendador do Dokploy (ou um cron externo) para executar o script periodicamente.
7. Depois do primeiro deploy, conecte-se ao banco de dados e execute `database/schema.sql`.

### Atualizacoes futuras

1. Efetue as alteracoes localmente e gere commits.
2. Execute testes manuais/localmente conforme necessario.
3. Faça push para o GitHub:
   ```bash
   git push origin main
   ```
4. O Dokploy buscara a nova revisao e recriara o container automaticamente (ou conforme configurado).

## Scripts uteis

- `docker compose up --build`: sobe ambiente local completo.
- `docker compose exec app php tools/process_lead_jobs.php`: roda o worker manualmente.
- `docker compose down -v`: encerra containers e remove volumes (cuidado, apaga dados).

## Pagamentos via PIX (Asaas)

Foi adicionada uma camada de pagamentos integrada com a API oficial do Asaas para venda de créditos.

- Endpoints internos:
  - `POST /api/payments/create.php` – gera um QR Code PIX a partir do plano (`basic`, `pro`, `premium`).
  - `GET /api/payments/status.php?id={transactionId}` – consulta o status da transação.
  - `POST /api/payments/webhook.php` – recepciona eventos do Asaas. Configure o cabeçalho `X-Webhook-Token` com o valor de `ASAAS_WEBHOOK_TOKEN`.
- Todos os registros ficam em `transactions` (vide `database/schema.sql`); sucessos e erros são registrados em `storage/logs/payments.log`.
- Ao receber `PAYMENT_RECEIVED` ou `PAYMENT_CONFIRMED` o sistema marca a transação como paga e soma os créditos informados ao usuário.
- A tela `public/comprar-creditos.php` consome os endpoints acima, gera o QR Code automaticamente e inicia o polling até a confirmação.

## Checklist de seguranca

- Nunca expor `.env` ou segredos no repositorio.
- Utilizar usuarios de banco dedicados com privilegios limitados no ambiente de producao.
- Garantir HTTPS via proxy reverso (Traefik, Caddy, Nginx) no Dokploy.
