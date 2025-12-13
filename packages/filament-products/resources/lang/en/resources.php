<?php

declare(strict_types=1);

return [
    'attributes' => [
        'navigation_label' => 'Attributes',
        'model_label' => 'Attribute',
        'plural_model_label' => 'Attributes',

        'sections' => [
            'basic' => 'Basic Information',
            'options' => 'Select Options',
            'validation' => 'Validation',
            'visibility' => 'Visibility & Behavior',
        ],

        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'description' => 'Description',
            'type' => 'Type',
            'groups' => 'Groups',
            'position' => 'Position',
            'options' => 'Options',
            'option_value' => 'Value',
            'option_label' => 'Label',
            'is_required' => 'Required',
            'validation_rules' => 'Validation Rules',
            'rule' => 'Rule',
            'value' => 'Value',
            'add_rule' => 'Add Rule',
            'is_filterable' => 'Filterable',
            'is_filterable_help' => 'Show in layered navigation filters',
            'is_searchable' => 'Searchable',
            'is_searchable_help' => 'Include in search index',
            'is_comparable' => 'Comparable',
            'is_comparable_help' => 'Allow in product comparison',
            'is_visible_on_front' => 'Visible on Frontend',
            'is_visible_on_front_help' => 'Show on product detail page',
            'is_visible_in_admin' => 'Visible in Admin',
            'is_visible_in_admin_help' => 'Show in admin product form',
            'created_at' => 'Created',
        ],
    ],

    'attribute_groups' => [
        'navigation_label' => 'Attribute Groups',
        'model_label' => 'Attribute Group',
        'plural_model_label' => 'Attribute Groups',

        'sections' => [
            'basic' => 'Basic Information',
            'attributes' => 'Attributes',
        ],

        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'description' => 'Description',
            'position' => 'Position',
            'is_visible' => 'Visible',
            'attributes' => 'Attributes',
            'attributes_count' => 'Attributes',
            'created_at' => 'Created',
        ],
    ],

    'attribute_sets' => [
        'navigation_label' => 'Attribute Sets',
        'model_label' => 'Attribute Set',
        'plural_model_label' => 'Attribute Sets',

        'sections' => [
            'basic' => 'Basic Information',
            'attributes' => 'Attributes',
            'groups' => 'Attribute Groups',
        ],

        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'description' => 'Description',
            'is_default' => 'Default',
            'is_default_help' => 'Set as the default attribute set for new products',
            'attributes' => 'Attributes',
            'attributes_count' => 'Attributes',
            'groups' => 'Groups',
            'groups_count' => 'Groups',
            'created_at' => 'Created',
        ],

        'actions' => [
            'set_default' => 'Set as Default',
        ],
    ],

    'categories' => [
        'navigation_label' => 'Categories',
        'model_label' => 'Category',
        'plural_model_label' => 'Categories',
    ],

    'collections' => [
        'navigation_label' => 'Collections',
        'model_label' => 'Collection',
        'plural_model_label' => 'Collections',
    ],

    'products' => [
        'navigation_label' => 'Products',
        'model_label' => 'Product',
        'plural_model_label' => 'Products',
    ],
];
