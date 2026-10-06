import csv, re, sys
from collections import Counter
# Russia gate. B11: tree 83 L1 federal subjects vs ISO 3166-2:RU (83/83
# codes + 46/21/9/4/2/1 type split) + WP federal-subjects roster (83
# recognized + 6 unrecognized) + WP postal-division prefix table; postal
# 43531/43531 vs current GeoNames RU dump (43538 rows, all distinct codes):
# bundled = GN minus exactly the 7 Baikonur 468xxx rows (Kazakhstan).
# B11 FIX: 626xxx (136 codes, Tobolsk/Nizhnyaya Tavda/Vagay/Isetskoye
# blocks) Khanty-Mansi -> Tyumen (ru-WP 625-627 Tyumen / 628-only KHM +
# OSM Nominatim RU-TYU + WP district articles). Post-fix: Tyumen 485
# (625/626/627), KHM 238 (628 only), YAN 629, NEN 166, YEV 679, CHU 689.
# Keeps: 144700 UFPS-Moscow-Oblast office at Moscow city (GN admin1
# Moskva; 1v1 tie -> stability), 78 x 901xxx mail-route codes at GN
# origin region (ru-WP lists 901 as special-purpose), Chita->Zabaykalsky
# (436, incl. 687 Agin-Buryat), Kamchatka Obl->Krai (119, incl. 688
# Koryak). No 26x/27x/28x/29x (Crimea/new-territory ranges) present.
# Stance: pre-2014/ISO roster, Crimea/Sevastopol/2022-claimed absent.
# Run from repo root:
# python3 docs/agents/audit/gate_ru.py
A = './packages/addressing/resources/geography/russia-address-areas.csv'
C = './packages/addressing/resources/geography/russia-postal-codes.csv'
L = './packages/addressing/resources/geography/russia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- EOL: areas LF, codes/links CRLF ---
for p, want in ((A, 'LF'), (C, 'CRLF'), (L, 'CRLF')):
    b = open(p, 'rb').read()
    is_crlf = b.count(b'\r\n') == b.count(b'\n')
    check(f'eol-{p.split("/")[-1]}-{want}',
          (not is_crlf) if want == 'LF' else is_crlf)
