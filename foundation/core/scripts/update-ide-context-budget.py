#!/usr/bin/env python3
import json
import math
import re
from collections import OrderedDict
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parent.parent
BUDGET_PATH = REPO_ROOT / "specs" / "IDE-CONTEXT-BUDGET.json"
METHOD = "approx-chars-div-4"
MD_KEYS = ["ide-context-token-estimate", "token-estimate-method"]
JSON_KEYS = ["ide_context_token_estimate", "token_estimate_method"]
COMMENT_PATTERN = re.compile(
    r"\n*<!-- ide-context-token-estimate: \d+; token-estimate-method: [^>]+ -->\s*$",
    re.MULTILINE,
)


def approx_tokens(text: str) -> int:
    return int(math.ceil(len(text) / 4.0))


def load_json(path: Path) -> OrderedDict:
    return json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=OrderedDict)


def dump_json(path: Path, data: OrderedDict) -> None:
    path.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")


def split_frontmatter(text: str):
    if not text.startswith("---\n"):
        return None, None
    end = text.find("\n---\n", 4)
    if end == -1:
        return None, None
    header = text[4:end]
    body = text[end + 5 :]
    return header, body


def update_markdown_file(path: Path, token_value: int) -> None:
    text = path.read_text(encoding="utf-8")
    header, body = split_frontmatter(text)
    if header is None:
        stripped = COMMENT_PATTERN.sub("", text).rstrip()
        updated = (
            stripped
            + "\n\n"
            + f"<!-- ide-context-token-estimate: {token_value}; token-estimate-method: {METHOD} -->\n"
        )
        path.write_text(updated, encoding="utf-8")
        return

    lines = header.splitlines()
    new_lines = []
    inserted = False
    for line in lines:
        if line.startswith("ide-context-token-estimate:") or line.startswith("token-estimate-method:"):
            continue
        new_lines.append(line)
        if line.startswith("next-focus:"):
            new_lines.append(f"ide-context-token-estimate: {token_value}")
            new_lines.append(f"token-estimate-method: {METHOD}")
            inserted = True

    if not inserted:
        insertion_index = min(len(new_lines), 10)
        new_lines[insertion_index:insertion_index] = [
            f"ide-context-token-estimate: {token_value}",
            f"token-estimate-method: {METHOD}",
        ]

    updated = "---\n" + "\n".join(new_lines) + "\n---\n" + body
    path.write_text(updated, encoding="utf-8")


def update_json_file(path: Path, token_value: int) -> None:
    data = load_json(path)
    data[JSON_KEYS[0]] = token_value
    data[JSON_KEYS[1]] = METHOD
    dump_json(path, data)


def update_file_metadata(path: Path, token_value: int) -> None:
    if path.suffix.lower() == ".md":
        update_markdown_file(path, token_value)
        return
    if path.suffix.lower() == ".json":
        update_json_file(path, token_value)


def stabilize_file_metadata(path: Path) -> int:
    previous = None
    for _ in range(8):
        current_text = path.read_text(encoding="utf-8")
        current_tokens = approx_tokens(current_text)
        update_file_metadata(path, current_tokens)
        refreshed_tokens = approx_tokens(path.read_text(encoding="utf-8"))
        if refreshed_tokens == previous:
            return refreshed_tokens
        previous = refreshed_tokens
    raise SystemExit(f"Token metadata for {path} did not converge within the expected iterations.")


def bundle_sum(paths, count_map):
    return sum(count_map[path] for path in paths)


def refresh_budget_file(count_map: dict[str, int]) -> None:
    bundles = OrderedDict(
        [
            (
                "minimal_bootstrap",
                [
                    ".github/copilot-instructions.md",
                    "README-FIRST.md",
                ],
            ),
            (
                "planning_core",
                [
                    ".github/copilot-instructions.md",
                    "README-FIRST.md",
                    "specs/ARCHITECTURE-MAP.md",
                    "specs/FEATURE-REQUEST.md",
                    "specs/OFFICIAL-ABBREVIATIONS.md",
                    "specs/app-features/feature-inventory.json",
                ],
            ),
            (
                "workspace_setup_core",
                [
                    ".github/copilot-instructions.md",
                    "README-FIRST.md",
                    "WORKSPACE-SETUP.md",
                    "DEVOPS-TECH-STACK.md",
                    "specs/VALIDATION-STACK.md",
                    "specs/TERMINAL-RULES.md",
                    "specs/FILE-SAFETY-RULES.md",
                    "specs/ARCHITECTURE-MAP.md",
                    "specs/OFFICIAL-ABBREVIATIONS.md",
                    "specs/app-features/README.md",
                    "specs/app-features/feature-inventory.json",
                ],
            ),
            (
                "implementation_core",
                [
                    ".github/copilot-instructions.md",
                    "README-FIRST.md",
                    "specs/IMPLEMENTATION-RULES.md",
                    "specs/CODESTYLE-RULES.md",
                    "specs/FILE-SAFETY-RULES.md",
                    "specs/VALIDATION-STACK.md",
                    "specs/OFFICIAL-ABBREVIATIONS.md",
                ],
            ),
            (
                "spec_generation_core",
                [
                    ".github/copilot-instructions.md",
                    "README-FIRST.md",
                    "specs/FEATURE-REQUEST.md",
                    "specs/AI-SPECS-WORKFLOW.md",
                    "specs/ARCHITECTURE-MAP.md",
                    "FEATURE-LIFECYCLE.md",
                    "specs/OFFICIAL-ABBREVIATIONS.md",
                    "specs/app-features/README.md",
                    "specs/app-features/feature-inventory.json",
                ],
            ),
        ]
    )

    previous = None
    for _ in range(8):
        data = load_json(BUDGET_PATH)
        for item in data["tracked_files"]:
            item["approx_tokens"] = count_map[item["path"]]

        for bundle in data["bundle_estimates"]:
            bundle["approx_tokens"] = bundle_sum(bundles[bundle["bundle"]], count_map)

        data["tracked_total_approx_tokens"] = sum(count_map.values())
        data[JSON_KEYS[0]] = previous or approx_tokens(BUDGET_PATH.read_text(encoding="utf-8"))
        data[JSON_KEYS[1]] = METHOD
        dump_json(BUDGET_PATH, data)

        refreshed = approx_tokens(BUDGET_PATH.read_text(encoding="utf-8"))
        if refreshed == previous:
            return refreshed
        previous = refreshed

    raise SystemExit("IDE context budget file did not converge within the expected iterations.")


def main() -> None:
    stable = False
    last_count_map = {}

    for _ in range(8):
        budget_data = load_json(BUDGET_PATH)
        tracked_paths = [item["path"] for item in budget_data["tracked_files"]]

        for relative_path in tracked_paths:
            path = REPO_ROOT / relative_path
            stabilize_file_metadata(path)

        count_map = {}
        for relative_path in tracked_paths:
            path = REPO_ROOT / relative_path
            count_map[relative_path] = approx_tokens(path.read_text(encoding="utf-8"))

        count_map["specs/IDE-CONTEXT-BUDGET.json"] = refresh_budget_file(count_map)

        if count_map == last_count_map:
            stable = True
            break
        last_count_map = count_map

    if not stable:
        raise SystemExit("IDE context budget refresh did not converge within the expected iterations.")


if __name__ == "__main__":
    main()