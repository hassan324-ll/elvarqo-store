const path=require('node:path');const fs=require('node:fs');const assert=require('node:assert/strict');
const projectRoot=path.resolve(__dirname,'../..');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(projectRoot,'.qa/browsers');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || path.join(projectRoot,'.qa/node_modules/playwright'));
const extension=path.join(projectRoot,'.qa/zoom-extension');fs.mkdirSync(extension,{recursive:true});
fs.writeFileSync(path.join(extension,'manifest.json'),JSON.stringify({manifest_version:3,name:'ELVARQO isolated zoom test',version:'1.0',permissions:['tabs'],background:{service_worker:'worker.js'}}));
fs.writeFileSync(path.join(extension,'worker.js'),'chrome.runtime.onInstalled.addListener(() => {});');
const output=path.join(projectRoot,'docs/verification/phase2');
(async()=>{
 const context=await chromium.launchPersistentContext(path.join(projectRoot,'.qa/zoom-test-profile'),{channel:'chromium',headless:true,viewport:{width:1440,height:1000},args:[`--disable-extensions-except=${extension}`,`--load-extension=${extension}`]});
 let worker=context.serviceWorkers()[0];if(!worker)worker=await context.waitForEvent('serviceworker');
 const results=[];
 for(const [name,url] of [['storefront','http://localhost:3000'],['admin','http://localhost:5173']]){
  const page=await context.newPage();await page.goto(url,{waitUntil:'networkidle'});
  const setZoom=async factor=>worker.evaluate(async({url,factor})=>{const [tab]=await chrome.tabs.query({url:url+'/*'});await chrome.tabs.setZoom(tab.id,factor);return chrome.tabs.getZoom(tab.id);},{url,factor});
  await setZoom(1);const before=await page.evaluate(()=>({width:innerWidth,deviceScale:devicePixelRatio}));
  assert.equal(await setZoom(2),2);await page.waitForFunction(()=>devicePixelRatio>=2);
  const after=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth,deviceScale:devicePixelRatio}));
  assert(after.width<=before.width/1.9,JSON.stringify({before,after}));assert(after.scrollWidth<=after.width+1,`${name} overflows at native 200% zoom`);
  await page.screenshot({path:path.join(output,`${name}-native-200-percent.png`),fullPage:true});
  results.push({app:name,status:'passed',nativeBrowserZoom:2,before,after});await setZoom(1);await page.close();
 }
 fs.writeFileSync(path.join(output,'native-zoom-results.json'),JSON.stringify({status:'passed',results},null,2));console.log(JSON.stringify(results,null,2));await context.close();
})().catch(error=>{console.error(error);process.exit(1)});
