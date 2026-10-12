#!/usr/bin/env python3
"""Build deterministic, versioned Joomla installer and source distribution ZIPs."""
from __future__ import annotations

from pathlib import Path
import shutil
import zipfile
import xml.etree.ElementTree as ET

VERSION = "0-1-8"
ROOT = Path(__file__).resolve().parent
BUILD = ROOT / "build"
DIST = ROOT / "dist"
STAMP = (2026, 10, 12, 12, 0, 0)


def write_zip(destination: Path, sources: list[tuple[Path, str]]) -> None:
    """Create a stable ZIP archive from explicitly mapped paths."""
    destination.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for src, name in sorted(sources, key=lambda item: item[1]):
            info = zipfile.ZipInfo(name, STAMP)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o644 << 16)
            archive.writestr(info, src.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def tree_files(path: Path) -> list[tuple[Path, str]]:
    """Collect source files without private files, artifacts, or Git internals."""
    return [(p, p.relative_to(path).as_posix()) for p in path.rglob("*") if p.is_file() and not p.name.startswith(".")]


def validate_package_manifest(path: Path, component_name: str, module_name: str) -> None:
    """Assert the installer contains exactly the named children."""
    tree = ET.parse(path)
    files = tree.findall("./files/file")
    assert [(f.attrib["type"], f.attrib["id"], f.text) for f in files] == [
        ("component", "com_pungacontactform", component_name),
        ("module", "mod_pungacontactform", module_name),
    ]


def build() -> None:
    """Create Joomla package installer, its children, and the Git-friendly source ZIP."""
    if BUILD.exists():
        shutil.rmtree(BUILD)
    BUILD.mkdir(exist_ok=True)
    DIST.mkdir(exist_ok=True)

    component = BUILD / "com_pungacontactform.zip"
    module = BUILD / "mod_pungacontactform.zip"
    write_zip(component, tree_files(ROOT / "component"))
    write_zip(module, tree_files(ROOT / "module"))

    package_manifest = ROOT / "package" / "pkg_pungacontactform.xml"
    validate_package_manifest(package_manifest, component.name, module.name)
    package = DIST / f"pkg_pungacontactform_v{VERSION}.zip"
    write_zip(package, [(package_manifest, package_manifest.name), (component, component.name), (module, module.name)])

    source = DIST / f"pungacontactform_v{VERSION}_source.zip"
    source_files: list[tuple[Path, str]] = []
    for folder in ["component", "module", "package", "tests", "docs"]:
        source_files.extend((path, f"pungacontactform/{folder}/{name}") for path, name in tree_files(ROOT / folder))
    source_files.append((ROOT / "README.md", "pungacontactform/README.md"))
    source_files.append((ROOT / "CHANGELOG.md", "pungacontactform/CHANGELOG.md"))
    source_files.append((ROOT / ".gitignore", "pungacontactform/.gitignore"))
    source_files.append((ROOT / "build.py", "pungacontactform/build.py"))
    write_zip(source, source_files)

    for archive in [component, module, package, source]:
        with zipfile.ZipFile(archive) as zipped:
            assert zipped.testzip() is None, f"Corrupt ZIP: {archive}"
        print(f"OK: {archive} ({archive.stat().st_size:,} bytes)")


if __name__ == "__main__":
    build()
