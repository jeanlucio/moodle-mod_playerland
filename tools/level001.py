#!/usr/bin/env python3
"""Level 1 - A Trilha.

The invisible tutorial: three flat rooms that teach the core verbs by playing,
with nothing that can hurt the fox.

  A  Bem-vindo   - walk, collect a cherry trail, read the "!" lesson block, then
                   answer the "?" question block (both tied to mini-lesson 1).
  B  O Salto     - one shallow pit with a cherry arc showing the jump; a
                   checkpoint sits right before it.
  C  O Guarda    - the first (leashed) opossum, a gem on a low ledge to reach
                   for, then the burrow.

Advanced verbs (roll, crouch, climb, wall-jump) are NOT here - each is taught in
the level that first needs it.

The lesson block only appears if the teacher fills "Mini-licao 1" on the form.
Recommended activity setting: "Perguntas necessarias" = 1.

    python3 tools/level001.py        # writes assets/maps/map_level001.json
"""

from levelkit import build, write

NAME = 'map_level001.json'

SIGN_TEXTS = [
    'Setas para andar, ESPACO para pular. Segure para pular mais alto.',
    'Bata no bloco azul (!) para ler a licao. O amarelo (?) tem a pergunta.',
]

# ROOM A - flat welcome: signs, cherry trail, the lesson block, then a question.
ROOM_A = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-13
    "             ccc",  # 14
    "",  # 15
    "          L      ?",  # 16  lesson (read) then question (answer)
    "",  # 17
    "   @",  # 18
    "       1  2              c c c",  # 19
    "==============================================",  # 20
]

# ROOM B - the first jump: a 3-tile pit with a cherry arc, checkpoint before it.
ROOM_B = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-15
    "         ccc",  # 16  cherry arc over the pit
    "", "",  # 17-18
    "  S                    c c c",  # 19  checkpoint, then a cherry trail
    "=========   ==============================",  # 20  pit cols 9-11
]

# ROOM C - the first opossum, a gem to reach for, the burrow.
ROOM_C = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "                     g",  # 15  gem above the ledge
    "                  ------",  # 16  one-way ledge, ~4 tiles up (reachable)
    "", "",  # 17-18
    "        o                  c c c        E",  # 19
    "======================================================",  # 20
]

ROOMS = [ROOM_A, ROOM_B, ROOM_C]

# Room A's question practises mini-lesson 1; B and C are the general pool.
ROOM_TOPICS = (1, 0, 0)


def make():
    return build(ROOMS, sign_texts=SIGN_TEXTS, room_topics=ROOM_TOPICS)


if __name__ == '__main__':
    write(NAME, make())
