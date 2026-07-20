import os
import zipfile

OUT = 'cc_telegram-oc3.ocmod.zip'
ROOT = os.path.dirname(os.path.abspath(__file__))

MEMBERS = ['install.xml', 'upload']
SKIP_EXT = ('.zip', '.pyc')

with zipfile.ZipFile(os.path.join(ROOT, OUT), 'w', zipfile.ZIP_DEFLATED) as z:
    for member in MEMBERS:
        path = os.path.join(ROOT, member)
        if os.path.isfile(path):
            z.write(path, member)
            continue
        for base, dirs, files in os.walk(path):
            dirs[:] = [d for d in dirs if d not in ('.git', '__pycache__')]
            for f in files:
                if f.endswith(SKIP_EXT):
                    continue
                full = os.path.join(base, f)
                # OpenCart reads the archive on Linux — always forward slashes.
                arc = os.path.relpath(full, ROOT).replace(os.sep, '/')
                z.write(full, arc)

print(OUT, os.path.getsize(os.path.join(ROOT, OUT)), 'bytes')
