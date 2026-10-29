import sys, re, html
from html.parser import HTMLParser
class T(HTMLParser):
    def __init__(s): super().__init__(); s.out=[]; s.skip=0; s.tag=None
    def handle_starttag(s,t,a):
        if t in('script','style','svg','noscript'): s.skip+=1
        if t in('h1','h2','h3','h4'): s.out.append('\n['+t.upper()+'] ')
        elif t in('p','li','button','a','label','div','section','br'): s.out.append('\n')
    def handle_endtag(s,t):
        if t in('script','style','svg','noscript') and s.skip: s.skip-=1
    def handle_data(s,d):
        if not s.skip: s.out.append(d)
p=T(); p.feed(open(sys.argv[1],encoding='utf-8',errors='replace').read())
txt=re.sub(r'[ \t]+',' ',''.join(p.out)); txt=re.sub(r'\n\s*\n+','\n',txt)
print(txt.strip()[:int(sys.argv[2]) if len(sys.argv)>2 else 6000])
