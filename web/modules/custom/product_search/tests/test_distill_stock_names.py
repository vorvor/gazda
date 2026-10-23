import importlib.util
import pathlib
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[1] / 'scripts' / 'distill_stock_names.py'

class DistillNamesTest(unittest.TestCase):
    def test_distillation(self):
        self.assertTrue(SCRIPT.exists(), 'The stock-name distiller must exist')
        spec = importlib.util.spec_from_file_location('distill', SCRIPT)
        assert spec is not None and spec.loader is not None
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        types = ['Kerti gereblye', 'Lombseprű', 'Gumicsizma', 'Virágcserép', 'Dekoratív kaspó', 'Asztali lámpa', 'Kerti zsák', 'Virágföld']
        cases = [
            ('Limezöld műanyag lombseprű', 'Kerti gereblye', 'Lombseprű'),
            ('Dunlop olívazöld magas gumicsizma', 'Gumicsizma', 'Gumicsizma'),
            ('Fehér függőlegesen bordázott virágcserép', 'Virágcserép', 'Virágcserép'),
            ('Fehér magas, kúpos kaspó', 'Dekoratív kaspó', 'Kaspó'),
            ('Kanlux SUZI HR-60 csíptetős asztali lámpa', 'Asztali lámpa', 'Asztali lámpa'),
            ('Muskátli virágföldje zsák', 'Virágföld', 'Virágföld'),
        ]
        for name, kind, expected in cases:
            self.assertEqual(module.distill(name, kind, types), expected)

if __name__ == '__main__':
    unittest.main()
