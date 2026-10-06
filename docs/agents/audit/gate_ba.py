import csv, sys
from collections import Counter
# Bosnia and Herzegovina gate. Pins the B14 fix pass (postal-only; the
# 143-municipality tree is WP-sealed with zero diffs): 71000 completed
# to all 4 Sarajevo-city municipalities, 71123 gained Istočno Novo
# Sarajevo (Pošte Srpske office sits in Lukavica), 74208 re-primaried
# to Stanari (2014 municipality, office + seat), 77253 re-primaried to
# Bosanski Petrovac (Krnjeuša village), Bijeljina main swapped
# 76000 (retired SFRY code) -> 76300 (current). Codes 517 -> 517,
# legs 570 -> 575, multis 50 -> 53.
# OPEN #12 fill 2026-10-06 (routing-table + 3 live operator networks):
# 37 PROVEN-live WP-only codes filled (15 Sarajevo BHP units, duals
# 71124/71213, Kiseljak 71275/71335, 72293, Orašje/Odžak 76281/76298,
# Livno/Tomislavgrad 80202/80205/80246, 10 Mostar units, 88221/88322)
# + 10 beyond-65 proven (71126, duals 71214/71216, 73300, 79101,
# 74231, 74273, 71212, 71218, 71323) + 79293 dropped (erroneous Opara;
# replaced by 72293). Codes 517 -> 563, legs 575 -> 625, multis 53 -> 57.
# Retired 74321/75000/88267 confirmed absent (successors live); 10
# likely-live + 15 likely-retired + named singletons pinned absent (held).
# Oracles: WP municipality tables (79 FBiH + 64 RS), WP postcode list
# (582 unit rows), BH Pošta PostNet office list (112), Pošte Srpske
# office list (67), UPU bihEn.pdf (71000 anchor), municipality kontakt
# pages (Novi Grad 71000, Stanari 74208), directories (76300 usage),
# HP routing table 2013 (561 codes, operator tags), Pošte Srpske live
# finder (234 codes), BH Pošta live network map (240 codes), HP Mostar
# live finder API (91 codes), Foča/Prijedor/Usora kontakt pages.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_ba.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/bosnia-and-herzegovina-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/bosnia-and-herzegovina-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/bosnia-and-herzegovina-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 3 L1 + 143 municipalities (79 FBiH + 64 RS) ---
check('areas-146', len(rows) == 146, str(len(rows)))
check('l1-3', sum(1 for r in rows if r['level'] == '1') == 3)
check('l2-143', sum(1 for r in rows if r['level'] == '2') == 143)
iso = {'ba:entity:federation-of-bosnia-and-herzegovina':
       ('Federation of Bosnia and Herzegovina', 'BIH'),
       'ba:entity:republika-srpska': ('Republika Srpska', 'SRP'),
       'ba:district:brcko': ('Brčko', 'BRC')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
check('fbih-79',
      got.get('ba:entity:federation-of-bosnia-and-herzegovina') == 79,
      str(got.get('ba:entity:federation-of-bosnia-and-herzegovina')))
check('rs-64', got.get('ba:entity:republika-srpska') == 64,
      str(got.get('ba:entity:republika-srpska')))
check('brcko-terminal', got.get('ba:district:brcko', 0) == 0)
# Disambiguation twins + Sarajevo city structure.
check('kupres-twins',
      sum(1 for r in rows if r['name'] == 'Kupres') == 2)
check('trnovo-twins',
      {r['source_id'] for r in rows if r['name'].startswith('Trnovo')}
      == {'ba:municipality:trnovo-fbih', 'ba:municipality:trnovo-rs'})
sarajevo = {'ba:municipality:centar-sarajevo',
            'ba:municipality:novi-grad-sarajevo',
            'ba:municipality:novo-sarajevo',
            'ba:municipality:stari-grad-sarajevo'}
check('sarajevo-city-4',
      sarajevo <= set(byid)
      and all(byid[s]['parent_source_id']
              == 'ba:entity:federation-of-bosnia-and-herzegovina'
              for s in sarajevo))
# --- postal: 563 codes / 625 legs / 57 multis ---
check('codes-563', len(codes) == 563, str(len(codes)))
check('legs-625', len(links) == 625, str(len(links)))
bycode = {}
for r in links:
    bycode.setdefault(r['postcode'], []).append(r)
multis = sorted(k for k, v in bycode.items() if len(v) > 1)
check('multis-57', len(multis) == 57, str(len(multis)))
for code, legs in bycode.items():
    prim = [r for r in legs if r['is_primary'] == 'true']
    check(f'primary-{code}', len(prim) == 1, str(len(prim)))
# T1: 71000 serves all 4 Sarajevo-city municipalities.
legs71000 = {r['area_source_id']: r['is_primary']
             for r in bycode.get('71000', [])}
check('71000-quad', legs71000 == {
    'ba:municipality:centar-sarajevo': 'true',
    'ba:municipality:novo-sarajevo': 'false',
    'ba:municipality:novi-grad-sarajevo': 'false',
    'ba:municipality:stari-grad-sarajevo': 'false'}, str(legs71000))
# T2: 71123 gains Lukavica (office sits in Istočno Novo Sarajevo).
legs71123 = {r['area_source_id']: r['is_primary']
             for r in bycode.get('71123', [])}
check('71123-dual', legs71123 == {
    'ba:municipality:istocna-ilidza': 'true',
    'ba:municipality:istocno-novo-sarajevo': 'false'},
    str(legs71123))
# T3: 74208 re-primaried Stanari over Doboj.
legs74208 = {r['area_source_id']: r['is_primary']
             for r in bycode.get('74208', [])}
check('74208-stanari', legs74208 == {
    'ba:municipality:stanari': 'true',
    'ba:municipality:doboj': 'false'}, str(legs74208))
# T4: 77253 re-primaried Bosanski Petrovac over Bihać.
legs77253 = {r['area_source_id']: r['is_primary']
             for r in bycode.get('77253', [])}
check('77253-krnjeusa', legs77253 == {
    'ba:municipality:bosanski-petrovac': 'true',
    'ba:municipality:bihac': 'false'}, str(legs77253))
# T5+T6: Bijeljina main swap 76000 -> 76300.
check('76000-absent', '76000' not in bycode)
legs76300 = bycode.get('76300', [])
check('76300-bijeljina',
      len(legs76300) == 1
      and legs76300[0]['area_source_id'] == 'ba:municipality:bijeljina'
      and legs76300[0]['is_primary'] == 'true', str(legs76300))
# Sealed triples (manual cross-boundary, adjacency-plausible).
triples = {'75445': ('ba:municipality:milici', {'ba:municipality:bratunac',
                                                'ba:municipality:vlasenica'}),
           '78233': ('ba:municipality:celinac', {'ba:municipality:banja-luka',
                                                 'ba:municipality:knezevo'}),
           '88420': ('ba:municipality:jablanica',
                     {'ba:municipality:konjic',
                      'ba:municipality:prozor-rama'})}
for code, (p, ss) in triples.items():
    legs = {r['area_source_id']: r['is_primary']
            for r in bycode.get(code, [])}
    check(f'triple-{code}',
          legs.get(p) == 'true'
          and {s for s, v in legs.items() if v == 'false'} == ss
          and len(legs) == 3, str(legs))
# Brčko terminal: 13 codes at L1.
brcko = [c for c, legs in bycode.items()
         if any(r['area_source_id'] == 'ba:district:brcko' for r in legs)]
check('brcko-13', len(brcko) == 13, str(sorted(brcko)))
# --- OPEN #12: new dual-operator codes (BHP/FBiH primary, PS/RS secondary) ---
duals12 = {'71124': ('ba:municipality:novo-sarajevo', 'ba:municipality:istocna-ilidza'),
           '71213': ('ba:municipality:ilidza', 'ba:municipality:istocna-ilidza'),
           '71214': ('ba:municipality:ilidza', 'ba:municipality:istocna-ilidza'),
           '71216': ('ba:municipality:ilidza', 'ba:municipality:istocna-ilidza')}
for code, (p, s) in duals12.items():
    legs = {r['area_source_id']: r['is_primary']
            for r in bycode.get(code, [])}
    check(f'dual12-{code}', legs == {p: 'true', s: 'false'}, str(legs))
# --- OPEN #12: proven-fill singles ---
singles12 = {
    '71101': 'ba:municipality:centar-sarajevo', '71103': 'ba:municipality:centar-sarajevo',
    '71104': 'ba:municipality:centar-sarajevo', '71108': 'ba:municipality:centar-sarajevo',
    '71120': 'ba:municipality:novo-sarajevo', '71121': 'ba:municipality:novo-sarajevo',
    '71122': 'ba:municipality:novo-sarajevo', '71125': 'ba:municipality:novo-sarajevo',
    '71126': 'ba:municipality:istocno-novo-sarajevo',
    '71140': 'ba:municipality:stari-grad-sarajevo', '71141': 'ba:municipality:stari-grad-sarajevo',
    '71160': 'ba:municipality:novi-grad-sarajevo', '71162': 'ba:municipality:novi-grad-sarajevo',
    '71165': 'ba:municipality:novi-grad-sarajevo', '71166': 'ba:municipality:novi-grad-sarajevo',
    '71167': 'ba:municipality:novi-grad-sarajevo',
    '71212': 'ba:municipality:ilidza', '71218': 'ba:municipality:ilidza',
    '71275': 'ba:municipality:kiseljak', '71335': 'ba:municipality:kiseljak',
    '71323': 'ba:municipality:vogosca',
    '72293': 'ba:municipality:novi-travnik',
    '73300': 'ba:municipality:foca', '79101': 'ba:municipality:prijedor',
    '74231': 'ba:municipality:usora', '74273': 'ba:municipality:teslic',
    '76281': 'ba:municipality:orasje', '76298': 'ba:municipality:odzak',
    '80202': 'ba:municipality:livno', '80205': 'ba:municipality:livno',
    '80246': 'ba:municipality:tomislavgrad',
    '88103': 'ba:municipality:mostar', '88105': 'ba:municipality:mostar',
    '88106': 'ba:municipality:mostar', '88107': 'ba:municipality:mostar',
    '88108': 'ba:municipality:mostar', '88109': 'ba:municipality:mostar',
    '88110': 'ba:municipality:mostar', '88121': 'ba:municipality:mostar',
    '88122': 'ba:municipality:mostar', '88123': 'ba:municipality:mostar',
    '88221': 'ba:municipality:siroki-brijeg', '88322': 'ba:municipality:ljubuski'}
for code, area in singles12.items():
    legs = bycode.get(code, [])
    check(f'fill12-{code}',
          len(legs) == 1
          and legs[0]['area_source_id'] == area
          and legs[0]['is_primary'] == 'true', str(legs))
# --- OPEN #12: retired drops + live successors ---
for code in ('74321', '75000', '88267', '79293'):
    check(f'retired12-absent-{code}', code not in bycode)
for code in ('74231', '75101', '88266', '72293'):
    check(f'successor12-live-{code}', code in bycode)
# --- OPEN #12: holds pinned absent (likely-live 10 + likely-retired 15) ---
holds12 = ['76234', '76271', '76276', '80203', '88327', '88365', '88366',
           '88368', '88375', '88395', '71145', '76100', '76231', '80241',
           '88005', '88101', '88222', '88241', '88242', '88261', '88264',
           '88301', '88302', '88321', '88341']
for code in holds12:
    check(f'hold12-absent-{code}', code not in bycode)
# --- OPEN #12: recorded-live single-lineage singletons pinned absent ---
singletons12 = ['88003', '88125', '88262', '80242', '76110', '72295',
                '79291', '73301']
for code in singletons12:
    check(f'singleton12-absent-{code}', code not in bycode)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
