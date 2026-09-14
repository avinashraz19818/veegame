// Execute the actual shared wallet methods with synthetic API responses.
// No browser account, real API, database or wallet mutation is used.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const assert=require('node:assert/strict'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const patched=fs.readFileSync(path.join(root,'assets/js/index-CBcbycSk.js'),'utf8');
const guard='!["/","/home","/home/","/login"].includes(Ye.currentRoute.value.path)&&';
assert.equal(patched.split(guard).length-1,3);
const original=patched.split(guard).join('');
assert.equal(crypto.createHash('sha256').update(original).digest('hex'),'88cb0a8ed86e3dee2d9f855b62a3c4154574171d41ccfe61f69b68f4c27e08f9','Only the three notification guards may differ from the verified live baseline');
const signatures={
 GetARGameAndPlatWallets:['async GetARGameAndPlatWallets(e){',',async getAllwalletsBalance('],
 getAllwalletsBalance:['async getAllwalletsBalance(e,t=!1){',',async resetData('],
 resetData:['async resetData(e,t){',',async getPayTypeName(']
};
function method(src,name){const [open,close]=signatures[name],a=src.indexOf(open),b=src.indexOf(close,a);assert(a>=0&&b>a);return src.slice(a,b);}
const resultData={amount:71.25,thidGameBalanceList:[{vendorCode:'Lottery',balance:50},{vendorCode:'JILI',balance:21.25}]};
async function run(src,name,route,mode,args){
 const notices=[],calls=[],waits=[];let networkError=null;
 const clock=1700000000000;
 class FixedDate extends Date { constructor(){super(clock);} static now(){return clock;} }
 const context=vm.createContext({
  Date:FixedDate,Zg:mode==='busy',Ye:{currentRoute:{value:{path:route}}},
  St:()=>({getIsNotify:true}),pz:async ms=>waits.push(ms),
  tde:()=>({endpoint:'all-wallets'}),ede:v=>({endpoint:'vendor-wallets',argument:v}),
  Zue:()=>({endpoint:'alternate-wallet'}),Que:()=>({endpoint:'normal-wallet'}),
  Qg:key=>key,kr:message=>notices.push(message),
  Pe:async req=>{calls.push(req);if(mode==='error')throw new Error('fixture API failure');return mode==='empty'?null:{data:resultData};}
 });
 const wallet=vm.runInContext('({'+method(src,name)+'})',context);
 Object.assign(wallet,{amount:9,allwallets:null,timestamp:mode==='cooldown'?clock/1000:0,timestampLast:mode==='cooldown'?clock/1000:0});
 try{await wallet[name](...args);}catch(e){networkError=e.message;}
 return JSON.parse(JSON.stringify({notices,calls,waits,networkError,busy:context.Zg,amount:wallet.amount,allwallets:wallet.allwallets,timestamp:wallet.timestamp,timestampLast:wallet.timestampLast}));
}
(async()=>{
 let cases=0;
 for(const route of ['/','/home','/home/','/login','/main','/wallet','/saasLottery/WinGo','/home/AllLotteryGames/WinGo']){
  for(const name of Object.keys(signatures)){
   for(const mode of ['success','empty','error','cooldown','busy']){
    for(const show of [true,false]){
     const args=name==='resetData'?[!show,false]:[show,false];
     const before=await run(original,name,route,mode,args),after=await run(patched,name,route,mode,args);
     const beforeNotices=before.notices,afterNotices=after.notices;
     delete before.notices;delete after.notices;
     assert.deepEqual(after,before,`${name}/${route}/${mode}: requests, balances, limits, busy flags and error behavior must be identical`);
     assert.deepEqual(afterNotices,['/','/home','/home/','/login'].includes(route)?[]:beforeNotices);
     cases++;
    }
   }
  }
 }
 // Repeated home entry after each cooldown: every refresh still updates the balance, none toasts.
 for(let i=0;i<5;i++)for(const name of Object.keys(signatures)){
  const r=await run(patched,name,'/','success',name==='resetData'?[false,false]:[true,false]);
  assert.equal(r.calls.length,1);assert.equal(r.amount,71.25);assert.deepEqual(r.notices,[]);cases++;
 }
 console.log(`PASS: ${cases} shared-wallet regression cases. Home/login success toasts suppressed; API requests, balances, throttling, busy flags, errors and non-home manual toasts preserved.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
