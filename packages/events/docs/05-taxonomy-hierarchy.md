# Event taxonomy hierarchy

`EventTaxonomy` and `EventTerm` support multi-level vocabularies. The package exposes
`EventTaxonomyHierarchy` for loading active terms, building trees, creating path
options, validating UUID selections, expanding descendants for filters, and
minimizing parent/child selections.

```php
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;

$hierarchy = app(EventTaxonomyHierarchy::class);

$options = $hierarchy->options('event_category');
$selected = $hierarchy->minimalTermIds('event_category', $request->array('term_ids'));
$matchingTerms = $hierarchy->descendantIds('event_category', $selected);
```

Terms also expose `taxonomy()`, `parent()`, and ordered `children()` relationships,
plus `active()` and `roots()` query scopes. The service is taxonomy-code based so
applications can keep vocabulary names and policy metadata in their own seeders.
