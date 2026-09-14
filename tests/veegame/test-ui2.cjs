// Narrow UI regression tests. No database, credentials or network calls.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
(async()=>{
  const home=fs.readFileSync(path.join(root,'assets/js/publicCHome-BXq3dfgY.js'),'utf8');
  assert(home.includes('C.resetData(!0,!1))});const O='));
  const main=fs.readFileSync(path.join(root,'assets/js/index-CBcbycSk.js'),'utf8');
  const start=main.indexOf('async resetData(e,t){');
  const end=main.indexOf(',async getPayTypeName()',start);
  assert(start>=0 && end>start);
  const toasts=[],requests=[];
  const wallet=vm.runInNewContext('({'+main.slice(start,end)+'})',{
    Pe:async req=>{requests.push(req);return {data:{amount:731.25}};},
    Que:()=> 'normal-wallet-refresh',Zue:()=> 'alternate-wallet-refresh',
    kr:text=>toasts.push(text),Qg:key=>key,Ye:{currentRoute:{value:{path:'/wallet'}}}
  });
  // Actual homepage startup arguments: silent=true, alternateWallet=false.
  await wallet.resetData(true,false);
  assert.equal(wallet.amount,731.25);assert.deepEqual(toasts,[]);
  assert.deepEqual(requests,['normal-wallet-refresh']);
  // Manual refresh behavior remains intact; only unsolicited login toast is removed.
  await wallet.resetData(false,false);
  assert.deepEqual(toasts,['refreshSuccess']);
  const src=fs.readFileSync(path.join(root,'saas_lottery/bootstrap_live_v4.php'),'utf8');
  function extract(name){const a=src.indexOf('function '+name+'(');assert(a>=0);let b=src.indexOf('\nfunction ',a+10);return src.slice(a,b<0?src.length:b);}
  const names=['sl_game_config','sl_game_family','sl_game_info','sl_game_list','sl_game_rates','sl_bet_limits'];
  const php='<?php $SL_CONFIG=require '+JSON.stringify(path.join(root,'saas_lottery/config_live_v4.php'))+'; function sl_game_enabled($g){return true;}\n'+names.map(extract).join('\n')+'\n$out=[];foreach(array_keys($SL_CONFIG["games"]) as $g)$out[$g]=sl_game_info($g);echo json_encode(["info"=>$out,"list"=>sl_game_list()]);';
  const result=spawnSync('php',[],{input:php,encoding:'utf8'});assert.equal(result.status,0,result.stderr);
  const payload=JSON.parse(result.stdout);
  for(const [game,info] of Object.entries(payload.info)){
    assert.deepEqual(info.betMultiples,[1,5,10,20,100],game);
    assert.deepEqual(info.betScopes,[1,10,100,1000]);
  }
  const rule=fs.readFileSync(path.join(root,'assets/js/BetRule-BmuRxM2O.js'),'utf8');
  assert(rule.includes('Y=n(()=>i.value?.betMultiples||[])'));
  const view=fs.readFileSync(path.join(root,'assets/js/index-BSvowaeJ.js'),'utf8');
  assert.equal((view.match(/x\(e\.betMultiples/g)||[]).length,2,'main row and popup both use API presets');
  const notice=fs.readFileSync(path.join(root,'assets/js/veegame-migration-notice.js'),'utf8');
  assert(!notice.includes('position:fixed'));assert(!notice.includes('GetMigrationStatus'));
  assert(notice.includes("document.querySelector('.winGo3 .history')"));
  assert(!notice.includes('WinGoBet'));assert(!notice.includes('--activate'));
  const output=process.env.VEE_UI_FIXTURE_JSON;
  if(output)fs.writeFileSync(output,JSON.stringify(payload));
  console.log('PASS: silent login refresh updates balance; manual refresh unchanged; five presets on all four games; both rows use those presets; no floating banner or activation change.');
})().catch(e=>{console.error(e);process.exitCode=1;});
