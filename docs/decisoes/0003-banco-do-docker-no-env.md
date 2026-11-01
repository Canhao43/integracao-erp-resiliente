# 0003 — Conexão do banco do Docker no `.env`

**Marco:** M1

## Contexto

O `phpunit.xml` manda os testes para SQLite em memória, e os testes usam `RefreshDatabase`, que recria as tabelas. O caminho natural para configurar o banco no Docker seria o `environment:` do `compose.yaml` — mas variável de ambiente do container entra no `$_SERVER`, e o Laravel (via phpdotenv) lê o `$_SERVER` **antes** de tudo. O `phpunit.xml` não a sobrescreve.

Resultado, se fosse feito do jeito natural: `php artisan test` dentro do container rodaria no MySQL de desenvolvimento, e o `RefreshDatabase` apagaria os dados.

## Decisão

A conexão do banco do Docker fica no `.env` (criado a partir do `.env.example`), e o `compose.yaml` não define variável de banco. Valor do `.env` perde para o `phpunit.xml`; os testes vão para o SQLite.

## Alternativas descartadas

- **`environment:` no compose.** É a armadilha descrita acima.
- **`force="true"` nas variáveis do `phpunit.xml`.** Não resolve: o PHPUnit ajusta `getenv` e `$_ENV`, e o Laravel consulta `$_SERVER` primeiro.
- **Um banco de teste separado no compose.** Mais um serviço para contornar um problema que basta não criar.

## Consequências

- Quem roda sem Docker precisa ajustar o `.env` para o próprio banco.
- O `compose.yaml` e o `.env.example` têm um comentário apontando um para o outro, para ninguém "consertar" isso de volta.

## Como é verificado

- CI, job `docker`: depois de rodar os testes **dentro do container**, confere no MySQL que o cadastro feito pela API continua lá.
