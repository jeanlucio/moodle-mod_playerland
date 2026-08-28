#!/usr/bin/env python3
"""Level 1 - A Trilha.

The gentlest possible start: walk, collect cherries, read one mini-lesson block,
answer one low question block, make one small jump over a shallow pit, meet the
first (leashed) opossum, reach the burrow. No hazards, nothing that can kill.

The lesson block only appears in-game if the teacher fills "Mini-licao 1" on the
activity form. Recommended activity setting: "Perguntas necessarias" = 1.

    python3 tools/level001.py        # writes assets/maps/map_level001.json
"""

from levelkit import build, write

NAME = 'map_level001.json'

SIGN_TEXTS = [
    'Setas para andar, ESPACO para pular. Pegue as cerejas!',
    'Bata no bloco azul (!) por baixo para ler a licao.',
]

# ROOM A - flat trail: controls sign, cherries, the lesson block, then a question.
ROOM_A = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "             ccc",  # 15
    "          L      ?",  # 16  lesson (read) then question (answer)
    "",  # 17
    "   @",  # 18
    "       1  2         c c c",  # 19  sign 1 = controls, sign 2 points at the lesson block
    "========================================",  # 20
]

# ROOM B - one shallow pit to jump, the first opossum, the burrow.
ROOM_B = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "", "", "", "",  # 15-18
    "             o    c c c       E     ",  # 19
    "====   =============================",  # 20  pit cols 4-6
]

ROOMS = [ROOM_A, ROOM_B]

# Room A's question practises mini-lesson 1; Room B is the general pool.
ROOM_TOPICS = (1, 0)


def make():
    return build(ROOMS, sign_texts=SIGN_TEXTS, room_topics=ROOM_TOPICS)


if __name__ == '__main__':
    write(NAME, make())
