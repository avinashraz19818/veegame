"""Local-only tests: MariaDB socket fixture + HTTP fixture server. No live accounts or bets."""
import ssl, urllib.parse
import unittest, subprocess, json, time, urllib.request, urllib.error, hashlib, concurrent.futures, os
from pathlib import Path
SITE=Path('/home/user/.cache/veegame-testsite'); BASE=os.environ.get('VEE_FIXTURE_URL','http://127.0.0.1:8791')
assert urllib.parse.urlsplit(BASE).hostname in ('127.0.0.1','localhost'), 'Tests must never target a live site'
CONTEXT=ssl._create_unverified_context() if BASE.startswith('https://') else None  # self-signed LOCAL Apache fixture only
SOCKET='/home/user/.cache/veegame-db/mysql.sock'
def sql(q):
    p=subprocess.run(['mariadb','--socket='+SOCKET,'-u','root','--batch','--skip-column-names','veegame_fixture'],input=q,text=True,capture_output=True,check=True);return p.stdout.strip()
def token(uid=1,exp=None):
    payload=json.dumps({'id':uid,'exp':exp or int(time.time())+3600})
    code="require '"+str(SITE/'evenvessis/functions2.php')+"'; echo generate_jwt(['alg'=>'HS256','typ'=>'JWT'],json_decode('"+payload+"',true));"
    return subprocess.check_output(['php','-r',code],text=True)
def state(mode='normal',issue='20260914900010240',start=None,number=6):
    (SITE/'fixture-state.json').write_text(json.dumps({'mode':mode,'issue':issue,'start':start or int(time.time()*1000)-10000,'number':number}))
def request(path,body=None,auth=None):
    headers={'Content-Type':'application/json'}
    if auth is not None: headers['Authorization']='Bearer '+auth
    req=urllib.request.Request(BASE+path,data=json.dumps(body).encode() if body is not None else None,headers=headers)
    try:r=urllib.request.urlopen(req,timeout=20,context=CONTEXT)
    except urllib.error.HTTPError as e:r=e
    raw=r.read().decode();return r.status,json.loads(raw)
def api(action,body=None,auth=None): return request('/api/Lottery/'+action,body,auth)
def cli(*args):
    return subprocess.run(['php',str(SITE/'tools/veegame-migration/migrate.php'),*args],text=True,capture_output=True)