# --- tree: 83 L1, ISO type split ---
check('areas-83', len(rows) == 83, str(len(rows)))
check('oblasts-46', sum(1 for r in rows if r['type'] == 'oblast') == 46)
check('republics-21', sum(1 for r in rows if r['type'] == 'republic') == 21)
check('krais-9', sum(1 for r in rows if r['type'] == 'krai') == 9)
check('okrugs-4', sum(1 for r in rows if r['type'] == 'okrug') == 4)
check('federal-cities-2', sum(1 for r in rows if r['type'] == 'federal_city') == 2)
check('autonomous-oblasts-1', sum(1 for r in rows if r['type'] == 'autonomous_oblast') == 1)
check('all-l1', all(r['level'] == '1' for r in rows))
check('no-parents', all(not r['parent_source_id'] for r in rows))
iso = {'ru:republic:adygea': ('Adygea', 'AD', 'republic'),
 'ru:republic:altai': ('Altai', 'AL', 'republic'),
 'ru:krai:altai-krai': ('Altai Krai', 'ALT', 'krai'),
 'ru:oblast:amur': ('Amur', 'AMU', 'oblast'),
 'ru:oblast:arkhangelsk': ('Arkhangelsk', 'ARK', 'oblast'),
 'ru:oblast:astrakhan': ('Astrakhan', 'AST', 'oblast'),
 'ru:republic:bashkortostan': ('Bashkortostan', 'BA', 'republic'),
 'ru:oblast:belgorod': ('Belgorod', 'BEL', 'oblast'),
 'ru:oblast:bryansk': ('Bryansk', 'BRY', 'oblast'),
 'ru:republic:buryatia': ('Buryatia', 'BU', 'republic'),
 'ru:republic:chechnya': ('Chechnya', 'CE', 'republic'),
 'ru:oblast:chelyabinsk': ('Chelyabinsk', 'CHE', 'oblast'),
 'ru:okrug:chukotka-okrug': ('Chukotka Okrug', 'CHU', 'okrug'),
 'ru:republic:chuvashia': ('Chuvashia', 'CU', 'republic'),
 'ru:republic:dagestan': ('Dagestan', 'DA', 'republic'),
 'ru:republic:ingushetia': ('Ingushetia', 'IN', 'republic'),
 'ru:oblast:irkutsk': ('Irkutsk', 'IRK', 'oblast'),
 'ru:oblast:ivanovo': ('Ivanovo', 'IVA', 'oblast'),
 'ru:autonomous_oblast:jewish-autonomous-oblast': ('Jewish Autonomous Oblast', 'YEV', 'autonomous_oblast'),
 'ru:republic:kabardino-balkaria': ('Kabardino-Balkaria', 'KB', 'republic'),
 'ru:oblast:kaliningrad': ('Kaliningrad', 'KGD', 'oblast'),
 'ru:republic:kalmykia': ('Kalmykia', 'KL', 'republic'),
 'ru:oblast:kaluga': ('Kaluga', 'KLU', 'oblast'),
 'ru:krai:kamchatka-krai': ('Kamchatka Krai', 'KAM', 'krai'),
 'ru:republic:karachay-cherkessia': ('Karachay-Cherkessia', 'KC', 'republic'),
 'ru:republic:karelia': ('Karelia', 'KR', 'republic'),
 'ru:oblast:kemerovo': ('Kemerovo', 'KEM', 'oblast'),
 'ru:krai:khabarovsk-krai': ('Khabarovsk Krai', 'KHA', 'krai'),
 'ru:republic:khakassia': ('Khakassia', 'KK', 'republic'),
 'ru:okrug:khanty-mansi-okrug': ('Khanty-Mansi Okrug', 'KHM', 'okrug'),
 'ru:oblast:kirov': ('Kirov', 'KIR', 'oblast'),
 'ru:republic:komi': ('Komi', 'KO', 'republic'),
 'ru:oblast:kostroma': ('Kostroma', 'KOS', 'oblast'),
 'ru:krai:krasnodar-krai': ('Krasnodar Krai', 'KDA', 'krai'),
 'ru:krai:krasnoyarsk-krai': ('Krasnoyarsk Krai', 'KYA', 'krai'),
 'ru:oblast:kurgan': ('Kurgan', 'KGN', 'oblast'),
 'ru:oblast:kursk': ('Kursk', 'KRS', 'oblast'),
 'ru:oblast:leningrad': ('Leningrad', 'LEN', 'oblast'),
 'ru:oblast:lipetsk': ('Lipetsk', 'LIP', 'oblast'),
 'ru:oblast:magadan': ('Magadan', 'MAG', 'oblast'),
 'ru:republic:mari-el': ('Mari El', 'ME', 'republic'),
 'ru:republic:mordovia': ('Mordovia', 'MO', 'republic'),
 'ru:federal_city:moscow': ('Moscow', 'MOW', 'federal_city'),
 'ru:oblast:moscow-oblast': ('Moscow Oblast', 'MOS', 'oblast'),
 'ru:oblast:murmansk': ('Murmansk', 'MUR', 'oblast'),
 'ru:okrug:nenets-okrug': ('Nenets Okrug', 'NEN', 'okrug'),
 'ru:oblast:nizhny-novgorod': ('Nizhny Novgorod', 'NIZ', 'oblast'),
 'ru:republic:north-ossetia-alania': ('North Ossetia-Alania', 'SE', 'republic'),
 'ru:oblast:novgorod': ('Novgorod', 'NGR', 'oblast'),
 'ru:oblast:novosibirsk': ('Novosibirsk', 'NVS', 'oblast'),
 'ru:oblast:omsk': ('Omsk', 'OMS', 'oblast'),
 'ru:oblast:orenburg': ('Orenburg', 'ORE', 'oblast'),
 'ru:oblast:oryol': ('Oryol', 'ORL', 'oblast'),
 'ru:krai:perm-krai': ('Perm Krai', 'PER', 'krai'),
 'ru:oblast:penza': ('Penza', 'PNZ', 'oblast'),
 'ru:krai:primorsky-krai': ('Primorsky Krai', 'PRI', 'krai'),
 'ru:oblast:pskov': ('Pskov', 'PSK', 'oblast'),
 'ru:oblast:rostov': ('Rostov', 'ROS', 'oblast'),
 'ru:oblast:ryazan': ('Ryazan', 'RYA', 'oblast'),
 'ru:federal_city:saint-petersburg': ('Saint Petersburg', 'SPE', 'federal_city'),
 'ru:republic:sakha-yakutia': ('Sakha (Yakutia)', 'SA', 'republic'),
 'ru:oblast:sakhalin': ('Sakhalin', 'SAK', 'oblast'),
 'ru:oblast:samara': ('Samara', 'SAM', 'oblast'),
 'ru:oblast:saratov': ('Saratov', 'SAR', 'oblast'),
 'ru:oblast:smolensk': ('Smolensk', 'SMO', 'oblast'),
 'ru:krai:stavropol-krai': ('Stavropol Krai', 'STA', 'krai'),
 'ru:oblast:sverdlovsk': ('Sverdlovsk', 'SVE', 'oblast'),
 'ru:oblast:tambov': ('Tambov', 'TAM', 'oblast'),
 'ru:republic:tatarstan': ('Tatarstan', 'TA', 'republic'),
 'ru:oblast:tomsk': ('Tomsk', 'TOM', 'oblast'),
 'ru:oblast:tula': ('Tula', 'TUL', 'oblast'),
 'ru:republic:tuva': ('Tuva', 'TY', 'republic'),
 'ru:oblast:tver': ('Tver', 'TVE', 'oblast'),
 'ru:oblast:tyumen': ('Tyumen', 'TYU', 'oblast'),
 'ru:republic:udmurtia': ('Udmurtia', 'UD', 'republic'),
 'ru:oblast:ulyanovsk': ('Ulyanovsk', 'ULY', 'oblast'),
 'ru:oblast:vladimir': ('Vladimir', 'VLA', 'oblast'),
 'ru:oblast:volgograd': ('Volgograd', 'VGG', 'oblast'),
 'ru:oblast:vologda': ('Vologda', 'VLG', 'oblast'),
 'ru:oblast:voronezh': ('Voronezh', 'VOR', 'oblast'),
 'ru:okrug:yamalo-nenets-okrug': ('Yamalo-Nenets Okrug', 'YAN', 'okrug'),
 'ru:oblast:yaroslavl': ('Yaroslavl', 'YAR', 'oblast'),
 'ru:krai:zabaykalsky-krai': ('Zabaykalsky Krai', 'ZAB', 'krai')}
