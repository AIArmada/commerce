#!/usr/bin/env python3
"""Gate for bundled Faroe Islands (FO) addressing geography.

Run from the repo root::

    python3 /tmp/geo-verify/B8/FO/gate_fo.py

Verify-only gate: asserts the exact current bundled state (35 areas,
118 codes, 119 links). Prints ``ALL PASS`` on success, exits non-zero
on the first failure.
"""

import csv
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve()
REPO = Path.cwd()
AREAS = REPO / "packages/addressing/resources/geography/faroe-islands-address-areas.csv"
CODES = REPO / "packages/addressing/resources/geography/faroe-islands-postal-codes.csv"
LINKS = REPO / "packages/addressing/resources/geography/faroe-islands-postal-code-areas.csv"

FAILURES: list[str] = []


def check(name: str, cond: bool, detail: str = "") -> None:
    if cond:
        print(f"PASS {name}")
    else:
        msg = f"FAIL {name}" + (f" :: {detail}" if detail else "")
        print(msg)
        FAILURES.append(msg)


def raw(path: Path) -> bytes:
    return path.read_bytes()


def main() -> int:
    # --- file hygiene: LF only, single trailing newline ---
    for path in (AREAS, CODES, LINKS):
        data = raw(path)
        check(f"{path.name} exists", path.exists())
        check(f"{path.name} LF-only", b"\r" not in data)
        check(f"{path.name} trailing newline", data.endswith(b"\n") and not data.endswith(b"\n\n"))

    areas = list(csv.DictReader(AREAS.read_text(encoding="utf-8").splitlines()))
    codes = list(csv.DictReader(CODES.read_text(encoding="utf-8").splitlines()))
    links = list(csv.DictReader(LINKS.read_text(encoding="utf-8").splitlines()))

    # --- areas: 35 rows = 6 regions + 29 municipalities ---
    check("areas row count", len(areas) == 35, f"got {len(areas)}")
    regions = [a for a in areas if a["type"] == "region"]
    munis = [a for a in areas if a["type"] == "municipality"]
    check("regions count", len(regions) == 6, f"got {len(regions)}")
    check("municipalities count", len(munis) == 29, f"got {len(munis)}")
    check(
        "region codes",
        sorted(a["code"] for a in regions) == ["EY", "NO", "SA", "ST", "SU", "VA"],
    )
    check(
        "regions level 1, no parents",
        all(a["level"] == "1" and a["parent_source_id"] == "" for a in regions),
    )
    by_id = {a["source_id"]: a for a in areas}
    check(
        "municipality parents resolve to regions",
        all(
            m["level"] == "2"
            and m["parent_source_id"] in by_id
            and by_id[m["parent_source_id"]]["type"] == "region"
            for m in munis
        ),
    )
    expected_parents = {
        "fo:municipality:torshavn": "fo:region:streymoy",
        "fo:municipality:klaksvik": "fo:region:northern-isles",
        "fo:municipality:runavik": "fo:region:eysturoy",
        "fo:municipality:eystur": "fo:region:eysturoy",
        "fo:municipality:vagar": "fo:region:vagar",
        "fo:municipality:sunda": "fo:region:eysturoy",
        "fo:municipality:tvoroyri": "fo:region:suuroy",
        "fo:municipality:fuglafjordur": "fo:region:eysturoy",
        "fo:municipality:nes": "fo:region:eysturoy",
        "fo:municipality:vagur": "fo:region:suuroy",
        "fo:municipality:vestmanna": "fo:region:streymoy",
        "fo:municipality:sorvagur": "fo:region:vagar",
        "fo:municipality:sjovar": "fo:region:eysturoy",
        "fo:municipality:eidi": "fo:region:eysturoy",
        "fo:municipality:kvivik": "fo:region:streymoy",
        "fo:municipality:sandur": "fo:region:sandoy",
        "fo:municipality:skopun": "fo:region:sandoy",
        "fo:municipality:hvannasund": "fo:region:northern-isles",
        "fo:municipality:vidareidi": "fo:region:northern-isles",
        "fo:municipality:sumba": "fo:region:suuroy",
        "fo:municipality:porkeri": "fo:region:suuroy",
        "fo:municipality:kunoy": "fo:region:northern-isles",
        "fo:municipality:skalavik": "fo:region:sandoy",
        "fo:municipality:hov": "fo:region:suuroy",
        "fo:municipality:husavik": "fo:region:sandoy",
        "fo:municipality:famjin": "fo:region:suuroy",
        "fo:municipality:fugloy": "fo:region:northern-isles",
        "fo:municipality:skuvoy": "fo:region:sandoy",
        "fo:municipality:hvalba": "fo:region:suuroy",
    }
    check(
        "municipality parent links",
        all(by_id[k]["parent_source_id"] == v for k, v in expected_parents.items()),
    )

    # --- codes: 118 FO-NNN rows ---
    check("codes row count", len(codes) == 118, f"got {len(codes)}")
    code_vals = [c["code"] for c in codes]
    check("codes unique", len(set(code_vals)) == 118)
    import re

    check("codes FO-NNN shape", all(re.fullmatch(r"FO-\d{3}", c) for c in code_vals))
    check("codes country FO", all(c["country_code"] == "FO" for c in codes))
    held_out = [
        "FO-110", "FO-165", "FO-215", "FO-355", "FO-375", "FO-405",
        "FO-515", "FO-535", "FO-610", "FO-710", "FO-810", "FO-910",
    ]
    check(
        "12 postboks codes held out",
        all(c not in code_vals for c in held_out),
        f"leaked={[c for c in held_out if c in code_vals]}",
    )

    # --- links: 119 rows = 118 primaries + FO-485 secondary ---
    check("links row count", len(links) == 119, f"got {len(links)}")
    primaries = [l for l in links if l["is_primary"] == "true"]
    secondaries = [l for l in links if l["is_primary"] == "false"]
    check("primaries count", len(primaries) == 118, f"got {len(primaries)}")
    check(
        "one primary per code",
        Counter(l["postcode"] for l in primaries).most_common(1)[0][1] == 1
        and set(l["postcode"] for l in primaries) == set(code_vals),
    )
    check(
        "secondary leg is FO-485 -> eystur",
        len(secondaries) == 1
        and secondaries[0]["postcode"] == "FO-485"
        and secondaries[0]["area_source_id"] == "fo:municipality:eystur",
    )
    check(
        "links resolve to municipalities",
        all(l["area_source_id"] in by_id and by_id[l["area_source_id"]]["type"] == "municipality" for l in links),
    )
    check(
        "relationship served_by",
        all(l["relationship_type"] == "served_by" for l in links),
    )

    # --- per-municipality primary counts ---
    expected_counts = {
        "fo:municipality:eidi": 3,
        "fo:municipality:eystur": 6,
        "fo:municipality:famjin": 1,
        "fo:municipality:fuglafjordur": 2,
        "fo:municipality:fugloy": 2,
        "fo:municipality:hov": 1,
        "fo:municipality:husavik": 3,
        "fo:municipality:hvalba": 2,
        "fo:municipality:hvannasund": 5,
        "fo:municipality:klaksvik": 9,
        "fo:municipality:kunoy": 2,
        "fo:municipality:kvivik": 5,
        "fo:municipality:nes": 3,
        "fo:municipality:porkeri": 1,
        "fo:municipality:runavik": 15,
        "fo:municipality:sandur": 1,
        "fo:municipality:sjovar": 5,
        "fo:municipality:skalavik": 1,
        "fo:municipality:skopun": 1,
        "fo:municipality:skuvoy": 2,
        "fo:municipality:sorvagur": 4,
        "fo:municipality:sumba": 4,
        "fo:municipality:sunda": 12,
        "fo:municipality:torshavn": 17,
        "fo:municipality:tvoroyri": 4,
        "fo:municipality:vagar": 3,
        "fo:municipality:vagur": 2,
        "fo:municipality:vestmanna": 1,
        "fo:municipality:vidareidi": 1,
    }
    got_counts = Counter(l["area_source_id"] for l in primaries)
    check(
        "per-municipality counts",
        dict(got_counts) == expected_counts,
        f"diff={set(expected_counts.items()) ^ set(got_counts.items())}",
    )

    # --- per-code pins: Tórshavn cluster (17) + key cells + secondary leg ---
    primary_of = {l["postcode"]: l["area_source_id"] for l in primaries}
    torshavn_cluster = [
        "FO-100", "FO-160", "FO-175", "FO-176", "FO-177", "FO-178",
        "FO-180", "FO-185", "FO-186", "FO-187", "FO-188", "FO-270",
        "FO-280", "FO-285", "FO-410", "FO-415", "FO-416",
    ]
    check(
        "torshavn cluster pins",
        all(primary_of.get(c) == "fo:municipality:torshavn" for c in torshavn_cluster),
    )
    pins = {
        "FO-485": "fo:municipality:runavik",  # primary; eystur secondary
        "FO-510": "fo:municipality:eystur",  # Gøta district code
        "FO-925": "fo:municipality:vagur",  # Nes (Vágur)
        "FO-286": "fo:municipality:skuvoy",  # Stóra Dímun
        "FO-360": "fo:municipality:vagar",  # Sandavágur (Vága)
        "FO-700": "fo:municipality:klaksvik",
        "FO-800": "fo:municipality:tvoroyri",
        "FO-900": "fo:municipality:vagur",
        "FO-970": "fo:municipality:sumba",
    }
    check(
        "key cell pins",
        all(primary_of.get(c) == m for c, m in pins.items()),
        f"bad={[c for c, m in pins.items() if primary_of.get(c) != m]}",
    )

    if FAILURES:
        print(f"{len(FAILURES)} FAILURES")
        return 1
    print("ALL PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
