#!/usr/bin/env python3
"""Convert docs/sql-a-ecrire/requetes-sql-a-ecrire.md to PDF using fpdf2.

Simplified Markdown → PDF : headings (#, ##, ###), bold (**...**), inline code
(`...`), fenced code blocks (```...```), bullet/numbered lists, horizontal rules (---),
pipe tables (|), and paragraphs. Good enough for a working document.
"""
import os, re, sys, textwrap

from fpdf import FPDF

MD_PATH = "/home/user/SAE-302/docs/sql-a-ecrire/requetes-sql-a-ecrire.md"
PDF_PATH = "/home/user/SAE-302/docs/sql-a-ecrire/requetes-sql-a-ecrire.pdf"

# --- Font configuration -------------------------------------------------------
# We need a TTF font that supports the full Latin-1 range (French accents) and
# has a mono variant for code blocks. DejaVu is shipped with fpdf2.
from fpdf.enums import XPos, YPos

FONT_DIR = None
for cand in [
    "/usr/share/fonts/truetype/dejavu",
    "/usr/share/fonts/dejavu",
]:
    if os.path.isdir(cand):
        FONT_DIR = cand
        break
if not FONT_DIR:
    # fall back: try to locate via fc-list
    import subprocess
    out = subprocess.run(["fc-list", "DejaVuSans.ttf"], capture_output=True, text=True).stdout
    if out.strip():
        FONT_DIR = os.path.dirname(out.strip().split(":")[0])
if not FONT_DIR:
    print("ERROR: DejaVu fonts not found; install fonts-dejavu-core", file=sys.stderr)
    sys.exit(1)


