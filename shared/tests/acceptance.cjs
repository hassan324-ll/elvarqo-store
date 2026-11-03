const path = require('node:path');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const projectRoot = path.resolve(__dirname, '../..');
const playwrightPath = process.env.PLAYWRIGHT_MODULE || path.join(projectRoot, '.qa/node_modules/playwright');
const {chromium} = require(playwrightPath);
const output=process.env.QA_OUTPUT || path.join(projectRoot, 'docs/verification/phase2');
fs.mkdirSync(output,{recursive:true});
const tests=[]; const errors=[];
const sites=[['storefront','http://localhost:3000'],['admin','http://localhost:5173']];
async function fit(page,label){const dimensions=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth}));assert(dimensions.scroll<=dimensions.width+1,`${label} overflows: ${JSON.stringify(dimensions)}`);}
async function trap(page,dialog){for(let i=0;i<18;i++){await page.keyboard.press('Tab');assert(await dialog.evaluate(d=>d.contains(document.activeElement)),`Focus escaped dialog on Tab ${i}`);}await page.keyboard.press('Shift+Tab');assert(await dialog.evaluate(d=>d.contains(document.activeElement)),'Focus escaped on Shift+Tab');}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.platform==='win32'?{channel:'msedge'}:{})});
 for(const [name,url] of sites){
   for(const width of [390,768,1440]){
     const page=await browser.newPage({viewport:{width,height:1000}});page.on('pageerror',e=>errors.push(`${name}: ${e.message}`));
     await page.goto(url,{waitUntil:'networkidle'});await fit(page,`${name} ${width}`);
     if(name==='storefront'){await page.getByRole('heading',{name:/Less effort/}).waitFor();await page.locator('img').evaluateAll(imgs=>imgs.forEach(i=>i.loading='eager'));await page.waitForFunction(()=>Array.from(document.images).every(i=>i.complete&&i.naturalWidth>0));const broken=await page.locator('img').evaluateAll(imgs=>imgs.filter(i=>!i.complete||i.naturalWidth===0).map(i=>i.src));assert.equal(broken.length,0,'Broken concept images');}
     else await page.getByRole('heading',{name:'Store overview',exact:true}).waitFor();
     await page.screenshot({path:path.join(output,`${name}-${width}.png`),fullPage:true});
     tests.push(`${name}: ${width}px layout and no horizontal overflow`);
     const trigger=name==='storefront'?(width>900?page.getByRole('button',{name:'Shop',exact:true}):page.getByRole('button',{name:'Open shop navigation'})):(width<=900?page.getByRole('button',{name:'Open admin navigation'}):page.getByRole('button',{name:'Notifications',exact:true}));
     await trigger.focus();await page.keyboard.press('Enter');const dialog=page.locator('dialog[open]');await dialog.waitFor();if(width===1440||width===390)await page.screenshot({path:path.join(output,`${name}-${width}-overlay.png`),fullPage:true});await trap(page,dialog);await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});assert(await trigger.evaluate(el=>el===document.activeElement),'Focus did not return to trigger');
     tests.push(`${name}: ${width}px keyboard open / focus containment / Escape / restore`);
     if(name==='storefront'&&width===1440){await page.getByRole('button',{name:'Search',exact:true}).click();await page.getByLabel('Search concepts').fill('knit');await page.keyboard.press('Escape');await page.getByRole('button',{name:'Search',exact:true}).click();assert.equal(await page.getByLabel('Search concepts').inputValue(),'knit');await page.keyboard.press('Escape');tests.push('Storefront search draft preserved');}
     await page.close();
   }
   const page=await browser.newPage({viewport:{width:720,height:500},deviceScaleFactor:2});
   await page.goto(url,{waitUntil:'networkidle'});await fit(page,`${name} 200% equivalent`);await page.screenshot({path:path.join(output,`${name}-200-percent-reflow.png`),fullPage:true});tests.push(`${name}: 200% equivalent reflow (1440 physical / 720 CSS px at device scale 2; not OS browser zoom)`);await page.close();
 }
 for(const [name,url] of sites){
   const page=await browser.newPage({viewport:{width:390,height:1000},reducedMotion:'reduce'});page.on('pageerror',e=>errors.push(`${name} gallery: ${e.message}`));
   await page.goto(name==='storefront'?`${url}/design-system`:`${url}/?view=design-system`,{waitUntil:'networkidle'});
   await page.getByRole('button',{name:'Validate example'}).click();await page.getByRole('alert').getByText('Enter a valid email address.').waitFor();
   await page.getByLabel('Email address',{exact:true}).fill('preview@example.test');await page.getByRole('button',{name:'Validate example'}).click();await page.getByText('Example validated. Nothing was submitted.').waitFor();assert.equal(await page.getByLabel('Email address',{exact:true}).inputValue(),'preview@example.test');
   const trigger=page.getByRole('button',{name:'Open example dialog'});await trigger.click();const dialog=page.getByRole('dialog',{name:'Example dialog'});await trap(page,dialog);
   assert.equal(await dialog.evaluate(d=>getComputedStyle(d).animationName),'none');await page.keyboard.press('Escape');assert(await trigger.evaluate(el=>el===document.activeElement));
   await trigger.click();await page.getByRole('button',{name:'Close Example dialog',exact:true}).click();await dialog.waitFor({state:'hidden'});await trigger.click();await page.mouse.click(2,2);await dialog.waitFor({state:'hidden'});assert(await trigger.evaluate(el=>el===document.activeElement));await page.getByRole('button',{name:'Next',exact:true}).click();await page.getByText('Page 2 of 3',{exact:true}).waitFor();await fit(page,`${name} component gallery`);await page.screenshot({path:path.join(output,`${name}-controls-mobile.png`),fullPage:true});tests.push(`${name}: inline errors, preserved form values, local validation, pagination, dialog and reduced motion`);
   await page.goto(name==='storefront'?`${url}/development`:`${url}/?view=development`,{waitUntil:'networkidle'});await page.getByText('Connected to Laravel. Database is ready.',{exact:true}).waitFor();tests.push(`${name}: retained Phase 1 health connection`);await page.close();
 }
 const store=await browser.newPage({viewport:{width:390,height:1000}});await store.goto('http://localhost:3000');await store.getByRole('button',{name:'Preview The Knit Polo',exact:true}).click();await store.getByRole('dialog',{name:'Product preview'}).waitFor();assert(await store.getByRole('button',{name:'Add to bag unavailable'}).isDisabled());await store.keyboard.press('Escape');await store.getByRole('button',{name:'Bag 0',exact:true}).click();await store.getByText('Your bag is waiting.').waitFor();await store.keyboard.press('Escape');tests.push('Concept product preview and honest empty bag');await store.close();
 const admin=await browser.newPage();await admin.goto('http://localhost:5173');await admin.getByLabel('Filter demonstration orders').fill('no-such-order');await admin.getByText('No matching concepts').waitFor();await admin.getByLabel('Filter demonstration orders').fill('EV-10284');assert.equal(await admin.locator('.data-table tbody tr').count(),1);await admin.getByLabel('Preview period').selectOption('30');await admin.getByText('No 30-day fixture').waitFor();tests.push('Admin fixture filtering and empty period state');await admin.close();
 const nojs=await browser.newContext({javaScriptEnabled:false});const p=await nojs.newPage();await p.goto('http://localhost:3000');await p.getByRole('heading',{name:/Less effort/}).waitFor();assert(await p.getByRole('link',{name:'Collections',exact:true}).count()>0);await p.getByRole('link',{name:'Collections',exact:true}).click();await p.getByRole('heading',{name:'Everyday essentials.',exact:true}).waitFor();tests.push('Public meaningful HTML and navigation usable with JavaScript disabled');await nojs.close();
 assert.deepEqual(errors,[],'Browser page errors');
 fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({status:'passed',tests,errors,zoomLimitation:'200% represented by equivalent CSS viewport and device scale; native browser zoom not exercised'},null,2));console.log(JSON.stringify({status:'passed',count:tests.length,tests,errors},null,2));await browser.close();
})().catch(error=>{console.error(error);fs.writeFileSync(path.join(output,'failure.txt'),error.stack);process.exit(1);});
