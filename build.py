#!/usr/bin/env python3
"""Package the OpenCart 4 extension as cc_telegram.ocmod.zip.

Windows' Compress-Archive writes backslash separators into the archive, which
OpenCart's installer cannot read — always build through this script.
"""
import os
import zipfile

BASE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(BASE, 'cc_telegram.ocmod.zip')

# Everything the installer needs, and nothing else.
INCLUDE_DIRS = ('admin', 'catalog', 'system')
INCLUDE_FILES = ('install.json',)
SKIP_DIRS = {'.git', '__pycache__', '_screenshots'}
SKIP_EXT = {'.pyc'}

members = []
for name in INCLUDE_FILES:
    path = os.path.join(BASE, name)
    if os.path.isfile(path):
        members.append((path, name))

for top in INCLUDE_DIRS:
    for root, dirs, files in os.walk(os.path.join(BASE, top)):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for f in sorted(files):
            if os.path.splitext(f)[1] in SKIP_EXT:
                continue
            path = os.path.join(root, f)
            # Forward slashes: OpenCart reads archive paths verbatim.
            arc = os.path.relpath(path, BASE).replace(os.sep, '/')
            members.append((path, arc))

if os.path.exists(OUT):
    os.remove(OUT)

with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
    for path, arc in sorted(members, key=lambda m: m[1]):
        z.write(path, arc)

print(OUT, os.path.getsize(OUT), 'bytes,', len(members), 'files')
for _, arc in sorted(members, key=lambda m: m[1]):
    print('   ', arc)
