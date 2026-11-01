# 0001 — CPF normalizado e único no banco

**Marco:** M1

## Contexto

O CPF chega com ou sem máscara (`123.456.789-09` ou `12345678909`). A mesma pessoa não pode virar dois beneficiários — é exatamente o problema que este projeto existe para evitar. E a regra `unique` da validação tem uma janela: dois pedidos simultâneos passam pela checagem antes de qualquer um gravar.

## Decisão

- A máscara sai **antes** da validação; o banco guarda só os 11 dígitos.
- O CPF é validado pelos dígitos verificadores (módulo 11), não só pelo formato.
- O CPF tem **índice único no banco**, além do `unique` da validação.
- Se o índice barrar um pedido que passou pela validação, a resposta é `422 — CPF já cadastrado`, não `500` (entrou no M2).

## Alternativas descartadas

- **Guardar como veio.** A mesma pessoa, com e sem máscara, vira dois cadastros.
- **Validar só o formato.** Aceita CPF que não existe.
- **`unique` só na validação.** Fica a janela de corrida entre checar e gravar.

## Consequências

- Toda comparação é por dígitos; o cliente pode mandar como preferir.
- O erro do índice único precisa ser traduzido para a mesma mensagem da validação.

## Como é verificado

- `CadastroDeBeneficiarioTest::test_aceita_cpf_com_mascara_e_grava_so_os_digitos`
- `CadastroDeBeneficiarioTest::test_rejeita_cpf_com_digito_verificador_invalido`
- `CadastroDeBeneficiarioTest::test_cpf_gravado_por_outro_pedido_depois_da_validacao_responde_422_e_nao_500` — simula a corrida gravando o mesmo CPF entre a validação e o `INSERT`
- `CpfTest` — dígitos verificadores, sequências repetidas e formatos inválidos
