# -*- coding: utf-8 -*-
"""Génère la version HTML autonome (images en base64) du livrable 04."""
import base64, pathlib, re, markdown

DOCS = pathlib.Path("/home/user/docs")
MD = DOCS / "04-conception-bd-et-sql.md"
OUT = pathlib.Path("/home/user/artefacts/04-conception-bd-et-sql.html")  # artefact rendu

text = MD.read_text(encoding="utf-8")

# images -> base64
def embed(m):
    alt, path = m.group(1), m.group(2)
    p = (DOCS / path)
    b64 = base64.b64encode(p.read_bytes()).decode()
    return f'![{alt}](data:image/png;base64,{b64})'
text = re.sub(r"!\[([^\]]*)\]\((diagrams/[^)]+)\)", embed, text)

html_body = markdown.markdown(text, extensions=["tables", "fenced_code"], output_format="html5")

CSS = """
:root { --ink:#1a2332; --accent:#7c2d12; --accent2:#b91c1c; --soft:#fefce8; --line:#e2d9c8; --code:#fdf6e3; }
* { box-sizing:border-box; }
body { font-family:Georgia,'Times New Roman',serif; color:var(--ink); background:#faf8f3;
       max-width:980px; margin:0 auto; padding:48px 56px; line-height:1.62; font-size:16px; }
h1,h2,h3,h4 { font-family:'Segoe UI',system-ui,sans-serif; color:var(--accent); line-height:1.25; }
h1 { font-size:2.0em; border-bottom:3px solid var(--accent); padding-bottom:.35em; margin-top:1.2em; }
h2 { font-size:1.5em; border-bottom:1px solid var(--line); padding-bottom:.25em; margin-top:2em; }
h3 { font-size:1.2em; margin-top:1.6em; color:#9a3412; }
h4 { font-size:1.05em; color:#a16207; }
table { border-collapse:collapse; width:100%; margin:1em 0; font-family:'Segoe UI',system-ui,sans-serif; font-size:.86em; }
th { background:#7c2d12; color:#fff; text-align:left; padding:7px 9px; }
td { border:1px solid var(--line); padding:6px 9px; vertical-align:top; background:#fff; }
tr:nth-child(even) td { background:#fdfaf3; }
code { font-family:'JetBrains Mono',Consolas,monospace; font-size:.88em; background:var(--soft);
       border:1px solid #f0e6c8; border-radius:3px; padding:1px 4px; }
pre { background:var(--code); border:1px solid var(--line); border-left:4px solid var(--accent2);
      border-radius:4px; padding:14px 16px; overflow-x:auto; line-height:1.45; }
pre code { background:none; border:none; padding:0; font-size:.84em; }
blockquote { border-left:4px solid #ca8a04; background:var(--soft); margin:1em 0; padding:.6em 1.1em; color:#713f12; }
img { max-width:100%; border:1px solid var(--line); border-radius:6px; margin:8px 0; }
hr { border:none; border-top:1px solid var(--line); margin:2.2em 0; }
a { color:var(--accent2); }
@media print { body { background:#fff; padding:0; } pre,table { page-break-inside:auto; } }
"""

page = f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MiniShop — Conception de la base de données et SQL (livrable n°3)</title>
<style>{CSS}</style>
</head>
<body>
{html_body}
</body>
</html>"""

OUT.write_text(page, encoding="utf-8")
print(f"OK HTML : {OUT} ({OUT.stat().st_size/1024:.0f} Ko)")
