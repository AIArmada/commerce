import re, sys

STOP = {'TIADA','KN.','KN','GN','W.K.','WARTA','KERAJAAN','PELAN','PW','PP','PG',
        'SEKSYEN','RAJAH','SILA','NEGERI','KOD','NAMA','JAJAHAN','KECIL','DAERAH',
        'MUKIM/BANDAR/PEKAN/','BAGI','RUJUK','HANYA','ADA','DALAM','BERTARIKH',
        'BERKENAAN','PELAN','GLOSARI','DESA','DAN','RAJAH','MUKIM/BANDAR/PEKAN','LAND/TOWN'}

def parse(path):
    text = open(path).read()
    # find district sections: 'NN - Jajahan X' / 'NN - Daerah X' / 'NN - Daerah Kecil X'
    parts = re.split(r'(\d\d) - ((?:Jajahan Kecil|Jajahan|Daerah Kecil|Daerah)(?: [A-Z][A-Za-z ]*)?)', text)
    # parts[0]=pre, then triples (code, name, body)
    districts = {}
    it = iter(range(1, len(parts), 3))
    for i in it:
        code, name, body = parts[i], parts[i+1].strip(), parts[i+2]
        # cut body at next section start already handled by split; but pages repeat headers -
        # merge bodies with same code
        districts.setdefault(code, {'name': name, 'body': ''})
        districts[code]['body'] += '\n' + body
    return districts

def entities(body):
    # join lines; find CODE TYPE NAME sequences
    flat = re.sub(r'\s+', ' ', body)
    out = []
    for m in re.finditer(r'\b(\d{2,3})\s+(MUKIM|BANDAR|PEKAN)\s+([A-Z][A-Z \-\']*)', flat):
        code, typ, rest = m.group(1), m.group(2), m.group(3)
        words = []
        for w in rest.split():
            if w in STOP or re.match(r'^[0-9]', w) or '.' in w or '/' in w or '(' in w:
                break
            words.append(w)
        name = ' '.join(words).strip()
        # skip section-diagram repeats like 'BANDAR TUMPAT' inside 'Rajah Seksyen bagi Bandar Tumpat' (lowercase, won't match)
        out.append((code, typ, name))
    # dedupe preserving order
    seen, res = set(), []
    for e in out:
        if e not in seen:
            seen.add(e); res.append(e)
    return res

if __name__ == '__main__':
    d = parse(sys.argv[1])
    for code in sorted(d):
        print(f"===== {code} {d[code]['name']} =====")
        for e in entities(d[code]['body']):
            print(f'  {e[0]:>3} {e[1]:<6} {e[2]}')
