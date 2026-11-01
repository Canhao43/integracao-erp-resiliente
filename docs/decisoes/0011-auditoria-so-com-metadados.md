# 0011 — Auditoria só com metadados

**Marco:** M4

## Contexto

Quando um envio não chega ao ERP, o suporte precisa ver o que aconteceu: quantas tentativas, quais respostas, quanto tempo. Mas o corpo das chamadas tem nome e CPF, a query string da consulta tem o CPF, e o header tem o token — e log é um dos lugares por onde dado sensível mais vaza.

## Decisão

- Cada chamada ao ERP vira uma linha de auditoria com **método, caminho (sem query string), status, duração** e, em falha de conexão, a mensagem do erro. **Sem corpo, sem token.** O `beneficiario_id` diz de quem é a chamada.
- A auditoria **não se altera**: o model recusa `update` e `delete`.
- Sai só pela **retenção** (`ERP_AUDITORIA_RETENCAO_DIAS`, 90 dias), em lote, pelo `model:prune` agendado.
- O histórico de um cadastro fica em `GET /api/beneficiarios/{id}/chamadas-erp`.

## Alternativas descartadas

- **Registrar o corpo das chamadas.** Nome e CPF parariam na auditoria — o contrário de minimização (LGPD).
- **Registrar em arquivo de log.** Difícil consultar por cadastro, e mais difícil controlar retenção.
- **Imutabilidade só por convenção.** Nada impediria uma correção "rápida" de reescrever o histórico.

## Consequências

- Para investigar o conteúdo de um envio, é preciso cruzar com o cadastro.
- A imutabilidade é garantida pela aplicação, não pelo banco: um `UPDATE` direto no MySQL passa. O próximo passo seria um usuário de banco só com `INSERT` nessa tabela.
- Sem autenticação na API, o histórico fica aberto; num ambiente real, seria rota interna, com permissão de suporte.

## Como é verificado

- `AuditoriaErpTest` — registra cada chamada, registra falha de conexão, não guarda CPF nem token, recusa alteração e exclusão, apaga só o que passou da retenção, expõe o histórico do cadastro
- CI, job `docker`: confere que **nenhuma** linha da auditoria contém CPF de beneficiário
