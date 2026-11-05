from pathlib import Path
import subprocess,tempfile,email.parser,xml.etree.ElementTree as ET,json,time
from html.parser import HTMLParser
from concurrent.futures import ThreadPoolExecutor
from urllib.parse import urlparse
from datetime import datetime
root=Path(__file__).parent
def get(url,head=False):
 with tempfile.TemporaryDirectory() as tmp:
  h=Path(tmp)/'headers';b=Path(tmp)/'body'
  cmd=['curl.exe','-sS','--max-time','60','-D',str(h),'-o',str(b)]
  if head:cmd+=['-I']
  subprocess.run(cmd+[url],capture_output=True,check=True)
  raw=h.read_text().strip().split('\n\n')[-1]
  status=int(raw.splitlines()[0].split()[1]);headers=email.parser.Parser().parsestr('\n'.join(raw.splitlines()[1:]))
  return status,headers,b.read_bytes()
class Meta(HTMLParser):
 def __init__(self):super().__init__();self.robots=[];self.canonical=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='meta' and a.get('name','').lower() in ['robots','googlebot']:self.robots.append(a.get('content',''))
  if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href',''))
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
responses=[];expected=None;dates={}
for url in ['https://elvarqo.uk/sitemap.xml','https://www.elvarqo.uk/sitemap.xml','https://elvarqo.uk/sitemap.xml?check='+str(int(time.time()))]:
 status,h,body=get(url);assert status==200,(url,status)
 assert h.get_content_type()=='application/xml',(url,dict(h))
 assert 'noindex' not in h.get('X-Robots-Tag','').lower()
 assert b'<html' not in body.lower() and b"That page isn" not in body
 assert b'<!DOCTYPE' not in body
 tree=ET.fromstring(body);assert tree.tag=='{'+ns['s']+'}urlset'
 locs=[node.find('s:loc',ns).text for node in tree]
 assert len(locs)==len(set(locs)) and 0<len(locs)<=50000
 for loc in locs:
  u=urlparse(loc);assert u.scheme=='https' and u.netloc=='elvarqo.uk' and not u.query and not u.fragment
  assert u.path in ['/','/shop','/collections'] or u.path.startswith(('/products/','/collections/'))
 for node in tree:
  last=node.find('s:lastmod',ns)
  if last is not None:dates[node.find('s:loc',ns).text]=datetime.fromisoformat(last.text.replace('Z','+00:00'))
 if expected is None:expected=locs
 else:assert locs==expected
 responses.append({'url':url,'status':status,'content_type':h.get('Content-Type'),'cache_control':h.get('Cache-Control'),'cdn_cache':h.get('X-Hcdn-Cache-Status'),'age':h.get('Age'),'count':len(locs)})
 print('PASS XML',status,url,len(locs),'URLs',h.get('Content-Type'),h.get('X-Hcdn-Cache-Status'))
status,h,_=get('https://elvarqo.uk/sitemap.xml',True);assert status==200 and h.get_content_type()=='application/xml'
print('PASS HEAD sitemap.xml: HTTP 200, application/xml')
status,_,body=get('https://elvarqo.uk/robots.txt');assert status==200 and b'Sitemap: https://elvarqo.uk/sitemap.xml' in body
print('PASS robots.txt sitemap reference')
def page(loc):
 status,h,body=get(loc);assert status==200,(loc,status)
 m=Meta();m.feed(body.decode('utf-8'))
 assert not any('noindex' in v.lower() for v in m.robots+[h.get('X-Robots-Tag','')]),(loc,m.robots)
 assert any(v.rstrip('/')==loc.rstrip('/') for v in m.canonical),(loc,m.canonical)
 return {'url':loc,'status':status,'robots':m.robots,'canonical':m.canonical}
with ThreadPoolExecutor(max_workers=3) as pool:pages=list(pool.map(page,expected))
print('PASS every included URL returns 200, indexable metadata and its matching canonical:',len(pages))
_,_,body=get('https://api.elvarqo.uk/api/v1/catalogue/sitemap');feed=json.loads(body)['data']
assert feed['last_page']==1
for product in feed['products']:
 url='https://elvarqo.uk/products/'+product['slug'];assert url in expected
 if product['lastmod']:
  actual=datetime.fromisoformat(product['lastmod'].replace('Z','+00:00'))
  assert abs((actual-dates[url]).total_seconds())<0.001
for group in feed['groups']:assert 'https://elvarqo.uk/collections/'+group['slug'] in expected
assert len(expected)==3+len(feed['groups'])+len(feed['products'])
print('PASS live sitemap entries and lastmod match the current read-only API feed')
(root/'sitemap-live-results.json').write_text(json.dumps({'checked_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'responses':responses,'pages':pages,'head_pass':True,'robots_pass':True,'feed_lastmod_match':True},indent=2))
