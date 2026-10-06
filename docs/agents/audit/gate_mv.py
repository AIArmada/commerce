import csv, re, sys
from collections import Counter
# Maldives gate. M6 verify-only: tree 18 atolls + 5 cities + 192
# islands vs ISO 3166-2:MV + Decentralization Act (Thinadhoo 5th
# city 2023; Gnaviyani/29 absorbed by Fuvahmulah) + Wikipedia
# inhabited-island lists (192/192 names match); postal 199 codes /
# 202 links vs full Postcodebase crawl (20 atoll pages, 295 rows):
# 156/156 inhabited matches agree, zero code diffs; 56ok +
# worldpostalcode corroborate. UPU mdvEn (2004) prefix table is
# stale (ADh 10, V 11 .. S 20); live scheme is ADh 00, V 10 ..
# S 19 as shipped. Run from repo root:
# python3 docs/agents/audit/gate_mv.py
A = './packages/addressing/resources/geography/maldives-address-areas.csv'
C = './packages/addressing/resources/geography/maldives-postal-codes.csv'
L = './packages/addressing/resources/geography/maldives-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 18 atolls + 5 cities + 192 islands ---
check('areas-215', len(rows) == 215, str(len(rows)))
check('atolls-18', sum(1 for r in rows if r['type'] == 'atoll') == 18)
check('cities-5', sum(1 for r in rows if r['type'] == 'city') == 5)
check('islands-192', sum(1 for r in rows if r['type'] == 'island') == 192)
check('l1-23', sum(1 for r in rows if r['level'] == '1') == 23)
check('l2-192', sum(1 for r in rows if r['level'] == '2') == 192)
iso = {
 'mv:city:addu': ('Addu', '01'),
 'mv:atoll:alif-alif': ('Alif Alif', '02'),
 'mv:atoll:alif-dhaal': ('Alif Dhaal', '00'),
 'mv:atoll:baa': ('Baa', '20'),
 'mv:atoll:dhaalu': ('Dhaalu', '17'),
 'mv:atoll:faafu': ('Faafu', '14'),
 'mv:atoll:gaafu-alif': ('Gaafu Alif', '27'),
 'mv:atoll:gaafu-dhaalu': ('Gaafu Dhaalu', '28'),
 'mv:atoll:haa-alif': ('Haa Alif', '07'),
 'mv:atoll:haa-dhaalu': ('Haa Dhaalu', '23'),
 'mv:atoll:kaafu': ('Kaafu', '26'),
 'mv:atoll:laamu': ('Laamu', '05'),
 'mv:atoll:lhaviyani': ('Lhaviyani', '03'),
 'mv:city:male': ('Mal\u00e9', 'MLE'),
 'mv:atoll:meemu': ('Meemu', '12'),
 'mv:atoll:noonu': ('Noonu', '25'),
 'mv:atoll:raa': ('Raa', '13'),
 'mv:atoll:shaviyani': ('Shaviyani', '24'),
 'mv:atoll:thaa': ('Thaa', '08'),
 'mv:atoll:vaavu': ('Vaavu', '04'),
 'mv:city:fuvahmulah': ('Fuvahmulah', 'FVM'),
 'mv:city:kulhudhuffushi': ('Kulhudhuffushi', 'KUH'),
 'mv:city:thinadhoo': ('Thinadhoo', 'THD'),
}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Gnaviyani (ISO 29) is gone: Fuvahmulah city covers it entirely.
check('no-gnaviyani', not [r for r in rows if r['code'] == '29'])
check('no-iso29-name', not [r for r in rows if 'Gnaviyani' in r['name']])
table = {
 'mv:atoll:alif-alif': ['Bodufolhudhoo', 'Feridhoo', 'Fesdhoo', 'Himandhoo', 'Maalhos', 'Mathiveri', 'Rasdhoo', 'Thoddoo', 'Ukulhas'],
 'mv:atoll:alif-dhaal': ['Dhangethi', 'Dhiddhoo', 'Dhigurah', 'Fenfushi', 'Haggnaameedhoo', 'Kunburudhoo', 'Maamingili', 'Mahibadhoo', 'Mandhoo', 'Omadhoo'],
 'mv:atoll:baa': ['Dharavandhoo', 'Dhonfanu', 'Eydhafushi', 'Fehendhoo', 'Fulhadhoo', 'Goidhoo', 'Hithaadhoo', 'Kamadhoo', 'Kendhoo', 'Kihaadhoo', 'Kudarikilu', 'Maalhos', 'Thulhaadhoo'],
 'mv:atoll:dhaalu': ['Bandidhoo', 'Hulhudheli', 'Kudahuvadhoo', 'Maaenboodhoo', 'Meedhoo', 'Rinbudhoo', 'Vaanee'],
 'mv:atoll:faafu': ['Bileddhoo', 'Dharanboodhoo', 'Feeali', 'Magoodhoo', 'Nilandhoo'],
 'mv:atoll:gaafu-alif': ['Dhaandhoo', 'Dhevvadhoo', 'Gemanafushi', 'Kanduhulhudhoo', 'Kolamaafushi', 'Kondey', 'Maamendhoo', 'Nilandhoo', 'Villingili'],
 'mv:atoll:gaafu-dhaalu': ['Fares-Maathodaa', 'Fiyoaree', 'Gadhdhoo', 'Hoandeddhoo', 'Madaveli', 'Nadellaa', 'Rathafandhoo', 'Thinadhoo', 'Vaadhoo'],
 'mv:atoll:haa-alif': ['Baarah', 'Dhiddhoo', 'Filladhoo', 'Hoarafushi', 'Ihavandhoo', 'Kelaa', 'Maarandhoo', 'Mulhadhoo', 'Muraidhoo', 'Thakandhoo', 'Thuraakunu', 'Uligamu', 'Utheemu', 'Vashafaru'],
 'mv:atoll:haa-dhaalu': ['Finey', 'Hanimaadhoo', 'Hirimaradhoo', 'Kulhudhuffushi', 'Kumundhoo', 'Kurinbi', 'Makunudhoo', 'Naivaadhoo', 'Nellaidhoo', 'Neykurendhoo', 'Nolhivaram', 'Nolhivaranfaru', 'Vaikaradhoo'],
 'mv:atoll:kaafu': ['Dhiffushi', 'Gaafaru', 'Gulhi', 'Guraidhoo', 'Himmafushi', 'Hulhumalé', 'Huraa', 'Kaashidhoo', 'Maafushi', 'Malé', 'Thulusdhoo', 'Villimalé'],
 'mv:atoll:laamu': ['Dhanbidhoo', 'Fonadhoo', 'Gan', 'Hithadhoo', 'Isdhoo', 'Kunahandhoo', 'Maabaidhoo', 'Maamendhoo', 'Maavah', 'Mundoo'],
 'mv:atoll:lhaviyani': ['Hinnavaru', 'Kurendhoo', 'Maafilaafushi', 'Naifaru', 'Olhuvelifushi', 'Ookolhufinolhu'],
 'mv:atoll:meemu': ['Dhiggaru', 'Kolhufushi', 'Maduvvaree', 'Mulak', 'Muli', 'Naalaafushi', 'Raimmandhoo', 'Veyvah'],
 'mv:atoll:noonu': ['Foddhoo', 'Henbadhoo', 'Holhudhoo', 'Kendhikulhudhoo', 'Kudafari', 'Landhoo', 'Lhohi', 'Maafaru', 'Maalhendhoo', 'Magoodhoo', 'Manadhoo', 'Miladhoo', 'Velidhoo'],
 'mv:atoll:raa': ['Alifushi', 'Angolhitheemu', 'Dhuvaafaru', 'Fainu', 'Hulhudhuffaaru', 'Inguraidhoo', 'Innamaadhoo', 'Kinolhas', 'Maakurathu', 'Maduvvaree', 'Meedhoo', 'Rasgetheemu', 'Rasmaadhoo', 'Ungoofaaru', 'Vaadhoo'],
 'mv:atoll:shaviyani': ['Bileffahi', 'Feevah', 'Feydhoo', 'Foakaidhoo', 'Funadhoo', 'Goidhoo', 'Kanditheemu', 'Komandoo', 'Lhaimagu', 'Maaungoodhoo', 'Maroshi', 'Milandhoo', 'Narudhoo', 'Noomaraa'],
 'mv:atoll:thaa': ['Burunee', 'Dhiyamingili', 'Gaadhiffushi', 'Guraidhoo', 'Hirilandhoo', 'Kandoodhoo', 'Kinbidhoo', 'Madifushi', 'Omadhoo', 'Thimarafushi', 'Vandhoo', 'Veymandoo', 'Vilufushi'],
 'mv:atoll:vaavu': ['Felidhoo', 'Fulidhoo', 'Keyodhoo', 'Rakeedhoo', 'Thinadhoo'],
 'mv:city:addu': ['Feydhoo', 'Hithadhoo', 'Hulhudhoo', 'Maradhoo', 'Maradhoo-Feydhoo', 'Meedhoo'],
 'mv:city:fuvahmulah': ['Fuvahmulah'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 20 parent mappings (192 islands)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 199 codes / 202 links ---
check('codes-199', len(codes) == 199, str(len(codes)))
check('links-202', len(links) == 202, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 199 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
# --- multis: 05020 dual-island + 2 city secondaries ---
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-3', multis == ['02110', '05020', '17100'], str(multis))
exp = {'05020': [('mv:island:inguraidhoo', 'true'),
    ('mv:island:vaadhoo', 'false')],
 '02110': [('mv:island:kulhudhuffushi', 'true'),
    ('mv:city:kulhudhuffushi', 'false')],
 '17100': [('mv:island:gaafu-dhaalu:thinadhoo', 'true'),
    ('mv:city:thinadhoo', 'false')]}
for pc, legs in exp.items():
    got = sorted((l['area_source_id'], l['is_primary']) for l in links
                 if l['postcode'] == pc)
    check(f'multi-{pc}', got == sorted(legs), str(got))
# 05020 is genuinely shared: Postcodebase, 56ok, worldpostalcode
# all list BOTH Inguraidhoo and Vaadhoo under 05020. Tie: keep
# Inguraidhoo primary, never move on ties.
# --- live prefix scheme (UPU 2004 table stale, see header) ---
prefix = {
 'mv:atoll:haa-alif': '01', 'mv:atoll:haa-dhaalu': '02',
 'mv:atoll:shaviyani': '03', 'mv:atoll:noonu': '04',
 'mv:atoll:raa': '05', 'mv:atoll:baa': '06',
 'mv:atoll:lhaviyani': '07', 'mv:atoll:kaafu': '08',
 'mv:atoll:alif-alif': '09', 'mv:atoll:vaavu': '10',
 'mv:atoll:meemu': '11', 'mv:atoll:faafu': '12',
 'mv:atoll:dhaalu': '13', 'mv:atoll:thaa': '14',
 'mv:atoll:laamu': '15', 'mv:atoll:gaafu-alif': '16',
 'mv:atoll:gaafu-dhaalu': '17', 'mv:city:fuvahmulah': '18',
 'mv:city:addu': '19', 'mv:atoll:alif-dhaal': '00',
}
link1 = {}
for l in links:
    if l['is_primary'] == 'true':
        link1.setdefault(l['area_source_id'], []).append(l['postcode'])
badp = []
for sid, codes_ in link1.items():
    par = byid[sid]['parent_source_id']
    # Hulhumale carries the UPU 23000 enclave code.
    for pc in codes_:
        if sid == 'mv:island:hulhumale':
            if pc != '23000': badp.append((pc, sid))
        elif pc[:2] != prefix[par]:
            badp.append((pc, sid))
check('prefix-per-atoll', not badp, str(badp[:3]))
# --- every island primary code (Postcodebase-verified) ---
expect = {
 'mv:island:addu:feydhoo': ['19040'],
 'mv:island:addu:hithadhoo': ['19020'],
 'mv:island:addu:meedhoo': ['19010'],
 'mv:island:alif-alif:maalhos': ['09070'],
 'mv:island:alif-dhaal:dhiddhoo': ['00090'],
 'mv:island:alifushi': ['05010'],
 'mv:island:angolhitheemu': ['05040'],
 'mv:island:baa:goidhoo': ['06130'],
 'mv:island:baarah': ['01160'],
 'mv:island:bandidhoo': ['13020'],
 'mv:island:bileddhoo': ['12020'],
 'mv:island:bileffahi': ['03060'],
 'mv:island:bodufolhudhoo': ['09050'],
 'mv:island:burunee': ['14010'],
 'mv:island:dhaalu:meedhoo': ['13010'],
 'mv:island:dhaandhoo': ['16050'],
 'mv:island:dhanbidhoo': ['15020'],
 'mv:island:dhangethi': ['00060'],
 'mv:island:dharanboodhoo': ['12040'],
 'mv:island:dharavandhoo': ['06060'],
 'mv:island:dhevvadhoo': ['16060'],
 'mv:island:dhiddhoo': ['01100'],
 'mv:island:dhiffushi': ['08030'],
 'mv:island:dhiggaru': ['11080'],
 'mv:island:dhigurah': ['00070'],
 'mv:island:dhiyamingili': ['14040'],
 'mv:island:dhonfanu': ['06050'],
 'mv:island:eydhafushi': ['06080'],
 'mv:island:faafu:magoodhoo': ['12030'],
 'mv:island:fainu': ['05130'],
 'mv:island:fares-maathodaa': ['17080', '17090'],
 'mv:island:feeali': ['12010'],
 'mv:island:feevah': ['03050'],
 'mv:island:fehendhoo': ['06120'],
 'mv:island:felidhoo': ['10030'],
 'mv:island:fenfushi': ['00080'],
 'mv:island:feridhoo': ['09060'],
 'mv:island:fesdhoo': ['09110'],
 'mv:island:feydhoo': ['03040'],
 'mv:island:filladhoo': ['01110'],
 'mv:island:finey': ['02030'],
 'mv:island:fiyoaree': ['17070'],
 'mv:island:foakaidhoo': ['03070'],
 'mv:island:foddhoo': ['04120'],
 'mv:island:fonadhoo': ['15080'],
 'mv:island:fulhadhoo': ['06110'],
 'mv:island:fulidhoo': ['10010'],
 'mv:island:funadhoo': ['03150'],
 'mv:island:fuvahmulah': ['18011', '18012', '18013', '18014', '18015', '18016', '18017', '18018'],
 'mv:island:gaadhiffushi': ['14090'],
 'mv:island:gaafaru': ['08020'],
 'mv:island:gaafu-alif:maamendhoo': ['16030'],
 'mv:island:gaafu-alif:nilandhoo': ['16040'],
 'mv:island:gaafu-dhaalu:thinadhoo': ['17100'],
 'mv:island:gaafu-dhaalu:vaadhoo': ['17060'],
 'mv:island:gadhdhoo': ['17040'],
 'mv:island:gan': ['15061', '15062', '15063'],
 'mv:island:gemanafushi': ['16090'],
 'mv:island:goidhoo': ['03030'],
 'mv:island:gulhi': ['08070'],
 'mv:island:guraidhoo': ['08080'],
 'mv:island:haggnaameedhoo': ['00010'],
 'mv:island:hanimaadhoo': ['02020'],
 'mv:island:henbadhoo': ['04010'],
 'mv:island:himandhoo': ['09080'],
 'mv:island:himmafushi': ['08060'],
 'mv:island:hinnavaru': ['07010'],
 'mv:island:hirilandhoo': ['14080'],
 'mv:island:hirimaradhoo': ['02050'],
 'mv:island:hithaadhoo': ['06100'],
 'mv:island:hithadhoo': ['15110'],
 'mv:island:hoandeddhoo': ['17020'],
 'mv:island:hoarafushi': ['01060'],
 'mv:island:holhudhoo': ['04110'],
 'mv:island:hulhudheli': ['13040'],
 'mv:island:hulhudhoo': ['19060'],
 'mv:island:hulhudhuffaaru': ['05050'],
 'mv:island:hulhumale': ['23000'],
 'mv:island:huraa': ['08050'],
 'mv:island:ihavandhoo': ['01070'],
 'mv:island:inguraidhoo': ['05020'],
 'mv:island:innamaadhoo': ['05100'],
 'mv:island:isdhoo': ['15011', '15012'],
 'mv:island:kaashidhoo': ['08010'],
 'mv:island:kamadhoo': ['06020'],
 'mv:island:kanditheemu': ['03010'],
 'mv:island:kandoodhoo': ['14060'],
 'mv:island:kanduhulhudhoo': ['16100'],
 'mv:island:kelaa': ['01080'],
 'mv:island:kendhikulhudhoo': ['04020'],
 'mv:island:kendhoo': ['06030'],
 'mv:island:keyodhoo': ['10040'],
 'mv:island:kihaadhoo': ['06040'],
 'mv:island:kinbidhoo': ['14120'],
 'mv:island:kinolhas': ['05150'],
 'mv:island:kolamaafushi': ['16010'],
 'mv:island:kolhufushi': ['11070'],
 'mv:island:komandoo': ['03130'],
 'mv:island:kondey': ['16070'],
 'mv:island:kudafari': ['04040'],
 'mv:island:kudahuvadhoo': ['13080'],
 'mv:island:kudarikilu': ['06010'],
 'mv:island:kulhudhuffushi': ['02110'],
 'mv:island:kumundhoo': ['02120'],
 'mv:island:kunahandhoo': ['15120'],
 'mv:island:kunburudhoo': ['00030'],
 'mv:island:kurendhoo': ['07030'],
 'mv:island:kurinbi': ['02090'],
 'mv:island:landhoo': ['04050'],
 'mv:island:lhaimagu': ['03110'],
 'mv:island:lhohi': ['04070'],
 'mv:island:maabaidhoo': ['15030'],
 'mv:island:maaenboodhoo': ['13070'],
 'mv:island:maafaru': ['04060'],
 'mv:island:maafilaafushi': ['07050'],
 'mv:island:maafushi': ['08090'],
 'mv:island:maakurathu': ['05080'],
 'mv:island:maalhendhoo': ['04030'],
 'mv:island:maalhos': ['06070'],
 'mv:island:maamendhoo': ['15100'],
 'mv:island:maamingili': ['00100'],
 'mv:island:maarandhoo': ['01120'],
 'mv:island:maaungoodhoo': ['03140'],
 'mv:island:maavah': ['15071', '15072'],
 'mv:island:madaveli': ['17010'],
 'mv:island:madifushi': ['14030'],
 'mv:island:maduvvaree': ['05110'],
 'mv:island:magoodhoo': ['04090'],
 'mv:island:mahibadhoo': ['00040'],
 'mv:island:makunudhoo': ['02160'],
 'mv:island:manadhoo': ['04100'],
 'mv:island:mandhoo': ['00050'],
 'mv:island:maradhoo': ['19030'],
 'mv:island:maradhoo-feydhoo': ['19050'],
 'mv:island:maroshi': ['03100'],
 'mv:island:mathiveri': ['09040'],
 'mv:island:meedhoo': ['05140'],
 'mv:island:meemu:maduvvaree': ['11090'],
 'mv:island:miladhoo': ['04080'],
 'mv:island:milandhoo': ['03160'],
 'mv:island:mulak': ['11040'],
 'mv:island:mulhadhoo': ['01050'],
 'mv:island:muli': ['11050'],
 'mv:island:mundoo': ['15042'],
 'mv:island:muraidhoo': ['01150'],
 'mv:island:naalaafushi': ['11060'],
 'mv:island:nadellaa': ['17030'],
 'mv:island:naifaru': ['07020'],
 'mv:island:naivaadhoo': ['02040'],
 'mv:island:narudhoo': ['03080'],
 'mv:island:nellaidhoo': ['02070'],
 'mv:island:neykurendhoo': ['02130'],
 'mv:island:nilandhoo': ['12050'],
 'mv:island:nolhivaram': ['02080'],
 'mv:island:nolhivaranfaru': ['02060'],
 'mv:island:noomaraa': ['03020'],
 'mv:island:olhuvelifushi': ['07040'],
 'mv:island:omadhoo': ['00020'],
 'mv:island:raimmandhoo': ['11010'],
 'mv:island:rakeedhoo': ['10050'],
 'mv:island:rasdhoo': ['09020'],
 'mv:island:rasgetheemu': ['05030'],
 'mv:island:rasmaadhoo': ['05090'],
 'mv:island:rathafandhoo': ['17050'],
 'mv:island:rinbudhoo': ['13030'],
 'mv:island:thaa:guraidhoo': ['14050'],
 'mv:island:thaa:omadhoo': ['14130'],
 'mv:island:thakandhoo': ['01130'],
 'mv:island:thimarafushi': ['14100'],
 'mv:island:thinadhoo': ['10020'],
 'mv:island:thoddoo': ['09010'],
 'mv:island:thulhaadhoo': ['06090'],
 'mv:island:thulusdhoo': ['08040'],
 'mv:island:thuraakunu': ['01010'],
 'mv:island:ukulhas': ['09030'],
 'mv:island:uligamu': ['01020'],
 'mv:island:ungoofaaru': ['05060'],
 'mv:island:utheemu': ['01140'],
 'mv:island:vaanee': ['13060'],
 'mv:island:vaikaradhoo': ['02140'],
 'mv:island:vandhoo': ['14070'],
 'mv:island:vashafaru': ['01090'],
 'mv:island:velidhoo': ['04130'],
 'mv:island:veymandoo': ['14110'],
 'mv:island:veyvah': ['11030'],
 'mv:island:villingili': ['16020'],
 'mv:island:vilufushi': ['14020'],
}
for sid, want in expect.items():
    got = sorted(link1.get(sid, []))
    check(f"code-{sid.split(':')[-1]}", got == sorted(want), str(got))
# --- codeless by design / absent everywhere ---
# Male + Villimale: street-range codes (20XXX/21XXX) excluded by
# design. Ookolhufinolhu: resort island. Dhuvaafaru: inhabited
# but codeless in Postcodebase, 56ok, worldpostalcode alike
# (their Kadhonlhudhoo 05070 is the old uninhabited island, not
# the resettlement island); needs Maldives Post confirmation.
for sid in ['mv:island:male', 'mv:island:villimale',
            'mv:island:ookolhufinolhu', 'mv:island:dhuvaafaru']:
    check(f'codeless-{sid.split(":")[-1]}',
          not [l for l in links if l['area_source_id'] == sid])
# Fares-Maathodaa carries both halves (Fares 17090 + Maathodaa
# 17080); Nominatim/OSM treats the pair as one place.
check('fares-maathodaa-dual',
      sorted(link1.get('mv:island:fares-maathodaa', [])) == ['17080', '17090'])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
