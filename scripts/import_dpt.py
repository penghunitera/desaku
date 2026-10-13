#!/usr/bin/env python3
"""Import a TablePlus dpt dump into DESAKU's local SQLite without executing dump SQL.

Usage: python3 scripts/import_dpt.py /absolute/path/to/dump.sql
Source records stay separate from the verified residents registry. Only aggregate
statistics are written to the JSON report; names and contacts stay in SQLite.
"""

import argparse
from datetime import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import sqlite3
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]
COLUMNS = (
    "iddpt", "kabupaten", "kecamatan", "desa_kelurahan", "tps", "nama",
    "jenis_kelamin", "usia", "desakelurahan", "rt", "rw", "created_at",
    "created_by", "updated_at", "updated_by", "nowa",
)
TOKEN = re.compile(r"\s*(NULL|-?\d+|'(?:[^'\\]|\\.|'')*')\s*([,)])", re.S | re.I)


def statements(text):
    """Split statements, dropping SQL comments and respecting quoted semicolons."""
    start = 0
    position = 0
    quote = None
    parts = []
    while position < len(text):
        character = text[position]
        if quote:
            if character == "\\" and quote != "`":
                position += 2
                continue
            if character == quote:
                if position + 1 < len(text) and text[position + 1] == quote:
                    position += 2
                    continue
                quote = None
        elif character in "'\"`":
            quote = character
        elif text.startswith("/*", position) or character == "#" or (
            text.startswith("--", position)
            and (position + 2 == len(text) or text[position + 2].isspace())
        ):
            parts.append(text[start:position])
            if text.startswith("/*", position):
                end = text.find("*/", position + 2)
                if end == -1:
                    raise ValueError("Unterminated SQL comment")
                position = end + 2
            else:
                end = text.find("\n", position)
                position = len(text) if end == -1 else end + 1
            parts.append(" ")
            start = position
            continue
        elif character == ";":
            parts.append(text[start:position])
            statement = "".join(parts).strip()
            if statement:
                yield statement
            parts = []
            start = position + 1
        position += 1
    if quote:
        raise ValueError("Unterminated SQL string")
    if "".join(parts + [text[start:]]).strip():
        raise ValueError("SQL statement missing its terminating semicolon")


def decode_string(token):
    text = token[1:-1]
    escapes = {"0": "\0", "b": "\b", "n": "\n", "r": "\r", "t": "\t", "Z": "\x1a"}
    result = []
    position = 0
    while position < len(text):
        if text[position] == "\\":
            position += 1
            character = text[position]
            result.append("\\" + character if character in "%_" else escapes.get(character, character))
        elif text.startswith("''", position):
            result.append("'")
            position += 1
        else:
            result.append(text[position])
        position += 1
    return "".join(result)


def insert_rows(statement):
    header = re.match(r"INSERT\s+INTO\s+`dpt`\s*\((.*?)\)\s*VALUES\s*", statement, re.S | re.I)
    if not header or tuple(re.findall(r"`([^`]+)`", header[1])) != COLUMNS:
        raise ValueError("Unsupported INSERT table or column order")
    values = statement[header.end():]
    position = 0
    while position < len(values):
        while position < len(values) and values[position].isspace():
            position += 1
        if position == len(values) or values[position] != "(":
            raise ValueError("Expected a DPT value tuple")
        position += 1
        row = []
        for column in range(len(COLUMNS)):
            match = TOKEN.match(values, position)
            if not match or match[2] != (")" if column == len(COLUMNS) - 1 else ","):
                raise ValueError("Unsupported DPT value or incorrect column count")
            token = match[1]
            row.append(None if token.upper() == "NULL" else decode_string(token) if token[0] == "'" else int(token))
            position = match.end()
        if not isinstance(row[0], int) or row[0] < 0 or any(
            value is not None and not isinstance(value, str) for value in row[1:]
        ):
            raise ValueError("DPT values do not match the expected source types")
        yield tuple(row)
        while position < len(values) and values[position].isspace():
            position += 1
        if position < len(values):
            if values[position] != ",":
                raise ValueError("Unexpected content after a DPT tuple")
            position += 1


def parse_dump(text):
    rows = []
    schema_count = 0
    insert_count = 0
    for statement in statements(text):
        if re.fullmatch(r"DROP\s+TABLE\s+IF\s+EXISTS\s+`dpt`", statement, re.I):
            continue  # The dump's DROP is never executed.
        if re.match(r"CREATE\s+TABLE\s+`dpt`\s*\(", statement, re.I):
            columns = tuple(re.findall(r"^\s*`([^`]+)`\s+", statement, re.M))
            if columns != COLUMNS or schema_count:
                raise ValueError("Unexpected DPT schema")
            schema_count += 1
        elif statement.upper().startswith("INSERT"):
            rows.extend(insert_rows(statement))
            insert_count += 1
        else:
            raise ValueError("Dump contains an unsupported statement; no SQL was executed")
    if schema_count != 1 or not rows or len({row[0] for row in rows}) != len(rows):
        raise ValueError("Missing DPT schema/data or duplicate source IDs")
    return rows, insert_count


