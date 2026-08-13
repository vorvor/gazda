# Email Octopus Module

## Contents of This File

 - Introduction
 - Requirements
 - Installation
 - Configuration
 - Maintainers

## Introduction

Email Octopus module creates the subscriber at Email Octopus Account List.This
module contains Email API Integration for POST and GET request. The key feature
of this module is that you can place multiple blocks while selecting a different
list.

 - For a full description of the module, visit the project page:
   <https://www.drupal.org/project/email_octopus>

 - To submit bug reports and feature suggestions, or to track changes:
   <https://www.drupal.org/project/issues/email_octopus>

This module is safe to use on a production site. Just be sure to only grant
'access development information' permission to developers.

## Requirements

This module requires no modules outside of Drupal core.

## Installation

 - Install the Email Octopus module as you would normally install a contributed
   Drupal module.
   Visit [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules) for further information.

## Configuration

    1. `Admin -> Extend` & enable the module.
    2. `Admin -> System -> Email Octopus Settings`.
    3. Add an API key, it is required for successful integration.
    4. Click tab "`Subscriber/Unsubscriber List`" to check for lists and users.
    5. `Admin -> Structure -> Block layout -> Place block`.
        With the required fields.
        - Required list
        - Block title
        - Block body
        - Thank you Message

## Maintainers

 - Akshay Singh - <https://www.drupal.org/u/akshay-singh>

Supporting organizations:

 - TO THE NEW - <https://www.drupal.org/to-the-new>
