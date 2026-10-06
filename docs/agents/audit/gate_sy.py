import csv, os, sys
# Syria gate. No postcode system. M4 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_sy.py
A = './packages/addressing/resources/geography/syria-address-areas.csv'
C = './packages/addressing/resources/geography/syria-postal-codes.csv'
L = './packages/addressing/resources/geography/syria-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-80', len(rows) == 80, str(len(rows)))
check('provinces-14', sum(1 for r in rows if r['type'] == 'province') == 14)
check('districts-66', sum(1 for r in rows if r['type'] == 'district') == 66)
# ISO 3166-2:SY codes (bundled English names match the ISO en
# reference names; DI/RD/HL use Damascus/Rif Dimashq/Aleppo).
iso = {'sy:province:al-hasakah': ('Al-Hasakah', 'HA'),
 'sy:province:al-raqqah': ('Al-Raqqah', 'RA'),
 'sy:province:aleppo': ('Aleppo', 'HL'),
 'sy:province:as-suwayda': ('As-Suwayda', 'SU'),
 'sy:province:damascus': ('Damascus', 'DI'),
 'sy:province:daraa': ('Daraa', 'DR'),
 'sy:province:deir-ez-zor': ('Deir ez-Zor', 'DY'),
 'sy:province:hama': ('Hama', 'HM'),
 'sy:province:homs': ('Homs', 'HI'),
 'sy:province:idlib': ('Idlib', 'ID'),
 'sy:province:latakia': ('Latakia', 'LA'),
 'sy:province:quneitra': ('Quneitra', 'QU'),
 'sy:province:rif-dimashq': ('Rif Dimashq', 'RD'),
 'sy:province:tartus': ('Tartus', 'TA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full province -> districts table per Districts-of-Syria oracle.
# Note: the oracle intro still says "65 districts" but its own
# per-governorate lists total 66 (Taldou, created 2010 under Homs,
# never added to the intro count).
table = {'sy:province:hama': ['Hama', 'Masyaf', 'Mahardah',
    'Salamiyah', 'Al-Suqaylabiyah'],
 'sy:province:homs': ['Homs', 'Al-Mukharram', 'Al-Qusayr',
    'Ar-Rastan', 'Palmyra', 'Taldou', 'Talkalakh'],
 'sy:province:latakia': ['Latakia', 'Al-Haffah', 'Jableh',
    'Qardaha'],
 'sy:province:tartus': ['Tartus', 'Baniyas', 'Duraykish',
    'Safita', 'Al-Shaykh Badr'],
 'sy:province:aleppo': ['Mount Simeon', 'Afrin', 'Atarib',
    'Ayn al-Arab', 'Azaz', 'Al-Bab', 'Dayr Hafir', 'Jarabulus',
    'Manbij', 'Safirah'],
 'sy:province:deir-ez-zor': ['Deir ez-Zor', 'Abu Kamal',
    'Mayadin'],
 'sy:province:al-hasakah': ['Al-Hasakah', 'Al-Malikiyah',
    'Qamishli', "Ra's al-'Ayn", 'Al-Shaddadah'],
 'sy:province:idlib': ['Idlib', 'Arihah', 'Harem',
    'Jisr al-Shughur', "Ma'arrat al-Numan"],
 'sy:province:al-raqqah': ['Raqqa', 'Tell Abyad', 'Tabqa'],
 'sy:province:damascus': ['Damascus'],
 'sy:province:daraa': ['Daraa', 'Izra', 'Al-Sanamayn'],
 'sy:province:quneitra': ['Quneitra', 'Fiq'],
 'sy:province:rif-dimashq': ['Markaz Rif Dimashq', 'Darayya',
    'Douma', 'An-Nabek', 'Qatana', 'Qudsaya', 'Al-Qutayfah',
    'Al-Tall', 'Yabroud', 'Al-Zabadani'],
 'sy:province:as-suwayda': ['Suwayda', 'Salkhad', 'Shahba']}
ok = True
for prov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == prov)
    if have != sorted(names):
        print('FAIL province', prov, have); fails.append(f'province {prov}'); ok = False
if ok: print('PASS all 14 province mappings (66 districts)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU syrEn profile (09/2004) says the postcode
# system is still developing; Sep-2025 UPU list carries Syria on
# do-not-require; GeoNames has no SY postal dump (404); List of
# postal codes reads "no codes ... Status unknown".
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
