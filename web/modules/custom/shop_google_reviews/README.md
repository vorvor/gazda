# Shop Google Reviews

Displays cached Google Places ratings on full shop node pages.

## Configuration

Administrators can save a server-restricted Places API (New) key at:

    /admin/config/services/shop-google-reviews

The key is stored in Drupal State rather than exported configuration, remains
visible in the administration text field after saving, and must be entered
separately in each environment.
The module only uses the key saved on this administration page. Enable Places
API (New), billing, and suitable server/API restrictions for the key.
Saving a new key immediately forces a refresh for every published shop that has
a Google Place ID.

Editors set the **Google Place ID** field on each shop. Drupal cron refreshes due
ratings once per day and retains the last successful value when a refresh fails.
A forced refresh is available after deployment:

    drush shop-google-reviews:refresh --force
