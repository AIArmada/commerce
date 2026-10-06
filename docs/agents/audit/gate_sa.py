import csv, re, sys
from collections import Counter
# Saudi Arabia gate. M5 revisit: tree verified clean against ISO
# 3166-2:SA (13 regions, codes 01-12 + 14, no 13) and the Wikipedia
# Governorates list (139 names + parents, exact incl. order);
# postal fix = 86365/86366/86369 Madinah->Jizan (Samtah block;
# 86366 triple-proven Jizan: current Mapanet Samtah filing + OSM
# Samtah reverse + Al Hijfar village in Jizan per Wikipedia/56ok;
# siblings by unanimous-block extension). Run from repo root:
# python3 docs/agents/audit/gate_sa.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/saudi-arabia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/saudi-arabia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/saudi-arabia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 13 regions + 139 governorates ---
check('areas-152', len(rows) == 152, str(len(rows)))
check('regions-13', sum(1 for r in rows if r['type'] == 'region') == 13)
check('governorates-139', sum(1 for r in rows if r['type'] == 'governorate') == 139)
regions = {'01': 'Riyadh', '02': 'Makkah', '03': 'Al Madinah',
 '04': 'Eastern Province', '05': 'Al-Qassim', '06': "Ha'il",
 '07': 'Tabuk', '08': 'Northern Borders', '09': 'Jizan',
 '10': 'Najran', '11': 'Al Bahah', '12': 'Al Jawf', '14': 'Asir'}
