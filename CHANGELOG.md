# Lathe changelog

## `master`

Introduces a `pre_get_posts` hook for adjusting the main WP Query object. As an example adjustment, post type archives will only show top-level posts of that type, instead of including descendant posts as well.

Cleans up the context for archive / search pages.

## 0.2

### Breaking changes

Replaced the `style()` and `script()` functions to a single `asset()` function, and added the `inline` option. Also added an `asset` equivalent filter.

## 0.1

Initial version.