#!/usr/bin/env python3
"""Gate for bundled Haiti (HT) geography — fix-and-fill expectations (240 codes).

Run from the repo root::

    python3 /tmp/geo-verify/B8/HT/gate_ht.py

Checks (post-fix state):
  areas  52 rows (10 departments + 42 arrondissements), parent links, LF only
  codes  240 rows (HT + 4 digits), CRLF only, sorted, unique
  links  240 rows, CRLF only, sorted, every code linked exactly once as
         primary served_by to an existing arrondissement
  counts full per-arrondissement code table (42 rows incl. zeros: n/a — every
         arrondissement holds >= 1 code)
  pins   Cap-Haitien cluster (8), Port-au-Prince cluster (33), all 11 fixed
         cells with their arrondissement primaries
  eol    EOL assertions + trailing newlines on all three files

Pre-fix the gate FAILS on exactly the 11 fixed cells (plus the aggregate
counts derived from them); the tree checks pass unchanged.
"""

import csv
import sys
from collections import Counter
from pathlib import Path

BASE = Path("packages/addressing/resources/geography")
AREAS = BASE / "haiti-address-areas.csv"
CODES = BASE / "haiti-postal-codes.csv"
LINKS = BASE / "haiti-postal-code-areas.csv"

FAILURES: list[str] = []


def check(name: str, cond: bool, detail: str = "") -> None:
    if cond:
        print(f"PASS {name}")
    else:
        msg = f"FAIL {name}" + (f" — {detail}" if detail else "")
        print(msg)
        FAILURES.append(msg)


# Fixed cells: code -> primary arrondissement source_id (B8 HT revisit).
FIXED = {
    "HT1131": "ht:arrondissement:cap-haitien",
    "HT1212": "ht:arrondissement:acul-du-nord",
    "HT2333": "ht:arrondissement:trou-du-nord",
    "HT3223": "ht:arrondissement:saint-louis-du-nord",
    "HT3311": "ht:arrondissement:mole-saint-nicolas",
    "HT3312": "ht:arrondissement:mole-saint-nicolas",
    "HT3341": "ht:arrondissement:mole-saint-nicolas",
    "HT4330": "ht:arrondissement:saint-marc",
    "HT6112": "ht:arrondissement:port-au-prince",
    "HT6350": "ht:arrondissement:croix-des-bouquets",
    "HT8316": "ht:arrondissement:aquin",
}

# Post-fix per-arrondissement primary code counts (full table, 42 rows).
EXPECTED_COUNTS = {
    "ht:arrondissement:acul-du-nord": 7,
    "ht:arrondissement:anse-a-veau": 3,
    "ht:arrondissement:anse-d-hainault": 5,
    "ht:arrondissement:aquin": 7,
    "ht:arrondissement:arcahaie": 4,
    "ht:arrondissement:bainet": 2,
    "ht:arrondissement:baraderes": 2,
    "ht:arrondissement:belle-anse": 7,
    "ht:arrondissement:borgne": 6,
    "ht:arrondissement:cap-haitien": 8,
    "ht:arrondissement:cerca-la-source": 4,
    "ht:arrondissement:chardonnieres": 5,
    "ht:arrondissement:corail": 4,
    "ht:arrondissement:coteaux": 5,
    "ht:arrondissement:croix-des-bouquets": 8,
    "ht:arrondissement:dessalines": 5,
    "ht:arrondissement:fort-liberte": 6,
    "ht:arrondissement:gonaives": 4,
    "ht:arrondissement:grande-riviere-du-nord": 2,
    "ht:arrondissement:gros-morne": 4,
    "ht:arrondissement:hinche": 6,
    "ht:arrondissement:jacmel": 6,
    "ht:arrondissement:jeremie": 8,
    "ht:arrondissement:la-gonave": 2,
    "ht:arrondissement:lascahobas": 4,
    "ht:arrondissement:leogane": 6,
    "ht:arrondissement:les-cayes": 8,
    "ht:arrondissement:limbe": 3,
    "ht:arrondissement:marmelade": 3,
    "ht:arrondissement:miragoane": 5,
    "ht:arrondissement:mirebalais": 5,
    "ht:arrondissement:mole-saint-nicolas": 7,
    "ht:arrondissement:ouanaminthe": 3,
    "ht:arrondissement:plaisance": 3,
    "ht:arrondissement:port-au-prince": 33,
    "ht:arrondissement:port-de-paix": 7,
    "ht:arrondissement:port-salut": 3,
    "ht:arrondissement:saint-louis-du-nord": 5,
    "ht:arrondissement:saint-marc": 7,
    "ht:arrondissement:saint-raphael": 5,
    "ht:arrondissement:trou-du-nord": 8,
    "ht:arrondissement:vallieres": 5,
}

CAP_HAITIEN = ["HT1110", "HT1111", "HT1112", "HT1113", "HT1114", "HT1120", "HT1130", "HT1131"]

PORT_AU_PRINCE = [
    "HT6110", "HT6111", "HT6112", "HT6113", "HT6114", "HT6115", "HT6116",
    "HT6117", "HT6118", "HT6119", "HT6120", "HT6121", "HT6122", "HT6123",
    "HT6124", "HT6125", "HT6130", "HT6131", "HT6132", "HT6133", "HT6134",
    "HT6135", "HT6136", "HT6140", "HT6141", "HT6142", "HT6143", "HT6144",
    "HT6145", "HT6146", "HT6147", "HT6150", "HT6160",
]


