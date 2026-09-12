'use strict';
const toggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.navigation');
if (toggle && navigation) {
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? '×' : '☰';
    navigation.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      toggle.click();
      toggle.focus();
    }
  });
}
const figures = [...document.querySelectorAll('.gallery-item')];
const count = document.querySelector('.gallery-count');
document.querySelectorAll('.filter').forEach(button => {
  button.addEventListener('click', () => {
    document.querySelectorAll('.filter').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    figures.forEach(figure => { figure.hidden = button.dataset.filter !== 'all' && figure.dataset.category !== button.dataset.filter; });
    if (count) count.textContent = String(figures.filter(figure => !figure.hidden).length);
  });
});
const lightbox = document.querySelector('#lightbox');
if (lightbox && typeof lightbox.showModal === 'function') {
  let selected;
  const picture = lightbox.querySelector('img');
  const caption = lightbox.querySelector('p');
  const show = figure => {
    selected = figure;
    const image = figure.querySelector('img');
    picture.src = figure.querySelector('a').href;
    picture.alt = image.alt;
    caption.textContent = figure.querySelector('figcaption').textContent;
  };
  figures.forEach(figure => figure.querySelector('a').addEventListener('click', event => {
    event.preventDefault(); show(figure); lightbox.showModal();
  }));
  const move = direction => {
    const visible = figures.filter(figure => !figure.hidden);
    show(visible[(visible.indexOf(selected) + direction + visible.length) % visible.length]);
  };
  lightbox.querySelector('.lightbox-close').addEventListener('click', () => lightbox.close());
  lightbox.querySelector('.lightbox-prev').addEventListener('click', () => move(-1));
  lightbox.querySelector('.lightbox-next').addEventListener('click', () => move(1));
  lightbox.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') move(-1);
    if (event.key === 'ArrowRight') move(1);
  });
  lightbox.addEventListener('click', event => {
    const bounds = lightbox.getBoundingClientRect();
    if (event.target === lightbox && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) lightbox.close();
  });
}
