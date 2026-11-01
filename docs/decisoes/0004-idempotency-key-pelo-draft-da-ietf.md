# 0004 — `Idempotency-Key` pelo draft da IETF

**Marco:** M2

## Contexto

O cliente que cadastra dá timeout e reenvia. Sem proteção, o reenvio volta `422 — CPF já cadastrado`, e ele não sabe se o primeiro pedido gravou. Existe uma proposta de padrão para esse problema: o [draft da IETF `draft-ietf-httpapi-idempotency-key-header`](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) (versão 08, outubro de 2026).

## Decisão

O `POST /api/beneficiarios` exige o header `Idempotency-Key`, com os códigos que o draft recomenda:

| Caso | Resposta |
|---|---|
| Sem a chave | `400` |
| Chave reusada com outro conteúdo | `422` |
| Chave de um pedido ainda em processamento | `409` |
| Chave de um pedido concluído, mesmo conteúdo | a resposta original, com `Idempotent-Replayed: true` |

O draft não define header de resposta; `Idempotent-Replayed` é a convenção do Stripe. A chave é aceita com ou sem aspas (o draft a define como string entre aspas).

## Alternativas descartadas

- **`428 Precondition Required` para a chave ausente.** Muita API usa, por analogia — mas o `428` (RFC 6585) foi pensado para requisição condicional (`If-Match`). O draft recomenda `400`.
- **Chave opcional, como no Stripe.** Devolve o problema para o cliente que esquecer de mandar.
- **Idempotência pelo CPF.** Cobre só este endpoint, e não distingue reenvio de pedido diferente para o mesmo CPF.

## Consequências

- Todo cliente gera um identificador único por pedido (um UUID basta).
- Sem autenticação na API, a chave é única no sistema todo, e não por cliente.

## Como é verificado

- `IdempotenciaTest` — `400` sem chave e com chave longa demais, aspas aceitas, reenvio com a mesma resposta, `422` com outro conteúdo, `409` em processamento
- CI, job `docker`: reenvia o mesmo pedido no MySQL e compara as duas respostas byte a byte
