"""Synthetic import checks. No real resident data is used in these fixtures."""

from contextlib import redirect_stdout
import importlib.util
import io
from pathlib import Path
import sqlite3
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("import_dpt", Path(__file__).resolve().parents[1] / "import_dpt.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def dump(values):
    columns = ", ".join("`" + column + "`" for column in module.COLUMNS)
    definition = ",\n".join("  `" + column + "` TEXT" for column in module.COLUMNS)
    return (
        "-- Database: synthetic\n-- Generation Time: 2026-10-13 18:00:00\n"
        "/*!40101 SET NAMES utf8mb4 */;\nDROP TABLE IF EXISTS `dpt`;\n"
        "CREATE TABLE `dpt` (\n" + definition + "\n);\n"
        "INSERT INTO `dpt` (" + columns + ") VALUES\n" + values + ";\n"
    )


VALUES = (
    "(1, 'TOBA', 'TEST', 'TEST VILLAGE', '001', 'D\\'Test; -- example', 'L', '40', "
    "'Line\\nTwo', NULL, '000', NULL, NULL, NULL, NULL, '0000000000'),\n"
    "(2, 'TOBA', 'TEST', 'TEST VILLAGE', '002', 'A ''B'' \\\\ C', 'P', 'unknown', "
    "NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)"
)


class DptImportTest(unittest.TestCase):
    def test_mysql_quoting_nulls_and_comment_text(self):
        rows, count = module.parse_dump(dump(VALUES))
        self.assertEqual(count, 1)
        self.assertEqual(rows[0][5], "D'Test; -- example")
        self.assertEqual(rows[0][8], "Line\nTwo")
        self.assertIsNone(rows[0][9])
        self.assertEqual(rows[1][5], "A 'B' \\ C")

    def test_rejects_extra_sql_and_duplicate_ids(self):
        with self.assertRaises(ValueError):
            module.parse_dump(dump(VALUES) + "DELETE FROM residents;")
        with self.assertRaises(ValueError):
            module.parse_dump(dump(VALUES.replace("(2,", "(1,")))

    def test_atomic_import_preserves_registry_and_repeat_is_idempotent(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "synthetic.sql"
            source.write_text(dump(VALUES))
            database = root / "database.sqlite"
            with sqlite3.connect(database) as connection:
                connection.execute("CREATE TABLE residents (id INTEGER PRIMARY KEY, marker TEXT)")
                connection.execute("INSERT INTO residents VALUES (7, 'preserve')")
            with redirect_stdout(io.StringIO()):
                module.import_dump(source, database, root / "private")
                module.import_dump(source, database, root / "private")
            with sqlite3.connect(database) as connection:
                self.assertEqual(connection.execute("SELECT * FROM residents").fetchall(), [(7, "preserve")])
                self.assertEqual(connection.execute("SELECT COUNT(*) FROM dpt").fetchone()[0], 2)
                self.assertEqual(connection.execute("SELECT COUNT(*) FROM dpt_imports").fetchone()[0], 1)
                self.assertEqual(connection.execute("SELECT jumlah_dpt, laki_laki, perempuan, usia_belum_valid FROM dpt_potensi_desa").fetchone(), (2, 1, 1, 1))
                backup = Path(connection.execute("SELECT backup_path FROM dpt_imports").fetchone()[0])
            with sqlite3.connect(backup) as connection:
                self.assertEqual(connection.execute("SELECT * FROM residents").fetchall(), [(7, "preserve")])
                self.assertEqual(connection.execute("SELECT COUNT(*) FROM sqlite_master WHERE name='dpt'").fetchone()[0], 0)
            source.write_text(dump(VALUES).replace("'40'", "'41'"))
            with self.assertRaises(ValueError), redirect_stdout(io.StringIO()):
                module.import_dump(source, database, root / "private")
            with sqlite3.connect(database) as connection:
                self.assertEqual(connection.execute("SELECT usia FROM dpt WHERE iddpt=1").fetchone()[0], "40")

    def test_failed_verification_rolls_back_new_tables(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "synthetic.sql"
            source.write_text(dump(VALUES))
            database = root / "database.sqlite"
            with sqlite3.connect(database) as connection:
                connection.execute("CREATE TABLE residents (id INTEGER PRIMARY KEY)")
                connection.execute("INSERT INTO residents VALUES (7)")
            original = module.digest_rows
            calls = 0

            def mismatch(rows):
                nonlocal calls
                calls += 1
                return original(rows) if calls == 1 else "mismatch"

            module.digest_rows = mismatch
            try:
                with self.assertRaises(ValueError), redirect_stdout(io.StringIO()):
                    module.import_dump(source, database, root / "private")
            finally:
                module.digest_rows = original
            with sqlite3.connect(database) as connection:
                self.assertEqual(connection.execute("SELECT * FROM residents").fetchall(), [(7,)])
                self.assertEqual(connection.execute("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'dpt%'").fetchone()[0], 0)

    def test_header_rows_are_preserved_and_excluded_from_aggregates(self):
        header = ",\n(3, 'TOBA', 'TEST', 'TEST VILLAGE', '002', 'NAMA', 'JENIS KELAMIN', 'USIA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)"
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "synthetic.sql"
            source.write_text(dump(VALUES + header))
            database = root / "database.sqlite"
            with sqlite3.connect(database) as connection:
                connection.execute("CREATE TABLE residents (id INTEGER PRIMARY KEY)")
            with redirect_stdout(io.StringIO()):
                module.import_dump(source, database, root / "private")
            with sqlite3.connect(database) as connection:
                self.assertEqual(connection.execute("SELECT COUNT(*) FROM dpt").fetchone()[0], 3)
                self.assertEqual(connection.execute("SELECT jumlah_dpt FROM dpt_potensi_desa").fetchone()[0], 2)
                report = module.aggregate_report(connection)
                self.assertEqual(report["excluded_header_rows"], 1)
                self.assertEqual(report["analysable_records"], 2)
                self.assertEqual(report["missing_or_invalid_age"], 1)


if __name__ == "__main__":
    unittest.main()
