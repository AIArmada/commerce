import csv, sys
from collections import Counter
# Italy gate. Pins the B14 fix-and-fill pass: 4 Sardinian sigla fills
# (Gallura OT, Medio Campidano VS, Ogliastra OG, Sulcis SU — SU proven
# 2026-10-06 by CdM n.168 preliminare + n.181 definitivo + Lega press
# mapping, over stale ISTAT Feb-2026 CI), 48 postal fills, 2
# drops (32047 Sappada BL->UD move, 47023 Cesena generic retired),
# 08020 Sassari-leg drop, 08030 Oristano-leg drop + Cagliari re-primary,
# 09020 Cagliari secondary. Codes 4735 -> 4781,
# legs 4745 -> 4791, multis 9 -> 10 (09020 newly dual).
# Oracles: ISTAT Elenco-codici Feb-2026 xlsx (sigla + Sappada UD +
# Sarcidano/Cagliari-metro attributions), it.wiki Targa infoboxes +
# Codice-postale fields (Cesena 47521/47522), comuni.json
# (italia/city-plugs derived CAP table), NSC bank (04031/07051/07052/
# 09050/09051 VALID OT/CA localities), 3 courier CAP lists
# (Frangente-2025 + CantinePagnotta + MagicLand), en.wiki new-comune
# pages, addressed usage (Poste branches, municipal SUAP, hotel/booking
# listings). GeoNames IT.txt corroborates 20131-Milano and the 32047
# Sappada-BL old identity; its Cesena/Ravenna/Sardinia rows are stale
# or absent and were not used for dual-live calls.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_it.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/italy-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/italy-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/italy-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') and raw_c.count(b'\n') > 0)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') and raw_l.count(b'\n') > 0)
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 20 regions + 109 L2 (82 prov + 15 metro + 6 consortium + 4 decentr + 2 auton) ---
check('areas-129', len(rows) == 129, str(len(rows)))
check('l1-20', sum(1 for r in rows if r['level'] == '1') == 20)
check('l2-109', sum(1 for r in rows if r['level'] == '2') == 109)
check('sigla-gallura-ot', byid.get('it:province:gallura-north-east-sardinia', {}).get('code') == 'OT')
check('sigla-medio-campidano-vs', byid.get('it:province:medio-campidano', {}).get('code') == 'VS')
check('sigla-ogliastra-og', byid.get('it:province:ogliastra', {}).get('code') == 'OG')
check('sigla-sulcis-su', byid.get('it:province:sulcis-iglesiente', {}).get('code') == 'SU')
for sid in ('it:province:gallura-north-east-sardinia', 'it:province:medio-campidano',
            'it:province:ogliastra', 'it:province:sulcis-iglesiente'):
    check(f'sardegna-parent-{sid.split(":")[-1]}',
          byid.get(sid, {}).get('parent_source_id') == 'it:region:sardegna')
check('udine-decentr-ud', byid.get('it:decentralization_entity:udine', {}).get('code') == 'UD')
# --- postal: 4781 codes / 4791 legs / 10 multis ---
check('codes-4781', len(codes) == 4781, str(len(codes)))
check('legs-4791', len(links) == 4791, str(len(links)))
bycode = {}
for r in links:
    bycode.setdefault(r['postcode'], []).append(r)
multis = sorted(k for k, v in bycode.items() if len(v) > 1)
check('multis-10', len(multis) == 10, str(multis))
regcodes = {r['code'] for r in codes}
check('registry-equals-legs', regcodes == set(bycode), f'{len(regcodes)} vs {len(bycode)}')
badprim = [c for c, legs in bycode.items() if sum(1 for r in legs if r['is_primary'] == 'true') != 1]
check('one-primary-each', not badprim, str(badprim[:5]))
def legmap(code):
    return {r['area_source_id']: r['is_primary'] for r in bycode.get(code, [])}
# T1: 08020 drops the Sassari leg (ISTAT: zero Sassari-metro comuni).
check('08020-nuoro-gallura', legmap('08020') == {
    'it:province:nuoro': 'true',
    'it:province:gallura-north-east-sardinia': 'false'}, str(legmap('08020')))
