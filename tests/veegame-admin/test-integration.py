#!/usr/bin/env python3
"""Isolated HTTP/DB regression. NEVER points at a production database.
Fixture service: http://127.0.0.1:8786; fixture socket/database hardcoded below.
"""
import concurrent.futures, hashlib, json, re, subprocess
from pathlib import Path
import requests
BASE='http://127.0.0.1:8786/digitaladmin/'
SOCKET='/home/user/.cache/vee-admin-db/db.sock'
ROOT=Path('/home/user/.cache/vee-admin-fixture')
DB='veegame_admin_fixture'
KEY='fixture-only-setup-key-0123456789-abcdefghijklmnopqrstuvwxyz'
PASSWORD='Fixture-Only-Passphrase-782!'
PASSED=[]
def check(label, condition):
    if not condition: raise AssertionError(label)
    PASSED.append(label)
def sql(q):
    return subprocess.run(['mariadb','--no-defaults','--socket='+SOCKET,'-u','root','-BN',DB,'-e',q],capture_output=True,text=True,check=True).stdout.strip()
def token(r):
    m=re.search(r'name="csrf" value="([a-f0-9]{64})"',r.text)
    if not m: raise AssertionError('CSRF field missing: '+r.text[:300])
    return m.group(1)
def get(s,p):return s.get(BASE+p,allow_redirects=False,timeout=15)
def post(s,p,d):return s.post(BASE+p,data=d,allow_redirects=False,timeout=20)
def login(s,name='owner',password=PASSWORD):
    r=get(s,'login.php');return post(s,'login.php',{'csrf':token(r),'username':name,'password':password})
def financial_snapshot():
    return {t:sql('SELECT * FROM '+t+' ORDER BY 1') for t in ['shonu_kaichila','thevani','hintegedukolli','veegame_saas_settings','veegame_saas_bets','veegame_saas_wallet_ledger']}
