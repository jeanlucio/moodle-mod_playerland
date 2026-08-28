# 🔐 Segurança

## O Servidor como Fonte da Verdade

Toda chamada de web service revalida a instância da atividade, seu contexto e a capability de
quem chama (`mod/playerland:view`) antes de fazer qualquer coisa — um método auxiliar
(`get_validated_instance()`) é o único ponto de entrada por onde toda função externa passa, então
nenhum caminho de código pula essa checagem. O progresso devolvido ao cliente (respostas
distintas corretas, se a cota de saída foi atingida) é **sempre** recalculado no servidor a
partir da tabela `playerland_ans`; uma contagem enviada pelo cliente é aceita como parâmetro mas
nunca é confiada nem gravada como está.

## Isolamento entre Instâncias

Um id de pergunta ou opção é uma chave primária pura, sem checagem de dono própria. Toda busca
que resolve um deles é escopada pelo `playerlandid` já validado, não pelo id isolado — parear a
pergunta de uma instância com o `playerlandid` de outra é rejeitado, não respondido em silêncio.
Isso é garantido por um arquivo de teste automatizado dedicado
(`cross_instance_security_test.php`, veja [Testes Automatizados](#testing)), não só por revisão
de código.

## Escape de Saída

Texto de pergunta, texto de alternativa e texto de mini-lição são todos formatados com
`format_string()`/renderização simples antes de chegar à página — nada disso é ecoado cru.

## Controle de Acesso

* `mod/playerland:view` — necessária para jogar a atividade e chamar qualquer um de seus web
  services.
* `mod/playerland:manage` — necessária para acessar a tela de **Gerenciar perguntas**.
* `mod/playerland:addinstance` — necessária para adicionar a atividade a um curso.

O processamento POST do `manage_questions.php` (adicionar/editar/apagar uma pergunta) verifica
`sesskey` em toda ação destrutiva.

## Privacidade

Linhas de `user_preferences` gravadas pelo plugin (a marca de overlay dispensado) são limpas em
`db/uninstall.php` — a única coisa que o core não limpa automaticamente quando o plugin é
removido, já que toda tabela do `install.xml` é descartada pelo core sozinho.
