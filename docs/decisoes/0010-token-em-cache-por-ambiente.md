# 0010 — Token do ERP em cache, por ambiente

**Marco:** M4

## Contexto

O ERP exige token de validade curta e, como muito ERP faz, **cada login novo revoga o token anterior**. Dois problemas clássicos de integração saem daí: processos que fazem login cada um por si e derrubam o token um do outro, e um cache de token compartilhado entre homologação e produção, que manda o token de um ambiente para o outro.

## Decisão

- O token fica em cache e é **renovado um pouco antes de vencer** (`ERP_TOKEN_MARGEM`), para não vencer no meio de uma chamada.
- A renovação passa por um **lock**: quem chega enquanto outro processo renova espera e usa o token que acabou de sair.
- A chave do cache inclui a **URL do ERP e a credencial**: cada ambiente tem o seu token.
- Se o ERP responder `401` — o token foi revogado antes da hora —, o token sai do cache, é renovado e a chamada é repetida **uma vez**. Se ainda for `401`, é credencial errada ([0009](0009-o-que-tentar-de-novo.md)).

## Alternativas descartadas

- **Pedir token a cada chamada.** Dobra o tráfego, e com a revogação a cada login, cada chamada derrubaria o token da anterior.
- **Renovar só quando der `401`.** Toda chamada perto do vencimento falharia uma vez antes de renovar.
- **Chave de cache sem o ambiente.** É o bug do token de homologação indo para produção.
- **Repetir o `401` até passar.** Com credencial errada, vira um laço martelando o ERP.

## Consequências

- O token fica guardado no cache da aplicação (no Docker, a tabela de cache do MySQL) durante a validade dele.
- Uma revogação externa custa uma chamada `401` a mais, registrada na auditoria.

## Como é verificado

- `TokenDoErpTest` — reaproveita o token, renova antes de vencer, renova e repete no `401`, separa homologação de produção, trata credencial recusada
- `EnvioAoErpTest::test_token_revogado_por_outro_login_e_renovado_sem_perder_o_envio` — outro sistema faz login no meio; a auditoria registra *recusado → renovou → seguiu*
- CI, job `docker`: espera o token vencer, cadastra mais um e confere — token renovado e **nenhuma** chamada com `401`
