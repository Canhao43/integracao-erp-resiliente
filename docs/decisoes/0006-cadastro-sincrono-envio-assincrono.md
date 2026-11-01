# 0006 — Cadastro síncrono, envio ao ERP assíncrono

**Marco:** M3

## Contexto

O ERP do outro lado é lento e cai. Se o cadastro esperasse por ele, o cliente da API herdaria a lentidão e as quedas — e um timeout no meio deixaria o cliente sem saber o que aconteceu.

## Decisão

O cadastro grava no banco local e responde `201` na hora, com `erp.status: pendente`. O envio ao ERP vai para a fila `erp` e é processado por um worker. O cliente acompanha pelo `GET /api/beneficiarios/{id}`: `pendente` → `enviado` (com o código do ERP) ou `falhou`.

## Alternativas descartadas

- **Chamar o ERP dentro da requisição.** O tempo de resposta da API passa a ser o do ERP, e cada queda do ERP vira erro para o cliente.
- **`202 Accepted` sem gravar localmente.** Se a fila perder a mensagem, o cadastro some.

## Consequências

- Consistência eventual: há um intervalo em que o cadastro existe aqui e ainda não existe no ERP.
- A resposta de um reenvio idempotente ([0005](0005-reserva-antes-de-executar.md)) mostra o status do momento do cadastro; o atual está no `GET`.

## Como é verificado

- `CadastroDeBeneficiarioTest::test_cadastro_agenda_o_envio_ao_erp_e_responde_como_pendente`
- `CadastroDeBeneficiarioTest::test_cadastro_recusado_nao_agenda_envio_ao_erp`
- CI, job `docker`: espera a fila esvaziar e confere que todos os cadastros chegaram ao ERP
