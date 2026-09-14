"""Build an ISOLATED test site. Never load the repository's real connection settings."""
from pathlib import Path
import shutil,re,subprocess,json
ROOT=Path(__file__).resolve().parents[2]; SITE=Path('/home/user/.cache/veegame-testsite');SITE.mkdir(parents=True,exist_ok=True)
for directory in ['saas_lottery','api-live-v4','draw-live-v4','tools/veegame-migration']:
 shutil.copytree(ROOT/directory,SITE/directory,dirs_exist_ok=True)
for p in ['evenvessis/app_core_live_v4.php','evenvessis/veegame_legacy_gate.php','evenvessis/api/webapi/GetGameUrl.php','evenvessis/api/webapi/_common.php','evenvessis/api/webapi/GameBetting.php','veegame-legacy-history.html']:
 dst=SITE/p;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/p,dst)
(SITE/'evenvessis/conn.php').write_text("<?php $conn=new mysqli('localhost','root','','veegame_fixture',0,'/home/user/.cache/veegame-db/mysql.sock');")
s=(ROOT/'evenvessis/functions2.php').read_text();s=re.sub(r"\$secret = '[^']+'", "$secret = 'LOCAL-FIXTURE-ONLY-NOT-A-PRODUCTION-KEY'",s);(SITE/'evenvessis/functions2.php').write_text(s)
for d in ['assets','images','apiimages','css','js','fonts']:
 if (ROOT/d).exists() and not (SITE/d).exists():(SITE/d).symlink_to(ROOT/d,target_is_directory=True)
(SITE/'index.html').write_text((ROOT/'index.html').read_text().replace('https://veergame.club9.eu.cc',''))
provider=SITE/'saas_lottery/provider.php';s=provider.read_text();a=s.index('function sl_fetch_json(');b=s.index('\nfunction ',a+10)
f='''function sl_fetch_json($url,$timeout=null) {
    $state=json_decode(file_get_contents(__DIR__.'/../fixture-state.json'),true);
    if (($state['mode']??'')==='outage') return null;
    preg_match('~WinGo/(WinGo_(?:30S|1M|3M|5M))~',$url,$m);$game=$m[1]??'WinGo_1M';
    $seconds=['WinGo_30S'=>30,'WinGo_1M'=>60,'WinGo_3M'=>180,'WinGo_5M'=>300][$game];
    $now=sl_now_ms();$start=(int)$state['start'];$issue=(string)$state['issue'];
    if (($state['mode']??'')==='closed') {$start-=$seconds*1000;$issue=(string)((int)$issue+1);}
    $round=function($i,$t) use($seconds){return ['issueNumber'=>(string)$i,'startTime'=>$t,'endTime'=>$t+$seconds*1000];};
    if (strpos($url,'GetHistoryIssuePage')!==false) {
        $list=[];for($i=1;$i<=10;$i++)$list[]=['issueNumber'=>(string)((int)$issue-$i),'number'=>(string)($state['number']??6),'premium'=>(string)($state['number']??6),'color'=>'red','sum'=>0];
        if (($state['mode']??'')==='published') array_unshift($list,['issueNumber'=>$issue,'number'=>'6','premium'=>'6']);
        return ['code'=>0,'data'=>['list'=>$list,'pageNo'=>1,'totalPage'=>1,'totalCount'=>count($list)]];
    }
    $data=['gameCode'=>$game,'intervalMinute'=>$seconds/60,'state'=>1,'previous'=>$round((int)$issue-1,$start-$seconds*1000),'current'=>$round($issue,$start),'next'=>$round((int)$issue+1,$start+$seconds*1000)];
    if (($state['mode']??'')==='stale') foreach(['previous','current','next'] as $k){$data[$k]['startTime']-=900000;$data[$k]['endTime']-=900000;}
    return $data;
}
'''
provider.write_text(s[:a]+f+s[b:])
(SITE/'router.php').write_text('''<?php
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/api/Lottery/([A-Za-z]+)$~',$p,$m)) {$_GET['action']=$m[1];require __DIR__.'/api-live-v4/Lottery/index.php';return true;}
if (preg_match('~^/WinGo/(WinGo_(?:30S|1M|3M|5M))(/GetHistoryIssuePage)?\\.json$~',$p,$m)) {$_GET['gameCode']=$m[1];$_GET['lottery']='WinGo';$_GET['history']=isset($m[2]);require __DIR__.'/draw-live-v4/index.php';return true;}
if (preg_match('~^/evenvessis/api/webapi/(GetGameUrl|GameBetting)(?:\\.php)?$~',$p,$m)) {chdir(__DIR__.'/evenvessis/api/webapi');require $m[1].'.php';return true;}
if ($p==='/' || is_file(__DIR__.$p))return false;
http_response_code(404);header('Content-Type: application/json');echo json_encode(['code'=>404,'data'=>null,'msg'=>'Isolated test fixture: route not provided']);return true;
''')
print('ISOLATED fixture prepared:',SITE)
