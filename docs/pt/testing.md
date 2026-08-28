# 🧪 Testes Automatizados

O PlayerLand publica uma suíte PHPUnit cobrindo a lógica de notas e toda a API externa (web
service). Anotações `@covers` em doc-comment são usadas em toda a suíte (não atributos de PHP),
já que ela é verificada entre versões tanto no Moodle 5.1 (PHPUnit 11) quanto no Moodle 4.5
(PHPUnit 9).

## PHPUnit — Testes de Unidade e Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------|------:|-----------------|
| `lib_grade_test.php` | 13 | Cálculo proporcional de nota (limitado na meta, zerado no piso, meta ausente vira 1, zero para uma nota de atividade não-positiva); os caminhos VALUE/SCALE/NONE do item de nota; `update_grades()` para todos os usuários, para um usuário sem tentativa, e para um usuário com uma |
| `external/check_answer_test.php` | 5 | Respostas corretas registradas exatamente uma vez (idempotente numa repetição), respostas erradas não registradas em lugar nenhum mas ainda revelam o id da opção correta, um id de pergunta desconhecido rejeitado com o código de erro dedicado, o gradebook atualizado depois de uma resposta correta |
| `external/get_question_test.php` | 5 | A cadeia de fallback tópico→mini-lição: tópico específico não respondido preferido, caindo para qualquer pergunta desse tópico antes do pool geral, caindo para o pool geral quando o tópico não tem nenhuma, texto de alternativa formatado em vez de ecoado cru |
| `external/save_progress_test.php` | 5 | Criação de tentativa na primeira chamada, a contagem de progresso enviada pelo cliente nunca confiada (sempre recalculada do banco), conclusão reportada assim que a meta é atingida, a capability `view` realmente aplicada, um id de instância desconhecido rejeitado |
| `lib_crud_test.php` | 5 | Persistência de campos em `add_instance`/`update_instance` e o item de nota que criam; `delete_instance` propaga para perguntas, opções, respostas e tentativas; apagar um id desconhecido devolve `false` |
| `mod_form_test.php` | 5 | `levels`/`targetquestions` precisam ser inteiros positivos; uma mini-lição acima do limite de caracteres é rejeitada enquanto as outras ficam intocadas; o próprio limite é inclusivo |
| `cross_instance_security_test.php` | 3 | Um id de pergunta/opção de uma instância é rejeitado quando pareado com o `playerlandid` de outra instância; o pool de tópico nunca cruza fronteiras de instância; responder numa instância nunca marca uma pergunta de outra como respondida |
| `external/dismiss_intro_test.php` | 3 | A preferência do overlay de primeiro carregamento é gravada para o usuário que chama, escopada só a esse usuário, e rejeita um id de instância desconhecido |
| `uninstall_test.php` | 2 | O hook de desinstalação apaga só linhas de `user_preferences` prefixadas com `mod_playerland_`, deixando intactas as preferências de qualquer outro plugin; uma execução sem nada a apagar não gera erro |
| `phaser_loading_test.php` | 2 | Guarda de regressão estrutural: nenhuma tag `<script>` estática enfileira o Phaser, o `game.js` o carrega dinamicamente |
| `lib_supports_test.php` | 1 | Toda flag de feature declarada, incluindo uma feature não reconhecida devolvendo `null` |
| **Total** | **49** | |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/playerland/tests
```

## Cobertura

Medida localmente com Xdebug (`moodle-coverage`, uma ferramenta de bancada — não faz parte do
CI). O escopo padrão da ferramenta é `classes/` mais os arquivos de topo `lib.php`/
`db/upgrade.php` do plugin:

| | Cobertura |
|---|---|
| Classes | 0% (0/4 totalmente cobertas) |
| Métodos | 61,11% (22/36) |
| Linhas | 54,95% (294/535) |

O agregado parece baixo principalmente por **causa do que esta rodada intencionalmente ainda não
tocou**, não porque o código testado seja fraco:

* **`classes/external.php`** (os web services que o jogo realmente chama) é o arquivo mais
  forte: 98,46% de linhas, 94,12% de métodos (16/17). A única lacuna é `dismiss_intro_returns()`,
  que fica intocado porque os testes de `dismiss_intro` chamam o método diretamente em vez de
  passar pelo despacho completo de `call_external_function()` que `save_progress`/`get_question`/
  `check_answer` usam — e que é o que realmente exercita a própria etapa de conversão `_returns()`
  de cada método.
* **`lib.php`** está em 96,23% de linhas. Seus dois métodos abaixo do limite estrito de 100% são
  `grade_item_update()` (89,66% de linhas — o caminho de notas `'reset'` e o parâmetro opcional
  `gradepass` são os dois ramos que nenhum teste atual exercita) e `update_grades()` (96%, uma
  linha — o caminho de atividade inteira sem tentativas para `userid=0` numa instância com nota
  positiva que ninguém jogou ainda).
* **Três classes não têm nenhuma cobertura hoje, sinalizadas aqui em vez de escondidas:**
  `classes/form/question_form.php` (o formulário de autoria de perguntas),
  `classes/privacy/provider.php` (toda a API de Privacidade — exportação/exclusão de dados
  pessoais), e `classes/event/course_module_viewed.php` (o evento padrão de rastreio de
  visualização). Nenhuma estava no escopo desta rodada, que focou em `lib.php` e na API externa;
  fechar `privacy/provider.php` em particular é o próximo alvo natural, dado que lida com dados
  pessoais.
* **`db/upgrade.php`** (0/55 linhas) é o script histórico de migração de esquema. Pela convenção
  do projeto, ele mira um estado de esquema pré-upgrade específico em vez do esquema novo que o
  PHPUnit instala, então não é testado diretamente.