def main() -> int:
    for p in (AREAS, CODES, LINKS):
        check(f"exists {p.name}", p.is_file())
    if FAILURES:
        print(f"{len(FAILURES)} FAILURES")
        return 1

    raw_areas = AREAS.read_bytes()
    raw_codes = CODES.read_bytes()
    raw_links = LINKS.read_bytes()

    # --- EOL + trailing newlines ---
    check("areas EOL is LF-only", b"\r" not in raw_areas)
    check("areas trailing newline", raw_areas.endswith(b"\n"))
    for label, raw in (("codes", raw_codes), ("links", raw_links)):
        lf = raw.count(b"\n")
        crlf = raw.count(b"\r\n")
        check(f"{label} EOL is CRLF-only", lf == crlf and lf > 0, f"LF={lf} CRLF={crlf}")
        check(f"{label} trailing newline", raw.endswith(b"\r\n"))

    areas = list(csv.DictReader(raw_areas.decode("utf-8").splitlines()))
    codes = list(csv.DictReader(raw_codes.decode("utf-8").splitlines()))
    links = list(csv.DictReader(raw_links.decode("utf-8").splitlines()))

    # --- areas: 52 rows, departments + arrondissements, parent links ---
    check("areas row count is 52", len(areas) == 52, f"got {len(areas)}")
    depts = [r for r in areas if r["type"] == "department"]
    arrs = [r for r in areas if r["type"] == "arrondissement"]
    check("areas 10 departments", len(depts) == 10, f"got {len(depts)}")
    check("areas 42 arrondissements", len(arrs) == 42, f"got {len(arrs)}")
    by_id = {r["source_id"]: r for r in areas}
    check(
        "areas parent links resolve to departments",
        all(r["parent_source_id"] in by_id and by_id[r["parent_source_id"]]["type"] == "department" for r in arrs),
    )
    check(
        "areas departments have no parent",
        all(not r["parent_source_id"] for r in depts),
    )
    dept_codes = sorted(r["code"] for r in depts)
    check(
        "areas ISO 3166-2:HT codes",
        dept_codes == ["AR", "CE", "GA", "ND", "NE", "NI", "NO", "OU", "SD", "SE"],
        f"got {dept_codes}",
    )

    # --- codes: 240 rows, format, sorted, unique ---
    check("codes row count is 240", len(codes) == 240, f"got {len(codes)}")
    code_vals = [r["code"] for r in codes]
    import re

    check("codes HTNNNN format", all(re.fullmatch(r"HT\d{4}", c or "") for c in code_vals))
    check("codes country HT", all(r["country_code"] == "HT" for r in codes))
    check("codes sorted", code_vals == sorted(code_vals))
    check("codes unique", len(set(code_vals)) == len(code_vals))
    check("codes exclude HT3408 junk", "HT3408" not in code_vals)

    # --- links: 240 rows, one primary served_by per code ---
    check("links row count is 240", len(links) == 240, f"got {len(links)}")
    link_codes = [r["postcode"] for r in links]
    check("links sorted by postcode", link_codes == sorted(link_codes))
    check("links match code set", set(link_codes) == set(code_vals),
          f"only-links={sorted(set(link_codes) - set(code_vals))} only-codes={sorted(set(code_vals) - set(link_codes))}")
    check("links unique postcodes", len(set(link_codes)) == len(link_codes))
    check("links all served_by", all(r["relationship_type"] == "served_by" for r in links))
    check("links all primary", all(r["is_primary"] == "true" for r in links))
    check(
        "links target existing arrondissements",
        all(r["area_source_id"] in by_id and by_id[r["area_source_id"]]["type"] == "arrondissement" for r in links),
    )
    link_by_code = {r["postcode"]: r["area_source_id"] for r in links}

    # --- per-arrondissement counts (full table) ---
    counts = Counter(link_by_code.values())
    bad_counts = {
        k: (counts.get(k, 0), v) for k, v in EXPECTED_COUNTS.items() if counts.get(k, 0) != v
    }
    extra = sorted(set(counts) - set(EXPECTED_COUNTS))
    check("per-arrondissement counts (42 rows)", not bad_counts and not extra,
          f"mismatch={bad_counts} extra={extra}")

    # --- cluster pins ---
    cap = sorted(c for c, a in link_by_code.items() if a == "ht:arrondissement:cap-haitien")
    check("Cap-Haitien cluster (8)", cap == CAP_HAITIEN, f"got {cap}")
    pap = sorted(c for c, a in link_by_code.items() if a == "ht:arrondissement:port-au-prince")
    check("Port-au-Prince cluster (33)", pap == PORT_AU_PRINCE, f"got {pap}")

    # --- fixed-cell pins ---
    for code, area in sorted(FIXED.items()):
        check(
            f"fixed {code} -> {area}",
            code in link_by_code and link_by_code[code] == area,
            f"got {link_by_code.get(code)}",
        )

    # --- GN-only keep pins (must NOT be dropped by the fix) ---
    keeps = {
        "HT4530": "ht:arrondissement:marmelade",
        "HT6145": "ht:arrondissement:port-au-prince",
        "HT6146": "ht:arrondissement:port-au-prince",
        "HT6147": "ht:arrondissement:port-au-prince",
        "HT8313": "ht:arrondissement:aquin",
    }
    for code, area in sorted(keeps.items()):
        check(
            f"keep {code} -> {area}",
            code in link_by_code and link_by_code[code] == area,
            f"got {link_by_code.get(code)}",
        )

    if FAILURES:
        print(f"{len(FAILURES)} FAILURES")
        return 1
    print("ALL PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