class MigrationTests(unittest.TestCase):
    def setUp(self):
        self.auth=token();self.other=token(2);state()
        sql("DROP TRIGGER IF EXISTS reject_ledger; DELETE FROM veegame_saas_bets; DELETE FROM veegame_saas_requests; DELETE FROM veegame_saas_results; DELETE FROM veegame_saas_wallet_ledger; DELETE FROM veegame_legacy_archive; DELETE FROM bajikattuttate; DELETE FROM veegame_saas_settings; INSERT INTO veegame_saas_settings VALUES ('migration_state','active',NOW()); DELETE FROM shonu_subjects; DELETE FROM shonu_kaichila; INSERT INTO shonu_subjects VALUES (1,'FIXTURE-1',1,'"+self.auth+"','TEST'),(2,'FIXTURE-2',1,'"+self.other+"','TEST'); INSERT INTO shonu_kaichila VALUES (1,1000),(2,2000);")
    def bet(self,**kw):
        body={'gameCode':'WinGo_1M','issueNumber':'20260914900010240','amount':10,'betMultiple':1,'betContent':'Color_red','random':'fixture-request-0000001'};body.update(kw);return api('WinGoBet',body,self.auth)
    def balance(self):return sql('SELECT motta FROM shonu_kaichila WHERE balakedara=1;')
    def activate_preview(self):sql("UPDATE veegame_saas_settings SET setting_value='preview' WHERE setting_key='migration_state';")
    def test_01_launcher_no_missing_include(self):
        status,b=request('/evenvessis/api/webapi/GetGameUrl',{});self.assertEqual(status,200);self.assertEqual(b['code'],7)
    def test_02_auth_and_expiry(self):
        self.assertEqual(api('GetBalance')[0],401);self.assertEqual(api('GetBalance',auth='malformed')[0],401)
        expired=token(exp=int(time.time())-60);sql("UPDATE shonu_subjects SET akshinak='"+expired+"' WHERE id=1;");self.assertEqual(api('GetBalance',auth=expired)[0],401)
        sql("UPDATE shonu_subjects SET akshinak='"+self.auth+"',status=0 WHERE id=1;");self.assertEqual(api('GetBalance',auth=self.auth)[0],401)
    def test_03_signed_launcher_exact_screen(self):
        body={'gameCode':'WinGo_1M','language':0,'random':'fixture-random-123456','timestamp':int(time.time()),'vendorCode':'ARLottery'}
        canonical={k:body[k] for k in sorted(body) if k!='timestamp'};body['signature']=hashlib.md5(json.dumps(canonical,separators=(',',':')).encode()).hexdigest().upper()
        status,b=request('/evenvessis/api/webapi/GetGameUrl',body,self.auth);self.assertEqual(status,200);self.assertEqual(b['code'],0);self.assertIn('https://veergame.club9.eu.cc/?Token=',b['data']['url']);self.assertIn('#/saasLottery/WinGo',b['data']['url'])
    def test_04_screen_contracts(self):
        for action in ['GetGameList','GetGameInfo','GetUserInfo','GetBalance','GetBetLimit','GetGameIntroduce','GetRecordPage','GetMigrationStatus']:
            with self.subTest(action=action):self.assertEqual(api(action+'?gameCode=WinGo_1M',auth=self.auth)[1]['code'],0)
        self.assertEqual(api('GetUserInfo',auth=self.auth)[1]['data']['isOpenFollow'],False)
    def test_05_preview_blocks_wallet_writes(self):
        self.activate_preview();self.assertNotEqual(self.bet()[1]['code'],0);self.assertEqual(self.balance(),'1000.0000');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_bets'),'0')
    def test_06_valid_bet_and_true_timestamp(self):
        status,b=self.bet();self.assertEqual(b['code'],0,b);self.assertEqual(self.balance(),'990.0000');self.assertEqual(sql('SELECT status FROM veegame_saas_bets'),'pending')
        age=int(sql('SELECT ABS(TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP()+INTERVAL 330 MINUTE,created_at)) FROM veegame_saas_bets'));self.assertLess(age,5)
    def test_07_concurrent_duplicate_once(self):
        with concurrent.futures.ThreadPoolExecutor(max_workers=4) as ex: responses=list(ex.map(lambda _:self.bet(),range(4)))
        self.assertTrue(all(b['code']==0 for _,b in responses),responses);self.assertEqual(self.balance(),'990.0000');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_bets'),'1');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_wallet_ledger'),'1')
    def test_08_outage_and_stale_fail_closed(self):
        for mode in ['outage','stale']:
            state(mode);self.assertNotEqual(self.bet()[1]['code'],0);self.assertEqual(self.balance(),'1000.0000');self.assertEqual(request('/WinGo/WinGo_1M.json')[0],503)
    def test_09_closed_and_published_rejected(self):
        state(start=int(time.time()*1000)-57000);self.assertNotEqual(self.bet()[1]['code'],0)
        state('published');self.assertEqual(self.bet()[0],409);self.assertEqual(self.balance(),'1000.0000')
    def test_10_current_result_not_revealed(self):
        state('published');status,b=request('/WinGo/WinGo_1M/GetHistoryIssuePage.json');self.assertEqual(status,200);self.assertNotIn('20260914900010240',[r['issueNumber'] for r in b['data']['list']]);self.assertEqual(sql("SELECT COUNT(*) FROM veegame_saas_results WHERE issue_number='20260914900010240'"),'0')
    def test_11_fault_rolls_back(self):
        sql("CREATE TRIGGER reject_ledger BEFORE INSERT ON veegame_saas_wallet_ledger FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FIXTURE failure';")
        self.assertEqual(self.bet()[0],503);self.assertEqual(self.balance(),'1000.0000');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_requests'),'0');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_bets'),'0');sql('DROP TRIGGER reject_ledger')
        self.assertEqual(self.bet()[1]['code'],0)
    def test_12_settlement_once_and_identity(self):
        self.assertEqual(self.bet(random=123456789012,betContent='Color_Red')[1]['code'],0);state(issue='20260914900010241')
        with concurrent.futures.ThreadPoolExecutor(max_workers=3) as ex: responses=list(ex.map(lambda _:api('GetRecordPage?gameCode=WinGo_1M',auth=self.auth),range(3)))
        self.assertTrue(all(b['code']==0 for _,b in responses),responses);self.assertEqual(self.balance(),'1009.6000');self.assertEqual(sql("SELECT status FROM veegame_saas_bets"),'won');self.assertEqual(sql("SELECT COUNT(*) FROM veegame_saas_wallet_ledger WHERE entry_type='bet_payout'"),'1')
        self.assertEqual(sql("SELECT issue_number FROM veegame_saas_bets"),'20260914900010240')
    def test_13_settlement_failure_can_retry(self):
        self.bet();state(issue='20260914900010241');sql("CREATE TRIGGER reject_ledger BEFORE INSERT ON veegame_saas_wallet_ledger FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FIXTURE failure';")
        api('GetRecordPage?gameCode=WinGo_1M',auth=self.auth);self.assertEqual(self.balance(),'990.0000');self.assertEqual(sql('SELECT status FROM veegame_saas_bets'),'pending')
        sql('DROP TRIGGER reject_ledger');api('GetRecordPage?gameCode=WinGo_1M',auth=self.auth);self.assertEqual(self.balance(),'1009.6000');self.assertEqual(sql('SELECT status FROM veegame_saas_bets'),'won')
    def test_14_other_user_cannot_see_records(self):
        self.bet();self.assertEqual(api('GetRecordPage?gameCode=WinGo_1M',auth=self.other)[1]['data']['totalCount'],0)
    def test_15_migration_pending_blocks(self):
        self.activate_preview();self.assertEqual(cli('--freeze-legacy').returncode,0);sql("UPDATE veegame_saas_settings SET setting_value='1' WHERE setting_key='legacy_frozen_at'; INSERT INTO bajikattuttate VALUES(1,1,'20260101000000001',10,10,1,10,'perte',9.8,NULL,'2026-01-01 00:00:00');")
        result=cli('--archive','--maintenance-confirmed','--legacy-cron-stopped');self.assertNotEqual(result.returncode,0);self.assertIn('pending',result.stderr);self.assertEqual(self.balance(),'1000.0000')
    def test_16_archive_no_replay_no_wallet_change(self):
        self.activate_preview();cli('--freeze-legacy');sql("UPDATE veegame_saas_settings SET setting_value='1' WHERE setting_key='legacy_frozen_at'; INSERT INTO bajikattuttate VALUES(1,1,'20260101000000001',10,10,1,10,'gagner',19.6,6,'2026-01-01 00:01:00');")
        args=('--archive','--maintenance-confirmed','--legacy-cron-stopped');result=cli(*args);self.assertEqual(result.returncode,0,result.stderr);self.assertEqual(cli(*args).returncode,0);self.assertEqual(self.balance(),'1000.0000');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_saas_bets'),'0');self.assertEqual(sql('SELECT COUNT(*) FROM veegame_legacy_archive'),'1')
        b=api('GetLegacyRecordPage',auth=self.auth)[1]['data']['list'][0];self.assertEqual(b['recordedTime'],'2026-01-01 00:01:00');self.assertNotIn('betTime',b);self.assertEqual(api('GetLegacyRecordPage',auth=self.other)[1]['data']['totalCount'],0)
        result=cli('--activate','--maintenance-confirmed','--legacy-cron-stopped','--rewards-scope-reviewed');self.assertEqual(result.returncode,0,result.stderr);self.assertEqual(self.balance(),'1000.0000')
    def test_17_wallet_change_blocks_activation(self):
        self.activate_preview();cli('--freeze-legacy');sql("UPDATE veegame_saas_settings SET setting_value='1' WHERE setting_key='legacy_frozen_at';");self.assertEqual(cli('--archive','--maintenance-confirmed','--legacy-cron-stopped').returncode,0);sql('UPDATE shonu_kaichila SET motta=999 WHERE balakedara=1');self.assertNotEqual(cli('--activate','--maintenance-confirmed','--legacy-cron-stopped','--rewards-scope-reviewed').returncode,0)
    def test_18_legacy_gate_and_methods(self):
        self.assertEqual(request('/evenvessis/api/webapi/GameBetting',{},self.auth)[0],409);self.assertEqual(api('WinGoBet',auth=self.auth)[0],405)
    def test_19_invalid_amount_nonce_and_unsupported_game(self):
        for data in [{'amount':-1},{'amount':1.1},{'betMultiple':1.5},{'random':''},{'gameCode':'K3_1M'}]:
            with self.subTest(data=data):self.assertNotEqual(self.bet(**data)[1]['code'],0);self.assertEqual(self.balance(),'1000.0000')
    def test_20_pause_still_settles(self):
        self.bet();self.assertEqual(cli('--pause').returncode,0);self.assertNotEqual(self.bet(random='fixture-request-0000002')[1]['code'],0);state(issue='20260914900010241');api('GetRecordPage?gameCode=WinGo_1M',auth=self.auth);self.assertEqual(self.balance(),'1009.6000')
    def test_21_all_wingo_selections(self):
        code="require '"+str(SITE/'saas_lottery/bootstrap_live_v4.php')+"'; $out=[];for($n=0;$n<10;$n++){foreach(array_merge(array_map(fn($i)=>'Num_'.$i,range(0,9)),['Color_Red','Color_Green','Color_Violet','BigSmall_Big','BigSmall_Small']) as $c){$out[$n][$c]=sl_evaluate_bet('WinGo_1M',$c,['premium'=>(string)$n]);}} echo json_encode($out);"
        rows=json.loads(subprocess.check_output(['php','-r',code],text=True))
        for n,row in enumerate(rows):
            for i in range(10):self.assertEqual(row['Num_'+str(i)],[int(i==n),9])
            self.assertEqual(row['Color_Red'],[int(n%2==0),1.5 if n==0 else 2])
            self.assertEqual(row['Color_Green'],[int(n%2==1),1.5 if n==5 else 2])
            self.assertEqual(row['Color_Violet'],[int(n in (0,5)),4.5])
            self.assertEqual(row['BigSmall_Big'],[int(n>=5),2]);self.assertEqual(row['BigSmall_Small'],[int(n<5),2])
    def test_22_wrong_engine_blocks_money(self):
        sql('ALTER TABLE veegame_saas_bets ENGINE=MyISAM')
        try:self.assertEqual(self.bet()[0],503);self.assertEqual(self.balance(),'1000.0000')
        finally:sql('ALTER TABLE veegame_saas_bets ENGINE=InnoDB')
    def test_23_missing_unique_index_blocks_money(self):
        sql('ALTER TABLE veegame_saas_wallet_ledger DROP INDEX uq_saas_wallet_entry')
        try:self.assertEqual(self.bet()[0],503);self.assertEqual(self.balance(),'1000.0000')
        finally:sql('ALTER TABLE veegame_saas_wallet_ledger ADD UNIQUE KEY uq_saas_wallet_entry(entry_key)')
if __name__=='__main__': unittest.main(verbosity=2)
