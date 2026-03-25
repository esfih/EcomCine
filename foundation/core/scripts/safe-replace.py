#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Safely replace one exact string occurrence in a file.")
    parser.add_argument("--file", required=True, help="Target file.")
    parser.add_argument("--old", help="Exact old string to replace.")
    parser.add_argument("--new", help="Replacement string.")
    parser.add_argument("--old-file", help="Path to file containing the exact old string.")
    parser.add_argument("--new-file", help="Path to file containing the replacement string.")
    parser.add_argument("--write", action="store_true", help="Apply the replacement. Without this flag, show a dry-run preview only.")
    return parser.parse_args()


def read_value(inline_value: str | None, file_value: str | None, label: str) -> str:
    if inline_value is not None and file_value is not None:
        raise SystemExit(f"Use either --{label} or --{label}-file, not both.")
    if inline_value is not None:
        return inline_value
    if file_value is not None:
        return Path(file_value).read_text(encoding="utf-8")
    raise SystemExit(f"One of --{label} or --{label}-file is required.")


def line_number_for_offset(text: str, offset: int) -> int:
    return text.count("\n", 0, offset) + 1


def main() -> int:
    args = parse_args()
    target = Path(args.file)
    old_value = read_value(args.old, args.old_file, "old")
    new_value = read_value(args.new, args.new_file, "new")

    if not target.exists() or not target.is_file():
        raise SystemExit(f"Target file does not exist: {target}")

    text = target.read_text(encoding="utf-8")
    occurrences = text.count(old_value)

    if occurrences == 0:
        print("No exact match found. Nothing to replace.")
        return 1

    if occurrences > 1:
        print(f"Refusing to replace because the old string appears {occurrences} times.")
        return 1

    offset = text.index(old_value)
    line_number = line_number_for_offset(text, offset)
    print(f"Exact single match found in {target} at line {line_number}.")

    if not args.write:
        print("Dry run only. Re-run with --write to apply the replacement.")
        return 0

    target.write_text(text.replace(old_value, new_value, 1), encoding="utf-8", newline="\n")
    print("Replacement written successfully.")
    return 0


if __name__ == "__main__":
    sys.exit(main())