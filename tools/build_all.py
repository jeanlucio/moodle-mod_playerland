#!/usr/bin/env python3
"""Regenerates every assets/maps/map_levelNNN.json from its levelNNN.py module.

    python3 tools/build_all.py
"""

import glob
import importlib
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


def main():
    sys.path.insert(0, HERE)
    modules = sorted(
        os.path.splitext(os.path.basename(path))[0]
        for path in glob.glob(os.path.join(HERE, 'level[0-9][0-9][0-9].py'))
    )
    if not modules:
        print('no levelNNN.py modules found')
        return
    from levelkit import write
    for name in modules:
        module = importlib.import_module(name)
        write(module.NAME, module.make())


if __name__ == '__main__':
    main()
