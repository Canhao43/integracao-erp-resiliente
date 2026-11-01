# 0012 — ERP simulado no mesmo projeto, chamado por HTTP

**Marco:** M3

## Contexto

A integração precisa de um ERP contra o qual ser testada e demonstrada — e o ERP real não pode estar num repositório público. O valor do projeto está em como ele se comporta quando o outro lado falha, então o simulador precisa falhar de propósito, inclusive no caso mais difícil: gravar e cair antes de responder.

## Decisão

Um ERP simulado no próprio projeto, em `app/ErpSimulado`, servido em `/erp-simulado` e chamado **por HTTP de verdade**, pelo Nginx, com timeout de verdade. Ele não tem chave única, exige token (e revoga o anterior a cada login), demora, falha, e às vezes grava e cai. A intensidade fica no `.env` (`ERP_SIMULADO_*`), e ele pode ser desligado (`ERP_SIMULADO_ATIVO=false`).

## Alternativas descartadas

- **Só `Http::fake()` nos testes.** Prova o código, mas não dá para demonstrar nem medir a stack funcionando.
- **Um serviço separado, em outro repositório.** Mais realista, e mais custo para quem só quer clonar e rodar.
- **Um servidor de mocks genérico.** Não guarda estado — e o "grava e cai" depende de o registro continuar lá na consulta seguinte.

## Consequências

- O simulador divide código e banco com a aplicação (as tabelas dele têm prefixo `erp_simulado_`). Fica separado em pasta e rotas, e não deve ser ligado em produção.
- Não é um ERP de verdade: o comportamento é o necessário para exercitar a integração, não para imitar um produto.

## Como é verificado

- `ErpSimuladoTest` — sem chave única, busca por CPF, falha injetada, falha depois de gravar, token, credencial errada, token vencido, login que revoga o anterior
