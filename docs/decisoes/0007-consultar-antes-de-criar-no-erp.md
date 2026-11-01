# 0007 — Consultar o CPF no ERP antes de cadastrar

**Marco:** M3

## Contexto

O ERP não tem chave única: o mesmo CPF cadastrado duas vezes vira dois registros. E ele pode **gravar e cair antes de responder** — a integração recebe erro sem saber se o registro foi criado. Tentar de novo às cegas duplica.

## Decisão

Antes de cadastrar, a integração consulta o CPF no ERP. Se já existe, adota o código dele (o mais antigo, se o ERP já tiver duplicatas de antes). Se não existe, cadastra. É a idempotência que o ERP não oferece, feita do lado de cá.

## Alternativas descartadas

- **Tentar de novo sem consultar.** Cada "grava e cai" vira um registro a mais no ERP.
- **Cadastrar e corrigir depois, com uma conciliação.** O ERP fica com duplicata por um tempo, e desfazer duplicata em ERP costuma ser manual.
- **Pedir ao fornecedor do ERP uma chave de idempotência.** É a solução certa a longo prazo — e está fora do nosso controle.

## Consequências

- Uma chamada a mais por envio.
- Só é seguro se o mesmo cadastro não for enviado duas vezes em paralelo — ver [0008](0008-um-envio-por-vez.md).
- Depende de a consulta enxergar o que acabou de ser gravado. Num ERP com réplica de leitura atrasada, isso não vale, e entraria uma conciliação periódica.

## Como é verificado

- `EnvioAoErpTest::test_erp_que_grava_e_cai_antes_de_responder_nao_ganha_duplicata_na_nova_tentativa` — o simulador grava e responde `500` na 1ª tentativa; a 2ª adota o código e o ERP continua com um registro só
- `EnvioAoErpTest::test_se_o_erp_ja_tem_o_cpf_adota_o_codigo_sem_cadastrar_de_novo`
- CI, job `docker`: 20 cadastros com o ERP falhando de propósito — inclusive gravando e caindo —, **nenhum CPF duplicado** no ERP
