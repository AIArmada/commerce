import re, csv, sys, unicodedata

STOP = {'TIADA','KN.','KN','GN','W.K.','WARTA','KERAJAAN','PELAN','PW','PP','PG','PM','PHG','PHG.','FMS',
        'SEKSYEN','RAJAH','SILA','KOD','NAMA','BAGI','RUJUK','HANYA','ADA','DALAM','BERTARIKH',
        'BERKENAAN','GLOSARI','NEGERI','JAJAHAN','KECIL','DAERAH','NO','SUK'}

def is_gazette_token(w):
    # state-gazette reference tokens trailing entity names: WKJ/WKK/WKPP/WKTR/WK
    # (Warta Kerajaan {Negeri}; Perak uses bare WK), dotted refs like WKJ.33
    # already break on '.', plus Johor-only LN.73
    return w == 'LN' or re.fullmatch(r'WK[A-Z]{0,3}', w) is not None

def clean_name(words):
    # drop trailing single letters (Tiada/Warta/Kn artifacts) and gazette fragments; keep roman numerals
    while words and ((len(words[-1]) == 1 and words[-1] not in ('I', 'V', 'X')) or words[-1] in STOP or is_gazette_token(words[-1]) or re.match(r'^[0-9]', words[-1]) or '.' in words[-1] or '/' in words[-1] or '(' in words[-1]):
        words.pop()
    # halve exact-doubled names (page-header/footer echo artifacts), except
    # attested genuine doubles (Chin Chin, Jasin; Layang-Layang, Perak Tengah)
    if ' '.join(words) not in ('CHIN CHIN', 'LAYANG LAYANG') and len(words) % 2 == 0 and len(words) >= 2:
        h = len(words) // 2
        if words[:h] == words[h:]:
            words = words[:h]
    return ' '.join(words)

def parse(path):
    text = open(path).read().replace('’', "'").replace('‘', "'")
    parts = re.split(r'(\d\d)\s*[–—-]\s*((?i:Jajahan Kecil|Jajahan|Daerah Kecil|Daerah)(?: [A-Z][A-Za-z \-]*)?)', text)
    districts = {}
    for i in range(1, len(parts), 3):
        code, name, body = parts[i], parts[i+1].strip(), parts[i+2]
        districts.setdefault(code, {'name': name, 'body': ''})
        districts[code]['body'] += '\n' + body
    result = {}
    for code, d in districts.items():
        flat = re.sub(r'\s+', ' ', d['body'])
        ents = []
        for m in re.finditer(r'\b(\d{2,3})\s+\*?(MUKIM|BANDAR|PEKAN)\s+([A-Z][A-Z \-\']*)', flat):
            ecode, typ, rest = m.group(1), m.group(2), m.group(3)
            words = []
            for w in rest.split():
                if w in STOP or is_gazette_token(w) or re.match(r'^[0-9]', w) or '.' in w or '/' in w or '(' in w:
                    break
                words.append(w)
            name = clean_name(words)
            if not name:
                continue
            # skip district-table header echoes: district code + BANDAR + district
            # name (e.g. Kedah "10 BANDAR BAHARU" inside district 10's header)
            dname_bare = re.sub(r'^(Jajahan Kecil|Jajahan|Daerah Kecil|Daerah)\s+', '', d['name'])
            if ecode == code and typ == 'BANDAR' and norm(name) in (norm(dname_bare), norm('BANDAR ' + dname_bare)):
                continue
            # same echo without the district's own Bandar word (Kedah district 10
            # is itself named "Bandar Baharu", so its header echo parses as "BAHARU")
            if ecode == code and typ == 'BANDAR' and norm('BANDAR ' + name) == norm(dname_bare):
                continue
            # skip 'Rajah Seksyen' diagram-list repeats: they appear as 'BANDAR X' without code prefix... they HAVE code prefix. dedupe handles.
            ents.append((ecode, typ, name))
        # dedupe preserving order
        seen, res = set(), []
        for e in ents:
            if e not in seen:
                seen.add(e); res.append(e)
        result[code] = {'name': d['name'], 'entities': res}
    return result

def norm(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode()
    return re.sub(r'[^A-Z ]', '', s.upper()).strip()

def load_csv():
    rows = list(csv.DictReader(open('./packages/addressing/resources/geography/malaysia-address-areas.csv')))
    return rows

if __name__ == '__main__':
    state = sys.argv[1]  # e.g. kelantan
    upi_path = sys.argv[2]
    dmap = {}  # upi district code -> csv slug (auto)
    rows = load_csv()
    # csv districts of this state
    csvdist = {}
    for r in rows:
        if r['source_id'].startswith(f'my:district:{state}:') and r['parent_source_id'] == f'my:state:{state}':
            csvdist[r['source_id'].split(':')[-1]] = r['name']
    upi = parse(upi_path)
    # map upi district code -> slug by name
    print(f'### {state}: UPI districts={len(upi)} CSV districts={len(csvdist)}')
    code2slug = {}
    for code, d in sorted(upi.items()):
        uname = re.sub(r'^(Jajahan Kecil|Jajahan|Daerah Kecil|Daerah)\s+', '', d['name'])
        # special minors
        best = None
        for slug, cname in csvdist.items():
            if norm(uname) == norm(cname) or norm(uname).replace(' ','') == norm(cname).replace(' ',''):
                best = slug; break
        if not best:
            # try last-word match (e.g. 'KECIL MUADZAM SHAH' vs 'Muadzam Shah')
            for slug, cname in csvdist.items():
                if norm(cname) in norm(uname):
                    best = slug; break
        code2slug[code] = best
        print(f'  UPI {code} {d["name"][:40]:40} -> {best} (n={len(d["entities"])})')
    print()
    for code in sorted(upi):
        slug = code2slug[code]
        if not slug:
            print(f'--- {code} UNMAPPED; entities:'); 
            for e in upi[code]['entities']: print('   ', e)
            continue
        kids = [r for r in rows if r['parent_source_id'] == f'my:district:{state}:{slug}' or (slug in ('genting','gebeng','jelai','muadzam-shah','lojing') and r['parent_source_id']==f'my:district:{state}:{slug}')]
        # also minor district parents use same pattern
        csvnames = {}
        for r in kids:
            if r['type'] in ('mukim','bandar','pekan'):
                csvnames.setdefault(norm(r['name']), []).append((r['name'], r['type']))
        print(f'--- {code} {slug}: UPI n={len(upi[code]["entities"])} CSV(m/b/p) n={sum(len(v) for v in csvnames.values())}')
        for (ecode, typ, uname) in upi[code]['entities']:
            t = {'MUKIM':'mukim','BANDAR':'bandar','PEKAN':'pekan'}[typ]
            # candidate matches: full name or stripped (drop BANDAR/PEKAN if name starts with it? no - UPI name already excludes type word)
            key = norm(uname)
            if key in csvnames:
                cts = set(x[1] for x in csvnames[key])
                if t in cts:
                    pass  # matched
                else:
                    print(f'    TYPE? UPI {ecode} {typ} {uname}  vs CSV {csvnames[key]}')
            else:
                print(f'    MISSING UPI {ecode} {typ} {uname}')
        # extras: csv names not in UPI
        upikeys = set(norm(u) for _,_,u in upi[code]['entities'])
        for key, lst in sorted(csvnames.items()):
            if key not in upikeys:
                print(f'    EXTRA CSV {lst}')
