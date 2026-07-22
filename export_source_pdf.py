#!/usr/bin/env python3
"""
Export project source code to a single PDF document.
Usage: python export_source_pdf.py [--output Applicant_WPU_Source.pdf]
"""

import argparse
import os
import sys
from datetime import datetime
from pathlib import Path

from fpdf import FPDF

# Project root (directory containing this script)
ROOT = Path(__file__).resolve().parent

# Directories to skip entirely
SKIP_DIRS = {
    ".git",
    ".cursor",
    "vendor",
    "node_modules",
    "cache",
    "logs",
    "__pycache__",
    ".vscode",
    ".idea",
}

# Path prefixes (relative to root) to skip
SKIP_PATH_PREFIXES = (
    "assets/vendor/",
    "applicant/pages/uploads/",
)

# Individual files to skip (sensitive, generated, or non-source)
SKIP_FILES = {
    "composer.lock",
    "export_source_pdf.py",
    "config/email.php",
    "config/oauth.php",
    "config/google_credentials.json",
    ".env",
    ".env.local",
}

# Extensions to include
SOURCE_EXTENSIONS = {
    ".php",
    ".sql",
    ".js",
    ".css",
    ".html",
    ".json",
    ".md",
    ".htaccess",
    ".example.php",
}

# Extensions always excluded (binaries, images, archives)
BINARY_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".svg",
    ".pdf", ".zip", ".gz", ".tar", ".woff", ".woff2", ".ttf", ".eot",
    ".min.js", ".min.css",
}


def should_include(path: Path) -> bool:
    rel = path.relative_to(ROOT).as_posix()

    if rel in SKIP_FILES:
        return False

    for prefix in SKIP_PATH_PREFIXES:
        if rel.startswith(prefix):
            return False

    # Skip minified bundles
    name_lower = path.name.lower()
    if name_lower.endswith(".min.js") or name_lower.endswith(".min.css"):
        return False

    if path.suffix.lower() in BINARY_EXTENSIONS:
        return False

    if path.suffix.lower() in SOURCE_EXTENSIONS:
        return True

    # Files without extension (e.g. .htaccess handled above) or special names
    if path.name in (".htaccess", ".gitignore"):
        return True

    return False


def collect_source_files() -> list[Path]:
    files: list[Path] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        # Prune skipped directories in-place
        dirnames[:] = sorted(
            d for d in dirnames if d not in SKIP_DIRS and not d.startswith(".")
        )

        current = Path(dirpath)
        for filename in sorted(filenames):
            filepath = current / filename
            if should_include(filepath):
                files.append(filepath)

    return sorted(files, key=lambda p: p.relative_to(ROOT).as_posix().lower())


def sanitize_text(text: str) -> str:
    """Replace characters that core PDF fonts cannot render."""
    return text.encode("latin-1", errors="replace").decode("latin-1")


def read_file_safe(path: Path) -> str:
    try:
        raw = path.read_bytes()
    except OSError as exc:
        return f"[Error reading file: {exc}]"

    # Strip BOM
    if raw.startswith(b"\xef\xbb\xbf"):
        raw = raw[3:]

    for encoding in ("utf-8", "cp1252", "latin-1"):
        try:
            return raw.decode(encoding)
        except UnicodeDecodeError:
            continue

    return raw.decode("utf-8", errors="replace")


class SourceCodePDF(FPDF):
    def __init__(self, project_name: str):
        super().__init__(orientation="P", unit="mm", format="A4")
        self.project_name = project_name
        self.set_auto_page_break(auto=True, margin=15)
        self.set_margins(12, 15, 12)

    def header(self):
        self.set_font("Courier", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 5, sanitize_text(self.project_name), align="L")
        self.ln(6)

    def footer(self):
        self.set_y(-12)
        self.set_font("Courier", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 8, f"Page {self.page_no()}/{{nb}}", align="C")

    def add_title_page(self, file_count: int):
        self.add_page()
        self.set_font("Helvetica", "B", 22)
        self.set_text_color(30, 30, 30)
        self.ln(40)
        self.multi_cell(0, 12, sanitize_text(f"{self.project_name}\nSource Code Export"), align="C")
        self.ln(10)
        self.set_font("Helvetica", "", 11)
        self.set_text_color(80, 80, 80)
        self.multi_cell(
            0,
            7,
            sanitize_text(
                f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
                f"Files included: {file_count}\n"
                f"Root: {ROOT}"
            ),
            align="C",
        )

    def add_toc_entry(self, index: int, rel_path: str):
        self.set_font("Courier", "", 9)
        self.set_text_color(40, 40, 40)
        line = f"{index:3}. {rel_path}"
        self.cell(0, 5, sanitize_text(line), new_x="LMARGIN", new_y="NEXT")

    def add_source_file(self, rel_path: str, content: str):
        self.add_page()

        # File path banner
        self.set_fill_color(240, 240, 245)
        self.set_font("Courier", "B", 10)
        self.set_text_color(20, 60, 120)
        self.cell(0, 8, sanitize_text(rel_path), fill=True, new_x="LMARGIN", new_y="NEXT")
        self.ln(2)

        # Code body
        self.set_font("Courier", "", 7.5)
        self.set_text_color(30, 30, 30)

        line_height = 3.8
        max_chars = 100
        bottom_limit = self.h - self.b_margin

        for line in content.splitlines():
            safe = sanitize_text(line)

            if self.get_y() + line_height > bottom_limit:
                self.add_page()
                self.set_font("Courier", "", 7.5)
                self.set_text_color(30, 30, 30)

            if not safe:
                self.ln(line_height)
                continue

            chunks = [safe[i : i + max_chars] for i in range(0, len(safe), max_chars)] or [""]
            for chunk in chunks:
                if self.get_y() + line_height > bottom_limit:
                    self.add_page()
                    self.set_font("Courier", "", 7.5)
                    self.set_text_color(30, 30, 30)
                self.cell(0, line_height, chunk, new_x="LMARGIN", new_y="NEXT")


def build_pdf(output_path: Path) -> tuple[int, list[str]]:
    files = collect_source_files()
    if not files:
        print("No source files found.", file=sys.stderr)
        sys.exit(1)

    project_name = ROOT.name
    pdf = SourceCodePDF(project_name)
    pdf.alias_nb_pages()

    pdf.add_title_page(len(files))

    # Table of contents
    pdf.add_page()
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(30, 30, 30)
    pdf.cell(0, 10, "Table of Contents", new_x="LMARGIN", new_y="NEXT")
    pdf.ln(4)

    rel_paths: list[str] = []
    for i, filepath in enumerate(files, start=1):
        rel = filepath.relative_to(ROOT).as_posix()
        rel_paths.append(rel)
        pdf.add_toc_entry(i, rel)

    # Source files
    for rel, filepath in zip(rel_paths, files):
        content = read_file_safe(filepath)
        pdf.add_source_file(rel, content)

    pdf.output(str(output_path))
    return len(files), rel_paths


def main():
    parser = argparse.ArgumentParser(description="Export project source code to PDF")
    parser.add_argument(
        "--output", "-o",
        default=str(ROOT / "Applicant_WPU_Source_Code.pdf"),
        help="Output PDF path (default: Applicant_WPU_Source_Code.pdf in project root)",
    )
    args = parser.parse_args()

    output_path = Path(args.output).resolve()
    print(f"Scanning source files in: {ROOT}")
    count, paths = build_pdf(output_path)
    print(f"Exported {count} files to: {output_path}")
    print("Included files:")
    for p in paths:
        print(f"  - {p}")


if __name__ == "__main__":
    main()
