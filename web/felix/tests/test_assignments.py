"""Real HTTP + CSV integration using isolated private fixtures."""
import http.cookiejar
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.request
import urllib.error
import urllib.parse
import shutil
import test_assignment_dates as date_tests

ROOT = Path(__file__).resolve().parents[1]

class AssignmentsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.harness = date_tests.AssignmentDatesTest
        cls.addClassCleanup(cls.harness.doClassCleanups)
        cls.harness.setUpClass()
        cls.base = cls.harness.base
        for name in ['assignments.php', 'index.php', 'list_files.php', 'get_roster.php']:
            shutil.copy2(ROOT / name, cls.harness.app / name)
        (cls.harness.app / 'data').mkdir(exist_ok=True)

    def setUp(self):
        self.harness().setUp()
        self.harness.php("$db->beginTransaction(); $db->ensureDay('2026-11-12','Tesztforgatás'); $db->commit();")

    @classmethod
    def request(cls, path, user=None, data=None):
        headers = {}
        if user: headers['Cookie'] = 'FELIXSESSID=test' + user.lower() + '1234567890'
        if data is not None:
            data = urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(cls.base + path, data=data, headers=headers)
        try: response = urllib.request.urlopen(req)
        except urllib.error.HTTPError as error: response = error
        return response.status, response.read().decode()

    def test_01_admin_authorization(self):
        for path in ['assignments.php', 'assignments_api.php']:
            self.assertEqual(self.request(path)[0], 401)
            self.assertEqual(self.request(path, 'Kecsu')[0], 403)
            self.assertEqual(self.request(path, 'Kecsu', {'action':'assign', 'csrf':'a'*64})[0], 403)

    def test_02_persisted_assignment_lifecycle(self):
        status, body = self.request('assignments_api.php?date=2026-11-12', 'Elek')
        self.assertEqual(status, 200)
        self.assertTrue(body.startswith('{'), 'Expected real database-backed JSON')
        state = json.loads(body)
        self.assertEqual(state['day']['date'], '2026-11-12')
        self.assertGreaterEqual(len(state['people']), 8)
        person = state['people'][-1]
        payload = {'action':'assign', 'date':'2026-11-12', 'person_id':person['id'], 'csrf':'a'*64}
        self.assertEqual(self.request('assignments_api.php', 'Elek', {**payload, 'csrf':'bad'})[0], 403)
        for invalid in ['999999999', '1 OR 1=1', '-1', '0']:
            self.assertEqual(self.request('assignments_api.php', 'Elek', {**payload, 'person_id':invalid})[0], 400)
        self.assertEqual(self.request('assignments_api.php', 'Elek', {**payload, 'date':'2026-11-31'})[0], 400)
        self.assertEqual(self.request('assignments_api.php', 'Elek', {**payload, 'action':'invalid'})[0], 400)
        try:
            for _ in range(2):
                self.assertEqual(self.request('assignments_api.php', 'Elek', payload)[0], 200)
            reloaded = json.loads(self.request('assignments_api.php?date=2026-11-12', 'Elek')[1])
            self.assertEqual(sum(p['id'] == person['id'] for p in reloaded['assigned']), 1)
            searched = json.loads(self.request('assignments_api.php?date=2026-11-12&q=' + urllib.parse.quote(person['name']), 'Elek')[1])
            self.assertEqual([p['id'] for p in searched['people']], [person['id']])
        finally:
            self.assertEqual(self.request('assignments_api.php', 'Elek', {**payload, 'action':'remove'})[0], 200)
        final = json.loads(self.request('assignments_api.php?date=2026-11-12', 'Elek')[1])
        self.assertNotIn(person['id'], [p['id'] for p in final['assigned']])

    def test_03_browser_search_assign_reload_remove(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, args=['--no-sandbox'])
            context = browser.new_context(viewport={'width':390, 'height':844})
            context.add_cookies([{'name':'FELIXSESSID', 'value':'testelek1234567890', 'url':self.base}])
            page = context.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(self.base + 'assignments.php')
            self.assertEqual(page.title(), 'Forgatási nap beosztása · Felix')
            page.locator('#person-search').fill('Almavirág')
            page.wait_for_function("document.querySelectorAll('#people .person').length === 1")
            self.assertEqual(page.locator('#people .person').count(), 1)
            page.locator('#people .assignment-action').click()
            page.locator('#assigned .person').filter(has_text='Almavirág').wait_for()
            page.reload()
            card = page.locator('#assigned .person').filter(has_text='Almavirág')
            card.wait_for()
            self.assertEqual(card.count(), 1)
            self.assertTrue(page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            Path('/workspace/tmp').mkdir(exist_ok=True)
            page.screenshot(path='/workspace/tmp/felix-assignments-mobile.png', full_page=True)
            card.locator('.assignment-action').click()
            page.wait_for_function("!document.querySelector('#assigned').textContent.includes('Almavirág')")
            page.reload()
            page.locator('#person-search').wait_for()
            page.wait_for_function("document.querySelector('#status').textContent !== 'Betöltés…'")
            self.assertEqual(page.locator('#assigned .person').filter(has_text='Almavirág').count(), 0)
            self.assertFalse(errors, errors)
            browser.close()

    def test_04_new_date_reaches_roster_header(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, args=['--no-sandbox'])
            context = browser.new_context(viewport={'width':390, 'height':844})
            context.add_cookies([{'name':'FELIXSESSID', 'value':'testelek1234567890', 'url':self.base}])
            page = context.new_page()
            page.goto(self.base + 'assignments.php?date=2026-11-12')
            page.locator('#people .person').first.wait_for()
            page.locator('#filming-date').fill('2026-11-13')
            page.wait_for_function("document.querySelector('#day-date').textContent.includes('november 13.')")
            page.locator('#person-search').fill('Almavirág')
            page.wait_for_function("document.querySelectorAll('#people .person').length === 1")
            page.locator('#people .assignment-action').click()
            page.locator('#assigned .person').wait_for()
            page.reload()
            page.locator('#assigned .person').wait_for()
            self.assertEqual(page.locator('#filming-date').input_value(), '2026-11-13')
            page.locator('#back-to-roster').click()
            page.locator('#roster-body tr[data-id="1"]').wait_for()
            self.assertEqual(page.locator('.filming-summary').count(), 0)
            self.assertEqual(page.locator('.file-tab.active').get_attribute('data-file'), '2026-11-13-forgatas.csv')
            self.assertIn('Almavirág Anna', page.locator('#roster-body').inner_text())
            browser.close()

if __name__ == '__main__': unittest.main(verbosity=2)
