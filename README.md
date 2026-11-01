# Integração ERP resiliente

[![CI](https://github.com/Canhao43/integracao-erp-resiliente/actions/workflows/ci.yml/badge.svg)](https://github.com/Canhao43/integracao-erp-resiliente/actions/workflows/ci.yml)

API em Laravel que cadastra beneficiários de plano de saúde e os envia a um ERP que **não colabora**: não tem chave única (reenviar duplica o registro), responde devagar, falha de vez em quando e tem token que expira.

É o tipo de integração que eu mantenho no trabalho, com o TOTVS Protheus. Aqui o ERP é **simulado** e todo dado é fictício. O objetivo é mostrar, em código e em teste, como uma integração dessas sobrevive em produção.

> Construído com agentes de IA (Claude Code) e revisado por mim em cada PR. As regras que os agentes seguem estão em [`CLAUDE.md`](CLAUDE.md); as decisões estão explicadas abaixo e no PR de cada marco.

## Roteiro

| Marco | O que entra | Estado |
|---|---|---|
| **M1** | API de cadastro, validação de CPF, Docker e CI | ✅ |
| **M2** | `Idempotency-Key`: o mesmo pedido reenviado não cadastra duas vezes | ⏳ |
| **M3** | ERP simulado que falha de propósito; envio em fila, um por vez, com timeout e retry | ⏳ |
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

Testes: `docker compose exec app php artisan test`. Eles rodam em SQLite em memória — nunca no MySQL de desenvolvimento.

## API

### `POST /api/beneficiarios`

```bash
curl -X POST http://localhost:8080/api/beneficiarios \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"nome": "Maria da Silva", "cpf": "<CPF válido>", "data_nascimento": "1990-05-20", "email": "maria@example.com"}'
```

| Resposta | Quando |
|---|---|
| `201` + header `Location` | cadastrou |
| `422` | campo obrigatório ausente, CPF inválido ou já cadastrado, data de nascimento no futuro |

```json
{
  "data": {
    "id": 1,
    "nome": "Maria da Silva",
    "cpf": "***.456.789-**",
    "data_nascimento": "1990-05-20",
    "email": "maria@example.com",
    "criado_em": "2026-10-31T18:00:00+00:00"
  }
}
```

### `GET /api/beneficiarios/{id}`

`200` com o mesmo formato, ou `404`.

## Decisões, e por quê

### M1

- **CPF aceito com ou sem máscara, guardado só com dígitos.** A máscara sai antes da validação. Sem isso, `123.456.789-09` e `12345678909` passariam como dois beneficiários — a mesma pessoa duplicada, que é exatamente o problema que este projeto existe para evitar.
- **CPF único no banco, não só na validação.** A regra `unique` da validação tem uma janela: dois pedidos simultâneos passam pela checagem antes de qualquer um gravar. O índice único fecha essa janela. O que fazer com o erro que ele gera é assunto do M2.
- **A resposta não devolve o CPF inteiro.** Quem cadastrou já tem o número; devolvê-lo só multiplica os lugares por onde ele pode vazar (log do cliente, proxy, print de tela). Minimização é princípio da LGPD. A máscara é a mesma que o governo federal usa.
- **`201` com `Location`.** O cliente sabe onde consultar o que acabou de criar, sem montar URL.
- **Banco do Docker no `.env`, não no `environment:` do compose.** Variável de ambiente do container vence o `phpunit.xml`: os testes rodariam no MySQL de desenvolvimento e o `RefreshDatabase` apagaria os dados. O CI confere isso a cada PR.
- **PHPUnit, não Pest.** É o padrão do Laravel 13 e o que eu uso no trabalho.
- **CI em dois jobs.** Um roda testes e estilo. O outro sobe a stack Docker de verdade, cadastra pela API, roda os testes dentro do container e **lê de volta no MySQL** que o cadastro existe — e que os testes não tocaram nele. Medição, em vez de "deveria funcionar".

### O que ainda não está resolvido — de propósito

- **Pedido reenviado** (o cliente deu timeout e tentou de novo) volta `422 CPF já cadastrado`. O cliente não sabe se o primeiro deu certo. → M2
- **Dois pedidos simultâneos com o mesmo CPF:** o segundo esbarra no índice único e hoje vira erro `500`. → M2
- **Ainda não existe ERP do outro lado.** → M3

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Docker Compose (php-fpm + nginx + MySQL) · PHPUnit 12 · GitHub Actions
