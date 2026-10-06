import csv, os, sys
# Cape Verde gate. B9 revisit: areas verify-only (22 concelhos +
# 2 island groups ISO-pinned + 32 freguesias pinned); postal
# stays admin-ready (no CSVs): UPU POST*CODE Aug-2026 puts CV
# on the require-list (format 9999, length 4) but the full CPN
# allocation was portal-only (codigopostal.cv, dead since ~2020,
# unarchived for data) and the ARME 2019 annex carries examples
# only; no allocation table published. If a list publishes,
# add the CSV pair and update this gate + overlay row.
# Run from repo root:
# python3 docs/agents/audit/gate_cv.py
A = './packages/addressing/resources/geography/cape-verde-address-areas.csv'
C = './packages/addressing/resources/geography/cape-verde-postal-codes.csv'
L = './packages/addressing/resources/geography/cape-verde-postal-code-areas.csv'
L1 = {'cv:geographical_region:barlavento-islands': ('Barlavento Islands', 'B'),
      'cv:municipality:boa-vista': ('Boa Vista', 'BV'),
      'cv:municipality:brava': ('Brava', 'BR'),
      'cv:municipality:maio': ('Maio', 'MA'),
      'cv:municipality:mosteiros': ('Mosteiros', 'MO'),
      'cv:municipality:paul': ('Paul', 'PA'),
      'cv:municipality:porto-novo': ('Porto Novo', 'PN'),
      'cv:municipality:praia': ('Praia', 'PR'),
      'cv:municipality:ribeira-brava': ('Ribeira Brava', 'RB'),
      'cv:municipality:ribeira-grande': ('Ribeira Grande', 'RG'),
      'cv:municipality:ribeira-grande-de-santiago': ('Ribeira Grande de Santiago', 'RS'),
      'cv:municipality:sal': ('Sal', 'SL'),
      'cv:municipality:santa-catarina': ('Santa Catarina', 'CA'),
      'cv:municipality:santa-catarina-do-fogo': ('Santa Catarina do Fogo', 'CF'),
      'cv:municipality:santa-cruz': ('Santa Cruz', 'CR'),
      'cv:municipality:sao-domingos': ('São Domingos', 'SD'),
      'cv:municipality:sao-filipe': ('São Filipe', 'SF'),
      'cv:municipality:sao-lourenco-dos-orgaos': ('São Lourenço dos Órgãos', 'SO'),
      'cv:municipality:sao-miguel': ('São Miguel', 'SM'),
      'cv:municipality:sao-salvador-do-mundo': ('São Salvador do Mundo', 'SS'),
      'cv:municipality:sao-vicente': ('São Vicente', 'SV'),
      'cv:geographical_region:sotavento-islands': ('Sotavento Islands', 'S'),
      'cv:municipality:tarrafal': ('Tarrafal', 'TA'),
      'cv:municipality:tarrafal-de-sao-nicolau': ('Tarrafal de São Nicolau', 'TS')}
PARISHES = {
 'cv:municipality:tarrafal': ['Santo Amaro Abade'],
 'cv:municipality:sao-miguel': ['São Miguel Arcanjo'],
 'cv:municipality:sao-salvador-do-mundo': ['São Salvador do Mundo'],
 'cv:municipality:santa-cruz': ['Santiago Maior'],
 'cv:municipality:sao-domingos': ['Nossa Senhora da Luz', 'São Nicolau Tolentino'],
 'cv:municipality:praia': ['Nossa Senhora da Graça'],
 'cv:municipality:ribeira-grande-de-santiago': ['Santíssimo Nome de Jesus', 'São João Baptista'],
 'cv:municipality:sao-lourenco-dos-orgaos': ['São Lourenço dos Órgãos'],
 'cv:municipality:santa-catarina': ['Santa Catarina'],
 'cv:municipality:brava': ['São João Baptista', 'Nossa Senhora do Monte'],
 'cv:municipality:sao-filipe': ['São Lourenço', 'Nossa Senhora da Conceição'],
 'cv:municipality:santa-catarina-do-fogo': ['Santa Catarina do Fogo'],
 'cv:municipality:mosteiros': ['Nossa Senhora da Ajuda'],
 'cv:municipality:maio': ['Nossa Senhora da Luz'],
 'cv:municipality:boa-vista': ['Santa Isabel', 'São João Baptista'],
 'cv:municipality:sal': ['Nossa Senhora das Dores'],
 'cv:municipality:ribeira-brava': ['Nossa Senhora da Lapa', 'Nossa Senhora do Rosário'],
 'cv:municipality:tarrafal-de-sao-nicolau': ['São Francisco'],
 'cv:municipality:sao-vicente': ['Nossa Senhora da Luz'],
 'cv:municipality:porto-novo': ['São João Baptista', 'Santo André'],
 'cv:municipality:ribeira-grande': ['Nossa Senhora do Rosário', 'Nossa Senhora do Livramento',
                                    'Santo Crucifixo', 'São Pedro Apóstolo'],
 'cv:municipality:paul': ['Santo António das Pombas']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-56', len(areas) == 56, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
got_l1 = {r['source_id']: (r['name'], r['code']) for r in l1}
check('l1-24', got_l1 == L1,
      str({k for k in L1 if got_l1.get(k) != L1[k]}))
check('l1-types', {r['type'] for r in l1} == {'municipality', 'geographical_region'})
par = [r for r in areas if r['type'] == 'parish']
check('parishes-32', len(par) == 32, str(len(par)))
got = {}
for r in par:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in PARISHES if sorted(got.get(k, [])) != sorted(PARISHES[k])}
check('parish-xmap', not bad, str(sorted(bad)))
check('parish-ids-unique', len({r['source_id'] for r in par}) == 32)
check('levels', all(r['level'] == '2' for r in par))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
