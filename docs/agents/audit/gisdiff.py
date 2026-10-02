import json, csv, sys, re, unicodedata

def norm(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii','ignore').decode()
    return re.sub(r' +',' ', re.sub(r'[^A-Z ]','', s.upper())).strip()

# state -> (gis_code, district code->slug overrides)
STATE = sys.argv[1]
GISCODE = sys.argv[2]
rows = list(csv.DictReader(open('./packages/addressing/resources/geography/malaysia-address-areas.csv')))
csvdist = {}
for r in rows:
    if r['source_id'].startswith(f'my:district:{STATE}:') and r['parent_source_id']==f'my:state:{STATE}':
        csvdist[r['source_id'].split(':')[-1]] = r['name']
d = json.load(open(sys.argv[3]))
feats = d['features']
# group GIS by district code
gisd = {}
for f in feats:
    a = f['attributes']
    gisd.setdefault(a['KOD_DAERAH'], []).append((a['KOD_MUKIM'], (a.get('KATEGORI') or '').upper(), a['NAM']))
# map district codes via Admin2 names
a2 = json.load(open(sys.argv[4])) if len(sys.argv) > 4 else None
code2name = {}
if a2:
    for f in a2['features']:
        a = f['attributes']
        code2name[a['KOD_DAERAH']] = a['NAM']
print(f'### {STATE} GIS districts={len(gisd)} CSV districts={len(csvdist)}')
code2slug = {}
for code in sorted(gisd):
    gname = code2name.get(code, '')
    best = None
    for slug, cname in csvdist.items():
        if norm(gname).replace('DAERAH ','').replace('JAJAHAN ','') == norm(cname) or norm(cname) in norm(gname):
            best = slug; break
    code2slug[code] = best
    print(f'  GIS {code} {gname[:38]:38} -> {best} (n={len(gisd[code])})')
print()
TYP = {'MUKIM':'mukim','BANDAR':'bandar','PEKAN':'pekan'}
for code in sorted(gisd):
    slug = code2slug[code]
    if not slug:
        print(f'--- {code} UNMAPPED ({len(gisd[code])} entities)'); continue
    kids = [r for r in rows if r['parent_source_id']==f'my:district:{STATE}:{slug}' and r['type'] in ('mukim','bandar','pekan')]
    csvnames = {}
    for r in kids:
        csvnames.setdefault(norm(r['name']), []).append((r['name'], r['type']))
    print(f'--- {code} {slug}: GIS n={len(gisd[code])} CSV n={len(kids)}')
    for (mcode, kat, nam) in sorted(gisd[code]):
        t = TYP.get(kat, kat)
        # strip type word from NAM for matching
        bare = re.sub(r'^(MUKIM|BANDAR|PEKAN)\s+','', nam.upper()).strip()
        key = norm(bare)
        if key in csvnames:
            cts = set(x[1] for x in csvnames[key])
            if t not in cts:
                print(f'    TYPE? GIS {mcode} {kat} {bare} vs CSV {csvnames[key]}')
        else:
            # try with prefix (CSV keeps Bandar X / Pekan X)
            key2 = norm(nam)
            if key2 in csvnames:
                cts = set(x[1] for x in csvnames[key2])
                if t not in cts:
                    print(f'    TYPE? GIS {mcode} {kat} {nam} vs CSV {csvnames[key2]}')
            else:
                print(f'    MISSING GIS {mcode} {kat} {nam}')
    gkeys = set()
    for (mcode, kat, nam) in gisd[code]:
        gkeys.add(norm(re.sub(r'^(MUKIM|BANDAR|PEKAN)\s+','', nam.upper()).strip()))
        gkeys.add(norm(nam))
    for key, lst in sorted(csvnames.items()):
        if key not in gkeys:
            print(f'    EXTRA CSV {lst}')
