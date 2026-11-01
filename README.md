# Integração ERP resiliente

[![CI](https://github.com/Canhao43/integracao-erp-resiliente/actions/workflows/ci.yml/badge.svg)](https://github.com/Canhao43/integracao-erp-resiliente/actions/workflows/ci.yml)

API em Laravel que cadastra beneficiários de plano de saúde e os envia a um ERP que **não colabora**: não tem chave única (reenviar duplica o registro), responde devagar, falha de vez em quando e tem token que expira.

É o tipo de integração que eu mantenho no trabalho, com o TOTVS Protheus. Aqui o ERP é **simulado** e todo dado é fictício. O objetivo é mostrar, em código e em teste, como uma integração dessas sobrevive em produção.

> Construído com agentes de IA (Claude Code) e revisado por mim em cada PR. As regras que os agentes seguem estão em [`CLAUDE.md`](CLAUDE.md); as decisões estão explicadas abaixo e no PR de cada marco.

## Roteiro

| Marco | O que entra | Estado |
|---|---|---|
| **M1** | API de cadastro, validação de CPF, Docker e CI | ✅ |
| **M2** | `Idempotency-Key`: o mesmo pedido reenviado não cadastra duas vezes | ✅ |
| **M3** | ERP simulado que falha de propósito; envio em fila, um por vez, com timeout e retry | ✅ |
| **M4** | Token do ERP em cache com renovação; log de auditoria das chamadas | ⏳ |
| **M5** | Documento de decisões consolidado | ⏳ |

## Como rodar

Precisa de Docker.

```bash
git clone https://github.com/Canhao43/integracao-erp-resiliente.git
cd integracao-erp-resiliente
docker compose up
```

Na primeira subida o container instala as dependências, cria o `.env`, gera a chave e roda as migrations. A API fica em `http://localhost:8080`.

Sobem cinco containers: `app` (PHP-FPM), `web` (Nginx), `db` (MySQL), `worker` (a fila de envio ao ERP) e `scheduler` (tarefas agendadas). Para ver o ERP falhando e a fila tentando de novo: `docker compose logs -f worker`.

Testes: `docker compose exec app php artisan test`. Eles rodam em SQLite em memória — nunca no MySQL de desenvolvimento.

## Como a integração funciona

```
cliente ──POST──▶ API ──▶ banco local (status: pendente) ──▶ 201
                   │
                   └──▶ fila "erp" ──▶ worker, um envio por vez
                                         │
                                         ├─ consulta o CPF no ERP
                                         │    ├─ já existe → adota o código
                                         │    └─ não existe → cadastra
                                         │
                                         ├─ deu certo → status: enviado + código do ERP
                                         ├─ timeout, 5xx ou 429 → tenta de novo, com espera crescente
                                         └─ 4xx → status: falhou (tentar de novo não muda a resposta)
```

O ERP do outro lado é **simulado** (`/erp-simulado`, só para demonstração) e não colabora de propósito: não tem chave única, demora, falha — e às vezes **grava e cai antes de responder**. A intensidade fica no `.env` (`ERP_SIMULADO_*`).

## API

### `POST /api/beneficiarios`

Exige o header `Idempotency-Key` — um identificador único do pedido, gerado pelo cliente (um UUID, por exemplo). Reenviar com a mesma chave não cadastra de novo.

```bash
curl -X POST http://localhost:8080/api/beneficiarios \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"nome": "Maria da Silva", "cpf": "<CPF válido>", "data_nascimento": "1990-05-20", "email": "maria@example.com"}'
```

| Resposta | Quando |
|---|---|
| `201` + header `Location` | cadastrou |
| `201` + `Idempotent-Replayed: true` | reenvio de um pedido já concluído: devolve a resposta original, sem cadastrar de novo |
| `400` | sem `Idempotency-Key` |
| `409` | um pedido com a mesma chave ainda está em processamento |
| `422` | campo obrigatório ausente, CPF inválido ou já cadastrado, data de nascimento no futuro — ou chave já usada com outro conteúdo |

```json
{
  "data": {
    "id": 1,
    "nome": "Maria da Silva",
    "cpf": "***.456.789-**",
    "data_nascimento": "1990-05-20",
    "email": "maria@example.com",
    "criado_em": "2026-10-31T18:00:00+00:00",
    "erp": {
      "status": "pendente",
      "codigo": null
    }
  }
}
```

O cadastro responde na hora; o envio ao ERP acontece depois, pela fila.

### `GET /api/beneficiarios/{id}`

`200` com o mesmo formato, ou `404`. É aqui que se acompanha o envio: `erp.status` passa de `pendente` para `enviado` (com o `codigo` do ERP) ou `falhou`.

## Decisões, e por quê

### M1

- **CPF aceito com ou sem máscara, guardado só com dígitos.** A máscara sai antes da validação. Sem isso, `123.456.789-09` e `12345678909` passariam como dois beneficiários — a mesma pessoa duplicada, que é exatamente o problema que este projeto existe para evitar.
- **CPF único no banco, não só na validação.** A regra `unique` da validação tem uma janela: dois pedidos simultâneos passam pela checagem antes de qualquer um gravar. O índice único fecha essa janela. O que fazer com o erro que ele gera é assunto do M2.
- **A resposta não devolve o CPF inteiro.** Quem cadastrou já tem o número; devolvê-lo só multiplica os lugares por onde ele pode vazar (log do cliente, proxy, print de tela). Minimização é princípio da LGPD. A máscara é a mesma que o governo federal usa.
- **`201` com `Location`.** O cliente sabe onde consultar o que acabou de criar, sem montar URL.
- **Banco do Docker no `.env`, não no `environment:` do compose.** Variável de ambiente do container vence o `phpunit.xml`: os testes rodariam no MySQL de desenvolvimento e o `RefreshDatabase` apagaria os dados. O CI confere isso a cada PR.
- **PHPUnit, não Pest.** É o padrão do Laravel 13 e o que eu uso no trabalho.
- **CI em dois jobs.** Um roda testes e estilo. O outro sobe a stack Docker de verdade, cadastra pela API, roda os testes dentro do container e **lê de volta no MySQL** que o cadastro existe — e que os testes não tocaram nele. Medição, em vez de "deveria funcionar".

### M2

- **Segue o [draft da IETF](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) para `Idempotency-Key`:** `400` sem a chave, `422` para chave reusada com outro conteúdo, `409` enquanto o primeiro pedido ainda processa. Muita API usa `428` para a chave ausente; aqui ficou o que o draft recomenda.
- **A reserva da chave é gravada antes de executar, fora de transação, com índice único.** É isso que faz o segundo pedido simultâneo enxergar o primeiro em andamento e receber `409`, em vez de cadastrar junto.
- **Guarda a resposta, não só a chave.** No reenvio o cliente recebe exatamente o que teria recebido — mesmo corpo, mesmo `Location` — mais o header `Idempotent-Replayed: true`. O draft não define header de resposta; o nome é a convenção do Stripe.
- **Só sucesso fica guardado.** Erro de validação libera a chave: o cliente corrige o pedido e reenvia com a mesma chave.
- **Mesmo pedido = mesmo método, caminho e corpo, com os campos do JSON ordenados**, comparados por hash SHA-256. A ordem dos campos não faz dois pedidos iguais parecerem diferentes.
- **A chave vale 24 horas.** Depois sai pelo `model:prune`, agendado para rodar todo dia.
- **CPF duplicado em pedidos simultâneos volta `422`, não `500`.** O erro do índice único vira o mesmo erro de validação de "CPF já cadastrado".

### M3

- **O cadastro local responde na hora; o ERP recebe pela fila.** O cliente não fica preso à lentidão nem às quedas do ERP — e acompanha o envio pelo `erp.status`.
- **Antes de cadastrar no ERP, consulta o CPF lá.** O ERP não tem chave única, e o pior caso é ele gravar e cair antes de responder: o cliente não sabe se deu certo. Se o CPF já está lá, a integração adota o código em vez de cadastrar de novo. É a idempotência que o ERP não oferece, feita do lado de cá.
- **Um envio por vez.** Duas execuções do mesmo cadastro em paralelo — um job que passou do tempo e foi pego por outro worker, por exemplo — consultariam juntas, não achariam nada e cadastrariam duas vezes. Uma trava por CPF já resolveria isso; a trava aqui é **global** porque ERP desse tipo também costuma não aguentar escrita concorrente (numeração sequencial, lock de tabela). Troca vazão por segurança. O worker é um só, e o job ainda usa `WithoutOverlapping`: se alguém escalar os workers, a garantia continua. O lock expira sozinho se o worker morrer.
- **Só tenta de novo o que pode dar certo na próxima.** Timeout, `5xx` e `429` voltam para a fila com espera crescente; `4xx` é recusa do ERP e marca `falhou` na hora. O prazo total é de um dia, com até 10 falhas.
- **O ERP simulado mora no mesmo projeto, mas é chamado por HTTP de verdade** — pelo Nginx, com timeout de verdade. Fica em `app/ErpSimulado`, separado da aplicação, e pode ser desligado (`ERP_SIMULADO_ATIVO=false`).
- **O CI prova com o ERP falhando de propósito:** cadastra 20 beneficiários, espera a fila esvaziar e confere no MySQL que todos estão no ERP, **nenhum CPF duplicado** — e que as falhas de fato aconteceram, porque teste de resiliência sem falha não prova nada.

### O que ainda não está resolvido — de propósito

- **Se o processo morrer no meio de um pedido**, a chave de idempotência fica "em processamento" até vencer e o reenvio recebe `409`. A saída é considerar a reserva abandonada depois de um tempo limite.
- **A chave de idempotência é única no sistema todo, não por cliente.** Ainda não há autenticação; com ela, o escopo passa a ser por cliente. Com UUID, a chance de colisão é desprezível.
- **O ERP ainda não exige autenticação**, e as chamadas a ele não ficam registradas. → M4
- **"Consultar antes de criar" depende de a consulta enxergar o que acabou de ser gravado.** Num ERP com réplica atrasada, isso não vale — aí entra uma conciliação periódica.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Docker Compose (php-fpm + nginx + MySQL + worker + scheduler) · fila em banco · PHPUnit 12 · GitHub Actions
