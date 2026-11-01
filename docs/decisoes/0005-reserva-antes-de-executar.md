# 0005 — Reserva da chave antes de executar; só sucesso fica guardado

**Marco:** M2

## Contexto

Dois pedidos com a mesma `Idempotency-Key` podem chegar ao mesmo tempo. E o cliente que reenvia precisa da mesma resposta que teria recebido — com o `id` e o `Location` — para seguir em frente.

## Decisão

- A chave é **reservada antes** de executar o pedido, numa tabela com índice único, **fora de transação**. O segundo pedido simultâneo esbarra no índice, encontra a reserva em andamento e recebe `409`.
- Quando o pedido termina com sucesso, a **resposta inteira** (status, corpo, `Location`) é guardada junto da chave.
- **Só sucesso fica guardado.** Erro libera a chave: o cliente corrige o pedido e reenvia com a mesma.
- "Mesmo pedido" = mesmo método, caminho e corpo, com os campos do JSON **ordenados** antes do hash SHA-256.
- A chave vale 24 horas e sai pelo `model:prune`, agendado todo dia.

## Alternativas descartadas

- **Reservar dentro da transação.** A reserva só ficaria visível no commit: o segundo pedido não a veria e poderia executar junto.
- **Guardar só "esta chave já foi usada".** O cliente que reenvia continuaria sem saber o resultado.
- **Guardar também os erros.** O cliente precisaria de uma chave nova só para corrigir um campo.
- **Hash do corpo bruto.** Dois clientes que serializam o mesmo objeto em ordens diferentes teriam o reenvio recusado.

## Consequências

- Se o processo morrer entre a reserva e a conclusão, a chave fica "em processamento" até vencer, e o reenvio recebe `409`. A saída seria tratar a reserva como abandonada depois de um tempo limite — está nas limitações conhecidas.

## Como é verificado

- `IdempotenciaTest::test_chave_de_pedido_ainda_em_processamento_responde_409`
- `IdempotenciaTest::test_erro_de_validacao_nao_prende_a_chave`
- `IdempotenciaTest::test_mesmo_corpo_com_os_campos_em_outra_ordem_conta_como_o_mesmo_pedido`
- `IdempotenciaTest::test_reenvio_com_a_mesma_chave_devolve_a_resposta_original_sem_cadastrar_de_novo`
- `IdempotenciaTest::test_chaves_vencidas_sao_apagadas_e_as_validas_ficam`
