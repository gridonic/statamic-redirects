## Installation

1. Download the Addon, extract the folder and rename it to `Redirects`
2. Move the `Redirects` folder to `site/addons`

## How it works

Redirects from this module are kept separate from Statamic's redirects configured via routing YAML file.
They are only checked and possibly executed if a `404` response is created by Statamic.
The module differentiates two types of redirects:

* **Manual Redirects** are managed by authorized users in the control panel.
* **Auto Redirects** are created by the module to redirect old URLs of content (Pages, Entries, or Terms).

## Configuration

* **Enable auto redirects** Whether to enable auto redirects.
* **Log 404s** Whether to enable logging 404 requests.
* **Access to manage redirects** Enter role slugs to restrict access to view and manage redirects to certain roles.
If empty, all roles are able to view and manage.

## Manual Redirects

A manual redirect consist of the following options:

* **From URL** The relative source URL for the redirect, e.g. `/source`.
* **Target** The target is either a static URL or the ID of any content (which resolves to the content's URL).
* **Status Code** `301` Moved Permanently or `302` Moved Temporary.
* **Retain Query Strings** Whether to append query strings from the source URL to the target URL.
* **Locale** Only execute the redirect depending on a locale.
* **Timed Activation** Only execute the redirect in a given date range.
Only specifying a start date delays the activation of the redirect after the given date.
Only specifying an end date activates the redirect until the given date.
If both dates are specified, a temporary redirect status code `302` gets applied automatically.

### Route Parameters

It is possible to redirect multiple URLs by using route parameters in the source and target URLs.

* A source URL `/news/{year}/{slug}` and target URL `/blog/{year}/{slug}` redirects the URL `/news/2019/statamic-rocks`
to `/blog/2019/statamic-rocks`. Make use of this feature to prevent 404s if you update the route of a collection.
* Each parameter captures exactly one URL segment. For example, the source URL `/news/{category}/{slug}`
does not match the URL `/news/cms` because of the missing `slug` URL segment. The module
offers a special wildcard parameter `{any}` to match any number of URL segments. For example, the source URL
`/news/{any}` will match and redirect `/news/any/following/url/segment` to the configured target.

> Note that the order of the redirects matter if you use parameters, as multiple route definitions might match
the current request's URL. You can reorder manual redirects in the control panel. 

## Auto Redirects

The module listens to various events to detect changed URLs of content and to redirect ols URLs to the new ones.

* Updated slugs when content is saved
* Updated URLs due to moving pages in the page tree

In case of pages, the module recursively creates redirects for affected children. Multi language is supported as well.

> Auto redirects are viewable and deletable in the control panel, but they cannot be edited via user interface.

## 404 Monitor

The 404 monitor shows logged 404 requests with the possibility to quickly create redirects.

## API

The module offers an [API](https://docs.statamic.com/addons/classes/api) to manipulate redirects and logs programmatically.

```php
// Returns an instance of \Statamic\Addons\Redirects\ManualRedirectsManager
$this->api('Redirects')->manualRedirectsManager();

// Returns an instance of \Statamic\Addons\Redirects\AutoRedirectsManager
$this->api('Redirects')->autoRedirectsManager();

// Returns an instance of \Statamic\Addons\Redirects\RedirectsLogger
$this->api('Redirects')->logger();
```

The above services are also registered in Laravel's service container, allowing you to resolve them from the container
or use [automatic dependency injection](https://laravel.com/docs/5.8/container#automatic-injection).
