import csv, re, sys
from collections import Counter
# Afghanistan gate. M7 revisit: 12 link moves + 2 fills (1406->1408
# codes/links, 398->401 districts covered, zero codeless). Live
# re-pull of the Afghan Post finder GeoJSON (/client_postal_code,
# 1408 zones) + COD-AB v03 polygons (401 districts): every zone
# centroid PIP-verified; the 6 build "resolved by location" links
# were Nominatim/OSM-boundary errors (OSM extends Qala-e-Naw 100km+
# into Ghor, Kiti 25km+ into Baghran, Anar Dara 40km+ into Shindand).
# Overlay errata fixed: second drop is 396701 Bahramcha (not 396601
# Babajee, which ships to Nahr-e-Saraj); both drops were COD-AB
# districts and are now filled. Run from repo root:
# python3 /tmp/geo-verify/M7/AF/gate_af.py
REPO = '/Users/saiffil/Herd/commerce'
A = REPO + '/packages/addressing/resources/geography/afghanistan-address-areas.csv'
C = REPO + '/packages/addressing/resources/geography/afghanistan-postal-codes.csv'
L = REPO + '/packages/addressing/resources/geography/afghanistan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 34 provinces + 401 COD-AB v03 districts ---
check('areas-435', len(rows) == 435, str(len(rows)))
check('provinces-34', sum(1 for r in rows if r['type'] == 'province') == 34)
check('districts-401', sum(1 for r in rows if r['type'] == 'district') == 401)
iso = {'af:province:badakhshan': ('Badakhshan', 'BDS'),
 'af:province:badghis': ('Badghis', 'BDG'),
 'af:province:baghlan': ('Baghlan', 'BGL'),
 'af:province:balkh': ('Balkh', 'BAL'),
 'af:province:bamyan': ('Bamyan', 'BAM'),
 'af:province:daykundi': ('Daykundi', 'DAY'),
 'af:province:farah': ('Farah', 'FRA'),
 'af:province:faryab': ('Faryab', 'FYB'),
 'af:province:ghazni': ('Ghazni', 'GHA'),
 'af:province:ghor': ('Ghor', 'GHO'),
 'af:province:helmand': ('Helmand', 'HEL'),
 'af:province:herat': ('Herat', 'HER'),
 'af:province:jowzjan': ('Jowzjan', 'JOW'),
 'af:province:kabul': ('Kabul', 'KAB'),
 'af:province:kandahar': ('Kandahar', 'KAN'),
 'af:province:kapisa': ('Kapisa', 'KAP'),
 'af:province:khost': ('Khost', 'KHO'),
 'af:province:kunar': ('Kunar', 'KNR'),
 'af:province:kunduz': ('Kunduz', 'KDZ'),
 'af:province:laghman': ('Laghman', 'LAG'),
 'af:province:logar': ('Logar', 'LOG'),
 'af:province:nangarhar': ('Nangarhar', 'NAN'),
 'af:province:nimruz': ('Nimruz', 'NIM'),
 'af:province:nuristan': ('Nuristan', 'NUR'),
 'af:province:paktia': ('Paktia', 'PIA'),
 'af:province:paktika': ('Paktika', 'PKA'),
 'af:province:panjshir': ('Panjshir', 'PAN'),
 'af:province:parwan': ('Parwan', 'PAR'),
 'af:province:samangan': ('Samangan', 'SAM'),
 'af:province:sar-e-pol': ('Sar-e Pol', 'SAR'),
 'af:province:takhar': ('Takhar', 'TAK'),
 'af:province:uruzgan': ('Uruzgan', 'URU'),
 'af:province:wardak': ('Wardak', 'WAR'),
 'af:province:zabul': ('Zabul', 'ZAB')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Deliberate display deviations (ISO BGN/PCGN in parens): Uruzgan
# (Urozgan, matches list page + COD-AB), Wardak (Maidan Wardak),
# Sar-e Pol (Sar-e Pul), Ghor (Ghor), Helmand (Hilmand),
# Herat (Hirat), Paktia (Paktya), Jowzjan (Jawzjan),
# Panjshir (Panjsher). COD-AB v03 spellings kept at L2 byte-exact.
check('uruzgan-deviation', byid['af:province:uruzgan']['name'] == 'Uruzgan')
check('wardak-deviation', byid['af:province:wardak']['name'] == 'Wardak')
check('sarepol-deviation', byid['af:province:sar-e-pol']['name'] == 'Sar-e Pol')
table = {
 'af:province:badakhshan': ['Arghanj Khwah', 'Argo', 'Baharak', 'Darayem', 'Darwaz-e-Balla', 'Darwaz-e-Payin', 'Eshkashem', 'Fayzabad', 'Jorm', 'Keshem', 'Khash', 'Khwahan', 'Kofab', 'Kohistan', 'Koran Wa Monjan', 'Raghestan', 'Shahr-e-Buzorg', 'Shaki', 'Shighnan', 'Shuhada', 'Tagab', 'Teshkan', 'Wakhan', 'Warduj', 'Yaftal-e-Sufla', 'Yamgan', 'Yawan', 'Zebak'],
 'af:province:badghis': ['Ab Kamari', 'Bala Murghab', 'Ghormach', 'Jawand', 'Muqur', 'Qadis', 'Qala-e-Naw'],
 'af:province:baghlan': ['Andarab', 'Baghlan-e-Jadid', 'Burka', 'Dahana-e-Ghori', 'Deh Salah', 'Doshi', 'Fereng Wa Gharu', 'Guzargah-e-Nur', 'Khinjan', 'Khost Wa Fereng', 'Khwaja Hejran', 'Nahrin', 'Pul-e-Hisar', 'Pul-e-Khumri', 'Tala Wa Barfak'],
 'af:province:balkh': ['Balkh', 'Char Bolak', 'Charkent', 'Chemtal', 'Dawlat Abad', 'Dehdadi', 'Kaldar', 'Keshendeh', 'Marmul', 'Mazar-e-Sharif', 'Nahr-e-Shahi', 'Sharak-e-Hayratan', 'Sholgareh', 'Shortepa', 'Zari'],
 'af:province:bamyan': ['Bamyan', 'Kahmard', 'Panjab', 'Sayghan', 'Shibar', 'Waras', 'Yakawlang'],
 'af:province:daykundi': ['Ashtarlay', 'Kajran', 'Khadir', 'Kiti', 'Miramor', 'Nili', 'Patoo', 'Sang-e-Takht', 'Shahrestan'],
 'af:province:farah': ['Anar Dara', 'Bakwa', 'Bala Buluk', 'Farah', 'Gulistan', 'Khak-e-Safed', 'Lash-e-Juwayn', 'Pur Chaman', 'Pushtrod', 'Qala-e-Kah', 'Shibkoh'],
 'af:province:faryab': ['Almar', 'Andkhoy', 'Bilcheragh', 'Dawlat Abad', 'Garzewan', 'Khan-e-Char Bagh', 'Khwaja Sabz Posh', 'Kohistan', 'Maymana', 'Pashtun Kot', 'Qaram Qul', 'Qaysar', 'Qurghan', 'Shirin Tagab'],
 'af:province:ghazni': ['Ab Band', 'Ajristan', 'Andar', 'Deh Yak', 'Gelan', 'Ghazni', 'Giro', 'Jaghatu', 'Jaghuri', 'Khwaja Umari', 'Malistan', 'Muqur', 'Nawa', 'Nawur', 'Qara Bagh', 'Rashidan', 'Waghaz', 'Wal-e-Muhammad-e-Shahid', 'Zanakhan'],
 'af:province:ghor': ['Charsadra', 'Dawlatyar', 'DoLayna', 'Feroz Koh', 'Lal Wa Sarjangal', 'Pasaband', 'Saghar', 'Shahrak', 'Taywarah', 'Tolak'],
 'af:province:helmand': ['Baghran', 'Deh-e-Shu', 'Garmser', 'Kajaki', 'Lashkargah', 'Musa Qala', 'Nad-e-Ali', 'Nahr-e-Saraj', 'Nawa-e-Barakzaiy', 'Nawzad', 'Reg-i-Khan Nishin', 'Sangin', 'Washer'],
 'af:province:herat': ['Adraskan', 'Chisht-e-Sharif', 'Farsi', 'Ghoryan', 'Gulran', 'Guzara', 'Hirat', 'Injil', 'Karukh', 'Kohsan', 'Kushk', 'Kushk-e-Kuhna', 'Obe', 'Pashtun Zarghun', 'Shindand', 'Zindajan'],
 'af:province:jowzjan': ['Aqcha', 'Darzab', 'Fayzabad', 'Khamyab', 'Khanaqa', 'Khwaja Dukoh', 'Mardyan', 'Mingajik', 'Qarqin', 'Qush Tepa', 'Shiberghan'],
 'af:province:kabul': ['Bagrami', 'Chahar Asyab', 'Deh Sabz', 'Estalef', 'Farza', 'Guldara', 'Kabul', 'Kalakan', 'Khak-e-Jabbar', 'Mir Bacha Kot', 'Musahi', 'Paghman', 'Qara Bagh', 'Shakar Dara', 'Surobi'],
 'af:province:kandahar': ['Arghandab', 'Arghestan', 'Daman', 'Ghorak', 'Kandahar', 'Khakrez', 'Maruf', 'Maywand', 'Miyanshin', 'Nesh', 'Panjwayi', 'Reg', 'Shah Wali Kot', 'Shorabak', 'Spin Boldak', 'Zheray'],
 'af:province:kapisa': ['Alasay', 'Hisa-e-Awal-e-Kohistan', 'Hisa-e-Duwum-e-Kohistan', 'Koh Band', 'Mahmood-e-Raqi', 'Nijrab', 'Tagab'],
 'af:province:khost': ['Bak', 'Gurbuz', 'Jaji Maydan', 'Mandozayi', 'Matun', 'Musa Khel', 'Nadir Shah Kot', 'Qalandar', 'Sabari', 'Shamal', 'Spera', 'Tani', 'Terezayi'],
 'af:province:kunar': ['Asad Abad', 'Bar Kunar', 'Chapa Dara', 'Chawkay', 'Dangam', 'Dara-e-Pech', 'Ghazi Abad', 'Khas Kunar', 'Marawara', 'Narang', 'Nari', 'Nurgal', 'Sar Kani', 'Shigal', 'Watapur'],
 'af:province:kunduz': ['Ali Abad', 'Chahar Darah', 'Dasht-e-Archi', 'Imam Sahib', 'Khan Abad', 'Kunduz', 'Qala-e-Zal'],
 'af:province:laghman': ['Alingar', 'Alishang', 'Dawlatshah', 'Mehtarlam', 'Qarghayi'],
 'af:province:logar': ['Azra', 'Baraki Barak', 'Charkh', 'Kharwar', 'Khoshi', 'Mohammad Agha', 'Pul-e-Alam'],
 'af:province:nangarhar': ['Achin', 'Bati Kot', 'Behsud', 'Chaparhar', 'Dara-e-Nur', 'Deh Bala', 'Dur Baba', 'Goshta', 'Hesarak', 'Jalalabad', 'Kama', 'Khogyani', 'Kot', 'Kuz Kunar', 'Lalpur', 'Muhmand Dara', 'Nazyan', 'Pachir Wa Agam', 'Rodat', 'Sherzad', 'Shinwar', 'Surkh Rod'],
 'af:province:nimruz': ['Chakhansur', 'Char Burjak', 'Kang', 'Khashrod', 'Zaranj'],
 'af:province:nuristan': ['Barg-e-Matal', 'Duab', 'Kamdesh', 'Mandol', 'Nurgaram', 'Parun', 'Wama', 'Waygal'],
 'af:province:paktia': ['Ahmadaba', 'Chamkani', 'Dand Wa Patan', 'Gardez', 'Jaji', 'Jani Khel', 'Lija Ahmad Khel', 'Sayed Karam', 'Shawak', 'Zadran', 'Zurmat'],
 'af:province:paktika': ['Barmal', 'Dila', 'Giyan', 'Gomal', 'Jani Khel', 'Mata Khan', 'Nika', 'Omna', 'Sar Rawzah', 'Sharan', 'Surobi', 'Turwo', 'Urgun', 'Wazakhah', 'Wormamay', 'Yahya Khel', 'Yosuf Khel', 'Zarghun Shahr', 'Ziruk'],
 'af:province:panjshir': ['Anawa', 'Bazarak', 'Dara', 'Khenj', 'Paryan', 'Rukha', 'Shutul'],
 'af:province:parwan': ['Bagram', 'Charikar', 'Ghorband', 'Jabal Saraj', 'Koh-e-Safi', 'Salang', 'Sayed Khel', 'Shekh Ali', 'Shinwari', 'Surkh-e-Parsa'],
 'af:province:samangan': ['Aybak', 'Dara-e-Suf-e-Bala', 'Dara-e-Suf-e-Payin', 'Feroz Nakhchir', 'Hazrat-e-Sultan', 'Khulm', 'Khuram Wa Sarbagh', 'Ruy-e-Duab'],
 'af:province:sar-e-pol': ['Balkhab', 'Gosfandi', 'Kohestanat', 'Sancharak', 'Sar-e-Pul', 'Sayad', 'Sozmaqala'],
 'af:province:takhar': ['Baharak', 'Bangi', 'Chahab', 'Chal', 'Darqad', 'Dasht-e-Qala', 'Eshkmesh', 'Farkhar', 'Hazar Sumuch', 'Kalafgan', 'Khwaja Bahawuddin', 'Khwaja Ghar', 'Namak Ab', 'Rostaq', 'Taloqan', 'Warsaj', 'Yangi Qala'],
 'af:province:uruzgan': ['Chinarto', 'Chora', 'Dehrawud', 'Gizab', 'Khas Uruzgan', 'Shahid-e-Hassas', 'Tirinkot'],
 'af:province:wardak': ['Chak-e-Wardak', 'Daymirdad', 'Hesa-e-Awal-e-Behsud', 'Jaghatu', 'Jalrez', 'Markaz-e-Behsud', 'Maydan Shahr', 'Nerkh', 'Saydabad'],
 'af:province:zabul': ['Arghandab', 'Atghar', 'Daychopan', 'Kakar', 'Mizan', 'Nawbahar', 'Qalat', 'Shah Joi', 'Shamul Zayi', 'Shinkay', 'Tarnak Wa Jaldak'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 34 parent mappings (401 districts)')
# COD-AB pcode pins: Kabul city AF0101, Jaghatu under Ghazni.
check('kabul-pcode', byid['af:district:kabul']['code'] == 'AF0101')
check('jaghatu-parent',
      byid['af:district:ghazni:jaghatu']['parent_source_id'] == 'af:province:ghazni')
# List-page deltas are COD-AB truth, recorded not fixed: Ghor has no
# Murghab, Helmand folds Marja into Nad-e-Ali, Kunduz folds
# Aqtash/Gul Tepa/Kalbad, Laghman folds Baad Pakh, Paktia folds
# Mirzaka/Rohani Baba/Gerda Serai, Panjshir folds Abshar, Zabul
# renames Khak-e-Afghan to Kakar, Kandahar folds Dand, Herat has no
# Islam Qala/Turghandi rows, Farah has no Pusht-e-Koh row.
check('no-ghor-murghab', not [r for r in rows
      if r['parent_source_id'] == 'af:province:ghor' and 'Murghab' in r['name']])
check('no-dand-district', 'af:district:dand' not in byid)
check('kakar-not-khakeafghan', 'af:district:kakar' in byid
      and 'af:district:khake-afghan' not in byid)
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 1408 codes / 1408 links, all L2 singletons ---
check('codes-1408', len(codes) == 1408, str(len(codes)))
check('links-1408', len(links) == 1408, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 1408 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('zero-multis', not multis, str(multis[:3]))
check('all-l2', all(l['area_source_id'].split(':')[1] == 'district'
      for l in links))
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
check('served-by', all(l['relationship_type'] == 'served_by' for l in links))
# PP block map (UPU 07/2025: PP 10-43, city DD 01-50, rural DD 51-99).
PP = {'10': 'kabul', '11': 'parwan', '12': 'kapisa', '13': 'wardak',
 '14': 'logar', '15': 'panjshir', '16': 'bamyan', '17': 'balkh',
 '18': 'faryab', '19': 'jowzjan', '20': 'samangan', '21': 'sar-e-pol',
 '22': 'paktia', '23': 'ghazni', '24': 'paktika', '25': 'khost',
 '26': 'nangarhar', '27': 'laghman', '28': 'kunar', '29': 'nuristan',
 '30': 'herat', '31': 'farah', '32': 'ghor', '33': 'badghis',
 '34': 'badakhshan', '35': 'kunduz', '36': 'baghlan', '37': 'takhar',
 '38': 'kandahar', '39': 'helmand', '40': 'zabul', '41': 'uruzgan',
 '42': 'daykundi', '43': 'nimruz'}
check('pp-range-10-43', set(c['code'][:2] for c in codes) == set(PP))
link = {}
for l in links:
    if l['is_primary'] == 'true':
        link[l['postcode']] = l['area_source_id']
# Exactly 2 cross-PP codes, both resolved by location: Ghor-tagged
# 326201 Allah Yar (polygon 53% Jawand + toponym inside) and
# Daykundi-tagged 425201 Gizab (polygon 95% Gizab + exact name).
cross = sorted(pc for pc, sid in link.items()
               if byid[sid]['parent_source_id'] != 'af:province:' + PP[pc[:2]])
check('cross-pp-2', cross == ['326201', '425201'], str(cross))
# --- per-province code counts (post M7 moves+fills) ---
prov_expect = {'af:province:badakhshan': 37, 'af:province:badghis': 17,
 'af:province:baghlan': 22, 'af:province:balkh': 29,
 'af:province:bamyan': 12, 'af:province:daykundi': 12,
 'af:province:farah': 20, 'af:province:faryab': 29,
 'af:province:ghazni': 25, 'af:province:ghor': 16,
 'af:province:helmand': 35, 'af:province:herat': 133,
 'af:province:jowzjan': 15, 'af:province:kabul': 648,
 'af:province:kandahar': 34, 'af:province:kapisa': 11,
 'af:province:khost': 20, 'af:province:kunar': 24,
 'af:province:kunduz': 19, 'af:province:laghman': 17,
 'af:province:logar': 11, 'af:province:nangarhar': 35,
 'af:province:nimruz': 10, 'af:province:nuristan': 11,
 'af:province:paktia': 21, 'af:province:paktika': 27,
 'af:province:panjshir': 11, 'af:province:parwan': 16,
 'af:province:samangan': 11, 'af:province:sar-e-pol': 16,
 'af:province:takhar': 23, 'af:province:uruzgan': 11,
 'af:province:wardak': 14, 'af:province:zabul': 16}
have_prov = Counter(byid[sid]['parent_source_id'] for sid in link.values())
for sid, n in prov_expect.items():
    check(f"prov-{sid.split(':')[-1]}-{n}", have_prov[sid] == n,
          str(have_prov[sid]))
# --- per-district primary counts (post M7: 401/401 covered) ---
expect = {
 'af:district:ab-band': 1,
 'af:district:ab-kamari': 2,
 'af:district:achin': 2,
 'af:district:adraskan': 1,
 'af:district:ahmadaba': 1,
 'af:district:ajristan': 1,
 'af:district:alasay': 1,
 'af:district:ali-abad': 1,
 'af:district:alingar': 1,
 'af:district:alishang': 1,
 'af:district:almar': 1,
 'af:district:anar-dara': 1,
 'af:district:anawa': 1,
 'af:district:andar': 1,
 'af:district:andarab': 1,
 'af:district:andkhoy': 1,
 'af:district:aqcha': 1,
 'af:district:arghandab': 1,
 'af:district:arghanj-khwah': 1,
 'af:district:arghestan': 1,
 'af:district:argo': 1,
 'af:district:asad-abad': 8,
 'af:district:ashtarlay': 1,
 'af:district:atghar': 1,
 'af:district:aybak': 4,
 'af:district:azra': 1,
 'af:district:badakhshan:tagab': 1,
 'af:district:badghis:muqur': 1,
 'af:district:baghlan-e-jadid': 1,
 'af:district:baghran': 3,
 'af:district:bagram': 1,
 'af:district:bagrami': 1,
 'af:district:baharak': 1,
 'af:district:bak': 1,
 'af:district:bakwa': 1,
 'af:district:bala-buluk': 2,
 'af:district:bala-murghab': 2,
 'af:district:balkh': 1,
 'af:district:balkhab': 1,
 'af:district:bamyan': 5,
 'af:district:bangi': 1,
 'af:district:bar-kunar': 1,
 'af:district:baraki-barak': 1,
 'af:district:barg-e-matal': 1,
 'af:district:barmal': 1,
 'af:district:bati-kot': 1,
 'af:district:bazarak': 4,
 'af:district:behsud': 2,
 'af:district:bilcheragh': 1,
 'af:district:burka': 1,
 'af:district:chahab': 1,
 'af:district:chahar-asyab': 1,
 'af:district:chahar-darah': 1,
 'af:district:chak-e-wardak': 1,
 'af:district:chakhansur': 1,
 'af:district:chal': 1,
 'af:district:chamkani': 1,
 'af:district:chapa-dara': 1,
 'af:district:chaparhar': 1,
 'af:district:char-bolak': 1,
 'af:district:char-burjak': 1,
 'af:district:charikar': 7,
 'af:district:charkent': 1,
 'af:district:charkh': 1,
 'af:district:charsadra': 1,
 'af:district:chawkay': 1,
 'af:district:chemtal': 2,
 'af:district:chinarto': 1,
 'af:district:chisht-e-sharif': 1,
 'af:district:chora': 1,
 'af:district:dahana-e-ghori': 1,
 'af:district:daman': 1,
 'af:district:dand-wa-patan': 1,
 'af:district:dangam': 1,
 'af:district:dara': 2,
 'af:district:dara-e-nur': 1,
 'af:district:dara-e-pech': 1,
 'af:district:dara-e-suf-e-bala': 1,
 'af:district:dara-e-suf-e-payin': 1,
 'af:district:darayem': 1,
 'af:district:darqad': 1,
 'af:district:darwaz-e-balla': 1,
 'af:district:darwaz-e-payin': 1,
 'af:district:darzab': 1,
 'af:district:dasht-e-archi': 1,
 'af:district:dasht-e-qala': 1,
 'af:district:dawlat-abad': 2,
 'af:district:dawlatshah': 2,
 'af:district:dawlatyar': 1,
 'af:district:daychopan': 1,
 'af:district:daymirdad': 1,
 'af:district:deh-bala': 1,
 'af:district:deh-e-shu': 2,
 'af:district:deh-sabz': 1,
 'af:district:deh-salah': 1,
 'af:district:deh-yak': 1,
 'af:district:dehdadi': 1,
 'af:district:dehrawud': 1,
 'af:district:dila': 2,
 'af:district:dolayna': 1,
 'af:district:doshi': 1,
 'af:district:duab': 1,
 'af:district:dur-baba': 1,
 'af:district:eshkashem': 1,
 'af:district:eshkmesh': 1,
 'af:district:estalef': 1,
 'af:district:farah': 9,
 'af:district:farkhar': 1,
 'af:district:farsi': 1,
 'af:district:faryab:dawlat-abad': 1,
 'af:district:faryab:kohistan': 2,
 'af:district:farza': 1,
 'af:district:fayzabad': 9,
 'af:district:fereng-wa-gharu': 1,
 'af:district:feroz-koh': 7,
 'af:district:feroz-nakhchir': 1,
 'af:district:gardez': 7,
 'af:district:garmser': 1,
 'af:district:garzewan': 1,
 'af:district:gelan': 1,
 'af:district:ghazi-abad': 1,
 'af:district:ghazni': 7,
 'af:district:ghazni:jaghatu': 1,
 'af:district:ghazni:qara-bagh': 1,
 'af:district:ghorak': 1,
 'af:district:ghorband': 1,
 'af:district:ghormach': 1,
 'af:district:ghoryan': 1,
 'af:district:giro': 1,
 'af:district:giyan': 1,
 'af:district:gizab': 1,
 'af:district:gomal': 4,
 'af:district:gosfandi': 1,
 'af:district:goshta': 1,
 'af:district:guldara': 1,
 'af:district:gulistan': 1,
 'af:district:gulran': 1,
 'af:district:gurbuz': 1,
 'af:district:guzara': 1,
 'af:district:guzargah-e-nur': 1,
 'af:district:hazar-sumuch': 1,
 'af:district:hazrat-e-sultan': 1,
 'af:district:hesa-e-awal-e-behsud': 1,
 'af:district:hesarak': 1,
 'af:district:hirat': 112,
 'af:district:hisa-e-awal-e-kohistan': 1,
 'af:district:hisa-e-duwum-e-kohistan': 1,
 'af:district:imam-sahib': 3,
 'af:district:injil': 1,
 'af:district:jabal-saraj': 1,
 'af:district:jaghatu': 1,
 'af:district:jaghuri': 1,
 'af:district:jaji': 1,
 'af:district:jaji-maydan': 1,
 'af:district:jalalabad': 11,
 'af:district:jalrez': 1,
 'af:district:jani-khel': 3,
 'af:district:jawand': 2,
 'af:district:jorm': 1,
 'af:district:jowzjan:fayzabad': 1,
 'af:district:kabul': 634,
 'af:district:kahmard': 1,
 'af:district:kajaki': 1,
 'af:district:kajran': 1,
 'af:district:kakar': 1,
 'af:district:kalafgan': 1,
 'af:district:kalakan': 1,
 'af:district:kaldar': 1,
 'af:district:kama': 1,
 'af:district:kamdesh': 1,
 'af:district:kandahar': 16,
 'af:district:kandahar:arghandab': 1,
 'af:district:kang': 1,
 'af:district:karukh': 1,
 'af:district:keshem': 1,
 'af:district:keshendeh': 1,
 'af:district:khadir': 1,
 'af:district:khak-e-jabbar': 1,
 'af:district:khak-e-safed': 1,
 'af:district:khakrez': 1,
 'af:district:khamyab': 1,
 'af:district:khan-abad': 2,
 'af:district:khan-e-char-bagh': 1,
 'af:district:khanaqa': 1,
 'af:district:kharwar': 1,
 'af:district:khas-kunar': 1,
 'af:district:khas-uruzgan': 1,
 'af:district:khash': 1,
 'af:district:khashrod': 2,
 'af:district:khenj': 1,
 'af:district:khinjan': 1,
 'af:district:khogyani': 1,
 'af:district:khoshi': 1,
 'af:district:khost-wa-fereng': 1,
 'af:district:khulm': 1,
 'af:district:khuram-wa-sarbagh': 1,
 'af:district:khwahan': 1,
 'af:district:khwaja-bahawuddin': 1,
 'af:district:khwaja-dukoh': 1,
 'af:district:khwaja-ghar': 1,
 'af:district:khwaja-hejran': 1,
 'af:district:khwaja-sabz-posh': 1,
 'af:district:khwaja-umari': 1,
 'af:district:kiti': 1,
 'af:district:kofab': 1,
 'af:district:koh-band': 1,
 'af:district:koh-e-safi': 1,
 'af:district:kohestanat': 3,
 'af:district:kohistan': 1,
 'af:district:kohsan': 2,
 'af:district:koran-wa-monjan': 1,
 'af:district:kot': 1,
 'af:district:kunduz': 10,
 'af:district:kushk': 2,
 'af:district:kushk-e-kuhna': 1,
 'af:district:kuz-kunar': 1,
 'af:district:lal-wa-sarjangal': 1,
 'af:district:lalpur': 1,
 'af:district:lash-e-juwayn': 1,
 'af:district:lashkargah': 17,
 'af:district:lija-ahmad-khel': 2,
 'af:district:mahmood-e-raqi': 5,
 'af:district:malistan': 1,
 'af:district:mandol': 1,
 'af:district:mandozayi': 1,
 'af:district:marawara': 1,
 'af:district:mardyan': 1,
 'af:district:markaz-e-behsud': 1,
 'af:district:marmul': 1,
 'af:district:maruf': 1,
 'af:district:mata-khan': 1,
 'af:district:matun': 8,
 'af:district:maydan-shahr': 6,
 'af:district:maymana': 11,
 'af:district:maywand': 2,
 'af:district:mazar-e-sharif': 12,
 'af:district:mehtarlam': 12,
 'af:district:mingajik': 1,
 'af:district:mir-bacha-kot': 1,
 'af:district:miramor': 1,
 'af:district:miyanshin': 1,
 'af:district:mizan': 1,
 'af:district:mohammad-agha': 1,
 'af:district:muhmand-dara': 2,
 'af:district:muqur': 1,
 'af:district:musa-khel': 1,
 'af:district:musa-qala': 1,
 'af:district:musahi': 1,
 'af:district:nad-e-ali': 2,
 'af:district:nadir-shah-kot': 1,
 'af:district:nahr-e-saraj': 3,
 'af:district:nahr-e-shahi': 2,
 'af:district:nahrin': 1,
 'af:district:namak-ab': 1,
 'af:district:narang': 1,
 'af:district:nari': 2,
 'af:district:nawa': 1,
 'af:district:nawa-e-barakzaiy': 1,
 'af:district:nawbahar': 1,
 'af:district:nawur': 1,
 'af:district:nawzad': 1,
 'af:district:nazyan': 1,
 'af:district:nerkh': 1,
 'af:district:nesh': 1,
 'af:district:nijrab': 1,
 'af:district:nika': 1,
 'af:district:nili': 4,
 'af:district:nurgal': 1,
 'af:district:nurgaram': 1,
 'af:district:obe': 1,
 'af:district:omna': 1,
 'af:district:pachir-wa-agam': 1,
 'af:district:paghman': 1,
 'af:district:paktia:jani-khel': 1,
 'af:district:paktika:surobi': 1,
 'af:district:panjab': 1,
 'af:district:panjwayi': 1,
 'af:district:parun': 3,
 'af:district:paryan': 1,
 'af:district:pasaband': 1,
 'af:district:pashtun-kot': 3,
 'af:district:pashtun-zarghun': 1,
 'af:district:patoo': 1,
 'af:district:pul-e-alam': 5,
 'af:district:pul-e-hisar': 1,
 'af:district:pul-e-khumri': 8,
 'af:district:pur-chaman': 1,
 'af:district:pushtrod': 1,
 'af:district:qadis': 2,
 'af:district:qala-e-kah': 1,
 'af:district:qala-e-naw': 7,
 'af:district:qala-e-zal': 1,
 'af:district:qalandar': 1,
 'af:district:qalat': 5,
 'af:district:qara-bagh': 1,
 'af:district:qaram-qul': 1,
 'af:district:qarghayi': 1,
 'af:district:qarqin': 1,
 'af:district:qaysar': 3,
 'af:district:qurghan': 1,
 'af:district:qush-tepa': 1,
 'af:district:raghestan': 1,
 'af:district:rashidan': 1,
 'af:district:reg': 1,
 'af:district:reg-i-khan-nishin': 1,
 'af:district:rodat': 1,
 'af:district:rostaq': 1,
 'af:district:rukha': 1,
 'af:district:ruy-e-duab': 1,
 'af:district:sabari': 1,
 'af:district:saghar': 1,
 'af:district:salang': 1,
 'af:district:sancharak': 1,
 'af:district:sang-e-takht': 1,
 'af:district:sangin': 1,
 'af:district:sar-e-pul': 8,
 'af:district:sar-kani': 1,
 'af:district:sar-rawzah': 1,
 'af:district:sayad': 1,
 'af:district:saydabad': 1,
 'af:district:sayed-karam': 2,
 'af:district:sayed-khel': 1,
 'af:district:sayghan': 1,
 'af:district:shah-joi': 1,
 'af:district:shah-wali-kot': 2,
 'af:district:shahid-e-hassas': 1,
 'af:district:shahr-e-buzorg': 1,
 'af:district:shahrak': 1,
 'af:district:shahrestan': 1,
 'af:district:shakar-dara': 1,
 'af:district:shaki': 1,
 'af:district:shamal': 1,
 'af:district:shamul-zayi': 1,
 'af:district:sharak-e-hayratan': 1,
 'af:district:sharan': 3,
 'af:district:shawak': 1,
 'af:district:shekh-ali': 1,
 'af:district:sherzad': 1,
 'af:district:shibar': 1,
 'af:district:shiberghan': 5,
 'af:district:shibkoh': 1,
 'af:district:shigal': 2,
 'af:district:shighnan': 1,
 'af:district:shindand': 5,
 'af:district:shinkay': 2,
 'af:district:shinwar': 1,
 'af:district:shinwari': 1,
 'af:district:shirin-tagab': 1,
 'af:district:sholgareh': 1,
 'af:district:shorabak': 1,
 'af:district:shortepa': 1,
 'af:district:shuhada': 1,
 'af:district:shutul': 1,
 'af:district:sozmaqala': 1,
 'af:district:spera': 1,
 'af:district:spin-boldak': 2,
 'af:district:surkh-e-parsa': 1,
 'af:district:surkh-rod': 1,
 'af:district:surobi': 1,
 'af:district:tagab': 1,
 'af:district:takhar:baharak': 1,
 'af:district:tala-wa-barfak': 1,
 'af:district:taloqan': 7,
 'af:district:tani': 1,
 'af:district:tarnak-wa-jaldak': 1,
 'af:district:taywarah': 1,
 'af:district:terezayi': 1,
 'af:district:teshkan': 1,
 'af:district:tirinkot': 5,
 'af:district:tolak': 1,
 'af:district:turwo': 1,
 'af:district:urgun': 1,
 'af:district:waghaz': 1,
 'af:district:wakhan': 2,
 'af:district:wal-e-muhammad-e-shahid': 1,
 'af:district:wama': 1,
 'af:district:waras': 1,
 'af:district:warduj': 1,
 'af:district:warsaj': 1,
 'af:district:washer': 1,
 'af:district:watapur': 1,
 'af:district:waygal': 2,
 'af:district:wazakhah': 1,
 'af:district:wormamay': 1,
 'af:district:yaftal-e-sufla': 1,
 'af:district:yahya-khel': 1,
 'af:district:yakawlang': 2,
 'af:district:yamgan': 1,
 'af:district:yangi-qala': 1,
 'af:district:yawan': 1,
 'af:district:yosuf-khel': 1,
 'af:district:zadran': 2,
 'af:district:zanakhan': 1,
 'af:district:zaranj': 5,
 'af:district:zarghun-shahr': 1,
 'af:district:zari': 1,
 'af:district:zebak': 1,
 'af:district:zheray': 1,
 'af:district:zindajan': 1,
 'af:district:ziruk': 1,
 'af:district:zurmat': 2,
}
have = Counter(l['area_source_id'] for l in links
               if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f"count-{sid.split(':')[-1]}-{n}", have[sid] == n,
          str(have[sid]))
check('covered-401', len(have) == 401, str(len(have)))
# --- M7 move pins (12): polygon share + tag + 2nd signal each ---
moves = {'295801': 'af:district:waygal',  # Want=Want Waigal (Nuristan sources)
 '306701': 'af:district:shindand',  # 99.9% Shindand; OSM Anar Dara 40km off
 '326101': 'af:district:feroz-koh',  # 76% Feroz Koh; OSM Qala-e-Naw 100km off
 '396401': 'af:district:baghran',  # 99.8% Baghran; OSM Kiti 25km off
 '186401': 'af:district:qaysar',  # 52% + Chihil Gazi shrine inside polygon
 '366501': 'af:district:pul-e-khumri',  # 94% + Nominatim; Dand Ghuri false friend
 '346501': 'af:district:darwaz-e-payin',  # 99.5% + Nominatim + Darwaz village
 '216001': 'af:district:sar-e-pul',  # 91.5% + Nominatim (Sayed Abad suburb)
 '175502': 'af:district:sharak-e-hayratan',  # 99.7% + Hairatan town coords
 '326201': 'af:district:jawand',  # 53% + Allah Yar toponym inside polygon
 '335801': 'af:district:ab-kamari',  # 81%; eponymous Muqur is 335601
 '265702': 'af:district:behsud'}  # airport 100% Behsud + rural 'Boundary'
for pc, sid in moves.items():
    check(f'move-{pc}', link.get(pc) == sid, str(link.get(pc)))
# --- M7 fill pins (2): both were wrongly dropped as "non-COD-AB" ---
fills = {'396701': 'af:district:deh-e-shu',  # Bahramcha cap. of Dishu (wiki/IOM)
 '425201': 'af:district:gizab'}  # 95% Gizab AF2507; Daykundi tag only
for pc, sid in fills.items():
    check(f'fill-{pc}', link.get(pc) == sid, str(link.get(pc)))
# The old overlay row typo'd the Bahramcha drop as 396601: that code
# ships (Babajee -> Nahr-e-Saraj). No Garmser secondary on 396701
# (34% share, single signal).
check('not-a-drop-396601', link.get('396601') == 'af:district:nahr-e-saraj')
# --- keep pins: eponymous zones donors retain + verified adjudications ---
keeps = {'286301': 'af:district:dara-e-pech',
 '315401': 'af:district:anar-dara',
 '330101': 'af:district:qala-e-naw',
 '425801': 'af:district:kiti',
 '335301': 'af:district:ghormach',
 '366301': 'af:district:dahana-e-ghori',
 '346601': 'af:district:darwaz-e-balla',
 '215601': 'af:district:sayad',
 '175501': 'af:district:kaldar',
 '335601': 'af:district:badghis:muqur',
 '235301': 'af:district:muqur',
 '186301': 'af:district:qaysar',
 '186201': 'af:district:almar',
 '335101': 'af:district:ab-kamari',
 '335401': 'af:district:jawand',
 '395801': 'af:district:baghran',
 '295301': 'af:district:waygal',
 # Verified adjudications (geometry + tag + Nominatim agree):
 '396801': 'af:district:nahr-e-saraj',  # Gershk
 '396301': 'af:district:nad-e-ali',  # Marja
 '386701': 'af:district:spin-boldak',  # Takhtapul
 '386601': 'af:district:kandahar',  # Dand
 '225301': 'af:district:lija-ahmad-khel',  # Lija Mangal
 '347801': 'af:district:wakhan',  # Pamir
 '286601': 'af:district:shigal',  # Sheltan
 '300101': 'af:district:hirat',  # Herat city
 '435501': 'af:district:khashrod',  # Dilaram
 '155701': 'af:district:dara',  # Abshar
 '326001': 'af:district:feroz-koh',  # Morghab (no Ghor Murghab in COD-AB)
 '347301': 'af:district:eshkashem',  # Eshkmesh label, Badakhshan tag
 '376501': 'af:district:eshkmesh',  # Eshkashem label, Takhar tag
 '186701': 'af:district:qaysar',  # Khaibar: Almar/Qaysar strip tie, keep
 '115601': 'af:district:jabal-saraj',  # 94.5% polygon (centroid in Salang)
 '265701': 'af:district:behsud',  # 98% polygon (centroid in Kama)
 '120401': 'af:district:mahmood-e-raqi',  # 91.6% (centroid in Bagram)
 '275501': 'af:district:mehtarlam',  # Badpakh
 '316101': 'af:district:bala-buluk',  # Farah Rud
 '405301': 'af:district:kakar',  # Khak-e-Afghan rename
 '267201': 'af:district:achin'}  # Spin Ghar foothills
for pc, sid in keeps.items():
    check(f'keep-{pc}', link.get(pc) == sid, str(link.get(pc)))
# UPU afgEn 07/2025 example anchors.
check('upu-100208', link.get('100208') == 'af:district:kabul')
check('upu-100501', link.get('100501') == 'af:district:kabul')
check('upu-100613', link.get('100613') == 'af:district:kabul')
check('upu-106401', link.get('106401') == 'af:district:paghman')
check('upu-265101', link.get('265101') == 'af:district:hesarak')
check('upu-385301', link.get('385301') == 'af:district:spin-boldak')
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
