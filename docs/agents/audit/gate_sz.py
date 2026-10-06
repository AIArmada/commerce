#!/usr/bin/env python3
"""Gate for Eswatini (SZ) bundled geography — post-fix expectations.

Run from the repo root:
    python3 /tmp/geo-verify/B9/SZ/gate_sz.py

Checks the post-fix tree: 4 regions + 59 tinkhundla (15/11/18/15),
81 postcodes (H101 Swazi Plaza filled), 81 region-level primary links,
ISO 3166-2:SZ anchors, EOL discipline (areas LF, codes/links CRLF) and
trailing newlines. Prints ALL PASS. Fails pre-fix on exactly the cells
the fix touches (59->63 areas, 80->81 codes/links, renames, new rows).
"""
import csv
import sys
from pathlib import Path

ROOT = Path.cwd()
AREAS = ROOT / "packages/addressing/resources/geography/eswatini-address-areas.csv"
CODES = ROOT / "packages/addressing/resources/geography/eswatini-postal-codes.csv"
LINKS = ROOT / "packages/addressing/resources/geography/eswatini-postal-code-areas.csv"

fails: list[str] = []


def check(cond: bool, msg: str) -> None:
    if not cond:
        fails.append(msg)


# --- raw bytes: EOL + trailing newline ------------------------------------
raw_areas = AREAS.read_bytes()
raw_codes = CODES.read_bytes()
raw_links = LINKS.read_bytes()

check(b"\r" not in raw_areas, "areas: must be LF-only")
check(raw_areas.endswith(b"\n") and not raw_areas.endswith(b"\n\n"),
      "areas: must end with exactly one LF")
check(b"\n" in raw_codes and raw_codes.count(b"\r\n") == raw_codes.count(b"\n"),
      "codes: every line must end CRLF")
check(raw_codes.endswith(b"\r\n") and not raw_codes.endswith(b"\r\n\r\n"),
      "codes: must end with exactly one CRLF")
check(raw_links.count(b"\r\n") == raw_links.count(b"\n"),
      "links: every line must end CRLF")
check(raw_links.endswith(b"\r\n") and not raw_links.endswith(b"\r\n\r\n"),
      "links: must end with exactly one CRLF")

# --- areas ----------------------------------------------------------------
with AREAS.open(newline="", encoding="utf-8") as fh:
    areas = list(csv.DictReader(fh))
by_id = {a["source_id"]: a for a in areas}
regions = [a for a in areas if a["type"] == "region"]
inkh = [a for a in areas if a["type"] == "inkhundla"]

check(len(areas) == 63, f"areas: want 63 data rows, got {len(areas)}")
check(len(regions) == 4, f"areas: want 4 regions, got {len(regions)}")
check(len(inkh) == 59, f"areas: want 59 tinkhundla, got {len(inkh)}")

# ISO 3166-2:SZ anchors (iso-SZ.json: SZ-HH/LU/MA/SH regions)
for sid, code, name in [
    ("sz:region:hhohho", "HH", "Hhohho"),
    ("sz:region:lubombo", "LU", "Lubombo"),
    ("sz:region:manzini", "MA", "Manzini"),
    ("sz:region:shiselweni", "SH", "Shiselweni"),
]:
    a = by_id.get(sid)
    check(a is not None, f"areas: missing {sid}")
    if a is not None:
        check(a["code"] == code, f"areas: {sid} code want {code}, got {a['code']}")
        check(a["name"] == name, f"areas: {sid} name want {name}, got {a['name']}")
        check(a["level"] == "1", f"areas: {sid} level want 1")

# per-region tinkhundla counts (EBC 2018: 15/11/18/15)
counts = {"sz:region:hhohho": 15, "sz:region:lubombo": 11,
          "sz:region:manzini": 18, "sz:region:shiselweni": 15}
for parent, want in counts.items():
    got = [a for a in inkh if a["parent_source_id"] == parent]
    check(len(got) == want, f"areas: {parent} want {want}, got {len(got)}")

# every inkhundla: level 2, valid parent
for a in inkh:
    check(a["level"] == "2", f"areas: {a['source_id']} level want 2")
    check(a["parent_source_id"] in counts,
          f"areas: {a['source_id']} bad parent {a['parent_source_id']}")

# new 2018 inkhundla pins (EBC + Observer + externals)
for sid, name, parent in [
    ("sz:inkhundla:siphocosini", "Siphocosini", "sz:region:hhohho"),
    ("sz:inkhundla:nkomiyahlaba", "Nkomiyahlaba", "sz:region:manzini"),
    ("sz:inkhundla:phondo", "Phondo", "sz:region:manzini"),
    ("sz:inkhundla:kumethula", "Kumethula", "sz:region:shiselweni"),
]:
    a = by_id.get(sid)
    check(a is not None, f"areas: missing new {sid}")
    if a is not None:
        check(a["name"] == name, f"areas: {sid} name want {name}")
        check(a["parent_source_id"] == parent, f"areas: {sid} parent want {parent}")

# entity renames: new ids, old ids gone
for sid, name, parent in [
    ("sz:inkhundla:gilgal", "Gilgal", "sz:region:lubombo"),
    ("sz:inkhundla:zombodze-emuva", "Zombodze Emuva", "sz:region:shiselweni"),
]:
    a = by_id.get(sid)
    check(a is not None, f"areas: missing renamed {sid}")
    if a is not None:
        check(a["name"] == name, f"areas: {sid} name want {name}")
        check(a["parent_source_id"] == parent, f"areas: {sid} parent want {parent}")
