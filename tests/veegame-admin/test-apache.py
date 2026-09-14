#!/usr/bin/env python3
"""Against isolated TLS Apache fixture only. Self-signed fixture certificate."""
import re,json,requests,urllib3
from pathlib import Path
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
BASE='https://127.0.0.1:8787/digitaladmin/'
ROOT=Path('/home/user/.cache/vee-admin-fixture')
s=requests.Session();s.verify=False;passed=[]
def check(label,value):
 if not value:raise AssertionError(label)
 passed.append(label)
r=s.get(BASE+'login.php',timeout=10)
check('HTTPS login renders with real Apache PHP',r.status_code==200)
check('production secure cookie','secure' in r.headers.get('Set-Cookie','').lower() and 'HttpOnly' in r.headers['Set-Cookie'] and 'SameSite=Strict' in r.headers['Set-Cookie'])
check('HTTP fails closed',requests.get('http://127.0.0.1:8788/digitaladmin/login.php',timeout=10).status_code==400)
for path in ['lib/bootstrap.php','lib/setup-config.php','lib/schema.php','lib/view.php','lib/data.php']:
 check('Apache denies '+path,s.get(BASE+path,timeout=10).status_code==403)
for name in ['legacy-danger.php','private-backup.sql','private-note.txt']:
 p=ROOT/'digitaladmin'/name;p.write_text('UNSHIPPED-FIXTURE-MARKER')
 try:check('Apache blocks accidental file '+name,s.get(BASE+name,timeout=10).status_code==403)
 finally:p.unlink()
check('static assets available',s.get(BASE+'assets/admin.css',timeout=10).status_code==200)
check('font available',s.get(BASE+'assets/vendor/fonts/remixicon/remixicon.woff2',timeout=10).status_code==200)
csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
r=s.post(BASE+'login.php',data={'csrf':csrf,'username':'owner','password':'Fixture-Only-Passphrase-782!new'},allow_redirects=False,timeout=10)
check('real TLS login succeeds',r.status_code==303)
r=s.get(BASE+'index.php',timeout=10)
check('authenticated dashboard via TLS',r.status_code==200 and 'Welcome back' in r.text)
check('no credentialed cross-origin access','Access-Control-Allow-Origin' not in r.headers and 'Access-Control-Allow-Credentials' not in r.headers)
conn=ROOT/'evenvessis/conn.php';saved=conn.read_bytes()
try:
 conn.write_text('<?php throw new RuntimeException("DO-NOT-EXPOSE-DB-SECRET");')
 r=s.get(BASE+'index.php',timeout=10)
 check('database failure closed and redacted',r.status_code==503 and 'DO-NOT-EXPOSE-DB-SECRET' not in r.text and 'Admin service unavailable' in r.text)
finally:conn.write_bytes(saved)
print(json.dumps({'passed':len(passed),'tests':passed,'fixtureOnly':True},indent=2))
