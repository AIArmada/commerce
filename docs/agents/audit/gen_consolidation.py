import csv, sys
LINKS = './packages/addressing/resources/geography/malaysia-postal-code-areas.csv'
K = 'my:subdistrict:district:kedah'
pairs = [
 (f'{K}:kota-setar:bandar-alor-setar', f'{K}:kota-setar:alor-setar'),
 (f'{K}:pendang:bandar-pendang', f'{K}:pendang:pendang'),
 (f'{K}:pokok-sena:pekan-pokok-sena', f'{K}:pokok-sena:pokok-sena'),
]
links = list(csv.DictReader(open(LINKS)))
rep, drop = [], []
for old, new in pairs:
    for l in links:
        if l['area_source_id'] == old:
            rep.append((l['postcode'], old, new))
    for pc, _, _ in rep:
        hits = [l for l in links if l['postcode']==pc and l['area_source_id']==new]
        for h in hits:
            assert h['is_primary'] == 'false', (pc, new, 'would collide with primary!')
            drop.append((pc, new, 'false'))
drop = sorted(set(drop))
print(f'repoints={len(rep)} drops={len(drop)}')
for pc, o, n in sorted(rep):
    print(f'R\t{pc}\t{o}\t{n}')
for pc, a, prim in drop:
    print(f'D\t{pc}\t{a}\t{prim}')