def digest_rows(rows):
    digest = hashlib.sha256()
    for row in rows:
        digest.update(json.dumps(tuple(row), ensure_ascii=False, separators=(",", ":")).encode("utf-8"))
        digest.update(b"\n")
    return digest.hexdigest()


def aggregate_report(connection):
    connection.row_factory = sqlite3.Row
    summary = dict(connection.execute("""
        SELECT COUNT(*) AS analysable_records,
               COUNT(DISTINCT kabupaten) AS regencies,
               COUNT(DISTINCT kabupaten || char(31) || kecamatan) AS districts,
               COUNT(DISTINCT kabupaten || char(31) || kecamatan || char(31) || desa_kelurahan) AS villages,
               SUM(CASE WHEN jenis_kelamin = 'L' THEN 1 ELSE 0 END) AS male,
               SUM(CASE WHEN jenis_kelamin = 'P' THEN 1 ELSE 0 END) AS female,
               SUM(CASE WHEN jenis_kelamin NOT IN ('L', 'P') THEN 1 ELSE 0 END) AS unknown_gender,
               SUM(CASE WHEN usia_tercatat IS NULL THEN 1 ELSE 0 END) AS missing_or_invalid_age,
               SUM(CASE WHEN usia_tercatat < 17 THEN 1 ELSE 0 END) AS age_under_17,
               MIN(usia_tercatat) AS minimum_recorded_age,
               MAX(usia_tercatat) AS maximum_recorded_age
        FROM dpt_demografi WHERE baris_judul = 0
    """).fetchone())
    summary["total_records"] = connection.execute("SELECT COUNT(*) FROM dpt").fetchone()[0]
    summary["excluded_header_rows"] = connection.execute(
        "SELECT COUNT(*) FROM dpt_demografi WHERE baris_judul = 1"
    ).fetchone()[0]
    summary["by_district"] = [dict(row) for row in connection.execute("""
        SELECT kabupaten, kecamatan, SUM(jumlah_dpt) AS jumlah_dpt,
               COUNT(*) AS jumlah_desa FROM dpt_potensi_desa
        GROUP BY kabupaten, kecamatan ORDER BY jumlah_dpt DESC
    """)]
    summary["by_village"] = [dict(row) for row in connection.execute(
        "SELECT * FROM dpt_potensi_desa ORDER BY kabupaten, kecamatan, desa_kelurahan"
    )]
    summary["data_quality"] = dict(connection.execute("""
        SELECT SUM(CASE WHEN TRIM(COALESCE(nama, '')) = '' THEN 1 ELSE 0 END) AS missing_name,
               SUM(CASE WHEN TRIM(COALESCE(desa_kelurahan, '')) = '' THEN 1 ELSE 0 END) AS missing_village,
               SUM(CASE WHEN TRIM(COALESCE(tps, '')) = '' THEN 1 ELSE 0 END) AS missing_tps,
               SUM(CASE WHEN TRIM(COALESCE(rt, '')) = '' THEN 1 ELSE 0 END) AS missing_rt,
               SUM(CASE WHEN TRIM(COALESCE(rw, '')) = '' THEN 1 ELSE 0 END) AS missing_rw,
               SUM(CASE WHEN TRIM(COALESCE(rt, '')) IN ('', '0', '000') THEN 1 ELSE 0 END) AS missing_or_placeholder_rt,
               SUM(CASE WHEN TRIM(COALESCE(rw, '')) IN ('', '0', '000') THEN 1 ELSE 0 END) AS missing_or_placeholder_rw,
               SUM(CASE WHEN TRIM(COALESCE(nowa, '')) <> '' THEN 1 ELSE 0 END) AS records_with_contact
        FROM dpt
    """).fetchone())
    summary["data_quality"]["potential_duplicate_groups"] = connection.execute("""
        SELECT COUNT(*) FROM (
            SELECT 1 FROM dpt WHERE iddpt IN (
                SELECT iddpt FROM dpt_demografi WHERE baris_judul = 0
            ) GROUP BY kabupaten, kecamatan, desa_kelurahan,
                nama, jenis_kelamin, usia, desakelurahan, rt, rw HAVING COUNT(*) > 1
        )
    """).fetchone()[0]
    return summary


