"""Build the targeted update only; never include credentials, homepage, dumps or fixtures."""
from pathlib import Path
import hashlib, zipfile, json
ROOT=Path(__file__).resolve().parents[1]
FILES=[
    '.htaccess','README-VEEGAME-MIGRATION.md',
    'assets/js/main.vue_vue_type_style_index_0_scoped_d3b4a951_lang-BQvK2MsY.js',
    'assets/js/veegame-migration-notice.js',
    'evenvessis/api/webapi/GetGameUrl.php','evenvessis/api/webapi/_common.php',
    'evenvessis/api/webapi/GameBetting.php','evenvessis/app_core_live_v4.php','evenvessis/veegame_legacy_gate.php',
    'api-live-v4/Lottery/index.php','draw-live-v4/index.php',
    'saas_lottery/.htaccess','saas_lottery/bootstrap_live_v4.php','saas_lottery/config_live_v4.php',
    'saas_lottery/provider.php','saas_lottery/schema.php','saas_lottery/schema.sql','saas_lottery/legacy_archive.php',
    'tools/veegame-migration/.htaccess','tools/veegame-migration/migrate.php','veegame-legacy-history.html',
]
assert len(FILES)==len(set(FILES))
folder=ROOT/'veegame-update';folder.mkdir(exist_ok=True)
metadata={p:{'bytes':(ROOT/p).stat().st_size,'sha256':hashlib.sha256((ROOT/p).read_bytes()).hexdigest()} for p in FILES}
manifest=json.dumps({'release':'veegame-saas-migration-v1','mode':'preview-until-database-cutover','files':metadata},indent=2).encode()+b'\n'
archive=folder/'veegame-saas-migration-v1.zip'
with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for name in FILES+['VEEGAME-MIGRATION-MANIFEST.json']:
        info=zipfile.ZipInfo(name,date_time=(2026,9,14,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16
        z.writestr(info,manifest if name=='VEEGAME-MIGRATION-MANIFEST.json' else (ROOT/name).read_bytes())
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for name in FILES: assert z.read(name)==(ROOT/name).read_bytes()
report={'file':str(archive.relative_to(ROOT)),'bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'entries':len(FILES)+1,'payload':metadata}
(folder/'package-info.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k!='payload'},indent=2))