for sid, (name, code, typ) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == typ and r['level'] == '1', str(r))
# --- postal: 43531 codes / 43531 links, all primary, no multis ---
check('codes-43531', len(codes) == 43531, str(len(codes)))
check('links-43531', len(links) == 43531, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 43531 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
check('no-dupes', len(code_set) == 43531)
check('min-101000', min(code_set) == '101000', min(code_set))
check('max-901993', max(code_set) == '901993', max(code_set))
# Baikonur 468xxx (Kazakhstan) absent; no Crimea/new-territory ranges.
baik = sorted(code_set & {'468320', '468321', '468322', '468323', '468324',
                          '468325', '468700'})
check('no-baiknur', not baik, str(baik))
newt = sorted({pc for pc in code_set if pc[:2] in ('26', '27', '28', '29')})
check('no-26-29-ranges', not newt, str(newt[:5]))
# --- per-subject primary counts (post-fix: TYU 485, KHM 238) ---
counts = {'ru:republic:adygea': 138, 'ru:republic:altai': 118,
 'ru:krai:altai-krai': 1051, 'ru:oblast:amur': 396,
 'ru:oblast:arkhangelsk': 606, 'ru:oblast:astrakhan': 253,
 'ru:republic:bashkortostan': 1283, 'ru:oblast:belgorod': 610,
 'ru:oblast:bryansk': 626, 'ru:republic:buryatia': 263,
 'ru:republic:chechnya': 280, 'ru:oblast:chelyabinsk': 718,
 'ru:okrug:chukotka-okrug': 53, 'ru:republic:chuvashia': 405,
 'ru:republic:dagestan': 575, 'ru:republic:ingushetia': 58,
 'ru:oblast:irkutsk': 772, 'ru:oblast:ivanovo': 312,
 'ru:autonomous_oblast:jewish-autonomous-oblast': 88,
 'ru:republic:kabardino-balkaria': 203, 'ru:oblast:kaliningrad': 249,
 'ru:republic:kalmykia': 143, 'ru:oblast:kaluga': 463,
 'ru:krai:kamchatka-krai': 119, 'ru:republic:karachay-cherkessia': 159,
 'ru:republic:karelia': 281, 'ru:oblast:kemerovo': 609,
 'ru:krai:khabarovsk-krai': 317, 'ru:republic:khakassia': 138,
 'ru:okrug:khanty-mansi-okrug': 238, 'ru:oblast:kirov': 737,
 'ru:republic:komi': 385, 'ru:oblast:kostroma': 408,
 'ru:krai:krasnodar-krai': 1261, 'ru:krai:krasnoyarsk-krai': 820,
 'ru:oblast:kurgan': 501, 'ru:oblast:kursk': 698,
 'ru:oblast:leningrad': 548, 'ru:oblast:lipetsk': 527,
 'ru:oblast:magadan': 57, 'ru:republic:mari-el': 271,
 'ru:republic:mordovia': 492, 'ru:federal_city:moscow': 1422,
 'ru:oblast:moscow-oblast': 1164, 'ru:oblast:murmansk': 168,
 'ru:okrug:nenets-okrug': 33, 'ru:oblast:nizhny-novgorod': 990,
 'ru:republic:north-ossetia-alania': 179, 'ru:oblast:novgorod': 409,
 'ru:oblast:novosibirsk': 828, 'ru:oblast:omsk': 616,
 'ru:oblast:orenburg': 928, 'ru:oblast:oryol': 486,
 'ru:krai:perm-krai': 733, 'ru:oblast:penza': 572,
 'ru:krai:primorsky-krai': 551, 'ru:oblast:pskov': 487,
 'ru:oblast:rostov': 1122, 'ru:oblast:ryazan': 566,
 'ru:federal_city:saint-petersburg': 354, 'ru:republic:sakha-yakutia': 469,
 'ru:oblast:sakhalin': 162, 'ru:oblast:samara': 786,
 'ru:oblast:saratov': 926, 'ru:oblast:smolensk': 515,
 'ru:krai:stavropol-krai': 631, 'ru:oblast:sverdlovsk': 927,
 'ru:oblast:tambov': 678, 'ru:republic:tatarstan': 1110,
 'ru:oblast:tomsk': 306, 'ru:oblast:tula': 578,
 'ru:republic:tuva': 124, 'ru:oblast:tver': 916,
 'ru:oblast:tyumen': 485, 'ru:republic:udmurtia': 494,
 'ru:oblast:ulyanovsk': 528, 'ru:oblast:vladimir': 462,
 'ru:oblast:volgograd': 944, 'ru:oblast:vologda': 682,
 'ru:oblast:voronezh': 995, 'ru:okrug:yamalo-nenets-okrug': 96,
 'ru:oblast:yaroslavl': 444, 'ru:krai:zabaykalsky-krai': 436}
have = Counter(l['area_source_id'] for l in links)
badc = {s: (have.get(s, 0), n) for s, n in counts.items()
        if have.get(s, 0) != n}
check('per-subject-counts', not badc, str(badc))
check('subjects-with-links-83', len(have) == 83, str(len(have)))
# --- 3-digit prefix per subject (ru-WP postal-division table) ---
P = {'ru:republic:adygea': {'385'}, 'ru:republic:altai': {'649'},
 'ru:krai:altai-krai': {'656', '657', '658', '659'},
 'ru:oblast:amur': {'675', '676'},
 'ru:oblast:arkhangelsk': {'163', '164', '165'},
 'ru:oblast:astrakhan': {'414', '415', '416'},
 'ru:republic:bashkortostan': {'450', '451', '452', '453'},
 'ru:oblast:belgorod': {'308', '309'},
 'ru:oblast:bryansk': {'241', '242', '243'},
 'ru:republic:buryatia': {'670', '671'},
 'ru:oblast:vladimir': {'600', '601', '602'},
 'ru:oblast:volgograd': {'400', '401', '402', '403', '404'},
 'ru:oblast:vologda': {'160', '161', '162'},
 'ru:oblast:voronezh': {'394', '395', '396', '397'},
 'ru:republic:dagestan': {'367', '368'},
 'ru:autonomous_oblast:jewish-autonomous-oblast': {'679'},
 'ru:krai:zabaykalsky-krai': {'672', '673', '674', '687'},
 'ru:oblast:ivanovo': {'153', '154', '155'},
 'ru:republic:ingushetia': {'386'},
 'ru:oblast:irkutsk': {'664', '665', '666', '669'},
 'ru:republic:kabardino-balkaria': {'360', '361'},
 'ru:oblast:kaliningrad': {'236', '237', '238'},
 'ru:republic:kalmykia': {'358', '359'},
 'ru:oblast:kaluga': {'248', '249'},
 'ru:krai:kamchatka-krai': {'683', '684', '688'},
 'ru:republic:karachay-cherkessia': {'369'},
 'ru:republic:karelia': {'185', '186'},
 'ru:oblast:kemerovo': {'650', '651', '652', '653', '654'},
 'ru:oblast:kirov': {'610', '611', '612', '613'},
 'ru:republic:komi': {'167', '168', '169'},
 'ru:oblast:kostroma': {'156', '157'},
 'ru:krai:krasnodar-krai': {'350', '351', '352', '353', '354'},
 'ru:krai:krasnoyarsk-krai': {'660', '661', '662', '663', '647', '648'},
 'ru:oblast:kurgan': {'640', '641'},
 'ru:oblast:kursk': {'305', '306', '307'},
 'ru:oblast:leningrad': {'187', '188'},
 'ru:oblast:lipetsk': {'398', '399'},
 'ru:oblast:magadan': {'685', '686'},
 'ru:republic:mari-el': {'424', '425'},
 'ru:republic:mordovia': {'430', '431'},
 'ru:federal_city:moscow': {str(p) for p in range(101, 136)},
 'ru:oblast:moscow-oblast': {'140', '141', '142', '143', '144'},
 'ru:oblast:murmansk': {'183', '184'},
 'ru:okrug:nenets-okrug': {'166'},
 'ru:oblast:nizhny-novgorod': {str(p) for p in range(603, 608)},
 'ru:republic:north-ossetia-alania': {'362', '363'},
 'ru:oblast:novgorod': {'173', '174', '175'},
 'ru:oblast:novosibirsk': {'630', '631', '632', '633'},
 'ru:oblast:omsk': {'644', '645', '646'},
 'ru:oblast:orenburg': {'460', '461', '462'},
 'ru:oblast:oryol': {'302', '303'},
 'ru:oblast:penza': {'440', '441', '442'},
 'ru:krai:perm-krai': {'614', '615', '616', '617', '618', '619'},
 'ru:krai:primorsky-krai': {'690', '691', '692'},
 'ru:oblast:pskov': {'180', '181', '182'},
 'ru:oblast:rostov': {'344', '345', '346', '347'},
 'ru:oblast:ryazan': {'390', '391'},
 'ru:oblast:samara': {'443', '444', '445', '446'},
 'ru:federal_city:saint-petersburg': {str(p) for p in range(190, 200)},
 'ru:oblast:saratov': {'410', '411', '412', '413'},
 'ru:republic:sakha-yakutia': {'677', '678'},
 'ru:oblast:sakhalin': {'693', '694'},
 'ru:oblast:sverdlovsk': {'620', '621', '622', '623', '624'},
 'ru:oblast:smolensk': {'214', '215', '216'},
 'ru:krai:stavropol-krai': {'355', '356', '357'},
 'ru:oblast:tambov': {'392', '393'},
 'ru:republic:tatarstan': {'420', '421', '422', '423'},
 'ru:oblast:tver': {'170', '171', '172'},
 'ru:oblast:tomsk': {'634', '635', '636'},
 'ru:oblast:tula': {'300', '301'},
 'ru:republic:tuva': {'667', '668'},
 'ru:oblast:tyumen': {'625', '626', '627'},
 'ru:republic:udmurtia': {'426', '427'},
 'ru:oblast:ulyanovsk': {'432', '433'},
 'ru:krai:khabarovsk-krai': {'680', '681', '682'},
 'ru:republic:khakassia': {'655'},
 'ru:okrug:khanty-mansi-okrug': {'628'},
 'ru:oblast:chelyabinsk': {'454', '455', '456', '457'},
 'ru:republic:chechnya': {'364', '365', '366'},
 'ru:republic:chuvashia': {'428', '429'},
 'ru:okrug:chukotka-okrug': {'689'},
 'ru:okrug:yamalo-nenets-okrug': {'629'},
 'ru:oblast:yaroslavl': {'150', '151', '152'}}
# Documented exceptions: 144700 UFPS-MO office in Moscow city; 901xxx
# mail-route codes kept at GN origin region.
bycode = {l['postcode']: l['area_source_id'] for l in links}
check('exc-144700-moscow', bycode.get('144700') == 'ru:federal_city:moscow',
      str(bycode.get('144700')))
mob = [pc for pc, s in bycode.items() if s == 'ru:oblast:moscow-oblast'
       and pc.startswith('144')]
check('moscow-obl-144-block', len(mob) == 13, str(len(mob)))
n901 = sum(1 for pc in bycode if pc.startswith('901'))
check('routes-901x78', n901 == 78, str(n901))
badp = [(pc, s) for pc, s in bycode.items()
        if pc != '144700' and not pc.startswith('901')
        and pc[:3] not in P.get(s, set())]
check('prefix-per-subject', not badp, str(badp[:5]))
# --- cluster pins ---
check('khm-628-only', {pc[:3] for pc, s in bycode.items()
      if s == 'ru:okrug:khanty-mansi-okrug'} == {'628'})
check('tyu-625-626-627', {pc[:3] for pc, s in bycode.items()
      if s == 'ru:oblast:tyumen'} == {'625', '626', '627'})
check('tyu-626x136', sum(1 for pc, s in bycode.items()
      if s == 'ru:oblast:tyumen' and pc.startswith('626')) == 136)
check('yan-629x96', sum(1 for pc, s in bycode.items()
      if s == 'ru:okrug:yamalo-nenets-okrug') == 96)
check('nen-166x33', sum(1 for pc, s in bycode.items()
      if s == 'ru:okrug:nenets-okrug') == 33)
check('yev-679x88', sum(1 for pc, s in bycode.items()
      if s == 'ru:autonomous_oblast:jewish-autonomous-oblast') == 88)
check('chu-689x53', sum(1 for pc, s in bycode.items()
      if s == 'ru:okrug:chukotka-okrug') == 53)
check('zab-687-agin', sum(1 for pc, s in bycode.items()
      if s == 'ru:krai:zabaykalsky-krai' and pc.startswith('687')) == 18)
check('kam-688-koryak', sum(1 for pc, s in bycode.items()
      if s == 'ru:krai:kamchatka-krai' and pc.startswith('688')) == 30)
check('pin-626150-tyu', bycode.get('626150') == 'ru:oblast:tyumen')
check('pin-628011-khm', bycode.get('628011') == 'ru:okrug:khanty-mansi-okrug')
check('pin-629000-yan', bycode.get('629000') == 'ru:okrug:yamalo-nenets-okrug')
check('pin-166000-nen', bycode.get('166000') == 'ru:okrug:nenets-okrug')
check('pin-679000-yev', bycode.get('679000') == 'ru:autonomous_oblast:jewish-autonomous-oblast')
check('pin-689000-chu', bycode.get('689000') == 'ru:okrug:chukotka-okrug')
check('pin-101000-mow', bycode.get('101000') == 'ru:federal_city:moscow')
check('pin-190000-spe', bycode.get('190000') == 'ru:federal_city:saint-petersburg')
print('FAILURES:', fails if fails else 'none')
sys.exit(1 if fails else 0)
