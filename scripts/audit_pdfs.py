#!/usr/bin/env python3
import csv
import hashlib
import json
import os
import subprocess
import tempfile
import urllib.request
import urllib.error
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "migration" / "issues.json"
OUT_CSV = ROOT / "migration" / "pdf-audit.csv"
OUT_MD = ROOT / "migration" / "pdf-audit.md"

with SRC.open("r", encoding="utf-8") as f:
    payload = json.load(f)

issues = payload.get("issues", [])
groups = defaultdict(list)
for item in issues:
    groups[item["url"]].append(item)

rows = []
ua = "Mozilla/5.0 (compatible; CHGPU-Migration-Audit/1.0)"

for idx, (url, linked) in enumerate(groups.items(), 1):
    http_status = ""
    content_type = ""
    size_bytes = 0
    pages = ""
    sha256 = ""
    valid_pdf = False
    error = ""
    pdf_title = ""

    fd, tmp_name = tempfile.mkstemp(suffix=".pdf")
    os.close(fd)
    try:
        req = urllib.request.Request(url, headers={"User-Agent": ua})
        with urllib.request.urlopen(req, timeout=90) as resp, open(tmp_name, "wb") as out:
            http_status = getattr(resp, "status", 200)
            content_type = resp.headers.get("Content-Type", "")
            h = hashlib.sha256()
            while True:
                chunk = resp.read(1024 * 1024)
                if not chunk:
                    break
                out.write(chunk)
                h.update(chunk)
                size_bytes += len(chunk)
            sha256 = h.hexdigest()

        with open(tmp_name, "rb") as f:
            valid_pdf = f.read(5) == b"%PDF-"

        if valid_pdf:
            proc = subprocess.run(
                ["pdfinfo", tmp_name],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if proc.returncode == 0:
                for line in proc.stdout.splitlines():
                    if line.startswith("Pages:"):
                        pages = line.split(":", 1)[1].strip()
                    elif line.startswith("Title:"):
                        pdf_title = line.split(":", 1)[1].strip()
            else:
                error = (proc.stderr or "pdfinfo failed").strip()[:500]
        else:
            error = "File does not start with %PDF-"
    except urllib.error.HTTPError as e:
        http_status = e.code
        error = f"HTTPError {e.code}: {e.reason}"
    except Exception as e:
        error = f"{type(e).__name__}: {e}"
    finally:
        try:
            os.remove(tmp_name)
        except OSError:
            pass

    linked_labels = "; ".join(
        f'{x.get("year")} {x.get("series")} {x.get("issue")}' for x in linked
    )
    duplicate = len(linked) > 1
    ok = (
        str(http_status).startswith("2")
        and valid_pdf
        and str(pages).isdigit()
        and int(pages) > 0
        and size_bytes > 0
    )
    rows.append({
        "n": idx,
        "url": url,
        "http_status": http_status,
        "content_type": content_type,
        "size_bytes": size_bytes,
        "size_mb": round(size_bytes / (1024 * 1024), 2),
        "pages": pages,
        "valid_pdf": valid_pdf,
        "sha256": sha256,
        "duplicate_url": duplicate,
        "linked_entries": len(linked),
        "linked_issues": linked_labels,
        "pdf_title": pdf_title,
        "result": "OK" if ok else "CHECK",
        "error": error,
    })
    print(f"[{idx}/{len(groups)}] {rows[-1]['result']} {rows[-1]['size_mb']} MB {pages or '?'} pages")

OUT_CSV.parent.mkdir(parents=True, exist_ok=True)
with OUT_CSV.open("w", encoding="utf-8-sig", newline="") as f:
    writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
    writer.writeheader()
    writer.writerows(rows)

ok_rows = [r for r in rows if r["result"] == "OK"]
check_rows = [r for r in rows if r["result"] != "OK"]
dup_rows = [r for r in rows if r["duplicate_url"]]
total_bytes = sum(r["size_bytes"] for r in rows)
total_pages = sum(int(r["pages"]) for r in rows if str(r["pages"]).isdigit())

lines = [
    "# Аудит PDF архива «Известия ЧГПУ»",
    "",
    f"- Позиций архива: **{len(issues)}**",
    f"- Уникальных PDF URL: **{len(rows)}**",
    f"- Успешно проверено: **{len(ok_rows)} / {len(rows)}**",
    f"- Требуют проверки: **{len(check_rows)}**",
    f"- Уникальных URL, привязанных к нескольким выпускам: **{len(dup_rows)}**",
    f"- Общий объём уникальных PDF: **{round(total_bytes / (1024**3), 3)} ГБ**",
    f"- Суммарное число страниц по успешно прочитанным PDF: **{total_pages}**",
    "",
    "Проверка включает HTTP-доступность, PDF-сигнатуру %PDF-, размер файла, количество страниц через pdfinfo и SHA-256.",
    "",
    "## Результаты",
    "",
    "| № | Результат | Размер | Страниц | Привязок | Выпуски |",
    "|---:|---|---:|---:|---:|---|",
]
for r in rows:
    marker = "✅" if r["result"] == "OK" else "⚠️"
    labels = r["linked_issues"].replace("|", "/")
    lines.append(
        f"| {r['n']} | {marker} {r['result']} | {r['size_mb']} MB | "
        f"{r['pages'] or '—'} | {r['linked_entries']} | {labels} |"
    )

if check_rows:
    lines += ["", "## Требуют ручной проверки", ""]
    for r in check_rows:
        lines.append(f"- **{r['url']}** — {r['error'] or 'неполные метаданные'}")

if dup_rows:
    lines += ["", "## Дубли ссылок", ""]
    for r in dup_rows:
        lines.append(f"- **{r['linked_entries']} позиции → 1 PDF:** {r['url']}")
        for label in r["linked_issues"].split("; "):
            lines.append(f"  - {label}")

lines += [
    "",
    "## Следующий этап",
    "",
    "После устранения ошибочных ссылок: скачать подтверждённые файлы на сервер Beget, "
    "сверить SHA-256 после загрузки и заменить старые URL на новые локальные пути.",
]
OUT_MD.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Wrote {OUT_CSV}")
print(f"Wrote {OUT_MD}")
