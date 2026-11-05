from pathlib import Path
import subprocess,tempfile,json,email.parser,time
from html.parser import HTMLParser
from concurrent.futures import ThreadPoolExecutor
from urllib.robotparser import RobotFileParser
root=Path(__file__).parent
def get(url,head=False):
 with tempfile.TemporaryDirectory() as tmp:
  hp=Path(tmp)/'headers';bp=Path(tmp)/'body'
  cmd=['curl.exe','-sS','--max-time','60','-D',str(hp),'-o',str(bp)]
  if head:cmd+=['-I']
  subprocess.run(cmd+[url],capture_output=True,check=True)
  raw=hp.read_text().strip().split('\n\n')[-1];h=email.parser.Parser().parsestr('\n'.join(raw.splitlines()[1:]));return int(raw.splitlines()[0].split()[1]),h,bp.read_bytes()
class Page(HTMLParser):
 def __init__(self):super().__init__();self.json=[];self.schema=False;self.raw='';self.canonical=[];self.checked=[];self.price=False;self.prices=[];self.incrumb=False;self.crumbs=[];self.robots=[]
 def handle_starttag(self,tag,attributes):
  a=dict(attributes)
  if tag=='script' and a.get('id')=='product-structured-data':assert a.get('type')=='application/ld+json';self.schema=True;self.raw=''
  if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
  if tag=='meta' and a.get('name')=='robots':self.robots.append(a.get('content',''))
  if tag=='input' and a.get('type')=='radio' and 'checked' in a:self.checked.append((a.get('name'),a.get('value')))
  if tag=='p' and 'product-price' in a.get('class','').split():self.price=True
  if tag=='nav' and 'product-breadcrumbs' in a.get('class','').split():self.incrumb=True
 def handle_data(self,data):
  if self.schema:self.raw+=data
  if self.price:self.prices.append(data)
  if self.incrumb and data.strip() and data.strip()!='/':self.crumbs.append(data.strip())
 def handle_endtag(self,tag):
  if tag=='script' and self.schema:self.json.append(json.loads(self.raw));self.schema=False
  if tag=='p':self.price=False
  if tag=='nav':self.incrumb=False
def page(url):
 status,h,body=get(url);assert status==200,(url,status)
 p=Page();p.feed(body.decode());assert len(p.json)==1,(url,len(p.json));assert 'noindex' not in ','.join(p.robots+[h.get('X-Robots-Tag','')]);return p,h
status,_,body=get('https://api.elvarqo.uk/api/v1/catalogue/sitemap');assert status==200
slugs=[p['slug'] for p in json.loads(body)['data']['products']];assert len(slugs)==6
status,_,body=get('https://api.elvarqo.uk/robots.txt');assert status==200
# Google uses the most specific matching rule; urllib.robotparser uses first-match.
# This production file contains one wildcard user-agent group and literal prefixes.
from urllib.parse import urlparse
rules=[(line.split(':',1)[1].strip(),line.lower().startswith('allow:')) for line in body.decode().splitlines() if line.lower().startswith(('allow:','disallow:'))]
def crawler_can_fetch(url):
 path=urlparse(url).path;matches=[(len(prefix),allow) for prefix,allow in rules if prefix and path.startswith(prefix)]
 return max(matches,default=(0,True))[1]
assert not crawler_can_fetch('https://api.elvarqo.uk/api/v1/staff/catalogue/products')
forbidden={'aggregateRating','review','brand','gtin','gtin8','gtin12','gtin13','gtin14','mpn','shippingDetails','hasMerchantReturnPolicy','itemCondition','priceValidUntil','inventoryLevel'}
def audit_keys(value):
 if isinstance(value,dict):
  assert not forbidden.intersection(value)
  for child in value.values():audit_keys(child)
 elif isinstance(value,list):
  for child in value:audit_keys(child)
def check(slug):
 status,_,body=get('https://api.elvarqo.uk/api/v1/catalogue/products/'+slug);assert status==200
 product=json.loads(body)['data']['product'];url='https://elvarqo.uk/products/'+slug
 p,h=page(url);schema=p.json[0];assert schema['@context']=='https://schema.org';audit_keys(schema)
 group,crumb=schema['@graph'];assert group['@type']=='ProductGroup';assert group['name']==product['title'];assert group['description']==product['description'];assert group['url']==url and p.canonical==[url]
 assert group['productGroupID']==str(product['id'])
 variants=product['variants'];assert len(group['hasVariant'])==len(variants)
 ids=set();offers={}
 for v,node in zip(variants,group['hasVariant']):
  assert node['@type']=='Product' and node['@id'] not in ids;ids.add(node['@id'])
  assert node['sku']==v['sku'] and node['color']==v['colour'] and node['size']==v['size']
  assert node['description']==product['description']
  offer=node['offers'];offers[v['id']]=offer
  assert offer['@type']=='Offer' and offer['priceCurrency']=='GBP'
  assert offer['price']==f"{v['price_minor']/100:.2f}"
  stock=v.get('is_in_stock',v['available']>0)
  assert offer['availability']=='https://schema.org/'+('InStock' if stock else 'OutOfStock')
  assert offer['url']==url+'?variant='+str(v['id']) and node['url']==offer['url']
  expected=[m['url'] for m in sorted([m for m in product['media'] if not m['variant_id'] or m['variant_id']==v['id']],key=lambda m: not m['variant_id'])]
  assert node['image']==list(dict.fromkeys(expected))
  assert all(image.startswith('https://') for image in node['image'])
 expected_crumbs=['Shop']+([product['collections'][0]['title']] if product['collections'] else [])+[product['title']]
 assert crumb['@type']=='BreadcrumbList' and [c['name'] for c in crumb['itemListElement']]==expected_crumbs==p.crumbs
 assert [c['position'] for c in crumb['itemListElement']]==list(range(1,len(expected_crumbs)+1))
 for v in [variants[0],variants[-1]]:
  selected,_=page(offers[v['id']]['url']);assert selected.canonical==[url]
  assert ('colour-'+str(product['id']),v['colour']) in selected.checked
  assert ('size-'+str(product['id']),v['size']) in selected.checked
  assert f"£{v['price_minor']/100:,.2f}" in ''.join(selected.prices)
 image=group['image'][0];assert crawler_can_fetch(image)
 status,ih,_=get(image,True);assert status==200 and ih.get_content_type()=='image/webp';assert 'noindex' not in ih.get('X-Robots-Tag','')
 print('PASS LIVE',slug,len(variants),'genuine offers, breadcrumbs, initial HTML, first/last preselection and image crawlability')
 return {'url':url,'title':product['title'],'variants':len(variants),'initial_html_json_ld':True,'prices_skus_availability_match_api':True,'visible_breadcrumb_match':True,'first_last_variant_urls_preselect':True,'public_image_indexable':True,'canonical':p.canonical,'cache_control':h.get('Cache-Control'),'cdn_cache':h.get('X-Hcdn-Cache-Status')}
with ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(check,slugs))
status,_,body=get('https://elvarqo.uk/products/not-a-public-product-schema-test');assert status==404 and b'id="product-structured-data"' not in body
status,h,_=get('https://api.elvarqo.uk/api/v1/health');assert status==200 and 'noindex' in h.get('X-Robots-Tag','')
(root/'product-schema-live-results.json').write_text(json.dumps({'checked_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'products':results,'missing_product_no_schema':True,'private_api_exclusion_preserved':True,'google_rich_results_test':'Separate browser validation; not inferred from this script'},indent=2))
print('PASS missing product has no JSON-LD; private API remains noindex. Total offers:',sum(r['variants'] for r in results))
