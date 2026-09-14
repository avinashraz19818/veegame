from pathlib import Path
import json,hashlib,zipfile
root=Path(__file__).resolve().parents[1]
files=['assets/js/index-CBcbycSk.js','README-VEEGAME-UI3.txt']
payload={p:{'bytes':(root/p).stat().st_size,'sha256':hashlib.sha256((root/p).read_bytes()).hexdigest()} for p in files}
manifest=json.dumps({'version':'veegame-ui-v3','scope':'homepage/login wallet-success toast suppression only','files':payload},indent=2).encode()+b'\n'
out=root/'veegame-update/veegame-ui-v3.zip';out.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for p in files+['VEEGAME-UI3-MANIFEST.json']:
        item=zipfile.ZipInfo(p,date_time=(2026,9,14,0,0,0));item.compress_type=zipfile.ZIP_DEFLATED;item.external_attr=0o100644<<16
        z.writestr(item,manifest if p=='VEEGAME-UI3-MANIFEST.json' else (root/p).read_bytes())
with zipfile.ZipFile(out) as z:
    assert z.testzip() is None
    for p in files:assert z.read(p)==(root/p).read_bytes()
report={'zip':out.name,'bytes':out.stat().st_size,'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'entries':len(files)+1,'payload':payload}
(root/'veegame-update/ui-v3-package-info.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
