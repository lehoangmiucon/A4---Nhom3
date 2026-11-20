import {readFileSync, writeFileSync, mkdirSync, mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';

// Real DOM/timers with local assets and fake API only; no live application data.
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const temporary = mkdtempSync(join(tmpdir(), 'helpdesk-cr11-'));
const output = resolve('evidence/cr11-smoke');
mkdirSync(output, {recursive: true});
let markup = readFileSync('resources/views/customer/partials/support-conversation.blade.php', 'utf8');
markup = markup.replace(/<select id="conversation-room">[\s\S]*?<\/select>/, '<select id="conversation-room"><option value="">Chưa chọn phòng</option><option value="2">A203</option></select>')
    .replace(/<select id="conversation-category">[\s\S]*?<\/select>/, '<select id="conversation-category"><option value="">Chưa chọn danh mục</option><option value="1">Máy chiếu</option></select>');
const asset = (path) => pathToFileURL(resolve(path)).href;
const fixture = `<!doctype html><html lang="vi"><meta charset="utf-8">
<link rel="stylesheet" href="${asset('public/customer/assets/css/support-conversation.css')}">
<style>body{font:15px Arial;background:#eff6fc;color:#17324d;margin:32px}main{max-width:1120px;margin:auto}.report-card{background:white;border:1px solid #dbe8f4;border-radius:16px}.report-card-heading{padding:24px;display:flex;justify-content:space-between;gap:20px}h2{font-size:24px}#smoke-result{padding:12px;background:#dcfce7;white-space:pre-wrap}</style>
<main class="report-portal"><button id="report-mode-ai">Nhờ AI hỗ trợ</button><button id="report-mode-manual">Báo cáo thủ công</button><section id="report-manual-view"></section>${markup}</main>
<pre id="smoke-result">RUNNING</pre>
<script>
window.HELPDESK_CONFIG={currentUserId:1};
sessionStorage.setItem('helpdesk_active_support_session',JSON.stringify({userId:1,sessionId:5,mode:'ai'}));
let confirmDelete=false, deletes=0;
window.confirm=()=>confirmDelete;
const completed={id:5,room_code:'A203',status:'handed_off',initial_description:'Máy chiếu không có hình',is_completed:true,can_delete:true,ticket_status:'closed'};
let items=[completed,{...completed,id:6,room_code:'A204',is_completed:false,can_delete:false,ticket_status:'resolved'}];
window.fetch=async(url,options={})=>{
  if(options.method==='DELETE'){deletes++;items=items.filter(item=>item.id!==5);return {ok:true,status:200,json:async()=>({data:{id:5,deleted:true}})};}
  if(url.includes('?page='))return {ok:true,status:200,json:async()=>({data:items,meta:{pagination:{current_page:1,last_page:1,total:items.length}}})};
  return {ok:true,status:200,json:async()=>({data:{session:{...completed,version:2,room_id:2,category_id:1},messages:[{role:'user',content_text:'Máy chiếu không có hình'},{role:'assistant',content_text:'Báo cáo đã được gửi.'}],category_label:'Máy chiếu',report:{ticket_id:9,ticket_number:'HD-TEST',status:'closed'}}})};
};
const errors=[];window.addEventListener('error',e=>errors.push(e.message));window.addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
</script>
<script src="${asset('public/shared/js/page-feedback.js')}"></script>
<script src="${asset('public/customer/assets/js/support-conversation.js')}"></script>
<script>
const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const check=(condition,message)=>{if(!condition)throw new Error(message);};
(async()=>{
 try{
  await wait(6000);
  check(document.getElementById('conversation-history-status'),'history status removed after five seconds');
  check(document.getElementById('conversation-status'),'chat status removed after five seconds');
  check(document.querySelectorAll('.conversation-delete').length===1,'delete eligibility');
  check(document.getElementById('conversation-history-list').textContent.includes('Chờ xác nhận hoàn thành'),'pending badge');
  const button=document.querySelector('.conversation-delete');button.click();check(deletes===0,'cancel sent delete');
  confirmDelete=true;button.click();button.click();await wait(100);
  check(deletes===1,'duplicate delete');
  check(document.querySelectorAll('.conversation-delete').length===0,'deleted item remains');
  check(document.querySelectorAll('#conversation-history-list li').length===1,'pending item lost');
  check(JSON.parse(sessionStorage.getItem('helpdesk_active_support_session')).sessionId===null,'active session not cleared');
  check(document.getElementById('conversation-report').hidden,'receipt not cleared');
  check(document.getElementById('conversation-status').textContent.includes('Đã xóa phiên'),'missing success status');
  check(errors.length===0,errors.join('; '));
  document.getElementById('smoke-result').textContent='PASS: persistent statuses; eligible action; pending badge; cancel; one DELETE; preserved pending session; cleared active session; success status; no runtime errors';
 }catch(error){document.getElementById('smoke-result').textContent='FAIL: '+error.message;}
})();
</script></html>`;
const file = join(temporary, 'fixture.html');
writeFileSync(file, fixture);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--allow-file-access-from-files', `--user-data-dir=${join(temporary, 'profile')}`, '--window-size=1440,1100',
    '--virtual-time-budget=9000', '--dump-dom', `--screenshot=${join(output, 'completed-session-deleted.png')}`, pathToFileURL(file).href],
    {encoding:'utf8', timeout:30000, maxBuffer:2_000_000, windowsHide:true, stdio:['ignore','pipe','pipe']});
const result = dom.match(/<pre id="smoke-result">([^<]*)<\/pre>/)?.[1];
writeFileSync(join(output, 'result.json'), JSON.stringify({result, viewport:'1440x1100', api:'fake', browser:'Chrome headless', localDataChanged:false}, null, 2));
assert.match(result || '', /^PASS:/);
console.log(result);
