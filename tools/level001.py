#!/usr/bin/env python3
"""Level 1 - A Trilha.

The gentlest possible start: walk, collect cherries, hit one low question block,
make one small jump over a shallow pit, meet the first (leashed) opossum, reach
the burrow. No hazards, nothing that can kill.

Recommended activity setting: "Perguntas necessarias" = 1.

    python3 tools/level001.py        # writes assets/maps/map_level001.json
"""

from levelkit import build, write

NAME = 'map_level001.json'

SIGN_TEXTS = [
    'Setas para andar, ESPACO para pular. Pegue as cerejas!',
]

# ROOM A - flat trail: the sign, a cherry trail, one easy question block.
ROOM_A = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "             ccc",  # 15
    "              ?",  # 16
    "",  # 17
    "   @",  # 18
    "       1            c c c",  # 19
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


def make():
    return build(ROOMS, sign_texts=SIGN_TEXTS)


if __name__ == '__main__':
    write(NAME, make())
