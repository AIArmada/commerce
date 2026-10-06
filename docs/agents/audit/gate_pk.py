import csv, re, sys
from collections import Counter
# Pakistan gate. M6 revisit: tree verified clean against ISO 3166-2:PK
# (BA/GB/IS/JK/KP/PB/SD) + Districts-of-Pakistan lists incl the May 2026
# Balochistan batch (Barshore/Tump/Taftan/Wadh/Upper Dera Bugti/Quetta
# East+West) and the 2022 splits (Murree/Talagang/Taunsa/Kot Addu/
# Wazirabad; Paharpur; Upper/Lower South Waziristan; Central Dir;
# Upper Swat; Darel/Tangir/Roundu/Gupis-Yasin);
# postal verified clean, no fixes: 3114 codes / 3121 links, code set
# identical to the live Pakistan Post postcodes table, 7 directory-
# attested NPO/collision dual-links kept as-is. Run from repo root:
# python3 docs/agents/audit/gate_pk.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/pakistan-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/pakistan-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/pakistan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 4 provinces + 3 territories + 178 districts ---
check('areas-185', len(rows) == 185, str(len(rows)))
check('provinces-4', sum(1 for r in rows if r['type'] == 'province') == 4)
check('territories-3', sum(1 for r in rows if r['type'] == 'territory') == 3)
check('districts-178', sum(1 for r in rows if r['type'] == 'district') == 178)
l1 = {
 'pk:province:balochistan': ('province', 'Balochistan', 'BA'),
 'pk:territory:gilgit-baltistan': ('territory', 'Gilgit-Baltistan', 'GB'),
 'pk:territory:islamabad': ('territory', 'Islamabad', 'IS'),
 'pk:territory:azad-jammu-and-kashmir': ('territory', 'Azad Jammu and Kashmir', 'JK'),
 'pk:province:khyber-pakhtunkhwa': ('province', 'Khyber Pakhtunkhwa', 'KP'),
 'pk:province:punjab': ('province', 'Punjab', 'PB'),
 'pk:province:sindh': ('province', 'Sindh', 'SD'),
}
for sid, (typ, name, iso) in l1.items():
    got = [r for r in rows if r['source_id'] == sid]
    check(f'l1-{iso}', len(got) == 1 and got[0]['type'] == typ
          and got[0]['name'] == name and got[0]['code'] == iso
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- per-parent district counts (Balochistan 42 incl the May 2026
# batch; Punjab 41 incl the 5 2022 splits; KP 40; Sindh 30) ---
exp_parent = {
 'pk:province:balochistan': 42,
 'pk:province:khyber-pakhtunkhwa': 40,
 'pk:province:punjab': 41,
 'pk:province:sindh': 30,
 'pk:territory:azad-jammu-and-kashmir': 10,
 'pk:territory:gilgit-baltistan': 14,
 'pk:territory:islamabad': 1,
}
have_parent = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
wrong = [sid for sid, n in exp_parent.items() if have_parent.get(sid, 0) != n]
check('per-parent-counts-7', not wrong, str(wrong))
# --- postal: 3114 codes / 3121 links ---
check('codes-3114', len(codes) == 3114, str(len(codes)))
check('links-3121', len(links) == 3121, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 3114 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-7', multis == ['02502', '06011', '06536', '07418', '07514', '07529', '24302'], str(multis))
# Full multi map (M6-adjudicated, all keeps: 06011 + 07418 double in
# the PART-II NPO directory; 02502/06536/07514/07529/24302 double on
# the live postcodes table, the second office circular- or page-
# attested. Primaries follow the directory-listed/first office.)
exp = {
 '02502': [('pk:district:mirpur', 'true'), ('pk:district:peshawar', 'false')],
 '06011': [('pk:district:multan', 'true'), ('pk:district:vehari', 'false')],
 '06536': [('pk:district:khairpur', 'true'), ('pk:district:sukkur', 'false')],
 '07418': [('pk:district:karachi-south', 'true'), ('pk:district:malir', 'false')],
 '07514': [('pk:district:keamari', 'true'), ('pk:district:korangi', 'false')],
 '07529': [('pk:district:karachi-east', 'false'), ('pk:district:malir', 'true')],
 '24302': [('pk:district:nowshera', 'true'), ('pk:district:peshawar', 'false')],
}
got = {}
for l in links:
    if l['postcode'] in exp:
        got.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
ok = all(sorted(got.get(pc, [])) == sorted(v) for pc, v in exp.items())
check('multi-map-7', ok, str({k: got.get(k) for k in exp if sorted(got.get(k, [])) != sorted(exp[k])}))
# GPO-headquarter + UPU-profile anchors (UPU PAK: 5-digit, ISLAMABAD 44000).
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
check('pin-44000-islamabad', pin.get('44000') == 'pk:district:islamabad')
check('pin-54000-lahore', pin.get('54000') == 'pk:district:lahore')
check('pin-25000-peshawar', pin.get('25000') == 'pk:district:peshawar')
check('pin-87300-quetta-east', pin.get('87300') == 'pk:district:quetta-east')
check('pin-75600-karachi-south', pin.get('75600') == 'pk:district:karachi-south')
check('pin-03816-faisalabad-npo', pin.get('03816') == 'pk:district:faisalabad')
check('pin-80510-jafarabad-circ', pin.get('80510') == 'pk:district:jafarabad')
check('pin-16810-shigar', pin.get('16810') == 'pk:district:shigar')
# Per-district primary counts (167 covered; 11 codeless: allai,
# darel, haveli, kolai-palas, lower-south-waziristan, mohmand,
# roundu, sohbatpur, surab, upper-dera-bugti, wadh).
exp_counts = {
 'pk:district:abbottabad': 58,
 'pk:district:allai': 0,
 'pk:district:astore': 3,
 'pk:district:attock': 81,
 'pk:district:awaran': 1,
 'pk:district:badin': 12,
 'pk:district:bagh': 16,
 'pk:district:bahawalnagar': 24,
 'pk:district:bahawalpur': 39,
 'pk:district:bajaur': 1,
 'pk:district:bannu': 30,
 'pk:district:barkhan': 3,
 'pk:district:barshore': 1,
 'pk:district:battagram': 12,
 'pk:district:bhakkar': 18,
 'pk:district:bhimber': 15,
 'pk:district:buner': 5,
 'pk:district:central-dir': 1,
 'pk:district:chagai': 6,
 'pk:district:chakwal': 76,
 'pk:district:chaman': 2,
 'pk:district:charsadda': 19,
 'pk:district:chiniot': 9,
 'pk:district:dadu': 13,
 'pk:district:darel': 0,
 'pk:district:dera-bugti': 1,
 'pk:district:dera-ghazi-khan': 19,
 'pk:district:dera-ismail-khan': 41,
 'pk:district:diamer': 2,
 'pk:district:duki': 1,
 'pk:district:faisalabad': 92,
 'pk:district:ghanche': 5,
 'pk:district:ghizer': 2,
 'pk:district:ghotki': 13,
 'pk:district:gilgit': 10,
 'pk:district:gujranwala': 48,
 'pk:district:gujrat': 80,
 'pk:district:gupis-yasin': 1,
 'pk:district:gwadar': 6,
 'pk:district:hafizabad': 7,
 'pk:district:hangu': 9,
 'pk:district:haripur': 28,
 'pk:district:harnai': 3,
 'pk:district:hattian-bala': 4,
 'pk:district:haveli': 0,
 'pk:district:hub': 4,
 'pk:district:hunza': 6,
 'pk:district:hyderabad': 32,
 'pk:district:islamabad': 70,
 'pk:district:jacobabad': 11,
 'pk:district:jafarabad': 4,
 'pk:district:jamshoro': 15,
 'pk:district:jhal-magsi': 3,
 'pk:district:jhang': 26,
 'pk:district:jhelum': 71,
 'pk:district:kacchi': 10,
 'pk:district:kalat': 6,
 'pk:district:karachi-central': 42,
 'pk:district:karachi-east': 29,
 'pk:district:karachi-south': 47,
 'pk:district:karachi-west': 9,
 'pk:district:karak': 17,
 'pk:district:kashmore': 10,
 'pk:district:kasur': 23,
 'pk:district:keamari': 23,
 'pk:district:kech': 9,
 'pk:district:khairpur': 23,
 'pk:district:khanewal': 24,
 'pk:district:kharan': 2,
 'pk:district:kharmang': 3,
 'pk:district:khushab': 32,
 'pk:district:khuzdar': 15,
 'pk:district:khyber': 3,
 'pk:district:killa-saifullah': 4,
 'pk:district:kohat': 31,
 'pk:district:kohlu': 2,
 'pk:district:kolai-palas': 0,
 'pk:district:korangi': 18,
 'pk:district:kot-addu': 11,
 'pk:district:kotli': 19,
 'pk:district:kurram': 3,
 'pk:district:lahore': 163,
 'pk:district:lakki-marwat': 21,
 'pk:district:larkana': 26,
 'pk:district:lasbela': 3,
 'pk:district:layyah': 14,
 'pk:district:lodhran': 9,
 'pk:district:loralai': 11,
 'pk:district:lower-chitral': 11,
 'pk:district:lower-dir': 9,
 'pk:district:lower-kohistan': 2,
 'pk:district:lower-south-waziristan': 0,
 'pk:district:malakand': 5,
 'pk:district:malir': 44,
 'pk:district:mandi-bahauddin': 26,
 'pk:district:mansehra': 41,
 'pk:district:mardan': 41,
 'pk:district:mastung': 7,
 'pk:district:matiari': 8,
 'pk:district:mianwali': 37,
 'pk:district:mirpur': 30,
 'pk:district:mirpur-khas': 12,
 'pk:district:mohmand': 0,
 'pk:district:multan': 39,
 'pk:district:murree': 35,
 'pk:district:musakhel': 2,
 'pk:district:muzaffarabad': 11,
 'pk:district:muzaffargarh': 22,
 'pk:district:nagar': 3,
 'pk:district:nankana-sahib': 14,
 'pk:district:narowal': 16,
 'pk:district:nasirabad': 8,
 'pk:district:naushahro-feroze': 24,
 'pk:district:neelam-valley': 5,
 'pk:district:north-waziristan': 7,
 'pk:district:nowshera': 39,
 'pk:district:nushki': 3,
 'pk:district:okara': 29,
 'pk:district:orakzai': 1,
 'pk:district:paharpur': 2,
 'pk:district:pakpattan': 12,
 'pk:district:panjgur': 4,
 'pk:district:peshawar': 43,
 'pk:district:pishin': 6,
 'pk:district:poonch': 24,
 'pk:district:qambar-shahdadkot': 12,
 'pk:district:qila-abdullah': 7,
 'pk:district:quetta-east': 33,
 'pk:district:quetta-west': 8,
 'pk:district:rahim-yar-khan': 32,
 'pk:district:rajanpur': 9,
 'pk:district:rawalpindi': 151,
 'pk:district:roundu': 0,
 'pk:district:sahiwal': 33,
 'pk:district:sanghar': 25,
 'pk:district:sargodha': 72,
 'pk:district:shaheed-benazirabad': 22,
 'pk:district:shangla': 4,
 'pk:district:sheikhupura': 19,
 'pk:district:sherani': 4,
 'pk:district:shigar': 1,
 'pk:district:shikarpur': 10,
 'pk:district:sialkot': 60,
 'pk:district:sibi': 3,
 'pk:district:skardu': 10,
 'pk:district:sohbatpur': 0,
 'pk:district:sudhanoti': 12,
 'pk:district:sujawal': 6,
 'pk:district:sukkur': 27,
 'pk:district:surab': 0,
 'pk:district:swabi': 25,
 'pk:district:swat': 16,
 'pk:district:taftan': 1,
 'pk:district:talagang': 33,
 'pk:district:tando-allahyar': 7,
 'pk:district:tando-muhammad-khan': 2,
 'pk:district:tangir': 1,
 'pk:district:tank': 8,
 'pk:district:taunsa': 11,
 'pk:district:tharparkar': 8,
 'pk:district:thatta': 11,
 'pk:district:toba-tek-singh': 25,
 'pk:district:torghar': 1,
 'pk:district:tump': 1,
 'pk:district:umerkot': 6,
 'pk:district:upper-chitral': 8,
 'pk:district:upper-dera-bugti': 0,
 'pk:district:upper-dir': 4,
 'pk:district:upper-kohistan': 1,
 'pk:district:upper-south-waziristan': 3,
 'pk:district:upper-swat': 7,
 'pk:district:usta-muhammad': 1,
 'pk:district:vehari': 18,
 'pk:district:wadh': 0,
 'pk:district:washuk': 3,
 'pk:district:wazirabad': 7,
 'pk:district:zhob': 1,
 'pk:district:ziarat': 2,
}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-district-counts-178', not wrong, str(wrong[:5]))
check('covered-167', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 167)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
