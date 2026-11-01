# Registro de decisões

Uma decisão por arquivo, no formato ADR (*Architecture Decision Record*): o contexto, a decisão, as alternativas descartadas, as consequências — e **como ela é verificada**, apontando o teste ou a etapa do CI que a prova.

| # | Decisão | Marco |
|---|---|---|
| [0001](0001-cpf-normalizado-e-unico-no-banco.md) | CPF normalizado e único no banco | M1 |
| [0002](0002-cpf-mascarado-na-resposta.md) | CPF mascarado na resposta | M1 |
| [0003](0003-banco-do-docker-no-env.md) | Conexão do banco do Docker no `.env` | M1 |
| [0004](0004-idempotency-key-pelo-draft-da-ietf.md) | `Idempotency-Key` pelo draft da IETF | M2 |
| [0005](0005-reserva-antes-de-executar.md) | Reserva da chave antes de executar; só sucesso fica guardado | M2 |
| [0006](0006-cadastro-sincrono-envio-assincrono.md) | Cadastro síncrono, envio ao ERP assíncrono | M3 |
| [0007](0007-consultar-antes-de-criar-no-erp.md) | Consultar o CPF no ERP antes de cadastrar | M3 |
| [0008](0008-um-envio-por-vez.md) | Um envio ao ERP por vez | M3 |
| [0009](0009-o-que-tentar-de-novo.md) | O que tentar de novo, e o que não | M3 · M4 |
| [0010](0010-token-em-cache-por-ambiente.md) | Token do ERP em cache, por ambiente | M4 |
| [0011](0011-auditoria-so-com-metadados.md) | Auditoria só com metadados | M4 |
| [0012](0012-erp-simulado-no-mesmo-projeto.md) | ERP simulado no mesmo projeto, chamado por HTTP | M3 |
| [0013](0013-ci-que-mede.md) | CI que mede, em vez de só rodar teste | M1 → M4 |
