#!/usr/bin/env python3
"""Step-1 structural audit for ALL bundled geography countries (pure CSV work).

Run from repo root:
  python3 docs/agents/audit/struct_all.py [SLUG ...]

Checks per country: counts by type/level, duplicate source_ids, orphans,
level-vs-parent consistency, parent code-prefix consistency (reported, not
failed: not every hierarchy uses prefix codes), postcode shape distribution,
exactly-one primary per code, dangling links, coverage per (level, type).

Writes regenerable scratch: /tmp/geo-verify/struct_all.json
Prints one line per country; non-clean lines carry FLAGs.
Exit 0 always (this is a report, not a gate); gates live in gate_<cc>.py.
"""
import csv
import json
import os
import re
import sys
from collections import Counter

GEO = './packages/addressing/resources/geography'
OUT = '/tmp/geo-verify/struct_all.json'


def read_rows(path):
    with open(path, newline='', encoding='utf-8-sig') as f:
        return list(csv.DictReader(f))


def code_shape(code):
    if re.fullmatch(r'\d+', code):
        return f'digits-{len(code)}'
    if ' ' in code:
        return 'has-space'
    if '-' in code:
        return 'has-dash'
    if re.fullmatch(r'[A-Z]+', code):
        return 'alpha-up'
    if re.fullmatch(r'[A-Z0-9]+', code):
        return 'alnum-up'
    return 'other'


