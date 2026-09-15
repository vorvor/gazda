"""Add deterministic editing hooks without replacing legacy anchor IDs."""
import hashlib
import re
import unicodedata
from bs4 import BeautifulSoup

REGIONS = {
    'site-header': 'site-header',
    'header-inner': 'header-inner',
    'topline': 'site-topline',
    'page-heading': 'page-hero',
    'heading-composition': 'page-hero-layout',
    'heading-copy': 'page-hero-copy',
    'heading-art': 'page-hero-art',
    'breadcrumbs': 'breadcrumbs',
    'page-layout': 'page-layout',
    'sidebar': 'page-sidebar',
    'sidebar-inner': 'page-sidebar-inner',
    'prose': 'article-content',
    'contact-band': 'contact-section',
    'site-footer': 'site-footer',
    'footer-top': 'footer-top',
    'footer-links': 'footer-links',
    'footer-bottom': 'footer-bottom',
    'gallery-grid': 'gallery-grid',
    'gallery-toolbar': 'gallery-filters',
    'reference-hero': 'home-hero',
    'reference-intro': 'home-intro',
    'reference-cards': 'home-cards',
    'reference-jubilee': 'home-jubilee',
    'reference-features': 'home-features',
}

def decorate_structure(output, filename):
    soup = BeautifulSoup(output, 'html.parser')
    existing = [tag['id'] for tag in soup.select('[id]')]
    if len(existing) != len(set(existing)):
        raise ValueError(f'{filename}: duplicate original IDs must be resolved explicitly')
    used = set(existing)

    def unique_id(stem):
        stem = unicodedata.normalize('NFKD', stem).encode('ascii', 'ignore').decode()
        stem = re.sub(r'[^a-zA-Z0-9_-]+', '-', stem).strip('-').lower() or 'content'
        candidate = stem
        suffix = 2
        while candidate in used:
            candidate = f'{stem}-{suffix}'
            suffix += 1
        used.add(candidate)
        return candidate

    body = soup.body
    body['class'] = list(dict.fromkeys(body.get('class', []) + ['site-page']))
    if not body.get('id'):
        body['id'] = unique_id('page-' + filename.removesuffix('.html'))
    for tag in soup.select('header, main, footer, section, aside, nav, article, figure, div'):
        classes = tag.get('class', [])
        source = tag.find_parent(class_='source-body')
        if not classes:
            classes = ['source-block' if source else ('layout-block' if tag.name == 'div' else f'content-{tag.name}')]
            tag['class'] = classes
        if tag.get('id'):
            continue
        if tag.get('data-source'):
            stem = 'source-' + hashlib.sha256(tag['data-source'].encode()).hexdigest()[:10]
        else:
            stem = next((value for key, value in REGIONS.items() if key in classes), None)
            parent = tag.find_parent(id=True)
            context = parent['id'] if parent else 'page'
            if stem is None:
                if source:
                    stem = source['id'] + '-block'
                elif classes[0] in ['container', 'layout-block']:
                    stem = context + '-' + classes[0]
                else:
                    stem = classes[0]
        tag['id'] = unique_id(stem)
    return str(soup).rstrip() + '\n'
