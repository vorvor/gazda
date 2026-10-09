"""Personal cards: real authenticated HTTP/CSV and browser; isolated storage only."""
import csv
import json
import unittest
from urllib.parse import urlencode
import test_assignment_dates as fixtures


class PersonCardTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.h = fixtures.AssignmentDatesTest
        cls.addClassCleanup(cls.h.doClassCleanups)
        cls.h.setUpClass()

    def setUp(self):
        self.h().setUp()
        self.file = '2026-11-12-test.csv'
        self.other = '2026-11-13-test.csv'
        self.h.php("$db->beginTransaction(); $db->people[1]['name']=$db->people[0]['name']; $db->sources=[['file'=>'2026-11-12-test.csv','legacy_id'=>'old-7','name'=>'Almavirág Anna','person_id'=>1,'identity_key'=>'private-key','ambiguity'=>''],['file'=>'2026-11-13-test.csv','legacy_id'=>'old-7','name'=>'Almavirág Anna','person_id'=>2,'identity_key'=>'other-key','ambiguity'=>'']]; $d=$db->ensureDay('2026-11-12','Teszt'); $db->assign($d['id'],1,'Elek'); $db->commit();")
        for file, note in [(self.file, '<img src=x onerror=alert(1)>'), (self.other, 'OTHER PERSON')]:
            with (self.h.app / 'data' / file).open('w') as f:
                writer = csv.writer(f)
                writer.writerow(['id','name','allergy','cls','time','arrived','late','cancelled','reason','notes','checkedOut','flag'])
                writer.writerow(['old-7','Almavirág Anna','gluten','A','08:30','1','0','0','saved reason',note,'0','red'])
        (self.h.app / 'logs').mkdir()
        for filename in ['general-log.csv', '2026-11-12-test-log.csv']:
            with (self.h.app / 'logs' / filename).open('w') as f:
                writer = csv.writer(f)
                writer.writerow(['event_id','timestamp','user','project','action','person_id','person_name','field','old_value','new_value'])
                writer.writerow(['e1','2026-11-12','Elek',self.file,'notes','old-7','Almavirág Anna','notes','','saved reason'])

    def test_browser_saved_card(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(args=['--no-sandbox'])
            context = browser.new_context(viewport={'width':390,'height':844})
            context.add_cookies([{'name':'FELIXSESSID','value':'testelek1234567890','url':self.h.base}])
            page = context.new_page()
            errors, writes = [], []
            page.on('pageerror', lambda e: errors.append(str(e)))
            page.on('request', lambda r: writes.append(r.url) if r.method == 'POST' else None)
            page.goto(self.h.base + 'index.php?file=' + self.file)
            button = page.locator('#roster-body .person-card')
            button.wait_for(timeout=5000)
            button.click()
            dialog = page.locator('#person-qr-dialog')
            page.get_by_text('Nyilvántartási adatok', exact=True).wait_for()
            self.assertIn('Budapest', dialog.inner_text())
            self.assertIn('<img src=x onerror=alert(1)>', dialog.inner_text())
            self.assertNotIn('OTHER PERSON', dialog.inner_text())
            self.assertEqual(dialog.locator('img').count(), 0)
            self.assertTrue(dialog.locator('.person-photo-placeholder').is_visible())
            self.assertEqual(dialog.locator('.person-qr-large svg').count(), 1)
            self.assertEqual(page.locator('#roster-body .is-selected').count(), 0)
            page.keyboard.press('Escape')
            self.assertTrue(button.evaluate('(el) => el === document.activeElement'))
            for trigger in ['#view-log-btn', '#all-logs-btn']:
                page.locator(trigger).click()
                page.locator('#log-body .person-card').click()
                page.get_by_text('Nyilvántartási adatok', exact=True).wait_for()
                self.assertIn('saved reason', dialog.inner_text())
                self.assertNotIn('OTHER PERSON', dialog.inner_text())
                page.keyboard.press('Escape')
                page.locator('#log-back-btn').click()
            page.goto(self.h.base + 'assignments.php?date=2026-11-12')
            page.locator('#assigned .person-card').click()
            page.get_by_text('Nyilvántartási adatok', exact=True).wait_for()
            self.assertIn('saved reason', dialog.inner_text())
            box = dialog.bounding_box()
            self.assertLessEqual(box['x'] + box['width'], 390)
            page.keyboard.press('Escape')
            page.locator('#people .person-card').nth(1).click()
            page.get_by_text('Nyilvántartási adatok', exact=True).wait_for()
            self.assertIn('OTHER PERSON', dialog.inner_text())
            self.assertNotIn('<img src=x onerror=alert(1)>', dialog.inner_text())
            self.assertFalse(errors, errors)
            self.assertFalse(writes, writes)
            browser.close()

    def test_saved_card_source_identity_and_security(self):
        before = {str(p): p.read_bytes() for folder in ['registry','data','logs'] for p in (self.h.app / folder).rglob('*') if p.is_file()}
        path = 'person_card.php?' + urlencode({'file':self.file, 'id':'old-7', 'name':'Almavirág Anna'})
        status, body = self.h.request(path)
        self.assertEqual(status, 200)
        self.assertIsInstance(body, dict, 'Personal-card endpoint must return saved JSON, not the index fallback')
        self.assertEqual(body['person']['id'], 1)
        self.assertEqual(body['person']['city'], 'Budapest')
        self.assertEqual(len(body['rosters']), 1)
        self.assertEqual(body['rosters'][0]['row']['notes'], '<img src=x onerror=alert(1)>')
        self.assertEqual(body['rosters'][0]['row']['allergy'], 'gluten')
        self.assertEqual(len(body['assignments']), 1)
        self.assertNotIn('identity_key', json.dumps(body))
        self.assertNotIn('demo_key', json.dumps(body))
        self.assertEqual(self.h.request(path, user=None)[0], 401)
        self.assertEqual(self.h.request(path, user='Kecsu')[0], 200)
        self.assertEqual(self.h.request(path, data={})[0], 405)
        for query in [{'id':'1 OR 1=1'}, {'id': 'old-7', 'file':'../secret.csv'}, {'id[]':'1'}]:
            self.assertEqual(self.h.request('person_card.php?' + urlencode(query))[0], 400)
        self.assertEqual(self.h.request('person_card.php?id=999')[0], 404)
        self.assertEqual(self.h.request('person_card.php?' + urlencode({'file':self.file,'id':'old-7','name':'Someone else'}))[0], 404)
        self.assertEqual(self.h.request('person_card.php?id=2')[1]['rosters'][0]['row']['notes'], 'OTHER PERSON')
        after = {str(p): p.read_bytes() for folder in ['registry','data','logs'] for p in (self.h.app / folder).rglob('*') if p.is_file()}
        self.assertEqual(before, after, 'Card reads must not mutate storage')
        # A broken/ambiguous link never falls through to a numeric registry ID.
        self.assertEqual(self.h.request('person_card.php?' + urlencode({'file':self.file,'id':'1'}))[0], 404)
        self.h.php("$db->beginTransaction(); $s=$db->sources[0]; $s['person_id']=2; $db->sources[]=$s; $db->commit();")
        self.assertEqual(self.h.request('person_card.php?' + urlencode({'file':self.file,'id':'old-7'}))[0], 404)
        self.assertEqual(self.h.request('person_card.php?id=1')[1]['rosters'], [])
        # Stale mappings are not allowed to pull a replacement row by ID alone.
        self.h.php("$db->beginTransaction(); array_pop($db->sources); $db->commit();")
        path = self.h.app / 'data' / self.file
        path.write_text(path.read_text().replace('Almavirág Anna', 'Replacement'))
        self.assertEqual(self.h.request('person_card.php?id=1')[1]['rosters'], [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
