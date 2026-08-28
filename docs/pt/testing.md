# 🧪 Testes Automatizados

O PlayerLand publica uma suíte PHPUnit cobrindo a lógica de notas, toda a API externa (web
service), a API de Privacidade, o formulário de autoria de perguntas e o evento de rastreio de
visualização. Anotações `@covers` em doc-comment são usadas em toda a suíte (não atributos de
PHP), já que ela é verificada entre versões tanto no Moodle 5.1 (PHPUnit 11) quanto no Moodle 4.5
(PHPUnit 9).

## PHPUnit — Testes de Unidade e Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------|------:|-----------------|
| `privacy/provider_test.php` | 23 | Declaração de metadados mais uma guarda de deriva que garante que toda coluna declarada da tabela bate com o esquema real; contextos, lista de usuários, exportação em contexto único/múltiplo, e os três caminhos de exclusão, cada um checado contra um contexto não-módulo, um módulo de curso órfão (apagado), e uma lista de usuários vazia, além de uma guarda de regressão contra colisão de tipo de módulo |
| `lib_grade_test.php` | 16 | Cálculo proporcional de nota (limitado na meta, zerado no piso, meta ausente vira 1, zero para uma nota de atividade não-positiva); os caminhos VALUE/SCALE/NONE do item de nota; uma nota mínima configurada realmente chegando ao item de nota; `'reset'` limpando notas registradas sem apagar o item; `update_grades()` para todos os usuários (com e sem nenhuma tentativa ainda), para um usuário sem tentativa, e para um usuário com uma |
| `external/check_answer_test.php` | 5 | Respostas corretas registradas exatamente uma vez (idempotente numa repetição), respostas erradas não registradas em lugar nenhum mas ainda revelam o id da opção correta, um id de pergunta desconhecido rejeitado com o código de erro dedicado, o gradebook atualizado depois de uma resposta correta |
| `external/get_question_test.php` | 5 | A cadeia de fallback tópico→mini-lição: tópico específico não respondido preferido, caindo para qualquer pergunta desse tópico antes do pool geral, caindo para o pool geral quando o tópico não tem nenhuma, texto de alternativa formatado em vez de ecoado cru |
| `external/save_progress_test.php` | 5 | Criação de tentativa na primeira chamada, a contagem de progresso enviada pelo cliente nunca confiada (sempre recalculada do banco), conclusão reportada assim que a meta é atingida, a capability `view` realmente aplicada, um id de instância desconhecido rejeitado |
| `lib_crud_test.php` | 5 | Persistência de campos em `add_instance`/`update_instance` e o item de nota que criam; `delete_instance` propaga para perguntas, opções, respostas e tentativas; apagar um id desconhecido devolve `false` |
| `mod_form_test.php` | 5 | `levels`/`targetquestions` precisam ser inteiros positivos; uma mini-lição acima do limite de caracteres é rejeitada enquanto as outras ficam intocadas; o próprio limite é inclusivo |
| `event/course_module_viewed_test.php` | 4 | O crud/edulevel/objecttable/component declarados, o `get_url()` herdado apontando para o próprio `view.php` do módulo, um nome/descrição não vazios, e o evento realmente sendo observável assim que disparado |
| `cross_instance_security_test.php` | 3 | Um id de pergunta/opção de uma instância é rejeitado quando pareado com o `playerlandid` de outra instância; o pool de tópico nunca cruza fronteiras de instância; responder numa instância nunca marca uma pergunta de outra como respondida |
| `external/dismiss_intro_test.php` | 3 | A preferência do overlay de primeiro carregamento é gravada para o usuário que chama, escopada só a esse usuário, e rejeita um id de instância desconhecido |
| `form/question_form_test.php` | 3 | A única lógica do lado do servidor nesse formulário — uma seleção de opção correta ausente ou zero é rejeitada, qualquer opção selecionada passa |
| `uninstall_test.php` | 2 | O hook de desinstalação apaga só linhas de `user_preferences` prefixadas com `mod_playerland_`, deixando intactas as preferências de qualquer outro plugin; uma execução sem nada a apagar não gera erro |
| `phaser_loading_test.php` | 2 | Guarda de regressão estrutural: nenhuma tag `<script>` estática enfileira o Phaser, o `game.js` o carrega dinamicamente |
| `lib_supports_test.php` | 1 | Toda flag de feature declarada, incluindo uma feature não reconhecida devolvendo `null` |
| **Total** | **82** | |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/playerland/tests
```

## Cobertura

Medida localmente com Xdebug (`moodle-coverage`, uma ferramenta de bancada — não faz parte do
CI). O escopo padrão da ferramenta é `classes/` mais os arquivos de topo `lib.php`/
`db/upgrade.php` do plugin:

| | Cobertura |
|---|---|
| Classes | 75% (3/4 totalmente cobertas) |
| Métodos | 96,30% (26/27) |
| Linhas | 89,01% (486/546) |

* **`lib.php` agora está em 100% de linhas e métodos.** Fechar essa lacuna revelou um bug real,
  não só um buraco de teste: `playerland_grade_item_update()` repassava `gradepass` para o
  `grade_update()` do core, que ignora essa chave em silêncio — a própria lista de permissão
  interna dele (`lib/gradelib.php`) só deixa passar `itemname`/`idnumber`/`gradetype`/
  `grademax`/`grademin`/`scaleid`/`multfactor`/`plusfactor`/`deleted`/`hidden`. Uma nota mínima
  configurada nunca chegava de fato ao gradebook. Corrigido aplicando-a diretamente no objeto
  `grade_item` depois (o mesmo padrão que o `mod_workshop` usa), e coberto por um teste que
  garante que a nota mínima realmente chega ao item, mais um para o caminho `'reset'` (limpa
  notas registradas sem apagar o item) e um para o caminho de atividade inteira sem nenhuma
  tentativa ainda.
* **`classes/privacy/provider.php`** está em **100% de linhas e métodos (7/7)**, incluindo três
  guardas de contexto não-módulo, uma guarda de módulo de curso órfão, e uma guarda de lista de
  usuários vazia que um teste de caminho feliz puro nunca alcançaria.
* **`classes/form/question_form.php`** e **`classes/event/course_module_viewed.php`** também
  estão em **100%** — ambos são pequenos o suficiente para que toda sua lógica real (uma regra de
  validação; um `init()` definindo três propriedades) seja coberta por completo.
* **`classes/external.php`** (os web services que o jogo realmente chama) está em 98,46% de
  linhas, 94,12% de métodos (16/17). A única lacuna é `dismiss_intro_returns()`, que fica
  intocado porque os testes de `dismiss_intro` chamam o método diretamente em vez de passar pelo
  despacho completo de `call_external_function()` que `save_progress`/`get_question`/
  `check_answer` usam — e que é o que realmente exercita a própria etapa de conversão `_returns()`
  de cada método.
* **`db/upgrade.php`** (0/55 linhas) é o script histórico de migração de esquema. Pela convenção
  do projeto, ele mira um estado de esquema pré-upgrade específico em vez do esquema novo que o
  PHPUnit instala, então não é testado diretamente — a única razão restante pela qual a cobertura
  de linhas agregada acima fica abaixo dos 100% que cada classe individual alcança agora.
