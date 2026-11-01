# 0009 — O que tentar de novo, e o que não

**Marcos:** M3 · M4

## Contexto

Nem toda falha do ERP é igual. Uma queda passa sozinha; um dado recusado é recusado de novo a cada tentativa; uma credencial errada atinge todos os cadastros ao mesmo tempo.

## Decisão

| O ERP respondeu | O envio |
|---|---|
| Timeout ou falha de conexão | volta para a fila |
| `5xx` | volta para a fila |
| `429` (muitas requisições) | volta para a fila |
| `401` | renova o token e repete **uma vez** ([0010](0010-token-em-cache-por-ambiente.md)) |
| `401` de novo, ou credencial recusada no login | volta para a fila, **continua `pendente`** |
| Outro `4xx` | marca `falhou` na hora, com o motivo |

Entre tentativas, a espera cresce (configurável; o padrão é 10 s, 30 s, 90 s, 270 s, 810 s). O envio desiste depois de 10 falhas ou de um dia, e aí marca `falhou`. Enquanto tenta, o último erro fica visível em `erro_erp`.

## Alternativas descartadas

- **Tentar tudo de novo.** Um `4xx` de dado nunca vai passar; insistir só ocupa a fila e martela o ERP.
- **Não tentar nada.** Uma queda de dois minutos viraria falha permanente em todos os cadastros do período.
- **Tentar de novo na hora, sem espera.** Durante uma queda, piora a queda.
- **Tratar credencial errada como `4xx` comum.** Marcaria todos os cadastros como `falhou` até alguém corrigir a configuração. Como `pendente`, eles saem sozinhos quando a credencial for arrumada.

## Consequências

- `429` não é tratado como recusa, mesmo sendo `4xx`.
- Um envio pode levar até um dia para virar `falhou`; até lá, o `erro_erp` e o histórico de chamadas ([0011](0011-auditoria-so-com-metadados.md)) mostram o que está acontecendo.

## Como é verificado

- `EnvioAoErpTest::test_erro_5xx_volta_para_a_fila_e_guarda_o_motivo`
- `EnvioAoErpTest::test_timeout_volta_para_a_fila`
- `EnvioAoErpTest::test_429_e_passageiro_e_volta_para_a_fila`
- `EnvioAoErpTest::test_recusa_4xx_falha_de_vez_sem_nova_tentativa`
- `EnvioAoErpTest::test_credencial_recusada_e_problema_de_configuracao_e_volta_para_a_fila`
- `EnvioAoErpTest::test_quando_as_tentativas_acabam_marca_como_falhou`
