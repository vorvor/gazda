# Public Holiday

Stores Hungarian public holidays from `https://szunetnapok.hu/api/` in a
custom database table. Drupal cron makes at most one API request in each
three-month interval. It refreshes the current calendar year, or the following
year during the final quarter so January holidays are already available.

The `Public holiday today` block is empty on ordinary days. On a public
holiday (`type = 1` in the API), it displays the date, holiday name, and
weekday using only the locally stored data.

## API key

The API key is intentionally stored in Drupal State rather than exported
configuration or source code:

```sh
vendor/bin/drush state:set public_holiday.api_key 'YOUR_API_KEY'
```

Set the key before enabling the module so its install hook can perform the
initial 2026 import. Place the block from the Drupal block layout page after
enabling the module.
