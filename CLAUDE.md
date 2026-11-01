# Como agentes trabalham neste repositório

Este projeto é construído com agentes de IA (Claude Code) e revisado por mim, Caio. As regras abaixo valem para qualquer agente que mexa aqui.

## O critério

**Eu preciso saber explicar cada decisão.** Código que funciona, mas que eu não consigo defender numa conversa técnica, não entra.

## Fluxo

- Um marco do roteiro = uma branch = um PR. Nada vai direto para o `main`.
- Commits pequenos e lógicos, em português, com mensagem curta e direta.
- Descrição de PR resumida: o que entra, em poucos tópicos.
- Commits e PRs sem trailer ou rodapé automático (`Co-Authored-By`, link de sessão, "Generated with").
- Quem mergeia sou eu, depois de ler.

## Código

- Domínio em português (beneficiário, CPF); o framework continua em inglês.
- Teste junto com a funcionalidade, nunca depois. `php artisan test` e `vendor/bin/pint --test` precisam passar.
- Todo dado em teste e seed é fictício — CPF gerado pelo Faker. Nenhum dado real, de pessoa ou de empresa.
- Comentário explica o porquê, não o quê.

## Não fazer

- Instalar pacote ou ferramenta sem dizer por quê no PR.
- Colocar variável de banco no `environment:` do `compose.yaml` — o motivo está no `.env.example`.
