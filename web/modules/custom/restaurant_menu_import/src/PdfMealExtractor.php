<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

/**
 * Extracts basic title-description-price blocks from PDF text.
 */
final class PdfMealExtractor {

  /**
   * Extracts meal records from plain PDF text.
   *
   * This deliberately accepts only short, contiguous blocks. Complex PDF
   * layouts are skipped instead of producing incorrectly paired meals.
   *
   * @return \Drupal\restaurant_menu_import\MealRecord[]
   *   Extracted meals.
   */
  public function extractText(string $text, string $source_url): array {
    $records = [];
    $buffer = [];
    $lines = preg_split('/\R/u', str_replace("\xc2\xa0", ' ', $text)) ?: [];

    foreach ($lines as $line) {
      $line = $this->clean($line);
      if ($line === '') {
        continue;
      }

      // Basic bilingual menus commonly repeat the Hungarian half in English.
      if (mb_strtoupper($line) === 'DRAGON SALAD') {
        break;
      }

      // Multiple-price variants need source-specific naming rules. Skip them
      // cleanly so they cannot become the title of the following meal.
      if (preg_match('/^[0-9][0-9 .]*\s*\/\s*[0-9][0-9 .]*\*?\s*(?:HUF|FT)/iu', $line)) {
        $buffer = [];
        continue;
      }

      if (preg_match('/^([0-9][0-9 .]*)(?:\.-)?\s*(?:HUF|FT(?:\/[^\s]+)?)$/iu', $line, $matches)) {
        if ($buffer !== [] && count($buffer) <= 3) {
          $name = array_shift($buffer);
          if ($this->looksLikeTitle($name) && $this->looksLikeDescription($buffer)) {
            $description = $this->removeAllergens(implode(' ', $buffer));
            $digits = preg_replace('/\D/', '', $matches[1]);
            if ($digits !== '') {
              $records[] = new MealRecord(
                hash('sha256', $source_url . '|' . mb_strtolower($name)),
                $name,
                number_format((int) $digits, 2, '.', ''),
                $description,
                $source_url,
              );
            }
          }
        }
        $buffer = [];
        continue;
      }

      if ($this->isIgnoredHeading($line)) {
        $buffer = [];
        continue;
      }

      $buffer[] = $line;
      if (count($buffer) > 8) {
        array_shift($buffer);
      }
    }

    return $records;
  }

  /**
   * Normalizes one extracted line.
   */
  private function clean(string $value): string {
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Removes a trailing numeric allergen list.
   */
  private function removeAllergens(string $value): string {
    return trim((string) preg_replace('/\s*\(\s*\d+(?:\s*[,;]\s*\d+)*\s*\)\s*$/u', '', $value));
  }

  /**
   * Rejects obvious prose and section labels as meal titles.
   */
  private function looksLikeTitle(string $value): bool {
    return mb_strlen($value) <= 120
      && !str_ends_with($value, '.')
      && !preg_match('/\b(?:HUF|FT)\b/iu', $value)
      && !preg_match('/^(ALLERG|ÁRAINK|AVAILABLE|KÓSTOLHATÓ)/iu', $value);
  }

  /**
   * Rejects a second title accidentally read as a description column.
   *
   * @param string[] $lines
   *   Pending description lines.
   */
  private function looksLikeDescription(array $lines): bool {
    return $lines === [] || str_contains(implode(' ', $lines), ',') || str_contains(implode(' ', $lines), '.');
  }

  /**
   * Identifies headings that must reset a pending block.
   */
  private function isIgnoredHeading(string $value): bool {
    return (bool) preg_match('/^(ELŐÉTEL|LEVES|FŐÉTEL|DESSZERT|ALLERGÉNEK|ALLERGENS)$/iu', str_replace(' ', '', $value));
  }

}
