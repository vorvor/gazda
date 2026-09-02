# Shop Google Reviews

Displays cached Google Places ratings on full shop node pages.

## Configuration

Provide a server-restricted Places API (New) key outside Drupal configuration:

- preferred: `GOOGLE_PLACES_API_KEY` environment variable;
- alternative in environment-specific `settings.php`:
  `$settings['shop_google_reviews_api_key'] = getenv('GOOGLE_PLACES_API_KEY');`

Do not export the key in Drupal configuration. Enable Places API (New), billing,
and suitable server/API restrictions for the key.

Editors set the **Google Place ID** field on each shop. Drupal cron refreshes due
ratings once per day and retains the last successful value when a refresh fails.
A forced refresh is available after deployment:

    drush shop-google-reviews:refresh --force
