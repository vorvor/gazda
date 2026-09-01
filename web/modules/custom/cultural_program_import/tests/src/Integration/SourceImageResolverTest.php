<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/SourceImageResolver.php';

use Drupal\cultural_program_import\SourceImageResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function cultural_image_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$resolver = new SourceImageResolver(\Drupal::httpClient());
$html = <<<'HTML'
<!doctype html>
<html>
<head>
  <meta property="og:image" content="/uploads/event-1024x536.jpg?version=1">
</head>
<body>
  <main>
    <img src="/sites/default/files/styles/medium/public/2026-08/library-event.jpg?itok=test" alt="Program">
    <img src="/assets/tribe-loading.gif" alt="Loading">
    <img src="/_next/image?url=https%3A%2F%2Fmedia.example.test%2Fimages%2Fskanzen-event.png&amp;w=1920&amp;q=75">
  </main>
</body>
</html>
HTML;

$candidates = $resolver->extractCandidates($html, 'https://programs.example.test/event/one');
cultural_image_assert($candidates[0] === 'https://programs.example.test/uploads/event.jpg?version=1', 'The original WordPress image variant must be preferred.');
cultural_image_assert(in_array('https://programs.example.test/sites/default/files/2026-08/library-event.jpg', $candidates, TRUE), 'Drupal image-style URLs must resolve to the original file.');
cultural_image_assert(in_array('https://media.example.test/images/skanzen-event.png', $candidates, TRUE), 'Next.js image proxies must resolve to their original URL.');
cultural_image_assert(!array_filter($candidates, static fn(string $url): bool => str_contains($url, 'tribe-loading')), 'Loading images must be excluded.');

$history = [];
$stack = HandlerStack::create(new MockHandler([
  new Response(200, ['Content-Type' => 'text/html'], '<meta property="og:image" content="/images/remote-event.jpg">'),
]));
$stack->push(Middleware::history($history));
$noDownloadResolver = new SourceImageResolver(new Client(['handler' => $stack]));
$resolved = $noDownloadResolver->resolveUrl('https://programs.example.test/event/one');
cultural_image_assert($resolved === 'https://programs.example.test/images/remote-event.jpg', 'The original remote URL must be returned.');
cultural_image_assert(count($history) === 1, 'Resolving an image URL must request only the source page, not the image.');

print "PASS: source image URLs are normalized without downloading image bytes\n";
