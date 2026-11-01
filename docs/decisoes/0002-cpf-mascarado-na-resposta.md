# 0002 — CPF mascarado na resposta

**Marco:** M1

## Contexto

Quem cadastra um beneficiário já tem o CPF dele. Cada cópia a mais do número — em resposta de API, log de cliente, proxy, print de tela — é mais um lugar de onde um dado pessoal sensível pode vazar.

## Decisão

As respostas devolvem o CPF mascarado no padrão usado pelo governo federal: `***.456.789-**`.

## Alternativas descartadas

- **Devolver o CPF inteiro.** Não acrescenta nada a quem já tem o número, e multiplica a exposição.
- **Não devolver o CPF.** O cliente perderia como conferir, de relance, de qual registro se trata.

## Consequências

- Minimização de dados, princípio da LGPD, aplicada na borda da API.
- O cliente não pode usar a resposta para recuperar um CPF que perdeu — e não deveria.

## Como é verificado

- `CadastroDeBeneficiarioTest::test_nao_devolve_o_cpf_completo_na_resposta` — confere a máscara e que os 11 dígitos não aparecem em nenhum lugar do corpo