check("sz:inkhundla:hlane" not in by_id, "areas: stale sz:inkhundla:hlane still present")
check("sz:inkhundla:zombodze" not in by_id, "areas: stale sz:inkhundla:zombodze still present")

# spelling renames: id stable, name fixed (EBC + 2nd signal each)
for sid, name in [
    ("sz:inkhundla:hlambanyatsi", "Mhlambanyatsi"),
    ("sz:inkhundla:ekukhanyeni", "Kukhanyeni"),
    ("sz:inkhundla:mpholonjeni", "Mpolonjeni"),
    ("sz:inkhundla:lubuli", "Lubulini"),
    ("sz:inkhundla:timpisini", "Timphisini"),
]:
    a = by_id.get(sid)
    check(a is not None, f"areas: missing {sid}")
    if a is not None:
        check(a["name"] == name, f"areas: {sid} name want {name}, got {a['name']}")

# holds: bundled spellings kept (EBC split or 1-1 with counter-signals)
for sid, name in [
    ("sz:inkhundla:motjane", "Motjane"),
    ("sz:inkhundla:madlangempisi", "Madlangempisi"),
    ("sz:inkhundla:mahlangatja", "Mahlangatja"),
    ("sz:inkhundla:piggs-peak", "Piggs Peak"),
    ("sz:inkhundla:shiselweni-i", "Shiselweni I"),
    ("sz:inkhundla:shiselweni-ii", "Shiselweni II"),
    ("sz:inkhundla:maphalaleni", "Maphalaleni"),
    ("sz:inkhundla:mtfongwaneni", "Mtfongwaneni"),
    ("sz:inkhundla:ngwempisi", "Ngwempisi"),
    ("sz:inkhundla:lamgabhi", "Lamgabhi"),
    ("sz:inkhundla:nkilongo", "Nkilongo"),
    ("sz:inkhundla:ntondozi", "Ntondozi"),
    ("sz:inkhundla:mangcongco", "Mangcongco"),
]:
    a = by_id.get(sid)
    check(a is not None, f"areas: missing held {sid}")
    if a is not None:
        check(a["name"] == name, f"areas: {sid} hold want {name}, got {a['name']}")

# --- codes ----------------------------------------------------------------
with CODES.open(newline="", encoding="utf-8") as fh:
    codes = list(csv.DictReader(fh))
code_list = [c["code"] for c in codes]
check(len(codes) == 81, f"codes: want 81 rows, got {len(codes)}")
check(all(c["country_code"] == "SZ" for c in codes), "codes: country_code must be SZ")
check(len(set(code_list)) == len(code_list), "codes: duplicate codes")
check("H101" in code_list, "codes: H101 Swazi Plaza missing")

# per-letter counts: H=25 L=18 M=22 S=16 (youbianku 81-set)
for letter, want in [("H", 25), ("L", 18), ("M", 22), ("S", 16)]:
    got = sum(1 for c in code_list if c.startswith(letter))
    check(got == want, f"codes: letter {letter} want {want}, got {got}")

# --- links ----------------------------------------------------------------
with LINKS.open(newline="", encoding="utf-8") as fh:
    links = list(csv.DictReader(fh))
check(len(links) == 81, f"links: want 81 rows, got {len(links)}")
check(all(r["relationship_type"] == "served_by" for r in links),
      "links: relationship_type must be served_by")
check(all(r["is_primary"] == "true" for r in links), "links: all rows primary")
link_by_code = {r["postcode"]: r["area_source_id"] for r in links}
check(set(link_by_code) == set(code_list), "links: postcode set must equal codes set")

# region-letter discipline: H->hhohho M->manzini L->lubombo S->shiselweni
want_region = {"H": "sz:region:hhohho", "M": "sz:region:manzini",
               "L": "sz:region:lubombo", "S": "sz:region:shiselweni"}
for code, sid in link_by_code.items():
    check(sid == want_region[code[0]],
          f"links: {code} want {want_region[code[0]]}, got {sid}")

# per-code pins: H101 fill + capitals + every fixed cell
for code, sid in [
    ("H101", "sz:region:hhohho"),   # Swazi Plaza (WP + youbianku)
    ("H100", "sz:region:hhohho"),   # Mbabane (UPU anchor)
    ("M200", "sz:region:manzini"),  # Manzini
    ("S400", "sz:region:shiselweni"),  # Nhlangano
    ("L300", "sz:region:lubombo"),  # Siteki
    ("M211", "sz:region:manzini"),  # Sithobela: postal/admin mismatch kept
    ("M214", "sz:region:manzini"),  # Siphofaneni: postal/admin mismatch kept
    ("H121", "sz:region:hhohho"),   # Emsahweni extra kept
    ("H124", "sz:region:hhohho"),   # Mahlanya extra kept
    ("H125", "sz:region:hhohho"),   # Ebuhleni extra kept
    ("H126", "sz:region:hhohho"),   # The Gables extra kept
    ("M223", "sz:region:manzini"),  # The Hub extra kept
]:
    check(link_by_code.get(code) == sid, f"links: pin {code}->{sid} failed")

if fails:
    print(f"{len(fails)} FAILURES:")
    for f in fails:
        print(f"  FAIL {f}")
    sys.exit(1)
print("ALL PASS")
