# Shop Google Reviews

Displays cached Google Places ratings on full shop node pages.

## Configuration

Administrators can save a server-restricted Places API (New) key at:

    /admin/config/services/shop-google-reviews

The key is stored in Drupal State rather than exported configuration, is never
shown again in the form, and must be entered separately in each environment.

For infrastructure-managed secrets, use one of these options instead:

- preferred: `GOOGLE_PLACES_API_KEY` environment variable;
- alternative in environment-specific `settings.php`:
  `$settings['shop_google_reviews_api_key'] = getenv('GOOGLE_PLACES_API_KEY');`

An infrastructure-managed key takes precedence over a key saved through the
administration page. Enable Places API (New), billing, and suitable server/API
restrictions for the key.

Editors set the **Google Place ID** field on each shop. Drupal cron refreshes due
ratings once per day and retains the last successful value when a refresh fails.
A forced refresh is available after deployment:

    drush shop-google-reviews:refresh --force
