import csv

AREAS = './packages/addressing/resources/geography/malaysia-address-areas.csv'
LINKS = './packages/addressing/resources/geography/malaysia-postal-code-areas.csv'
K = 'my:subdistrict:district:kelantan'
D = 'my:district:kelantan'

exec(open('kelantan_data.py').read())  # ADDS, RETYPES, DELETE_ROW, REPOINTS, DROP_LINKS

def main():
    rows = list(csv.DictReader(open(AREAS)))
    byid = {r['source_id']: r for r in rows}
    for sid in RETYPES: assert sid in byid, sid
    assert DELETE_ROW in byid
    newrows = {}
    for d, slug, name, t in ADDS:
        sid = f'{K}:{d}:{slug}'
        assert sid not in byid, f'collision {sid}'
        newrows.setdefault(f'{D}:{d}', []).append(
            {'source_id': sid, 'country_code': 'MY', 'type': t, 'name': name,
             'native_name': '', 'code': '', 'parent_source_id': f'{D}:{d}',
             'level': '3', 'latitude': '', 'longitude': ''})
    for p in newrows: newrows[p].sort(key=lambda r: r['source_id'])

    # main contiguous run per parent
    runs = {}
    i, n = 0, len(rows)
    while i < n:
        p = rows[i]['parent_source_id']
        j = i
        while j + 1 < n and rows[j + 1]['parent_source_id'] == p:
            j += 1
        runs.setdefault(p, (i, j))  # first (main) run only
        i = j + 1

    out = []
    inserted = set()
    for i, r in enumerate(rows):
        if r['source_id'] == DELETE_ROW:
            continue
        p = r['parent_source_id']
        if p in newrows and p not in inserted and runs.get(p, (0, 0))[0] == i:
            # merge new rows sorted into this run
            start, end = runs[p]
            seg = [x for x in rows[start:end + 1] if x['source_id'] != DELETE_ROW]
            merged = sorted(seg + newrows[p], key=lambda x: x['source_id'])
            for m in merged:
                if m['source_id'] in RETYPES:
                    m = dict(m); m['type'] = RETYPES[m['source_id']]
                out.append(m)
            inserted.add(p)
            # skip rest of run in main loop
            for _ in range(start, end + 1):
                pass
            # mark consumed by advancing: handle via skip set
            continue_marker = getattr(main, 'skip', set())
            main.skip = continue_marker | set(range(start + 1, end + 1))
            continue
        if hasattr(main, 'skip') and i in main.skip:
            continue
        if r['source_id'] in RETYPES:
            r = dict(r); r['type'] = RETYPES[r['source_id']]
        out.append(r)
    # any parents whose run-start logic missed (shouldn't happen)
    missing = [p for p in newrows if p not in inserted]
    assert not missing, missing
    assert len(out) == len(rows) - 1 + sum(len(v) for v in newrows.values()), (len(out), len(rows))
    with open(AREAS, 'w', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(out[0].keys()), lineterminator='\n')
        w.writeheader(); w.writerows(out)
    print(f'areas: {len(out)} rows')

    links = list(csv.DictReader(open(LINKS)))
    drop = set(DROP_LINKS)
    n0 = len(links)
    links2 = [l for l in links if (l['postcode'], l['area_source_id'], l['is_primary']) not in drop]
    assert n0 - len(links2) == len(drop)
    for pc, old, new in REPOINTS:
        hits = [l for l in links2 if l['postcode'] == pc and l['area_source_id'] == old]
        assert len(hits) == 1, (pc, old, len(hits))
        hits[0]['area_source_id'] = new
    with open(LINKS, 'w', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(links2[0].keys()), lineterminator='\n')
        w.writeheader(); w.writerows(links2)
    print(f'links: {len(links2)} rows')

main()
