# 0008 — Um envio ao ERP por vez

**Marco:** M3

## Contexto

"Consultar e depois cadastrar" ([0007](0007-consultar-antes-de-criar-no-erp.md)) tem uma janela: se o mesmo cadastro for processado duas vezes em paralelo — um job que passou do tempo e foi pego por outro worker, por exemplo —, as duas execuções consultam juntas, não acham nada e cadastram duas vezes.

## Decisão

Um envio por vez, para toda a integração: o job usa `WithoutOverlapping` com uma trava **global**, e o Docker sobe um worker só. Se alguém escalar os workers, a trava mantém a garantia. Ela expira sozinha em 2 minutos, se o worker morrer com ela.

## Alternativas descartadas

- **Trava por CPF.** Basta para não duplicar, e permitiria envios em paralelo. Foi descartada porque ERP desse tipo costuma não aguentar escrita concorrente (numeração sequencial, lock de tabela). **Se o ERP aguentar, é a primeira coisa a reconsiderar.**
- **Só um worker, sem trava.** A garantia sumiria no dia em que alguém escalasse os workers.

## Consequências

- A vazão fica limitada a um envio por vez — troca deliberada de velocidade por segurança.
- Um job que encontra a trava ocupada volta para a fila em 2 segundos, sem chamar o ERP.

## Como é verificado

- `EnvioAoErpTest::test_com_outro_envio_em_andamento_volta_para_a_fila_sem_chamar_o_erp` — com a trava ocupada, o job é devolvido à fila e nenhuma chamada sai