# T2: 08030 drops Oristano, Cagliari primary over Nuoro (10 Sarcidano vs 7).
check('08030-cagliari-primary', legmap('08030') == {
    'it:metropolitan_city:cagliari': 'true',
    'it:province:nuoro': 'false'}, str(legmap('08030')))
# T3: 09020 gains Cagliari secondary (Ussana/Pimentel/Samatzai trio).
check('09020-vs-plus-ca', legmap('09020') == {
    'it:province:medio-campidano': 'true',
    'it:metropolitan_city:cagliari': 'false'}, str(legmap('09020')))
# T4+T5: drops absent.
check('32047-absent', '32047' not in bycode)
check('47023-absent', '47023' not in bycode)
# T6: holds absent.
for h in ('09132', '09133', '19127', '19128', '19129', '19130'):
    check(f'hold-{h}-absent', h not in bycode)
# T7: dual-live keeps.
check('71040-kept', legmap('71040') == {'it:province:foggia': 'true'}, str(legmap('71040')))
check('28922-kept', legmap('28922') == {'it:province:verbano-cusio-ossola': 'true'}, str(legmap('28922')))
check('20131-kept', legmap('20131') == {'it:metropolitan_city:milan': 'true'}, str(legmap('20131')))
# Fills: every new code primary-linked to its fixlist area.
fills = {'04031': 'it:province:latina', '07051': 'it:province:gallura-north-east-sardinia',
    '07052': 'it:province:gallura-north-east-sardinia',
    '09050': 'it:metropolitan_city:cagliari', '09051': 'it:metropolitan_city:cagliari',
    '09052': 'it:metropolitan_city:cagliari', '09053': 'it:metropolitan_city:cagliari',
    '09054': 'it:metropolitan_city:cagliari', '09055': 'it:metropolitan_city:cagliari',
    '09056': 'it:metropolitan_city:cagliari', '09057': 'it:metropolitan_city:cagliari',
    '09058': 'it:metropolitan_city:cagliari', '09059': 'it:metropolitan_city:cagliari',
    '09060': 'it:metropolitan_city:cagliari', '09061': 'it:metropolitan_city:cagliari',
    '09062': 'it:metropolitan_city:cagliari', '09063': 'it:metropolitan_city:cagliari',
    '09064': 'it:province:ogliastra', '09065': 'it:province:nuoro',
    '09066': 'it:metropolitan_city:cagliari', '09067': 'it:metropolitan_city:cagliari',
    '09068': 'it:metropolitan_city:cagliari', '09069': 'it:metropolitan_city:cagliari',
    '09089': 'it:province:oristano', '10079': 'it:metropolitan_city:turin',
    '15122': 'it:province:alessandria', '28921': 'it:province:verbano-cusio-ossola',
    '28923': 'it:province:verbano-cusio-ossola', '28924': 'it:province:verbano-cusio-ossola',
    '28925': 'it:province:verbano-cusio-ossola', '29031': 'it:province:piacenza',
    '33012': 'it:decentralization_entity:udine', '33014': 'it:decentralization_entity:udine',
    '36044': 'it:province:vicenza', '36048': 'it:province:vicenza',
    '41123': 'it:province:modena', '47521': 'it:province:forli-cesena',
    '47522': 'it:province:forli-cesena', '48121': 'it:province:ravenna',
    '48122': 'it:province:ravenna', '48123': 'it:province:ravenna',
    '48124': 'it:province:ravenna', '48125': 'it:province:ravenna',
    '52019': 'it:province:arezzo', '61036': 'it:province:pesaro-and-urbino',
    '62031': 'it:province:macerata', '71051': 'it:province:foggia',
    '82014': 'it:province:benevento'}
check('fills-48', len(fills) == 48, str(len(fills)))
badfill = {c: legmap(c) for c, a in fills.items() if legmap(c) != {a: 'true'}}
check('fills-primary', not badfill, str(badfill)[:300])
# Pre-existing multis preserved.
check('09010-sulcis-primary', legmap('09010') == {
    'it:province:sulcis-iglesiente': 'true',
    'it:metropolitan_city:cagliari': 'false'}, str(legmap('09010')))
check('07030-sassari-primary', legmap('07030') == {
    'it:metropolitan_city:sassari': 'true',
    'it:province:gallura-north-east-sardinia': 'false'}, str(legmap('07030')))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
