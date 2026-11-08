#!/usr/bin/env python3
"""Prints every text file of the package with CR (Windows) line endings; exit 1 when there is one."""
import os
import sys

bad = []
for root, dirs, files in os.walk('upload'):
    for name in files:
        path = os.path.join(root, name)
        if name.endswith(('.php', '.tpl', '.js', '.css', '.xml')):
            with open(path, 'rb') as f:
                if b'\r' in f.read():
                    bad.append(path)
with open('install.xml', 'rb') as f:
    if b'\r' in f.read():
        bad.append('install.xml')
print('\n'.join(bad))
sys.exit(1 if bad else 0)
