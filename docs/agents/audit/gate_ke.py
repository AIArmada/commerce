import csv, re, sys
# Kenya gate. Pins the B18 inline pass (337 areas: 47 counties
# ISO-exact + 290 constituencies IEBC-exact; 977 codes / 977
# county-level legs): tree 47/47 ISO 3166-2:KE codes+names +
# 290/290 vs en.wiki numbered roster 1-290 with zero parent
# mismatches; postal code-set = GN 877 + 72 PCK-via-block (69
# confirmed by npc/kuccps/techweez lists, 3 confirmed targeted:
# 10234 Kora + 10239 Gakungu Murang'a, 70303 Takaba Mandera) +
# 28 fills (4-list agreement npc+kuccps+techweez+kfw/mwafrikah +
# town-level county evidence each); 26 leg moves (21 Nairobi
# fallbacks + 5 odd-legs 01102/10311/20420/30711/90148);
# 5 holds (60210 Tigiji ghost, 80204 Watalii unattributable,
# 01029/30216/90149 Nairobi-kept unattributable).
# EOL: areas LF-only; postal CRLF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_ke.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/kenya-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/kenya-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/kenya-postal-code-areas.csv'
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
check('codes-CRLF', b'\r\n' in raw_c and b'\r' not in raw_c.replace(b'\r\n', b''))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', b'\r\n' in raw_l and b'\r' not in raw_l.replace(b'\r\n', b''))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
byid = {r['source_id']: r for r in rows}
check('areas-337', len(rows) == 337, str(len(rows)))
check('county-47', sum(1 for r in rows if r['level'] == '1') == 47)
check('constituency-290', sum(1 for r in rows if r['level'] == '2') == 290)
iso = {'baringo': '01', 'bomet': '02', 'bungoma': '03', 'busia': '04',
       'elgeyo-marakwet': '05', 'embu': '06', 'garissa': '07',
       'homa-bay': '08', 'isiolo': '09', 'kajiado': '10',
       'kakamega': '11', 'kericho': '12', 'kiambu': '13',
       'kilifi': '14', 'kirinyaga': '15', 'kisii': '16',
       'kisumu': '17', 'kitui': '18', 'kwale': '19',
       'laikipia': '20', 'lamu': '21', 'machakos': '22',
       'makueni': '23', 'mandera': '24', 'marsabit': '25',
       'meru': '26', 'migori': '27', 'mombasa': '28',
       'murang-a': '29', 'nairobi-city': '30', 'nakuru': '31',
       'nandi': '32', 'narok': '33', 'nyamira': '34',
       'nyandarua': '35', 'nyeri': '36', 'samburu': '37',
       'siaya': '38', 'taita-taveta': '39', 'tana-river': '40',
       'tharaka-nithi': '41', 'trans-nzoia': '42', 'turkana': '43',
       'uasin-gishu': '44', 'vihiga': '45', 'wajir': '46',
       'west-pokot': '47'}
ok = all(byid.get(f'ke:county:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-47', ok and len(iso) == 47)
check('codes-977', len(codes) == 977, str(len(codes)))
check('links-977', len(links) == 977, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-unique', len(set(clist)) == len(clist))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L1-only', all(byid[l['area_source_id']]['level'] == '1' for l in links))
check('legs-keyset', set(l['postcode'] for l in links) == set(clist))
leg = {l['postcode']: l['area_source_id'] for l in links}
fills = {'00514': 'ke:county:nairobi-city', '00617': 'ke:county:nairobi-city',
         '00624': 'ke:county:nairobi-city', '01026': 'ke:county:murang-a',
         '10110': 'ke:county:murang-a', '10225': 'ke:county:murang-a',
         '10134': 'ke:county:nyeri', '10135': 'ke:county:nyeri',
         '10137': 'ke:county:nyeri', '10138': 'ke:county:nyeri',
         '20155': 'ke:county:nyandarua', '30127': 'ke:county:uasin-gishu',
         '30220': 'ke:county:bungoma', '40130': 'ke:county:kisumu',
         '40226': 'ke:county:homa-bay', '40324': 'ke:county:homa-bay',
         '40327': 'ke:county:homa-bay', '40636': 'ke:county:siaya',
         '40637': 'ke:county:siaya', '40638': 'ke:county:siaya',
         '40642': 'ke:county:siaya', '40643': 'ke:county:siaya',
         '50129': 'ke:county:kakamega', '50130': 'ke:county:kakamega',
         '50131': 'ke:county:kakamega', '50427': 'ke:county:busia',
         '90201': 'ke:county:kitui', '90409': 'ke:county:kitui'}
ok = all(leg.get(pc) == sid for pc, sid in fills.items())
check('fills-28', ok and len(fills) == 28)
moves = {'10224': 'ke:county:murang-a', '30703': 'ke:county:elgeyo-marakwet',
         '40312': 'ke:county:homa-bay', '40406': 'ke:county:migori',
         '40411': 'ke:county:migori', '40505': 'ke:county:nyamira',
         '40629': 'ke:county:siaya', '40634': 'ke:county:siaya',
         '40639': 'ke:county:siaya', '50301': 'ke:county:vihiga',
         '50407': 'ke:county:busia', '50415': 'ke:county:busia',
         '50419': 'ke:county:busia', '50420': 'ke:county:busia',
         '60217': 'ke:county:meru', '80312': 'ke:county:taita-taveta',
         '80316': 'ke:county:kwale', '80408': 'ke:county:kwale',
         '90216': 'ke:county:kitui', '90405': 'ke:county:kitui',
         '90406': 'ke:county:kitui', '01102': 'ke:county:kajiado',
         '10311': 'ke:county:kirinyaga', '20420': 'ke:county:bomet',
         '30711': 'ke:county:elgeyo-marakwet', '90148': 'ke:county:machakos'}
ok = all(leg.get(pc) == sid for pc, sid in moves.items())
check('moves-26', ok and len(moves) == 26)
check('hold-60210-absent', '60210' not in leg)
check('hold-80204-absent', '80204' not in leg)
check('hold-01029-nairobi', leg.get('01029') == 'ke:county:nairobi-city')
check('hold-30216-nairobi', leg.get('30216') == 'ke:county:nairobi-city')
check('hold-90149-nairobi', leg.get('90149') == 'ke:county:nairobi-city')
check('typo-00127-absent', '00127' not in leg)
check('anchor-00100', leg.get('00100') == 'ke:county:nairobi-city')
check('anchor-80100', leg.get('80100') == 'ke:county:mombasa')
check('anchor-20100', leg.get('20100') == 'ke:county:nakuru')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
