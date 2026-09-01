<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

/**
 * One normalized cultural program from an external source.
 */
final readonly class ProgramRecord {

  /**
   * @param string[] $categories
   *   Source category labels.
   */
  public function __construct(
    public string $sourceName,
    public string $externalId,
    public int $priority,
    public string $title,
    public string $description,
    public \DateTimeImmutable $start,
    public ?\DateTimeImmutable $end,
    public bool $allDay,
    public string $placeName,
    public string $placeAddress,
    public string $placeWebsite,
    public string $sourceUrl,
    public string $ticketUrl,
    public string $price,
    public array $categories,
    public bool $family,
    public string $status = 'scheduled',
    public ?\DateTimeImmutable $sourceUpdated = NULL,
  ) {
    if ($this->sourceName === '' || $this->externalId === '' || $this->title === '' || $this->placeName === '' || $this->sourceUrl === '') {
      throw new \InvalidArgumentException('A cultural program record is missing required source data.');
    }
    if (!in_array($this->status, ['scheduled', 'cancelled', 'postponed', 'sold_out'], TRUE)) {
      throw new \InvalidArgumentException('The cultural program status is invalid.');
    }
  }

}