for code, name in regions.items():
    got = [r for r in rows if r['type'] == 'region' and r['code'] == code]
    check(f'region-{code}', len(got) == 1 and got[0]['name'] == name
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
check('no-region-13', not [r for r in rows if r['type'] == 'region' and r['code'] == '13'])
# ISO 3166-2:SA names for the record: 01 Ar Riyad, 02 Makkah al
# Mukarramah, 03 Al Madinah al Munawwarah, 04 Ash Sharqiyah, 05 Al
# Qasim, 06 Ha'il, 07 Tabuk, 08 Al Hudud ash Shamaliyah, 09 Jazan,
# 10 Najran, 11 Al Bahah, 12 Al Jawf, 14 'Asir (no SA-13).
# all 139 governorates: (source_id, name, parent region slug).
# verified SAME as the wikipedia governorates list (names + parents
# + order, 13/13 regions). slugs expand to sa:region:<slug>.
govs = [
 ("sa:governorate:umluj", "Umluj", "tabuk"),
 ("sa:governorate:al-wajh", "Al-Wajh", "tabuk"),
 ("sa:governorate:duba", "Duba", "tabuk"),
 ("sa:governorate:tayma", "Tayma", "tabuk"),
 ("sa:governorate:haql", "Haql", "tabuk"),
 ("sa:governorate:al-bad", "Al-Bad'", "tabuk"),
 ("sa:governorate:hait", "Hait", "ha-il"),
 ("sa:governorate:baqaa", "Baqaa", "ha-il"),
 ("sa:governorate:shanan", "Shanan", "ha-il"),
 ("sa:governorate:shamli", "Shamli", "ha-il"),
 ("sa:governorate:sumairah", "Sumairah", "ha-il"),
 ("sa:governorate:sulaimi", "Sulaimi", "ha-il"),
 ("sa:governorate:mawqaq", "Mawqaq", "ha-il"),
 ("sa:governorate:ghazalah", "Ghazalah", "ha-il"),
 ("sa:governorate:rafha", "Rafha", "northern-borders"),
 ("sa:governorate:turaif", "Turaif", "northern-borders"),
 ("sa:governorate:al-uwayqilah", "Al-Uwayqilah", "northern-borders"),
 ("sa:governorate:yanbu", "Yanbu", "al-madinah"),
 ("sa:governorate:al-ula", "al-Ula", "al-madinah"),
 ("sa:governorate:badr", "Badr", "al-madinah"),
 ("sa:governorate:mahd", "Mahd", "al-madinah"),
 ("sa:governorate:khaybar", "Khaybar", "al-madinah"),
 ("sa:governorate:al-hunakiyah", "Al-Hunakiyah", "al-madinah"),
 ("sa:governorate:wadi-al-fara", "Wadi al-Fara", "al-madinah"),
 ("sa:governorate:al-ais", "Al-Ais", "al-madinah"),
 ("sa:governorate:al-ahsa", "Al-Ahsa", "eastern-province"),
 ("sa:governorate:khobar", "Khobar", "eastern-province"),
 ("sa:governorate:qatif", "Qatif", "eastern-province"),
 ("sa:governorate:jubail", "Jubail", "eastern-province"),
 ("sa:governorate:hafar-al-batin", "Hafar al-Batin", "eastern-province"),
 ("sa:governorate:khafji", "Khafji", "eastern-province"),
 ("sa:governorate:nariyah", "Nariyah", "eastern-province"),
 ("sa:governorate:abqaiq", "Abqaiq", "eastern-province"),
 ("sa:governorate:ras-tanura", "Ras Tanura", "eastern-province"),
 ("sa:governorate:qaryat-al-ulya", "Qaryat al-Ulya", "eastern-province"),
 ("sa:governorate:al-udeid", "Al-Udeid", "eastern-province"),
 ("sa:governorate:al-bayda", "Al-Bayda", "eastern-province"),
 ("sa:governorate:sharurah", "Sharurah", "najran"),
 ("sa:governorate:habona", "Habona", "najran"),
 ("sa:governorate:yadamah", "Yadamah", "najran"),
 ("sa:governorate:thar", "Thar", "najran"),
 ("sa:governorate:badr-al-janub", "Badr Al-Janub", "najran"),
 ("sa:governorate:khubash", "Khubash", "najran"),
 ("sa:governorate:qurayyat", "Qurayyat", "al-jawf"),
 ("sa:governorate:dumat-al-jandal", "Dumat al-Jandal", "al-jawf"),
 ("sa:governorate:tabarjal", "Tabarjal", "al-jawf"),
 ("sa:governorate:unaizah", "Unaizah", "al-qassim"),
 ("sa:governorate:ar-rass", "Ar Rass", "al-qassim"),
 ("sa:governorate:al-bukiryah", "Al-Bukiryah", "al-qassim"),
 ("sa:governorate:al-badai", "Al-Badai'", "al-qassim"),
 ("sa:governorate:al-mithnab", "Al-Mithnab", "al-qassim"),
 ("sa:governorate:al-nabhaniyah", "Al-Nabhaniyah", "al-qassim"),
 ("sa:governorate:asyah", "Asyah", "al-qassim"),
 ("sa:governorate:riyadh-al-khabra", "Riyadh Al-Khabra", "al-qassim"),
 ("sa:governorate:uyun-al-jiwa", "Uyun Al-Jiwa", "al-qassim"),
 ("sa:governorate:dhariyah", "Dhariyah", "al-qassim"),
 ("sa:governorate:uqlat-al-suqur", "Uqlat Al-Suqur", "al-qassim"),
 ("sa:governorate:al-shimasiyah", "Al-Shimasiyah", "al-qassim"),
 ("sa:governorate:abanat", "Abanat", "al-qassim"),
 ("sa:governorate:baljurashi", "Baljurashi", "al-bahah"),
 ("sa:governorate:al-mikhwah", "Al-Mikhwah", "al-bahah"),
 ("sa:governorate:al-aqiq", "Al-Aqiq", "al-bahah"),
 ("sa:governorate:qilwah", "Qilwah", "al-bahah"),
 ("sa:governorate:al-mandaq", "Al-Mandaq", "al-bahah"),
 ("sa:governorate:al-qura", "Al-Qura", "al-bahah"),
 ("sa:governorate:bani-hasan", "Bani Hasan", "al-bahah"),
 ("sa:governorate:far-at-ghamid-az-zinad", "Far'at Ghamid az-Zinad", "al-bahah"),
 ("sa:governorate:al-hujrah", "Al-Hujrah", "al-bahah"),
 ("sa:governorate:jeddah", "Jeddah", "makkah"),
 ("sa:governorate:taif", "Taif", "makkah"),
 ("sa:governorate:al-qunfudhah", "Al-Qunfudhah", "makkah"),
 ("sa:governorate:rabigh", "Rabigh", "makkah"),
 ("sa:governorate:bahrah", "Bahrah", "makkah"),
 ("sa:governorate:al-jumum", "Al-Jumum", "makkah"),
 ("sa:governorate:al-lith", "Al-Lith", "makkah"),
 ("sa:governorate:al-ardiyat", "Al-Ardiyat", "makkah"),
 ("sa:governorate:khulays", "Khulays", "makkah"),
 ("sa:governorate:ranyah", "Ranyah", "makkah"),
 ("sa:governorate:turubah", "Turubah", "makkah"),
 ("sa:governorate:al-khurmah", "Al-Khurmah", "makkah"),
 ("sa:governorate:adum", "Adum", "makkah"),
 ("sa:governorate:al-muwayh", "Al-Muwayh", "makkah"),
 ("sa:governorate:maysan", "Maysan", "makkah"),
 ("sa:governorate:al-kamil", "Al-Kamil", "makkah"),
 ("sa:governorate:al-kharj", "Al-Kharj", "riyadh"),
 ("sa:governorate:al-dawadmi", "Al-Dawadmi", "riyadh"),
 ("sa:governorate:majmaah", "Majmaah", "riyadh"),
 ("sa:governorate:diriyah", "Diriyah", "riyadh"),
 ("sa:governorate:wadi-al-dawasir", "Wadi Al-Dawasir", "riyadh"),
 ("sa:governorate:al-zulfi", "Al-Zulfi", "riyadh"),
 ("sa:governorate:afif", "Afif", "riyadh"),
 ("sa:governorate:al-quway-iyah", "Al-Quway'iyah", "riyadh"),
 ("sa:governorate:al-muzahmiyya", "Al-Muzahmiyya", "riyadh"),
 ("sa:governorate:al-dilam", "Al-Dilam", "riyadh"),
 ("sa:governorate:al-aflaj", "Al-Aflaj", "riyadh"),
 ("sa:governorate:shaqra", "Shaqra", "riyadh"),
 ("sa:governorate:hotat-bani-tamim", "Hotat Bani Tamim", "riyadh"),
 ("sa:governorate:rimah", "Rimah", "riyadh"),
 ("sa:governorate:al-sulayyil", "Al-Sulayyil", "riyadh"),
 ("sa:governorate:al-rayn", "Al-Rayn", "riyadh"),
 ("sa:governorate:dhurma", "Dhurma", "riyadh"),
 ("sa:governorate:huraymila", "Huraymila", "riyadh"),
 ("sa:governorate:thadig", "Thadig", "riyadh"),
 ("sa:governorate:al-ghat", "Al-Ghat", "riyadh"),
 ("sa:governorate:al-hariq", "Al-Hariq", "riyadh"),
 ("sa:governorate:marat", "Marat", "riyadh"),
 ("sa:governorate:khamis-mushait", "Khamis Mushait", "asir"),
 ("sa:governorate:muhayil", "Muhayil", "asir"),
 ("sa:governorate:bisha", "Bisha", "asir"),
 ("sa:governorate:ahad-rafidah", "Ahad Rafidah", "asir"),
 ("sa:governorate:balqarn", "Balqarn", "asir"),
 ("sa:governorate:sarat-ubaida", "Sarat Ubaida", "asir"),
 ("sa:governorate:rijal-almaa", "Rijal Almaa", "asir"),
 ("sa:governorate:al-majaridah", "Al-Majaridah", "asir"),
 ("sa:governorate:bariq", "Bariq", "asir"),
 ("sa:governorate:al-namas", "Al-Namas", "asir"),
 ("sa:governorate:tathlith", "Tathlith", "asir"),
 ("sa:governorate:dhahran-al-janub", "Dhahran Al-Janub", "asir"),
 ("sa:governorate:al-birk", "Al-Birk", "asir"),
 ("sa:governorate:tarib", "Tarib", "asir"),
 ("sa:governorate:al-harjah", "Al-Harjah", "asir"),
 ("sa:governorate:tanomah", "Tanomah", "asir"),
 ("sa:governorate:al-amwah", "Al-Amwah", "asir"),
 ("sa:governorate:sabya", "Sabya", "jizan"),
 ("sa:governorate:abu-arish", "Abu 'Arish", "jizan"),
 ("sa:governorate:samtah", "Samtah", "jizan"),
 ("sa:governorate:ahad-al-masarihah", "Ahad Al-Masarihah", "jizan"),
 ("sa:governorate:baish", "Baish", "jizan"),
 ("sa:governorate:al-ardah", "Al-Ardah", "jizan"),
 ("sa:governorate:al-darb", "Al-Darb", "jizan"),
 ("sa:governorate:damad", "Damad", "jizan"),
 ("sa:governorate:al-dayer", "Al-Dayer", "jizan"),
 ("sa:governorate:al-tuwal", "Al-Tuwal", "jizan"),
 ("sa:governorate:al-eidabi", "Al-Eidabi", "jizan"),
 ("sa:governorate:harub", "Harub", "jizan"),
 ("sa:governorate:fayfa", "Fayfa", "jizan"),
 ("sa:governorate:ar-rayth", "Ar Rayth", "jizan"),
 ("sa:governorate:farasan-islands", "Farasan Islands", "jizan"),
 ("sa:governorate:al-harth", "Al-Harth", "jizan"),
]

bad = []
for sid, name, pslug in govs:
    r = byid.get(sid)
    if (not r or r['name'] != name or r['type'] != 'governorate'
            or r['level'] != '2'
            or r['parent_source_id'] != f'sa:region:{pslug}'):
        bad.append(sid)
check('govs-139', len(govs) == 139 and not bad, str(bad[:3]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
exp_gov_counts = {'sa:region:riyadh': 22, 'sa:region:makkah': 16,
 'sa:region:al-madinah': 8, 'sa:region:eastern-province': 12,
 'sa:region:al-qassim': 13, 'sa:region:ha-il': 8, 'sa:region:tabuk': 6,
 'sa:region:northern-borders': 3, 'sa:region:jizan': 16,
 'sa:region:najran': 6, 'sa:region:al-bahah': 9, 'sa:region:al-jawf': 3,
 'sa:region:asir': 17}
have_gov = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
wrong = [k for k, n in exp_gov_counts.items() if have_gov.get(k, 0) != n]
check('per-region-gov-counts', not wrong, str(wrong))
# --- postal: 9256 codes / 9256 links, all L1 single primaries ---
check('codes-9256', len(codes) == 9256, str(len(codes)))
check('links-9256', len(links) == 9256, str(len(links)))
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
check('exactly-one-primary', len(prim) == 9256 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('zero-multilink', all(c == 1 for c in counts.values()))
check('all-l1', all(l['area_source_id'].startswith('sa:region:') for l in links))
# 11xxx is P.O.-Box space (UPU SAU profile's own POB example is
# 11564), correctly absent from this Wasel home-delivery dataset.
check('no-11xxx', not [c for c in cset if c.startswith('11')])
# digit grain: 1 riyadh, 2 makkah, 3 eastern, 4 madinah/tabuk,
# 5 qassim/ha-il, 6 asir/bahah/najran, 7 jawf/nb, 8 jizan.
grain = {'1': {'sa:region:riyadh'}, '2': {'sa:region:makkah'},
 '3': {'sa:region:eastern-province'},
 '4': {'sa:region:al-madinah', 'sa:region:tabuk'},
 '5': {'sa:region:al-qassim', 'sa:region:ha-il'},
 '6': {'sa:region:asir', 'sa:region:al-bahah', 'sa:region:najran'},
 '7': {'sa:region:al-jawf', 'sa:region:northern-borders'},
 '8': {'sa:region:jizan'}}
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
off = {pc: a for pc, a in pin.items() if a not in grain[pc[0]]}
exp_off = {
 '17276': 'sa:region:al-qassim', '26871': 'sa:region:al-bahah',
 '28769': 'sa:region:al-bahah', '28794': 'sa:region:al-bahah',
 '28795': 'sa:region:al-bahah', '28798': 'sa:region:al-bahah',
 '28799': 'sa:region:al-bahah', '28957': 'sa:region:asir',
 '28997': 'sa:region:asir', '42427': 'sa:region:al-qassim',
 '56988': 'sa:region:riyadh', '56994': 'sa:region:riyadh',
 '56997': 'sa:region:riyadh', '58259': 'sa:region:riyadh',
 '58277': 'sa:region:riyadh', '58278': 'sa:region:riyadh',
 '58279': 'sa:region:riyadh', '58288': 'sa:region:riyadh',
 '58289': 'sa:region:riyadh', '58456': 'sa:region:riyadh',
 '58459': 'sa:region:riyadh', '58617': 'sa:region:riyadh',
 '58627': 'sa:region:riyadh', '58791': 'sa:region:riyadh',
 '58793': 'sa:region:riyadh', '63666': 'sa:region:makkah',
 '63828': 'sa:region:makkah', '63843': 'sa:region:makkah',
 '63844': 'sa:region:makkah', '63864': 'sa:region:makkah',
 '65264': 'sa:region:makkah', '65379': 'sa:region:makkah',
 '65394': 'sa:region:makkah', '65397': 'sa:region:makkah',
 '65399': 'sa:region:makkah', '65434': 'sa:region:makkah',
 '65435': 'sa:region:makkah', '65457': 'sa:region:makkah',
 '65487': 'sa:region:makkah', '65489': 'sa:region:makkah',
 '65493': 'sa:region:makkah', '65495': 'sa:region:makkah',
 '65498': 'sa:region:makkah', '65499': 'sa:region:makkah',
 '65996': 'sa:region:makkah', '89933': 'sa:region:asir',
 '89936': 'sa:region:asir', '89973': 'sa:region:asir',
}
check('off-grain-48', off == exp_off,
      f'extra={sorted(set(off) - set(exp_off))[:3]} missing={sorted(set(exp_off) - set(off))[:3]}'
      f' flipped={[pc for pc in exp_off if pc in off and off[pc] != exp_off[pc]][:3]}')
# sampled adjudication pins (OSM-reverse verdicts in verdict.md):
# 28769 bahah/mukhwa, 58459 riyadh/dawadmi (qassim-row dup proven),
# 58276 qassim/dhariyah (overrules mapanet riyadh filing),
# 65434+65457 makkah/ardiyat + 65495 makkah/qunfudah (all mapanet-
# filed bahah/asir only: no ardiyat zone), 89973 asir/rijal both
# rows, 89799+89934 jizan/darb controls, 56967 qassim/maznab.
for pc, sid in [('28769', 'sa:region:al-bahah'),
 ('26871', 'sa:region:al-bahah'), ('28957', 'sa:region:asir'),
 ('58459', 'sa:region:riyadh'), ('58276', 'sa:region:al-qassim'),
 ('56967', 'sa:region:al-qassim'), ('63666', 'sa:region:makkah'),
 ('65434', 'sa:region:makkah'), ('65457', 'sa:region:makkah'),
 ('65495', 'sa:region:makkah'), ('89933', 'sa:region:asir'),
 ('89973', 'sa:region:asir'), ('89799', 'sa:region:jizan'),
 ('89934', 'sa:region:jizan'), ('17276', 'sa:region:al-qassim'),
 ('42427', 'sa:region:al-qassim')]:
    check(f'pin-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# keeps on ties (filing vs OSM split; see verdict.md residuals):
# 56988/56994/58259/58279/58617/58627 riyadh (OSM qassim-side),
# 65379/65394/65487 makkah (rows split bahah/makkah), 65996 makkah
# (OSM bahah/qura), 28997 asir (rows split makkah/asir), 89936
# asir (both sampled rows OSM jizan/darb).
for pc, sid in [('56988', 'sa:region:riyadh'),
 ('58259', 'sa:region:riyadh'), ('65379', 'sa:region:makkah'),
 ('65487', 'sa:region:makkah'), ('65996', 'sa:region:makkah'),
 ('28997', 'sa:region:asir'), ('89936', 'sa:region:asir')]:
    check(f'keep-{pc}', pin.get(pc) == sid, str(pin.get(pc)))
# M5 flips: 8636x samtah block madinah->jizan.
for pc in ['86365', '86366', '86369']:
    check(f'flip-{pc}-jizan', pin.get(pc) == 'sa:region:jizan',
          str(pin.get(pc)))
# block-run guard: shipped 863xx neighbours stay jizan.
for pc in ['86371', '86374', '86376', '86391', '86393',
 '86394', '86396', '86397']:
    if pc in pin:
        check(f'run-{pc}-jizan', pin.get(pc) == 'sa:region:jizan',
              str(pin.get(pc)))
# per-region primary counts (post-flip: madinah 651, jizan 948).
exp_counts = {'sa:region:al-bahah': 374, 'sa:region:al-jawf': 384,
 'sa:region:al-madinah': 651, 'sa:region:al-qassim': 966,
 'sa:region:asir': 1218, 'sa:region:eastern-province': 724,
 'sa:region:ha-il': 745, 'sa:region:jizan': 948,
 'sa:region:makkah': 1868, 'sa:region:najran': 193,
 'sa:region:northern-borders': 65, 'sa:region:riyadh': 943,
 'sa:region:tabuk': 177}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-region-counts-13', not wrong, str(wrong))
check('covered-13', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 13)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
