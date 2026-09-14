const {chromium}=require('/home/user/inspection/node_modules/playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});const results=[];
 for(const width of [360,390,430,1440]){
  const context=await browser.newContext({viewport:{width,height:width>500?1000:844}});const page=await context.newPage();const errors=[];const external=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)errors.push(r.status()+' '+r.url());});
  page.on('request',r=>{if(!r.url().startsWith('http://127.0.0.1:8786/'))external.push(r.url());});
  await page.goto('http://127.0.0.1:8786/digitaladmin/login.php');
  if(width===390)await page.screenshot({path:'/home/user/inspection/vee-admin/login-390.png',fullPage:true});
  await page.locator('[name=username]').fill('owner');await page.locator('[name=password]').fill('Fixture-Only-Passphrase-782!new');
  await Promise.all([page.waitForURL('**/index.php'),page.getByRole('button',{name:'Sign in securely'}).click()]);
  for(const name of ['dashboard','users','wallets','deposits','withdrawals','bets','ledger','checks','security']){
   await page.goto('http://127.0.0.1:8786/digitaladmin/index.php?page='+name);await page.evaluate(()=>document.fonts.ready);
   const bodyWidth=await page.evaluate(()=>document.documentElement.scrollWidth);
   if(bodyWidth>width+1)throw new Error(`${width} ${name} body overflow ${bodyWidth}`);
   if(width===390&&['dashboard','users','checks'].includes(name))await page.screenshot({path:`/home/user/inspection/vee-admin/${name}-390.png`,fullPage:true});
   if(width===1440&&name==='dashboard')await page.screenshot({path:'/home/user/inspection/vee-admin/dashboard-1440.png',fullPage:true});
   results.push({width,page:name,bodyWidth});
  }
  if(width===390){await page.locator('.va-menu-button').click();if(await page.locator('.va-menu-button').getAttribute('aria-expanded')!=='true')throw new Error('Menu not open');await page.keyboard.press('Escape');if(await page.locator('.va-menu-button').getAttribute('aria-expanded')!=='false')throw new Error('Menu not closed');}
  if(errors.length||external.length)throw new Error(JSON.stringify({width,errors,external}));await context.close();
 }
 await browser.close();fs.writeFileSync('/home/user/inspection/vee-admin/browser-results.json',JSON.stringify({passed:results.length,pages:results,consoleErrors:0,externalRequests:0,fixtureOnly:true},null,2));console.log('PASS 36 responsive page checks; no external requests / JS errors; mobile menu works');
})().catch(e=>{console.error(e);process.exit(1)});