def audit_country(slug):
    areas_path = os.path.join(GEO, f'{slug}-address-areas.csv')
    codes_path = os.path.join(GEO, f'{slug}-postal-codes.csv')
    links_path = os.path.join(GEO, f'{slug}-postal-code-areas.csv')
    areas = read_rows(areas_path)
    cc = areas[0]['country_code'] if areas else '?'
    by_id = {}
    dup_ids = []
    for r in areas:
        sid = r['source_id']
        if sid in by_id:
            dup_ids.append(sid)
        by_id[sid] = r
    orphans, self_par, level_bad, cc_bad = [], [], [], []
    prefix_ok, prefix_bad, prefix_ex = 0, 0, []
    for r in areas:
        p = (r.get('parent_source_id') or '').strip()
        if not p:
            continue
        if p == r['source_id']:
            self_par.append(r['source_id'])
            continue
        par = by_id.get(p)
        if par is None:
            orphans.append(r['source_id'])
            continue
        try:
            if int(r.get('level') or -1) != int(par.get('level') or -2) + 1:
                level_bad.append(r['source_id'])
        except ValueError:
            level_bad.append(r['source_id'] + '(non-int-level)')
        ccode, pcode = (r.get('code') or '').strip(), (par.get('code') or '').strip()
        if ccode and pcode:
            if ccode.startswith(pcode):
                prefix_ok += 1
            else:
                prefix_bad += 1
                if len(prefix_ex) < 5:
                    prefix_ex.append(f"{r['source_id']}={ccode} parent {p}={pcode}")
    for r in areas:
        if (r.get('country_code') or '').strip() != cc:
            cc_bad.append(r['source_id'])
    by_type = dict(Counter(r.get('type', '') for r in areas))
    by_level = dict(Counter(r.get('level', '') for r in areas))

    codes_have = os.path.exists(codes_path)
    links_have = os.path.exists(links_path)
    codes, dup_codes, shapes = [], [], {}
    if codes_have:
        for r in read_rows(codes_path):
            codes.append((r.get('country_code', '') or '').strip() + ':' + (r.get('code', '') or ''))
        seen, dup_codes = set(), []
        for c in codes:
            if c in seen:
                dup_codes.append(c)
            seen.add(c)
        shapes = dict(Counter(code_shape(c.split(':', 1)[1]) for c in codes))
    links = read_rows(links_path) if links_have else []
    code_set = set(codes)
    primaries = Counter()
    dangle_area, dangle_code, rel_types = [], [], Counter()
    for l in links:
        pc = (l.get('postcode') or '').strip()
        aid = (l.get('area_source_id') or '').strip()
        rel_types[l.get('relationship_type', '')] += 1
        if aid not in by_id:
            dangle_area.append(f'{pc}->{aid}')
        if codes_have and f'{cc}:{pc}' not in code_set:
            dangle_code.append(pc)
        if (l.get('is_primary') or '').strip().lower() == 'true':
            primaries[pc] += 1
        else:
            primaries.setdefault(pc, primaries.get(pc, 0))
    all_pcs = set((l.get('postcode') or '').strip() for l in links)
    no_prim = sorted(p for p in all_pcs if primaries.get(p, 0) == 0)
    multi_prim = sorted(p for p in all_pcs if primaries.get(p, 0) > 1)
    linked = set((l.get('area_source_id') or '').strip() for l in links) & set(by_id)
    coverage = {}
    groups = {}
    for r in areas:
        groups.setdefault((r.get('level', ''), r.get('type', '')), []).append(r['source_id'])
    for key, ids in sorted(groups.items()):
        unlinked = [i for i in ids if i not in linked]
        coverage[f'L{key[0]}:{key[1]}'] = {'total': len(ids), 'linked': len(ids) - len(unlinked),
                                           'unlinked_ex': unlinked[:8]}
    flags = []
    if dup_ids:
        flags.append(f'dup-ids:{len(dup_ids)}')
    if orphans:
        flags.append(f'orphans:{len(orphans)}')
    if self_par:
        flags.append(f'self-parent:{len(self_par)}')
    if level_bad:
        flags.append(f'level-mismatch:{len(level_bad)}')
    if cc_bad:
        flags.append(f'cc-mismatch:{len(cc_bad)}')
    if dup_codes:
        flags.append(f'dup-codes:{len(dup_codes)}')
    if dangle_area:
        flags.append(f'dangle-area:{len(dangle_area)}')
    if dangle_code:
        flags.append(f'dangle-code:{len(set(dangle_code))}')
    if no_prim:
        flags.append(f'no-primary:{len(no_prim)}')
    if multi_prim:
        flags.append(f'multi-primary:{len(multi_prim)}')
    prefix_note = 'n/a'
    if prefix_ok + prefix_bad >= 10:
        prefix_note = f'{prefix_ok}/{prefix_ok + prefix_bad}'
        if prefix_bad > prefix_ok:
            flags.append(f'prefix-minority:{prefix_note}')
    return {'slug': slug, 'cc': cc, 'areas': len(areas), 'by_type': by_type,
            'by_level': by_level, 'dup_ids': dup_ids[:10], 'orphans': orphans[:10],
            'orphan_n': len(orphans), 'self_parent': self_par[:10], 'level_bad': level_bad[:10],
            'level_bad_n': len(level_bad), 'cc_bad': cc_bad[:10], 'prefix': prefix_note,
            'prefix_bad_ex': prefix_ex, 'codes_file': codes_have, 'codes_n': len(codes),
            'codes_distinct': len(set(codes)), 'dup_codes': dup_codes[:10], 'shapes': shapes,
            'links_file': links_have, 'links_n': len(links), 'rel_types': dict(rel_types),
            'dangle_area': dangle_area[:10], 'dangle_area_n': len(dangle_area),
            'dangle_code_ex': sorted(set(dangle_code))[:10],
            'dangle_code_n': len(set(dangle_code)), 'no_primary': no_prim[:10],
            'no_primary_n': len(no_prim), 'multi_primary': multi_prim[:10],
            'multi_primary_n': len(multi_prim), 'coverage': coverage, 'flags': flags}


def main():
    only = set(sys.argv[1:])
    slugs = sorted(f[:-len('-address-areas.csv')]
                   for f in os.listdir(GEO) if f.endswith('-address-areas.csv'))
    if only:
        slugs = [s for s in slugs if s in only]
    results = [audit_country(s) for s in slugs]
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, 'w', encoding='utf-8') as f:
        json.dump(results, f, indent=1, sort_keys=True)
    n_flag = 0
    for r in results:
        flag_str = (' FLAGS:' + ','.join(r['flags'])) if r['flags'] else ''
        if r['flags']:
            n_flag += 1
        cov = ' '.join(f"{k}={v['linked']}/{v['total']}" for k, v in r['coverage'].items())
        print(f"{r['slug']} [{r['cc']}] areas={r['areas']} codes={r['codes_n']} "
              f"links={r['links_n']} orph={r['orphan_n']} dangA={r['dangle_area_n']} "
              f"dangC={r['dangle_code_n']} noP={r['no_primary_n']} multiP={r['multi_primary_n']} "
              f"prefix={r['prefix']} cov:[{cov}]{flag_str}")
    print(f'--- {len(results)} countries, {n_flag} with flags -> {OUT}')


if __name__ == '__main__':
    main()
