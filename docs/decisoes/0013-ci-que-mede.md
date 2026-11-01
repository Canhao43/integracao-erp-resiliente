# 0013 — CI que mede, em vez de só rodar teste

**Marcos:** M1 → M4

## Contexto

Teste unitário verde prova o código, não a stack: que o Nginx chega ao PHP-FPM, que as migrations rodam no MySQL, que o worker processa a fila. E teste de resiliência tem uma armadilha própria: se nada falhou durante a execução, "nenhum cadastro duplicado" é verdade por sorte, não por mérito.

## Decisão

O CI tem dois jobs:

1. **Testes e estilo** — PHPUnit e Pint, em PHP 8.4.
2. **Stack Docker com MySQL de verdade** — sobe os cinco containers e mede, lendo de volta no banco:
   - o cadastro pela API chega ao MySQL;
   - o reenvio com a mesma `Idempotency-Key` devolve a resposta original, byte a byte;
   - 20 cadastros com o ERP falhando de propósito chegam todos ao ERP, sem CPF duplicado — **e as falhas de fato aconteceram**;
   - depois de o token vencer, um cadastro novo é enviado, o token é renovado, nenhuma chamada leva `401`, e nenhuma linha da auditoria tem CPF;
   - os testes rodam dentro do container sem tocar no banco de desenvolvimento.

Cada etapa mede **uma** coisa. A prova de renovação de token roda com as falhas desligadas.

Antes de cada PR, o código também foi **quebrado de propósito** nos pontos críticos (tirar a consulta antes de criar, a trava, a renovação do token…), para confirmar que o teste certo falha.

## Alternativas descartadas

- **Só testes unitários.** Não provam a stack.
- **Prova de resiliência sem contar as falhas.** Passaria por sorte numa rodada em que o ERP não falhou.
- **Misturar provas numa etapa com prazo.** Foi o que aconteceu na primeira versão do M4: a prova de renovação esperava 60 s com as falhas aleatórias ligadas, e sete falhas seguidas estouraram o prazo — com o código certo. O log do Nginx mostrou que toda renovação tinha dado `200`; a correção foi isolar a prova.

## Consequências

- O job de Docker leva cerca de um minuto e meio (1 min 39 s na rodada final do M4), contra 15 s do job de testes.
- A prova de resiliência é estatística: com as taxas de falha do `.env.example`, a chance de um envio esgotar as tentativas dentro do prazo do CI é desprezível, mas não zero.

## Como é verificado

- `.github/workflows/ci.yml` — cada etapa imprime o que mediu (por exemplo, `Beneficiários: 21 | CPFs no ERP: 21 | CPFs duplicados no ERP: 0`)
