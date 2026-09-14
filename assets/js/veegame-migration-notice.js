// Small migration disclosure; no replacement of the existing SaaS screen.
let notice, sequence=0;
function token() { try { const v=JSON.parse(localStorage.getItem('ar_token')||'null'); return typeof v==='string'?v:v?.value||''; } catch { return ''; } }
async function update(attempt=0) {
  const id=++sequence;
  notice?.remove(); notice=null;
  if (!location.hash.startsWith('#/saasLottery/WinGo')) return;
  const auth=token();
  if (!auth) { if (attempt<10) setTimeout(()=>update(attempt+1),300); return; }
  try {
    const r=await fetch('/api/Lottery/GetMigrationStatus',{headers:{Authorization:`Bearer ${auth}`},cache:'no-store'});
    const body=await r.json();
    if (id!==sequence || !r.ok || body.code!==0) return;
    notice=document.createElement('div'); notice.setAttribute('role','status');
    notice.style.cssText='position:fixed;top:48px;left:50%;transform:translateX(-50%);z-index:1001;max-width:92vw;box-sizing:border-box;background:#28251e;color:#ffe4a8;border:1px solid #82704b;border-radius:6px;padding:6px 10px;font:12px/1.4 sans-serif;white-space:nowrap';
    if (body.data.state!=='active') notice.append(document.createTextNode('Migration '+body.data.state+' · Betting paused · '));
    const a=document.createElement('a');a.href='/veegame-legacy-history.html';a.textContent='Legacy history';a.style.color='inherit';notice.append(a);document.body.append(notice);
  } catch { /* Main API presents its own error; never claim migration succeeded. */ }
}
window.addEventListener('hashchange',()=>update());setTimeout(()=>update(),0);
