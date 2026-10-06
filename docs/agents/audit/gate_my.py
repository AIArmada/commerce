import csv, re, sys
from collections import Counter
A = './packages/addressing/resources/geography/malaysia-address-areas.csv'
C = './packages/addressing/resources/geography/malaysia-postal-codes.csv'
L = './packages/addressing/resources/geography/malaysia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-2222', len(rows) == 2222, str(len(rows)))
check('codes-3048', len(codes) == 3048, str(len(codes)))
check('links-3924', len(links) == 3924, str(len(links)))
types = Counter(r['type'] for r in rows)
check('states-13', types['state'] == 13, str(types['state']))
check('districts-163', types['district'] == 163, str(types['district']))
check('localities-246', types['locality'] == 246, str(types['locality']))
check('daerah-kecil-24', types['daerah_kecil'] == 24, str(types['daerah_kecil']))
# Padawan re-added as L4 daerah_kecil (open-log #3 L4 verdict): state portal
# sub-district column + Kuching division DK office + 11-Aug-1983 gazette
# history; admin-only, no postal links (postcode.my has no Padawan page).
check('padawan-dk', (byid.get('my:subdistrict:district:sarawak:kuching:padawan') or {}).get('type') == 'daerah_kecil')
check('padawan-no-links', all(l['area_source_id'] != 'my:subdistrict:district:sarawak:kuching:padawan' for l in links))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:3]))
have = {c['code'] for c in codes}
orph = [l['postcode'] for l in links if l['postcode'] not in have]
check('no-orphan-legs', not orph, str(orph[:3]))
legged = {l['postcode'] for l in links}
check('no-unlinked-codes', all(c in legged for c in have))
# 2026-10-06 open-log #2 retry fixes (gazette + MOH + addressed usage + directory)
by_post = {}
for l in links:
    by_post.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
check('pakan-96510-primary', ('my:subdistrict:district:sarawak:pakan:pakan', 'true') in by_post['96510'], str(by_post['96510']))
check('pakan-96100-dropped', all(a != 'my:subdistrict:district:sarawak:pakan:pakan' for a, _ in by_post['96100']), str(by_post['96100']))
check('sarikei-96100-primary', ('my:subdistrict:district:sarawak:sarikei:sarikei', 'true') in by_post['96100'])
check('tandek-89100', ('my:subdistrict:district:sabah:kota-marudu:tandek', 'false') in by_post['89100'])
check('tandek-not-89050', all(a != 'my:subdistrict:district:sabah:kota-marudu:tandek' for a, _ in by_post['89050']))
check('beluru-98050-dual', ('my:subdistrict:district:sarawak:beluru:beluru', 'false') in by_post['98050'], str(by_post['98050']))
check('beluru-98000-kept', ('my:subdistrict:district:sarawak:beluru:beluru', 'false') in by_post['98000'])
check('lachau-row', byid.get('my:subdistrict:district:sarawak:pantu:lachau', {}).get('type') == 'locality')
check('lachau-95000', ('my:subdistrict:district:sarawak:pantu:lachau', 'false') in by_post['95000'])
check('tenggang-row', byid.get('my:subdistrict:district:sarawak:pantu:sungai-tenggang', {}).get('type') == 'locality')
check('tenggang-95000', ('my:subdistrict:district:sarawak:pantu:sungai-tenggang', 'false') in by_post['95000'])
# retry holds / rejected leads (pinned so they cannot slip in silently)
check('langkon-89050-held', ('my:subdistrict:district:sabah:kota-marudu:langkon', 'false') in by_post['89050'])
check('no-tulid-row', 'my:subdistrict:district:sabah:keningau:tulid' not in byid)
check('song-town-only', [r['type'] for r in rows if r['parent_source_id'] == 'my:district:sarawak:song'] == ['locality'])
# office/stale codes keep their office legs and never gain the town legs
check('89308-ranau-counter', by_post['89308'] == [('my:subdistrict:district:sabah:ranau:pekan-ranau', 'true')], str(by_post['89308']))
check('89859-sipitang-lockbag', by_post['89859'] == [('my:subdistrict:district:sabah:sipitang:pekan-sipitang', 'true')], str(by_post['89859']))
check('91122-ld-pobox', by_post['91122'] == [('my:subdistrict:district:sabah:lahad-datu:bandar-lahad-datu', 'true')], str(by_post['91122']))
check('89500-donggongon', by_post['89500'] == [('my:subdistrict:district:sabah:penampang:pekan-donggongon', 'true')], str(by_post['89500']))
check('94100-stale-absent', '94100' not in by_post)
check('88400-not-menggatal', all(a != 'my:subdistrict:district:sabah:kota-kinabalu:menggatal' for a, _ in by_post['88400']), str(by_post['88400']))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
