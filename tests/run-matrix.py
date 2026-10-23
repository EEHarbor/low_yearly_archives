#!/usr/bin/env python3
"""Run the full configured suite with explicit Herd binaries and fresh reports."""

import json
import os
import re
from pathlib import Path
import subprocess
import tempfile
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parent.parent
reports = root / "tests" / "reports"
reports.mkdir(exist_ok=True)
report_dir = Path(tempfile.mkdtemp(prefix="matrix-", dir=str(reports)))
herd = Path(os.environ.get("HERD_PHP_DIRECTORY", str(Path.home() / "Library/Application Support/Herd/bin")))
results = []
for version in ("74", "80", "81", "82", "83", "84", "85"):
    binary = herd / ("php" + version)
    runtime = subprocess.run([str(binary), "-r", "echo PHP_VERSION;"], capture_output=True, text=True, check=True).stdout
    junit = report_dir / ("php" + version + ".xml")
    command = [str(binary), "-d", "error_reporting=-1", "-d", "display_errors=1", str(root / "tests/.phpunit/phpunit-9.phar"), "-c", str(root / "phpunit.xml"), "--log-junit", str(junit)]
    process = subprocess.run(command, cwd=str(root), capture_output=True, text=True, timeout=300)
    (report_dir / ("php" + version + ".log")).write_text(process.stdout + process.stderr)
    counts = {}
    if junit.is_file():
        suite = ET.parse(junit).getroot().find("testsuite")
        if suite is not None:
            counts = {key: int(suite.attrib.get(key, 0)) for key in ("tests", "assertions", "errors", "failures", "warnings", "skipped")}
    if not counts.get("tests"):
        raise RuntimeError("Missing fresh nonempty JUnit report for PHP " + runtime)
    for label in ("risky", "incomplete"):
        match = re.search(label + r": (\d+)", process.stdout, re.IGNORECASE)
        counts[label] = int(match.group(1)) if match else 0
    result = {"php": runtime, "command": command, "exit": process.returncode, "counts": counts, "log": str(report_dir / ("php" + version + ".log"))}
    results.append(result)
    print(json.dumps(result), flush=True)
(report_dir / "summary.json").write_text(json.dumps(results, indent=2) + "\n")
print("MATRIX_REPORT=" + str(report_dir))
raise SystemExit(1 if any(result["exit"] for result in results) else 0)
