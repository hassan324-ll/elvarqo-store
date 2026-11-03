// Paths are provided only by the trusted PHP worker; no shell interpolation.
let sharp;try{sharp=require('sharp');}catch{process.stderr.write('WORKER_UNAVAILABLE');process.exit(2);}const fs=require('node:fs');const path=require('node:path');
(async()=>{const [input,output]=process.argv.slice(2);if(!input||!output)throw Error('Missing paths');
 const minimum=process.argv[4]==='category'?64:200;
 const image=sharp(input,{limitInputPixels:24000000,failOn:'warning'});const meta=await image.metadata();
 if(!['jpeg','png','webp'].includes(meta.format))throw Error('FORMAT');
 if((meta.pages||1)!==1)throw Error('ANIMATED');
 if(!meta.width||!meta.height||meta.width<minimum||meta.height<minimum)throw Error('DIMENSIONS:'+meta.width+'x'+meta.height);
 if(meta.width*meta.height>24000000)throw Error('PIXELS');
 fs.mkdirSync(output,{recursive:true});const sizes=[];
 for(const width of [640,1280,1920]){const info=await sharp(input,{limitInputPixels:24000000,failOn:'warning'}).rotate().resize({width,withoutEnlargement:true}).webp({quality:82}).toFile(path.join(output,width+'.webp'));sizes.push({size:width,width:info.width,height:info.height});}
 process.stdout.write(JSON.stringify({width:meta.autoOrient?.width||meta.width,height:meta.autoOrient?.height||meta.height,format:meta.format,sizes}));
})().catch(error=>{const message=error.message||'';process.stderr.write(/^(FORMAT|ANIMATED|PIXELS|DIMENSIONS:\d+x\d+)$/.test(message)?message:'DECODE_FAILED');process.exit(1);});
