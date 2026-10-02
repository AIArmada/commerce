import sys, csv
sys.path.insert(0, '/tmp/my-audit')
from differ import parse, norm
state, path = sys.argv[1], sys.argv[2]
upi = parse(path)
rows = list(csv.DictReader(open('./packages/addressing/resources/geography/malaysia-address-areas.csv')))
csvdist = {}
for r in rows:
    if r['source_id'].startswith(f'my:district:{state}:') and r['parent_source_id'] == f'my:state:{state}':
        csvdist[r['name']] = r['source_id'].split(':')[-1]
for code in sorted(upi):
    dname = upi[code]['name']
    slug = None
    for cname, s in csvdist.items():
        if norm(dname.replace('Daerah ','').replace('Jajahan ','').replace('Kecil ','').strip()) == norm(cname) or norm(cname) in norm(dname):
            slug = s; break
    print(f'=== UPI {code} {dname} -> {slug} ===')
    print('  UPI entities:')
    for e in upi[code]['entities']:
        print(f'    {e[0]} {e[1]:6} {e[2]}')
    if slug:
        print('  CSV (mukim/bandar/pekan/locality):')
        for r in rows:
            if r['parent_source_id'] == f'my:district:{state}:{slug}' and r['type'] in ('mukim','bandar','pekan','locality'):
                print(f"    {r['type']:8} {r['name']} [{r['source_id'].split(':')[-1]}]")
    print()