def import_dump(source, database, private_directory):
    payload = source.read_bytes()
    rows, insert_count = parse_dump(payload.decode("utf-8"))
    checksum = hashlib.sha256(payload).hexdigest()
    source_digest = digest_rows(sorted(rows, key=lambda row: row[0]))
    private_directory.mkdir(parents=True, exist_ok=True, mode=0o700)
    os.chmod(private_directory, 0o700)
    timestamp = datetime.now(ZoneInfo("Asia/Jakarta"))
    connection = sqlite3.connect(database.as_uri() + "?mode=rw", uri=True, timeout=30)
    try:
        tables = {row[0] for row in connection.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        if "dpt" in tables:
            if "dpt_imports" not in tables or connection.execute(
                "SELECT source_sha256 FROM dpt_imports"
            ).fetchall() != [(checksum,)]:
                raise ValueError("Existing DPT data has a different source; refusing to overwrite it")
            status = "already_imported"
            backup_path = Path(connection.execute("SELECT backup_path FROM dpt_imports").fetchone()[0])
            connection.execute("BEGIN IMMEDIATE")
        else:
            connection.execute("BEGIN IMMEDIATE")
            # Read from another connection so the backup includes only committed data.
            backup_path = private_directory / ("before-dpt-" + timestamp.strftime("%Y%m%d-%H%M%S") + ".sqlite")
            with sqlite3.connect(database.as_uri() + "?mode=ro", uri=True) as reader:
                with sqlite3.connect(backup_path) as backup:
                    reader.backup(backup)
            os.chmod(backup_path, 0o600)
            definition = ", ".join('"' + column + '" ' + (
                "INTEGER PRIMARY KEY" if index == 0 else "TEXT"
            ) for index, column in enumerate(COLUMNS))
            connection.execute("CREATE TABLE dpt (" + definition + ")")
            connection.executemany("INSERT INTO dpt VALUES (" + ",".join(["?"] * len(COLUMNS)) + ")", rows)
            connection.execute("CREATE INDEX dpt_wilayah_index ON dpt (kabupaten, kecamatan, desa_kelurahan)")
            connection.execute("""CREATE TABLE dpt_imports (
                id INTEGER PRIMARY KEY CHECK (id = 1), source_filename TEXT NOT NULL,
                source_sha256 TEXT NOT NULL UNIQUE, source_database TEXT,
                source_generated_at TEXT, imported_at TEXT NOT NULL, row_count INTEGER NOT NULL,
                insert_statement_count INTEGER NOT NULL, rows_sha256 TEXT NOT NULL,
                backup_path TEXT NOT NULL
            )""")
            origin = re.search(r"^-- Database:\s*(.+)$", payload.decode("utf-8"), re.M)
            generated = re.search(r"^-- Generation Time:\s*(.+)$", payload.decode("utf-8"), re.M)
            connection.execute("INSERT INTO dpt_imports VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)", (
                source.name, checksum, origin[1].strip() if origin else None,
                generated[1].strip() if generated else None, timestamp.isoformat(),
                len(rows), insert_count, source_digest, str(backup_path),
            ))
            status = "imported"
        result_digest = digest_rows(connection.execute("SELECT * FROM dpt ORDER BY iddpt"))
        if result_digest != source_digest:
            raise ValueError("Full row verification failed; the import was rolled back")
        if connection.execute("PRAGMA quick_check").fetchall() != [("ok",)]:
            raise ValueError("SQLite integrity verification failed")
        # Rebuild only the derived views, including on a repeat import.
        connection.execute("DROP VIEW IF EXISTS dpt_potensi_desa")
        connection.execute("DROP VIEW IF EXISTS dpt_demografi")
        view_sql = (ROOT / "database/queries/dpt_potensi.sql").read_text()
        for statement in statements(view_sql):
            connection.execute(statement)
        report = aggregate_report(connection)
        if (
            sum(row["jumlah_dpt"] for row in report["by_village"]) != report["analysable_records"]
            or report["analysable_records"] + report["excluded_header_rows"] != len(rows)
        ):
            raise ValueError("Village aggregate verification failed")
        connection.commit()
        os.chmod(database, 0o600)
        report.update({
            "status": status, "source_filename": source.name, "source_sha256": checksum,
            "database": str(database), "backup": str(backup_path) if backup_path else None,
            "rows_verified_sha256": result_digest, "generated_at": timestamp.isoformat(),
            "limitations": [
                "DPT records are not a complete or verified population register.",
                "Ages are copied from the source; the age reference date is unknown.",
                "No NIK, family-card number, birth date, education, occupation, income or assets are supplied.",
                "Age bands indicate recorded demographics, not employment or economic capacity.",
                "Names/contact numbers are kept only in the local raw dpt table.",
                "Potential duplicate groups are retained and require source verification.",
                "Repeated column-header rows remain in dpt but are excluded from the demographic views.",
                "RT/RW fields mostly contain zero placeholders and cannot support detailed spatial analysis.",
            ],
        })
        report_path = private_directory / (source.stem + "-summary.json")
        report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
        os.chmod(report_path, 0o600)
        print(json.dumps({key: report[key] for key in (
            "status", "total_records", "analysable_records", "excluded_header_rows",
            "regencies", "districts", "villages", "male", "female",
            "unknown_gender", "missing_or_invalid_age", "age_under_17", "data_quality", "backup",
        )}, ensure_ascii=False, indent=2))
        print("Aggregate report:", report_path)
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path)
    parser.add_argument("--database", type=Path, default=ROOT / "database/database.sqlite")
    parser.add_argument("--private-directory", type=Path, default=ROOT / "storage/app/private/dpt-imports")
    arguments = parser.parse_args()
    try:
        import_dump(arguments.source.resolve(), arguments.database.resolve(), arguments.private_directory.resolve())
    except (ValueError, sqlite3.Error) as error:
        parser.exit(1, "Import failed: " + str(error) + "\n")
