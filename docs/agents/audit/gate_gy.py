import csv, os, sys
# Guyana gate. Tree verify-only, zero data changes. M4 revisit.
# Run from repo root: python3 docs/agents/audit/gate_gy.py
A = './packages/addressing/resources/geography/guyana-address-areas.csv'
C = './packages/addressing/resources/geography/guyana-postal-codes.csv'
L = './packages/addressing/resources/geography/guyana-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-85', len(rows) == 85, str(len(rows)))
check('regions-10', sum(1 for r in rows if r['type'] == 'region') == 10)
check('ndc-65', sum(1 for r in rows if r['type'] == 'neighbourhood_democratic_council') == 65)
check('towns-10', sum(1 for r in rows if r['type'] == 'town') == 10)
# ISO 3166-2:GY codes.
iso = {'gy:region:barima-waini': ('Barima-Waini', 'BA'),
 'gy:region:cuyuni-mazaruni': ('Cuyuni-Mazaruni', 'CU'),
 'gy:region:demerara-mahaica': ('Demerara-Mahaica', 'DE'),
 'gy:region:east-berbice-corentyne': ('East Berbice-Corentyne', 'EB'),
 'gy:region:essequibo-islands-west-demerara': ('Essequibo Islands-West Demerara', 'ES'),
 'gy:region:mahaica-berbice': ('Mahaica-Berbice', 'MA'),
 'gy:region:pomeroon-supenaam': ('Pomeroon-Supenaam', 'PM'),
 'gy:region:potaro-siparuni': ('Potaro-Siparuni', 'PT'),
 'gy:region:upper-demerara-berbice': ('Upper Demerara-Berbice', 'UD'),
 'gy:region:upper-takutu-upper-essequibo': ('Upper Takutu-Upper Essequibo', 'UT')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> NDC table per the MLGRD-cited NDC oracle (65).
# Region 8 has no subdivisions; Region 9's Ireng/Sawariwau NDC was
# dissolved in 2012, so both carry zero NDC rows by design.
ndc = {'gy:region:barima-waini': ['Ridge/Arakaka',
    'Mabaruma/Kumaka/Hosororo'],
 'gy:region:pomeroon-supenaam': ['Charity/Urasara', 'Evergreen/Paradise',
    'Aberdeen/Zorg-en-Vlygt', 'Annandale/Riverstown', 'Good Hope/Pomona'],
 'gy:region:essequibo-islands-west-demerara': ['Wakenaam', 'Leguan',
    'Mora/Parika', 'Hydronie/Good Hope', 'Greenwich Park, Vergenoegen',
    'Tuschen/Uitvlugt', 'Stewartville/Cornelia Ida', 'Hague/Blankenburg',
    'La Jalousie/Nouvelle Flanders', 'Best/Klien/Pouderoyen',
    'Malgre Tout/Meer Zorgen', 'La Grange/Nimes', "Canal's Polder",
    'Toevlugt/Patentia'],
 'gy:region:demerara-mahaica': ["Soesdyke/Huis't Coverden NDC",
    'Lamaha/Yarrowkabra NDC', 'Caledonia/Good Success',
    'Little Diamond/Herstelling', 'Golden Grove/Diamond Place',
    'Mocha/Arcadia', 'Ramsburg/Eccles', 'Industry/Plaisance',
    'Better Hope/LBI', 'Beterverwagting/Triumph',
    'Mon Repos/La Reconnaissance', 'Buxton/Foulis', 'Enmore/Hope',
    'Haslington/Grove', 'Unity/Vereeniging', 'Cane Grove'],
 'gy:region:mahaica-berbice': ['Woodlands/Farm', 'Hamlet/Chance',
    'Mahaicony/Abary', 'Profit/Rising Sun', 'Seafield/Tempie',
    'Union/Naarstigheid', 'Bath/Woodley Park', 'Woodlands/Bel Air',
    'Zeelugt/Rosignol', 'Blairmont/Gelderland'],
 'gy:region:east-berbice-corentyne': ['Enfield/New Doe Park',
    'Ordinance/FortLands', 'Canefield/Enterprise', 'Kintyre/No.37',
    'Gibraltar/Fyrish', 'Kilcoy/Hampshire', 'Port Mourant/John',
    'Bloomfield/Whim', 'Lancaster/Hogstye', 'Black Bush Polder',
    'Good Hope/No.51', 'Macedonia/Joppa', 'Bushlot/Adventure',
    'Maida/Tarlogie', 'No. 52/ No. 74', 'Crabwood Creek/Molsen Creek'],
 'gy:region:cuyuni-mazaruni': ['Bartica NDC'],
 'gy:region:potaro-siparuni': [],
 'gy:region:upper-takutu-upper-essequibo': [],
 'gy:region:upper-demerara-berbice': ['Kwakwani NDC']}
ok = True
for reg, names in ndc.items():
    have = sorted(r['name'] for r in rows
                  if r['parent_source_id'] == reg
                  and r['type'] == 'neighbourhood_democratic_council')
    if have != sorted(names):
        print('FAIL region', reg, have); fails.append(f'region {reg}'); ok = False
if ok: print('PASS all 10 region mappings (65 NDCs)')
# Town -> region placements: 8 are regional capitals per the Regions
# oracle; Corriverton + Rose Hall confirmed under East
# Berbice-Corentyne via their own articles.
towns = {'gy:town:mabaruma': 'gy:region:barima-waini',
 'gy:town:anna-regina': 'gy:region:pomeroon-supenaam',
 'gy:town:georgetown': 'gy:region:demerara-mahaica',
 'gy:town:corriverton': 'gy:region:east-berbice-corentyne',
 'gy:town:new-amsterdam': 'gy:region:east-berbice-corentyne',
 'gy:town:rose-hall': 'gy:region:east-berbice-corentyne',
 'gy:town:bartica': 'gy:region:cuyuni-mazaruni',
 'gy:town:mahdia': 'gy:region:potaro-siparuni',
 'gy:town:lethem': 'gy:region:upper-takutu-upper-essequibo',
 'gy:town:linden': 'gy:region:upper-demerara-berbice'}
for sid, reg in towns.items():
    r = byid.get(sid)
    check(f'town-{sid.split(":")[-1]}',
          bool(r) and r['parent_source_id'] == reg and r['type'] == 'town'
          and r['level'] == '2', str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Postcode gap (NOT a codeless verdict): UPU guyEn (08/2025)
# documents a live 7-digit system (region/sub-region/locality/
# office/delivery district, e.g. Georgetown 4130106); the UPU
# require list carries Guyana and the format table pins 9999999.
# No overlay files ship yet, so these checks record current state;
# building the overlay is a step-4 follow-up (guypost.gy is
# Cloudflare-walled from here). Update this gate deliberately when
# the overlay lands, never silently.
check('no-codes-file-yet', not os.path.exists(C))
check('no-links-file-yet', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
