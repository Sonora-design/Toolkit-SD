#!/usr/bin/env python3
"""Bouwt een release van Toolkit SD.

Gebruik:  python3 bin/build.py
Resultaat in dist/:
  - toolkit-sd-X.Y.Z.zip   (map toolkit-sd/ met de plugin, zonder bin/, dist/ en git-bestanden)
  - info.json              (versiebestand voor https://sonoradesign.be/updates/toolkit-sd/)

Beide bestanden komen op sonoradesign.be in de map updates/toolkit-sd/.
De zip hangt ook aan de release op GitHub.
"""

import datetime
import hashlib
import html
import json
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DIST = ROOT / "dist"
BASE_URL = "https://sonoradesign.be/updates/toolkit-sd/"
SKIP_DIRS = {".git", "bin", "dist", ".github", ".claude"}
SKIP_FILES = {".gitignore", ".DS_Store", "Thumbs.db"}


def header(name: str) -> str:
    text = (ROOT / "toolkit-sd.php").read_text(encoding="utf-8")
    m = re.search(r"^\s*\*\s*" + re.escape(name) + r":\s*(.+)$", text, re.M)
    return m.group(1).strip() if m else ""


def changelog_html(limit: int = 5) -> str:
    """De laatste versies uit CHANGELOG.md als eenvoudige HTML."""
    out, count, in_list = [], 0, False
    for line in (ROOT / "CHANGELOG.md").read_text(encoding="utf-8").splitlines():
        if line.startswith("## "):
            count += 1
            if count > limit:
                break
            if in_list:
                out.append("</ul>")
                in_list = False
            out.append("<h4>" + html.escape(line[3:].strip()) + "</h4>")
        elif line.startswith("- ") and count:
            if not in_list:
                out.append("<ul>")
                in_list = True
            item = html.escape(line[2:].strip())
            item = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", item)
            item = re.sub(r"`(.+?)`", r"<code>\1</code>", item)
            out.append("<li>" + item + "</li>")
        elif line.strip() and count and not line.startswith("#"):
            if in_list:
                out.append("</ul>")
                in_list = False
            out.append("<p>" + html.escape(line.strip()) + "</p>")
    if in_list:
        out.append("</ul>")
    return "".join(out)


def main() -> None:
    version = header("Version")
    code = (ROOT / "toolkit-sd.php").read_text(encoding="utf-8")
    if f"const VERSION = '{version}';" not in code:
        raise SystemExit(f"Versie in koptekst ({version}) en VERSION verschillen.")
    if f"## {version} " not in (ROOT / "CHANGELOG.md").read_text(encoding="utf-8"):
        raise SystemExit(f"CHANGELOG.md heeft nog geen regel voor {version}.")

    DIST.mkdir(exist_ok=True)
    zip_name = f"toolkit-sd-{version}.zip"
    zip_path = DIST / zip_name
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as z:
        for f in sorted(ROOT.rglob("*")):
            rel = f.relative_to(ROOT)
            if f.is_dir() or rel.parts[0] in SKIP_DIRS or f.name in SKIP_FILES:
                continue
            z.write(f, "toolkit-sd/" + rel.as_posix())

    info = {
        "name": "Toolkit SD",
        "version": version,
        "download_url": BASE_URL + zip_name,
        "requires": header("Requires at least"),
        "requires_php": header("Requires PHP"),
        "tested": "6.9",
        "last_updated": datetime.date.today().isoformat(),
        "sha256": hashlib.sha256(zip_path.read_bytes()).hexdigest(),
        "changelog": changelog_html(),
    }
    (DIST / "info.json").write_text(json.dumps(info, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"{zip_name}  {zip_path.stat().st_size} bytes  sha256 {info['sha256'][:12]}…")
    print("info.json klaar")


if __name__ == "__main__":
    main()
