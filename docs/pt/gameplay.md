# 🦊 Jogabilidade e Movimento

## Feel do Movimento

Todo número de movimento vive num único objeto `TUNING` em `amd/src/play.js`, ajustado e
aprovado em testes reais de jogo antes de qualquer fase ser construída em cima dele:

* **Correr** numa velocidade fixa, com câmera que acompanha suavemente.
* **Pular** tem altura variável: soltar a tecla ainda subindo multiplica a velocidade para cima
  por um fator de corte, então um toque é aproximadamente um pulinho de metade da altura e
  segurar dá o pulo cheio (~4 tiles). Vale tanto para o pulo normal quanto para o pulo de parede.
* **Arrancada/rolamento** (`Shift`) é um impulso horizontal de duração fixa, com seu próprio
  tempo de recarga.
* **Agachar** (`Baixo`) abaixa a hitbox e reduz a velocidade, necessário para algumas passagens
  baixas.
* **Escalar** funciona em tiles de hera/escada, movendo o jogador para cima e para baixo numa
  velocidade fixa, independente da gravidade.
* **Deslizar e pular de parede** disparam contra uma parede sólida no ar: deslizando para baixo
  numa velocidade limitada, e pulando para longe da parede com um breve bloqueio de entrada para
  o pulo realmente se afastar da parede em vez de grudar de novo nela.

Os verbos avançados (arrancada, agachar, escalar, pulo de parede) **não** são ensinados todos de
uma vez — cada um é introduzido na primeira fase que realmente precisa dele, com uma placa
exatamente no momento em que se torna relevante.

## Terreno, Perigos e Elementos

Tudo abaixo é posicionado como marcador de ponto na camada de objetos do Tiled de uma fase —
adicionar um nunca exige tocar em `play.js`:

| Marcador | Comportamento |
|--------|----------|
| Espinhos (voltados para cima/baixo) | Causa dano ao contato |
| Plataforma móvel (horizontal/vertical) | Carrega o jogador junto por um caminho fixo |
| Bloco que desmorona | Quebra um instante depois de o jogador pisar em cima |
| Caixote | Empurrável, bloqueia inimigos e perigos como terreno sólido |
| Escada | Trecho vertical escalável |
| Saliência de mão única | Sólida só por cima, deixa o jogador passar por baixo |
| Manivela + porta | Puxar uma manivela abre a porta vinculada a ela |
| Checkpoint | Move o ponto de renascimento para frente. Renderizado com o mesmo poste da placa, mas tingido de cinza até ser ativado (aí fica verde), para não ser confundido com uma placa de leitura |
| Placa | Mostra uma linha curta de dica quando o jogador está perto, sem precisar de tecla de interação. Sempre na cor natural da madeira |

Cair num buraco, ou ser atingido por um perigo/inimigo, renasce o jogador automaticamente no
último checkpoint; uma tecla de renascimento manual existe caso o jogador fique preso.

## Inimigos

| Inimigo | Comportamento |
|-------|----------|
| 🦡 Opossum | Patrulha de um lado para o outro dentro de um alcance de coleira a partir do ponto de nascimento, virando em paredes e bordas de plataforma. Pule em cima para derrotar; encostar de lado causa dano ao jogador. |
| 🦅 Águia | Patrulha ao longo de uma linha fixa e mergulha em alta velocidade quando o jogador passa bem embaixo, dentro do alcance e fora do tempo de recarga. |
| 🐸 Sapo | Fica parado até o jogador chegar perto, então pula em direção a ele. |

## Autoria de Fases

Uma fase do PlayerLand é um mapa JSON do Tiled (camadas de tile mais uma camada `objects` de
marcadores de ponto) gerado a partir de uma pequena ferramenta de autoria em Python mantida em
`tools/` (uso interno, não publicada no pacote de lançamento, excluída do ZIP do plugin via
`.gitattributes`):

* `tools/levelkit.py` — o construtor compartilhado: "salas" em ASCII (uma string por linha)
  unidas lado a lado, caracteres de tile mapeados para GIDs de tile reais, caracteres de marcador
  mapeados para objetos do jogo com suas propriedades.
* `tools/levelNNN.py` — um pequeno módulo por fase, descrevendo suas salas e marcadores em ASCII
  simples, depois chamando o `levelkit` para gerar `assets/maps/map_levelNNN.json`.
* `tools/build_all.py` — regenera todos os mapas publicados a partir de seus módulos
  `levelNNN.py` de uma vez.

Isso mantém o design de fase revisável como texto puro (uma sala é só um punhado de strings
curtas) e remove o risco de alinhar à mão uma grade de tile do Tiled pixel por pixel. Um
professor instalando o plugin só escolhe um mapa pronto no dropdown das configurações da
atividade — não precisa do editor Tiled para isso. Um terceiro que queira um mapa totalmente
customizado ainda pode criar um diretamente no [Tiled](https://www.mapeditor.org/), já que o
formato publicado é JSON puro do Tiled.