assert DB=='veegame_admin_fixture' and ROOT.as_posix().startswith('/home/user/.cache/')
(ROOT/'digitaladmin/lib/setup-config.php').write_text("<?php\nif (!defined('VA_ADMIN')) {http_response_code(404);exit;}\nreturn '"+hashlib.sha256(KEY.encode()).hexdigest()+"';\n")
sql('DROP TABLE IF EXISTS veegame_admin_accounts,veegame_admin_audit,veegame_admin_rate')
sql("UPDATE shonu_subjects SET codechorkamukala='Fixture Owner',status=1,akshinak='FIXTURE-USER-TOKEN-DO-NOT-EXPOSE' WHERE id=1")
before=financial_snapshot()
s=requests.Session();r=get(s,'setup.php');check('setup renders before install',r.status_code==200)
check('no-store header', 'no-store' in r.headers.get('Cache-Control',''))
check('frame denial',r.headers.get('X-Frame-Options')=='DENY')
check('CSP frame/form guards',"frame-ancestors 'none'" in r.headers.get('Content-Security-Policy','') and "form-action 'self'" in r.headers['Content-Security-Policy'])
check('HTTPOnly same-site scoped session','HttpOnly' in r.headers.get('Set-Cookie','') and 'SameSite=Strict' in r.headers['Set-Cookie'] and 'path=/digitaladmin/' in r.headers['Set-Cookie'])
check('invalid setup key rejected',post(s,'setup.php',{'csrf':token(r),'setup_key':'wrong','username':'owner','password':PASSWORD,'password_confirm':PASSWORD}).status_code==403)
check('invalid setup made no tables',sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'veegame_admin_%'")=='0')
check('setup missing csrf rejected',post(s,'setup.php',{'setup_key':KEY}).status_code==403)
sessions=[requests.Session(),requests.Session()];tokens=[token(get(x,'setup.php')) for x in sessions]
def setup(i):return post(sessions[i],'setup.php',{'csrf':tokens[i],'setup_key':KEY,'username':'owner','password':PASSWORD,'password_confirm':PASSWORD}).status_code
with concurrent.futures.ThreadPoolExecutor(2) as pool: statuses=list(pool.map(setup,[0,1]))
check('concurrent one-time setup permits one owner',sorted(statuses)==[303,409] and sql('SELECT COUNT(*) FROM veegame_admin_accounts')=='1')
check('password not stored plaintext',sql('SELECT password_hash FROM veegame_admin_accounts')!=PASSWORD)
check('setup does not change financial records',financial_snapshot()==before)
check('setup closed after install',get(s,'setup.php').status_code==409)
check('anonymous dashboard redirects to login',get(requests.Session(),'index.php').headers.get('Location')=='login.php')
check('anonymous action blocked',post(requests.Session(),'action.php',{'action':'user_update'}).status_code==403)
for path in ['lib/bootstrap.php','lib/setup-config.php','lib/schema.php','api/login.php','manage_userwallet.php','../evenvessis/conn.php']:
 check('blocked unshipped/internal path '+path,get(s,path).status_code==404)
r=get(s,'login.php');check('bad password does not log in',post(s,'login.php',{'csrf':token(r),'username':'owner','password':'wrong'}).status_code==200)
r=get(s,'login.php');old=s.cookies.get('VEEGAME_ADMIN_V1');check('valid login succeeds',post(s,'login.php',{'csrf':token(r),'username':'owner','password':PASSWORD}).status_code==303)
check('session id rotates on login',old!=s.cookies.get('VEEGAME_ADMIN_V1'))
for page in ['dashboard','users','wallets','deposits','withdrawals','bets','ledger','audit','checks','security']:
 r=get(s,'index.php?page='+page);check('page renders '+page,r.status_code==200 and 'Fatal error' not in r.text)
 check('no native secrets in '+page,all(v not in r.text for v in ['FIXTURE-USER-TOKEN-DO-NOT-EXPOSE','FIXTURE-HASH-DO-NOT-EXPOSE','FIXTURE-PLAIN-DO-NOT-EXPOSE']))
r=get(s,'index.php?page=users');check('stored XSS escaped','&lt;script&gt;alert(1)&lt;/script&gt;' in r.text and '<script>alert(1)</script>' not in r.text)
r=get(s,'index.php?page=wallets');check('raw balances retain precision and anomalies','100.1200' in r.text and 'invalid-balance' in r.text and '0.123456789' in r.text and '>NULL<' in r.text)
r=get(s,'index.php?page=checks');check('live wallet and game blockers shown','MyISAM' in r.text and 'varchar(500)' in r.text and 'preview' in r.text and 'Not enabled' in r.text)
check('unknown page rejected',get(s,'index.php?page=../../pay/config').status_code==404)
check('array input rejected',get(s,'index.php?page[]=users').status_code==400)
check('injection search does not dump users','Fixture Owner' not in get(s,"index.php?page=users&q=%27%20OR%201%3D1%20--").text)
check('wildcards are literal','Fixture Owner' not in get(s,'index.php?page=users&q=%25').text)
check('invalid pagination rejected',get(s,'index.php?page=users&p=-1').status_code==400)
check('GET action blocked',get(s,'action.php?action=user_update&id=1').status_code==405)
r=get(s,'index.php?page=user&id=1');csrf=token(r);version=re.search(r'name="version" value="([a-f0-9]+)"',r.text).group(1)
params={'csrf':csrf,'action':'user_update','id':'1','version':version,'nickname':'Updated fixture','status':'0','reason':'Fixture regression only','current_password':PASSWORD}
check('forged csrf blocked',post(s,'action.php',dict(params,csrf='bad')).status_code==403)
check('financial action not implemented cannot be called',post(s,'action.php',dict(params,action='reset_all')).status_code==400)
check('password confirmation required',post(s,'action.php',dict(params,current_password='bad')).status_code==403)
check('user profile write succeeds',post(s,'action.php',params).status_code==303)
check('correct user only changed',sql('SELECT codechorkamukala,status,CHAR_LENGTH(akshinak) FROM shonu_subjects WHERE id=1')=='Updated fixture\t0\t0')
check('profile replay rejected',post(s,'action.php',params).status_code==409)
check('profile audit intent and receipt recorded',sql("SELECT COUNT(*) FROM veegame_admin_audit WHERE action IN ('user.update.intent','user.update.applied')")=='2')
check('profile operation leaves all financial data untouched',financial_snapshot()==before)
r=get(s,'index.php?page=checks');report=post(s,'action.php',{'csrf':token(r),'action':'schema_export','current_password':PASSWORD})
check('schema export works',report.status_code==200 and report.json()['report']=='Veegame integration structure')
check('schema export excludes all record content',all(v not in report.text for v in [PASSWORD,'9000000001','Updated fixture','invalid-balance','FIXTURE-HASH','FIXTURE-USER-TOKEN']))
check('export has columns and indexes',any(x['TABLE_NAME']=='shonu_kaichila' for x in report.json()['columns']) and len(report.json()['indexes'])>0)
# Read-only role is enforced server-side, not just by hiding controls.
hashval=sql('SELECT password_hash FROM veegame_admin_accounts WHERE id=1')
sql("INSERT INTO veegame_admin_accounts VALUES(2,'viewer','"+hashval+"','viewer',1,1,NOW())")
v=requests.Session();check('viewer can sign in',login(v,'viewer').status_code==303)
r=get(v,'index.php?page=users');check('viewer can view users',r.status_code==200)
check('viewer cannot invoke write/export',post(v,'action.php',{'csrf':token(r),'action':'schema_export','current_password':PASSWORD}).status_code==403)
# Nonunique user IDs must fail closed even for owners.
sql('ALTER TABLE shonu_subjects DROP PRIMARY KEY')
r=get(s,'index.php?page=user&id=1');check('nonunique ID disables edit form','name="action" value="user_update"' not in r.text)
check('nonunique ID blocks direct write',post(s,'action.php',params).status_code==409)
sql('ALTER TABLE shonu_subjects ADD PRIMARY KEY(id)')
# Missing optional record module never auto-creates donor schema.
sql('RENAME TABLE thevani TO fixture_saved_thevani')
r=get(s,'index.php?page=deposits');check('missing native table shows honest diagnostic',r.status_code==200 and 'compatible thevani table' in r.text)
check('missing native table not autocreated',sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='thevani'")=='0')
sql('RENAME TABLE fixture_saved_thevani TO thevani')
# Reset only fixture rate counters to test credential change and revocation separately.
sql('DELETE FROM veegame_admin_rate')
s2=requests.Session();check('second owner session',login(s2).status_code==303)
r=get(s,'index.php?page=security');newpass=PASSWORD+'new'
check('password changed atomically',post(s,'action.php',{'csrf':token(r),'action':'password_change','current_password':PASSWORD,'new_password':newpass,'password_confirm':newpass}).status_code==303)
check('old concurrent session revoked',get(s2,'index.php').headers.get('Location')=='login.php')
check('new password can sign in',login(s,password=newpass).status_code==303)
r=get(s,'index.php');check('GET logout rejected',get(s,'logout.php').status_code==405)
check('POST logout succeeds',post(s,'logout.php',{'csrf':token(r)}).status_code==303)
check('logged out cookie cannot access data',get(s,'index.php').headers.get('Location')=='login.php')
sql('DELETE FROM veegame_admin_rate')
z=requests.Session();csrf=token(get(z,'login.php'));codes=[]
for i in range(11):codes.append(post(z,'login.php',{'csrf':csrf,'username':'owner','password':'wrong'}).status_code)
check('login attempts rate limited',codes[:10]==[200]*10 and codes[10]==429)
check('all financial rows unchanged after full suite',financial_snapshot()==before)
# Leave only synthetic owner, valid credentials and healthy counters for browser fixture.
sql('DELETE FROM veegame_admin_rate')
print(json.dumps({'passed':len(PASSED),'tests':PASSED,'scope':'Synthetic fixture only. No production DB, accounts, wagers or payouts.'},indent=2))