class PDF(FPDF):
    def __init__(self):
        super().__init__(orientation="P", unit="mm", format="A4")
        self.set_auto_page_break(auto=True, margin=18)
        self.set_margins(18, 18, 18)
        self.add_font("DejaVu", "", os.path.join(FONT_DIR, "DejaVuSans.ttf"))
        self.add_font("DejaVu", "B", os.path.join(FONT_DIR, "DejaVuSans-Bold.ttf"))
        self.add_font("DejaVu", "I", os.path.join(FONT_DIR, "DejaVuSans.ttf"))  # no Oblique shipped; fall back to regular
        self.add_font("DejaVuMono", "", os.path.join(FONT_DIR, "DejaVuSansMono.ttf"))
        self.add_font("DejaVuMono", "B", os.path.join(FONT_DIR, "DejaVuSansMono-Bold.ttf"))
        self.set_font("DejaVu", "", 10)

    def header(self):
        if self.page_no() > 1:
            self.set_font("DejaVu", "I", 8)
            self.set_text_color(120, 120, 120)
            self.cell(0, 6, "MiniShop — Requêtes SQL à écrire", align="L")
            self.cell(0, 6, f"page {self.page_no()}", align="R",
                      new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            self.set_text_color(0, 0, 0)
            self.ln(2)

    def footer(self):
        self.set_y(-12)
        self.set_font("DejaVu", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 6, "SAÉ 302 · MiniShop · v1.0 — 23 sept. 2026", align="C")
        self.set_text_color(0, 0, 0)


# -----------------------------------------------------------------------------
# Minimal markdown tokeniser / renderer
# -----------------------------------------------------------------------------

INLINE_BOLD = re.compile(r"\*\*(.+?)\*\*")
INLINE_CODE = re.compile(r"`([^`]+)`")
INLINE_ITAL = re.compile(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)")


def write_rich(pdf: PDF, text: str, base_size=10, code=False, bold=False):
    """Write a single line with inline **bold**, *italic*, `code` markup."""
    # We split the line into segments by alternating bold/code/italic patterns.
    # Simple approach: repeatedly find the earliest special pattern.
    pos = 0
    size = base_size - 1 if code else base_size
    font_family = "DejaVuMono" if code else "DejaVu"
    style = "B" if bold else ""
    pdf.set_font(font_family, style, size)
    if code:
        pdf.set_text_color(40, 40, 40)

    tokens = []
    i = 0
    while i < len(text):
        m_b = INLINE_BOLD.search(text, i)
        m_c = INLINE_CODE.search(text, i)
        m_i = INLINE_ITAL.search(text, i)
        candidates = [m for m in (m_b, m_c, m_i) if m is not None]
        if not candidates:
            tokens.append(("t", text[i:]))
            break
        m = min(candidates, key=lambda x: x.start())
        if m.start() > i:
            tokens.append(("t", text[i:m.start()]))
        if m.re is INLINE_BOLD:
            tokens.append(("b", m.group(1)))
        elif m.re is INLINE_CODE:
            tokens.append(("c", m.group(1)))
        else:
            tokens.append(("i", m.group(1)))
        i = m.end()

    for kind, val in tokens:
        if kind == "t":
            pdf.set_font(font_family, style, size)
            pdf.write(size * 0.55, val)
        elif kind == "b":
            pdf.set_font(font_family, "B", size)
            pdf.write(size * 0.55, val)
        elif kind == "i":
            pdf.set_font(font_family, "I", size)
            pdf.write(size * 0.55, val)
        elif kind == "c":
            # simulate inline-code highlight via a small colored cell background
            pdf.set_font("DejaVuMono", "", size)
            cw = pdf.get_string_width(val) + 1.2
            cx = pdf.get_x(); cy = pdf.get_y()
            pdf.set_fill_color(230, 236, 242)
            pdf.rect(cx, cy, cw, size * 0.62 + 0.6, style="F")
            pdf.set_text_color(20, 60, 110)
            pdf.write(size * 0.55, val)
            pdf.set_text_color(0, 0, 0)
    if code:
        pdf.set_text_color(0, 0, 0)


def paragraph(pdf: PDF, text: str, size=10, indent=0):
    if indent:
        pdf.set_x(pdf.l_margin + indent)
    write_rich(pdf, text, base_size=size)
    pdf.ln(size * 0.55 + 2)


def heading(pdf: PDF, text: str, level: int):
    sizes = {1: 18, 2: 14, 3: 12, 4: 11}
    size = sizes.get(level, 10)
    pdf.ln(3)
    pdf.set_font("DejaVu", "B", size)
    if level == 1:
        # title page-like
        pdf.set_text_color(20, 60, 110)
        pdf.multi_cell(0, size * 0.65, text, align="L",
                       new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.set_draw_color(20, 60, 110)
        y = pdf.get_y()
        pdf.line(pdf.l_margin, y, pdf.w - pdf.r_margin, y)
        pdf.ln(3)
    elif level == 2:
        pdf.set_text_color(20, 60, 110)
        pdf.multi_cell(0, size * 0.65, text,
                       new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        y = pdf.get_y()
        pdf.set_draw_color(20, 60, 110)
        pdf.line(pdf.l_margin, y, pdf.w - pdf.r_margin, y)
        pdf.ln(2)
    else:
        pdf.set_text_color(40, 40, 40)
        pdf.multi_cell(0, size * 0.6, text,
                       new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.ln(1)
    pdf.set_text_color(0, 0, 0)


def render_code_block(pdf: PDF, code_lines):
    pdf.ln(1)
    pdf.set_fill_color(245, 247, 250)
    pdf.set_draw_color(210, 215, 222)
    pdf.set_font("DejaVuMono", "", 8.5)
    line_h = 4.6
    for line in code_lines:
        x = pdf.get_x()
        y = pdf.get_y()
        # wrap long code lines by characters
        max_chars = 96  # approx at 8.5pt with DejaVu Mono
        wrapped = textwrap.wrap(line, width=max_chars,
                                replace_whitespace=False, drop_whitespace=False) or [""]
        for wl in wrapped:
            if pdf.get_y() + line_h > pdf.h - pdf.b_margin:
                pdf.add_page()
            pdf.cell(pdf.w - pdf.l_margin - pdf.r_margin, line_h, wl,
                     border="LR", fill=True, ln=1)
    # bottom border
    x = pdf.l_margin
    y = pdf.get_y()
    pdf.set_draw_color(210, 215, 222)
    pdf.line(x, y, pdf.w - pdf.r_margin, y)
    pdf.ln(2)
    pdf.set_font("DejaVu", "", 10)


def render_bullet(pdf: PDF, text: str, level=0):
    bullet = "• " if level == 0 else "– "
    indent = 4 + level * 4
    pdf.set_x(pdf.l_margin + indent)
    pdf.set_font("DejaVu", "", 10)
    pdf.write(5.5, bullet)
    write_rich(pdf, text, base_size=10)
    pdf.ln(6)


def render_numbered(pdf: PDF, num: int, text: str):
    prefix = f"{num}. "
    pdf.set_x(pdf.l_margin + 4)
    pdf.set_font("DejaVu", "", 10)
    pdf.write(5.5, prefix)
    write_rich(pdf, text, base_size=10)
    pdf.ln(6)


def hr(pdf: PDF):
    pdf.ln(2)
    pdf.set_draw_color(180, 180, 180)
    y = pdf.get_y()
    pdf.line(pdf.l_margin, y, pdf.w - pdf.r_margin, y)
    pdf.ln(3)


def render_table(pdf: PDF, rows):
    """Render a pipe table with simple column sizing. First row is header, second is separator (---)."""
    if len(rows) < 2:
        return
    header = [c.strip() for c in rows[0]]
    body = rows[2:]
    n_cols = len(header)
    # Compute available width
    avail = pdf.w - pdf.l_margin - pdf.r_margin
    col_w = avail / n_cols

    # Header
    pdf.set_fill_color(20, 60, 110)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font("DejaVu", "B", 9)
    h_h = 6
    x0 = pdf.get_x()
    y0 = pdf.get_y()
    if y0 + h_h > pdf.h - pdf.b_margin:
        pdf.add_page()
        y0 = pdf.get_y()
    for i, h in enumerate(header):
        pdf.set_xy(x0 + i * col_w, y0)
        pdf.cell(col_w, h_h, clean_cell(h), border=1, fill=True, align="L")
    pdf.set_xy(x0, y0 + h_h)
    pdf.set_text_color(0, 0, 0)
    pdf.set_font("DejaVu", "", 8.5)

    for ridx, row in enumerate(body):
        cells = [clean_cell(c) for c in row] + [""] * (n_cols - len(row))
        cells = cells[:n_cols]
        # Compute row height (wrap every cell)
        wraps = []
        for c in cells:
            # ~ how many chars per line for that col width at 8.5pt DejaVu? ~ 5.1 chars per 10 mm
            chars_per_line = max(8, int(col_w / 1.7))
            wrapped = textwrap.wrap(c, width=chars_per_line) or [""]
            wraps.append(wrapped)
        n_lines = max(len(w) for w in wraps)
        h_row = n_lines * 4.3 + 1.2
        y = pdf.get_y()
        if y + h_row > pdf.h - pdf.b_margin:
            pdf.add_page()
            y = pdf.get_y()
        if ridx % 2 == 1:
            pdf.set_fill_color(245, 247, 250)
        else:
            pdf.set_fill_color(255, 255, 255)
        # draw cells
        for i, w in enumerate(wraps):
            x = x0 + i * col_w
            pdf.set_xy(x, y)
            # fill rectangle
            pdf.rect(x, y, col_w, h_row, style="DF")
            pdf.set_xy(x + 1.2, y + 0.8)
            for ln_i, line in enumerate(w):
                # Inline bold/code in table cells
                if ln_i > 0:
                    pdf.set_xy(x + 1.2, y + 0.8 + ln_i * 4.3)
                # render without fill to keep table color; inline code will still be blue-ish
                write_rich(pdf, line, base_size=8.5)
        pdf.set_xy(x0, y + h_row)
    pdf.ln(2)
    pdf.set_font("DejaVu", "", 10)


def clean_cell(s: str) -> str:
    # remove bold/code markers for table (simpler)
    s = re.sub(r"\*\*(.+?)\*\*", r"\1", s)
    s = re.sub(r"`([^`]+)`", r"\1", s)
    s = re.sub(r"\*(.+?)\*", r"\1", s)
    return s.strip()


def blockquote(pdf: PDF, lines):
    pdf.ln(1)
    pdf.set_fill_color(255, 249, 232)
    pdf.set_draw_color(230, 200, 100)
    pdf.set_font("DejaVu", "I", 9.5)
    for ln in lines:
        if pdf.get_y() + 5.5 > pdf.h - pdf.b_margin:
            pdf.add_page()
        x = pdf.l_margin
        y = pdf.get_y()
        pdf.rect(x, y, pdf.w - x - pdf.r_margin, 6.5, style="DF")
        pdf.set_xy(x + 3, y + 1.2)
        write_rich(pdf, ln, base_size=9.5)
        pdf.ln(7)
    pdf.ln(1)
    pdf.set_font("DejaVu", "", 10)


# -----------------------------------------------------------------------------

def md_to_pdf(md_text: str, out_path: str):
    pdf = PDF()
    pdf.add_page()

    lines = md_text.splitlines()
    i = 0
    in_code = False
    code_buf = []
    table_buf = []
    quote_buf = []
    num_idx = 0  # numbered list counter per contiguous block

    def flush_table():
        nonlocal table_buf
        if table_buf:
            render_table(pdf, table_buf)
            table_buf = []

    def flush_quote():
        nonlocal quote_buf
        if quote_buf:
            blockquote(pdf, quote_buf)
            quote_buf = []

    while i < len(lines):
        line = lines[i].rstrip()

        # fenced code
        if line.startswith("```"):
            flush_table(); flush_quote()
            if in_code:
                render_code_block(pdf, code_buf)
                code_buf = []
                in_code = False
            else:
                in_code = True
                code_buf = []
            i += 1
            continue
        if in_code:
            code_buf.append(line)
            i += 1
            continue

        # table row?
        if line.startswith("|") and line.endswith("|"):
            flush_quote()
            table_buf.append([c for c in line.strip("|").split("|")])
            i += 1
            continue
        else:
            if table_buf:
                flush_table()

        # blockquote
        if line.startswith(">"):
            quote_buf.append(line[1:].strip())
            i += 1
            continue
        else:
            if quote_buf:
                flush_quote()

        # horizontal rule
        if re.match(r"^\s*---+\s*$", line):
            hr(pdf); i += 1; continue

        # headings
        m = re.match(r"^(#{1,4})\s+(.*)$", line)
        if m:
            level = len(m.group(1))
            heading(pdf, m.group(2).strip(), level); i += 1; num_idx = 0; continue

        # bullet list
        m = re.match(r"^\s*[-*]\s+(.*)$", line)
        if m:
            render_bullet(pdf, m.group(1).strip()); i += 1; num_idx = 0; continue

        # numbered list
        m = re.match(r"^\s*\d+\.\s+(.*)$", line)
        if m:
            num_idx += 1
            render_numbered(pdf, num_idx, m.group(1).strip()); i += 1; continue

        # blank line
        if line.strip() == "":
            pdf.ln(2); i += 1; num_idx = 0; continue

        # paragraph (accumulate until blank/structural)
        para = [line.strip()]
        j = i + 1
        while j < len(lines):
            nxt = lines[j].rstrip()
            if (nxt.strip() == "" or nxt.startswith("#") or nxt.startswith("```")
                or nxt.startswith("|") or nxt.startswith(">")
                or re.match(r"^\s*[-*]\s+", nxt) or re.match(r"^\s*\d+\.\s+", nxt)
                or re.match(r"^\s*---+\s*$", nxt)):
                break
            para.append(nxt.strip())
            j += 1
        paragraph(pdf, " ".join(para))
        i = j
        num_idx = 0

    # flush any trailing blocks
    flush_table(); flush_quote()

    pdf.output(out_path)
    print(f"PDF written: {out_path}")


if __name__ == "__main__":
    with open(MD_PATH, encoding="utf-8") as f:
        md = f.read()
    md_to_pdf(md, PDF_PATH)
