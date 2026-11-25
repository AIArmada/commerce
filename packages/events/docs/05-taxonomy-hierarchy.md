---
title: Event Taxonomy Hierarchy
---

# Event taxonomy hierarchy

`Features\Taxonomy\Models\EventTaxonomy` and
`Features\Taxonomy\Models\EventTerm` support multi-level vocabularies. The
package exposes the `AIArmada\Events\Contracts\EventTaxonomyHierarchy`
contract (default implementation
`AIArmada\Events\Features\Taxonomy\Queries\EventTaxonomyHierarchyService`,
bound as a singleton) for loading active terms, building trees, creating path
options, validating UUID selections, expanding descendants for filters, and
minimizing parent/child selections.

```php
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;

$hierarchy = app(EventTaxonomyHierarchy::class);

$options = $hierarchy->options('event_category');            // id => "Parent › Child"
$selected = $hierarchy->minimalTermIds('event_category', $request->array('term_ids'));
$matchingTerms = $hierarchy->descendantIds('event_category', $selected);
$tree = $hierarchy->tree('event_category');
$matches = $hierarchy->searchTerms('event_category', 'fiqh', limit: 25);
```

Terms also expose `taxonomy()`, `parent()`, and ordered `children()`
relationships, plus `active()` and `roots()` query scopes. The service is
taxonomy-code based so applications can keep vocabulary names and policy
metadata in their own seeders.

Term codes are unique per taxonomy.
`AIArmada\Events\Features\Taxonomy\Actions\SyncEventClassificationsAction`
resolves concurrent syncs against that unique key, runs the
delete-and-recreate inside a transaction, and verifies the event through the
owner write guard first.

```php
use AIArmada\Events\Features\Taxonomy\Actions\SyncEventClassificationsAction;

app(SyncEventClassificationsAction::class)->handle($session, ['topic' => ['Fiqh']]);
```

The same scope rules apply as for involvements and languages: event-level
syncs carry a null occurrence and session, a nullable session occurrence is
preserved as null, and only rows owned by that scope are replaced.
