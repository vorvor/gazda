"""Check that subpages use the homepage's rendered base palette."""
from pathlib import Path
import unittest
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]

class PaletteTest(unittest.TestCase):
    def test_subpage_palette_matches_homepage(self):
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, executable_path='/opt/hermes/.playwright/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell', args=['--no-sandbox'])
            page = browser.new_page()
            def palette():
                return page.evaluate('''() => Object.fromEntries(['body','.site-header','.site-footer'].map(selector => [selector, {background:getComputedStyle(document.querySelector(selector)).backgroundColor, color:getComputedStyle(document.querySelector(selector)).color}]))''')
            page.goto((ROOT/'index.html').as_uri())
            expected = palette()
            page.goto((ROOT/'egyesulet.html').as_uri())
            actual = palette()
            browser.close()
        self.assertEqual(actual, expected)

if __name__ == '__main__':
    unittest.main()
