# ✨ Funcionalidades

## ✅ Implementado

* 🦊 **Jogabilidade de plataforma:** a raposa Huddy corre, pula, cai e colide com uma fase
  baseada em tiles construída no Phaser 3. A câmera acompanha o jogador; terreno e colisão vêm
  de um mapa do Tiled, tile por tile.
* 🦘 **Pulo de altura variável:** tocar a tecla de pulo dá um pulinho curto; segurar dá o pulo
  cheio — soltar cedo, ainda subindo, corta a velocidade para cima pela metade. Veja
  [Jogabilidade e Movimento](#gameplay).
* 🏃 **Conjunto completo de movimento:** arrancada/rolamento (`Shift`), agachar (`Baixo`),
  escalar em heras/escadas, deslizar e pular de parede — cada um introduzido na primeira fase
  que realmente precisa dele, não tudo de uma vez.
* 🧱 **Perigos e elementos de fase:** espinhos, plataformas móveis, blocos que desmoronam,
  caixotes empurráveis, escadas, saliências de mão única, portas com manivela e checkpoints —
  todos posicionados como marcadores de objeto no Tiled, sem alterar código.
* 🦫 **Três tipos de inimigo:** um **opossum** que patrulha com coleira (pule em cima para
  derrotar; encostar de lado causa dano); uma **águia** que patrulha e mergulha quando o jogador
  passa bem embaixo; um **sapo** que fica parado até o jogador chegar perto e então pula em
  direção a ele. Cair num buraco ou tocar num perigo/inimigo renasce o jogador automaticamente.
* ❓ **Blocos de pergunta (amarelo "?"):** bater por baixo abre um diálogo `core/modal` nativo do
  Moodle com uma pergunta sorteada do banco interno da atividade. O feedback de certo/errado é
  imediato; blocos respondidos escurecem permanentemente e contam para a cota de saída.
* 📘 **Blocos de mini-lição (azul "!"):** até três explicações curtas em texto puro por
  atividade, cada uma mostrada pelo seu próprio bloco no jogo. Diferente do bloco de pergunta, um
  bloco de lição nunca é "gasto" — o estudante pode reler quantas vezes quiser.
* 🔗 **Perguntas vinculadas a um tópico:** uma pergunta pode ser vinculada a uma mini-lição
  específica (1–3), fazendo o bloco de pergunta logo depois de uma lição puxar primeiro do tópico
  correspondente, com uma cadeia de fallback em quatro camadas que garante que um bloco nunca
  fique sem pergunta. Veja [Blocos e Mini-Lições](#questions).
* 🍒 **Colecionáveis:** cerejas e gemas, com um contador ao vivo no HUD.
* ⛶ **Suporte a tela cheia:** um botão e a tecla `F` alternam tela cheia no contêiner do jogo.
* 👋 **Overlay de controles no primeiro carregamento:** um diálogo "Como jogar" dispensável,
  mostrado uma vez por estudante, lembrado por uma preferência de usuário do Moodle (não
  `localStorage`), então acompanha o estudante entre dispositivos em vez de reaparecer a cada
  navegador novo.
* 🧩 **Banco de perguntas interno:** professores gerenciam perguntas de múltipla escolha por
  atividade numa tela dedicada de **Gerenciar perguntas** — ainda sem integração com o Banco de
  Questões do Moodle, veja "Planejado" abaixo.
* 🎓 **Nota proporcional:** a nota da atividade escala com o número de respostas distintas
  corretas em relação a uma meta configurada pelo professor, calculada no servidor e nunca
  confiada ao cliente.
* 🗺️ **Ferramenta de autoria de fases:** as fases são construídas a partir de um pequeno DSL em
  Python (`tools/levelkit.py` + um módulo `tools/levelNNN.py` por fase, uso interno, não
  publicado no pacote de lançamento) que compila layouts de sala em ASCII para JSON do Tiled —
  sem editar grades de tile à mão.
* 🌍 **Bilíngue:** pacotes de idioma em inglês e português do Brasil.
* 🧪 **Testes automatizados:** uma suíte PHPUnit cobrindo a lógica de notas e toda a API externa,
  verde tanto no Moodle 5.1 (PHPUnit 11) quanto no Moodle 4.5 (PHPUnit 9) — veja
  [Testes Automatizados](#testing).
* 💾 **Backup e restauração:** suporte completo ao backup/restore moodle2, incluindo "Duplicar
  atividade" — perguntas, opções e (quando os dados de usuário são incluídos) o progresso de cada
  estudante e suas respostas corretas distintas, com os ids corretamente remapeados na cópia
  restaurada.

## ⏳ Em desenvolvimento / Planejado

* 🗺️ **Dez fases jogáveis:** a meta de lançamento é **uma atividade por fase** — o professor
  escolhe um mapa num dropdown e monta a sequência adicionando atividades ao curso em ordem (sem
  wrapper de campanha dentro do plugin, sem estado de desbloqueio). As fases 1 e 9 já existem (a
  última ainda rotulada "rascunho"); as fases 2–8 ainda precisam ser desenhadas e construídas.
* 🐲 **Fase de chefe (Fase 10):** um chefe "Águia-ninho" reaproveitando o sprite da águia numa
  escala maior — três pisões para derrotar, invocando opossums ao ser atingida. Projetado para
  não precisar de arte nova.
* 🎨 **Sistema de variantes de inimigo:** transformar os três inimigos base em cerca de oito ou
  nove combinando um tingimento de cor com um parâmetro de comportamento (velocidade, alcance da
  coleira, se pode ser pisado) — projetado, ainda não implementado no `play.js`.
* 🔄 **Bloco de pergunta de prática (azul "?"):** um bloco de pergunta recarregável, com um
  temporizador de recarga, reaproveitando a lógica de sorteio já existente.
* 🟢 **Bloco de pergunta de recompensa (verde "?"):** concede um item numa resposta correta —
  depende da decisão de economia abaixo.
* ⏱️ **Timer opcional / modo prática:** uma recomendação interna de acessibilidade (sem timer,
  sem pressão de perigos) para estudantes afetados por pressão de tempo ou exigência motora —
  ainda não implementado, veja [Acessibilidade](#accessibility).
* 💰 **Integração com PlayerHUD/PlayerCoins:** permitir que o PlayerLand conceda
  PlayerCoins/itens através do `local_playergames` em vez de construir sua própria economia —
  uma recomendação de arquitetura, ainda não é uma decisão fechada.
* 📚 **Integração com o Banco de Questões do Moodle:** o banco interno por atividade de hoje é
  uma escolha deliberada de escopo da v1; puxar do Banco de Questões do próprio curso é uma ideia
  pós-v1.
* 🏔️ **Um segundo clima visual:** alternar entre uma aparência de campina e uma de ruínas (já
  esboçada na Fase 9) ao longo das dez fases, a partir do mesmo tileset base.

<p class="page-hint">O plugin é software em estágio Alpha: tudo em "Implementado" acima funciona
hoje; tudo em "Planejado" está desenhado (veja o roadmap interno do projeto) mas ainda não
construído.</p>
