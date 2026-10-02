"""Place-name voter for Indonesia postal-code verdicts (rebuilt, persisted).

Method: normalize a place name, match against our own village/district tree
names, and count votes per regency (L2). Matches constrained by code-prefix ->
province plausibility (prov_ok) plus an unconstrained nationwide net.
"""
import csv
import re
import unicodedata
from collections import Counter, defaultdict

P = '/Users/Saiffil/Herd/commerce/packages/addressing/resources/geography/'

STRIP_PREFIX = re.compile(
    r'^(DESA|KELURAHAN|KEL|KECAMATAN|KEC|KOTA|KABUPATEN|KAB|DISTRIK|KAMPUNG|KP|KPG|GG|JL|JLN|JALAN)\.?\s+',
    re.I)
PUNCT = re.compile(r'[^A-Z0-9 ]+')


def norm(s):
    s = unicodedata.normalize('NFKD', (s or '').upper()).encode('ascii', 'ignore').decode()
    s = s.replace("'", ' ').replace('-', ' ')
    s = PUNCT.sub(' ', s)
    s = re.sub(r'\s+', ' ', s).strip()
    s = STRIP_PREFIX.sub('', s)
    s = re.sub(r'\s+', ' ', s).strip()
    # roman numerals / trailing numbers often noise in book rows
    return s


VILL_BY_NAME = defaultdict(list)   # norm name -> [(regency_code, district_code)]
DIST_BY_NAME = defaultdict(list)
REGPROV = {}                        # regency code -> province code
REGNAME = {}
PROVNAME = {}
PREFIX_PROV = {}                    # 2-digit prefix -> set(province codes), learned from tree+links


def load_all():
    if REGPROV:
        return
    reg_of_dist = {}
    for r in csv.DictReader(open(P + 'indonesia-address-areas.csv', encoding='utf-8-sig')):
        sid = r['source_id']
        if sid.startswith('id:regency:'):
            REGPROV[r['code']] = r['parent_source_id'].split(':')[-1]
            REGNAME[r['code']] = r['name']
        elif sid.startswith('id:district:'):
            reg_of_dist[r['code']] = r['parent_source_id'].split(':')[-1]
            n = norm(r['name'])
            if n:
                DIST_BY_NAME[n].append((r['parent_source_id'].split(':')[-1], r['code']))
        elif sid.startswith('id:province:'):
            PROVNAME[r['code']] = r['name']
    for r in csv.DictReader(open(P + 'indonesia-villages.csv', encoding='utf-8-sig')):
        n = norm(r['name'])
        if not n:
            continue
        d = r['parent_source_id'].split(':')[-1]
        reg = reg_of_dist.get(d)
        if reg:
            VILL_BY_NAME[n].append((reg, d))
    # learn prefix->province from current links
    links = defaultdict(set)
    area_prov = {}
    for r in csv.DictReader(open(P + 'indonesia-address-areas.csv', encoding='utf-8-sig')):
        sid = r['source_id']
        if sid.startswith('id:province:'):
            area_prov[sid] = r['code']
        elif sid.startswith('id:regency:'):
            area_prov[sid] = r['parent_source_id'].split(':')[-1]
        elif sid.startswith('id:district:'):
            reg = r['parent_source_id'].split(':')[-1]
            area_prov[sid] = REGPROV.get(reg)
    for r in csv.DictReader(open(P + 'indonesia-postal-code-areas.csv', encoding='utf-8-sig')):
        prov = area_prov.get(r['area_source_id'])
        if prov:
            PREFIX_PROV.setdefault(r['postcode'][:2], set()).add(prov)


def vote_place(place, strict=True):
    """Return Counter regency_code -> votes for one place string."""
    votes = Counter()
    n = norm(place)
    if not n:
        return votes
    hits = VILL_BY_NAME.get(n, []) + DIST_BY_NAME.get(n, [])
    if hits:
        for reg, _ in hits:
            votes[reg] += 2 if n in DIST_BY_NAME else 1
        return votes
    if strict:
        return votes
    # loose: token-subset fallback
    toks = set(n.split())
    if len(toks) < 2:
        return votes
    for name, lst in VILL_BY_NAME.items():
        nt = set(name.split())
        if toks <= nt or nt <= toks:
            for reg, _ in lst:
                votes[reg] += 1
    return votes


def prov_ok(code, prov):
    if not prov:
        return False
    return prov in PREFIX_PROV.get(code[:2], set())
