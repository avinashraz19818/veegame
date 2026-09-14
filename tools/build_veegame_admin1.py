#!/usr/bin/env python3
"""Build only the new admin directory. Never bundle native runtime/config or setup key."""
import hashlib,json,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
files=sorted(p for p in (ROOT/'digitaladmin').rglob('*') if p.is_file())+[ROOT/'README-VEEGAME-ADMIN1.md']
assert all(p.suffix in {'.php','.css','.js','.woff2','.md'} or p.name=='.htaccess' for p in files)
assert all(p.is_relative_to(ROOT/'digitaladmin') or p.name=='README-VEEGAME-ADMIN1.md' for p in files)
manifest={'release':'veegame-admin-v1','status':'foundation; full financial/WinGo parity incomplete','donor':'shreewin@86e5048e8b07a04e5fa4e3825db77dfa917ac1bd','files':{p.relative_to(ROOT).as_posix():{'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()} for p in files}}
def entry(name,data):
 i=zipfile.ZipInfo(name,(2026,9,14,7,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,data)
out=ROOT/'veegame-update/veegame-admin-v1.zip'
with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for p in files:entry(p.relative_to(ROOT).as_posix(),p.read_bytes())
 entry('VEEGAME-ADMIN1-MANIFEST.json',(json.dumps(manifest,indent=2)+'\n').encode())
with zipfile.ZipFile(out) as z:
 assert z.testzip() is None
 assert not any('setup-key' in n or n.startswith(('saas_lottery/','pay/','evenvessis/','assets/')) for n in z.namelist())
 entries=len(z.infolist())
info={'zip':out.relative_to(ROOT).as_posix(),'bytes':out.stat().st_size,'entries':entries,'sha256':hashlib.sha256(out.read_bytes()).hexdigest()}
(ROOT/'veegame-update/admin-v1-package-info.json').write_text(json.dumps(info,indent=2)+'\n')
print(json.dumps(info,indent=2))
