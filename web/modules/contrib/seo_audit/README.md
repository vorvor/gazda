# SEO Audit

## Introduction

**SEO Audit** is a powerful Drupal module that performs automated SEO audits on any website — including local development environments — by crawling pages and analyzing crucial SEO elements. It helps site administrators identify and resolve SEO issues, ultimately improving search engine visibility.

## Table of Contents

* [Requirements](#requirements)
* [Installation](#installation)
* [Configuration](#configuration)
* [Features](#features)
* [Troubleshooting & FAQ](#troubleshooting--faq)

## Requirements

* Drupal core: ^10 || ^11
* PHP dependencies:

  * `spatie/crawler`: ^8.0
  * `symfony/dom-crawler`: ^6.4
  * `symfony/css-selector`: ^6.4
  * `t1gor/robots-txt-parser`: ^0.2.5
  * `dompdf/dompdf`: ^3.1
* Drupal modules:

  * `queue_ui`: ^3.2

## Installation

Install via Drush or download the module manually:

```bash
drush en seo_audit -y
```

Place the module under `/modules/custom/seo_audit` if installing manually.

## Configuration

1. Enable the module and its dependencies:

   ```bash
   drush en seo_audit -y
   ```
2. Configure crawl behavior at:

   * `/admin/config/search/seo-audit/settings`
   * You can configure:

     * **Crawl concurrency**: Number of simultaneous requests to send
     * **Crawl limit**: Total number of URLs to be crawled (Use -1 for No limit.)
     * **Crawl depth**: How deep the crawler should follow internal links from the start page
     * Enable/disable the following checks:

       * H1 tag presence
       * Title tag presence
       * Meta description
       * Meta robots tag
       * Image `alt` attributes
       * Broken links
       * Visual breadcrumbs
       * JSON-LD breadcrumbs
     * **Email notification settings**:

       * Enable/disable notifications
       * Set one or more recipient email addresses
3. Set a high enough cron time limit (e.g., > 7200 seconds) at:

   * `/admin/config/system/queue-ui/cron/seo_audit_crawl_queue`
   * **Note:** Broken link checking consumes the most time.
4. *(Optional)* Set up a mailer service to receive crawl completion notifications.

## Features

* Crawl any site via: `/admin/config/search/seo-audit/crawl`
* Queued execution: Crawls are processed via the queue during cron runs, or manually via:

  ```bash
  drush queue:run seo_audit_crawl_queue
  ```
* Results dashboard:

  * View crawl history at: `/admin/config/search/seo-audit/results`
  * Users see only their own crawl results.
* Detailed crawl view:

  * `/admin/config/search/seo-audit/results/{id}`
* Download crawl reports in:

  * CSV
  * JSON
  * PDF
* Customizable PDF report:

  * Only includes checks that are enabled in settings.
* Email notifications after crawl completes (if configured).
* Supports local sites (with virtual host setup).
* Crawls only internal URLs (ensures domain-restricted scans).
* Respects `robots.txt` rules (skips disallowed paths).
* Color-coded crawl output:

  * **HTTP Status Codes:**

    * `2xx` (Success): Green `#28a745`
    * `3xx` (Redirect): Teal `#17a2b8`
    * `4xx` (Client Error): Orange `#fd7e14`
    * `5xx` (Server Error): Red `#dc3545`
  * **Result Cells:**

    * 'Found': Light Green `#cdf9cc`
    * Others: Light Red `#f9cccc`

## Troubleshooting & FAQ

**Q:** The crawl does not start or completes with errors?
**A:** Ensure your server allows outgoing HTTP requests. Firewall restrictions or misconfigured virtual hosts may block crawling.

**Q:** Emails are not received after crawl completion?
**A:** Confirm that the site has a working email system and the notification email is valid.

**Q:** PDF reports are missing styles or are unreadable?
**A:** Verify that all Dompdf dependencies are installed correctly and compatible with your server setup.
