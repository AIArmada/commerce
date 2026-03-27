---
title: Overview
---

# Filament Signals Plugin

The `aiarmada/filament-signals` package provides a Filament v5 analytics UI on top of the Signals package. It registers report pages, management resources, and dashboard widgets for product analytics workflows.

## Key Features

- Dashboard and report pages for page views, funnels, acquisition, journeys, retention, content performance, live activity, and goals
- Dedicated devices and technology report for device type, browser, OS, and brand analysis
- Resource management for tracked properties, goals, segments, saved reports, alert rules, and alert logs
- Feature flags to enable/disable individual pages/resources/widgets
- Navigation ordering and label customization via config
- Bot-aware reporting surfaces for page views and acquisition analysis
- Monetary-aware UI that automatically hides revenue-focused cards, columns, and actions when the Signals package disables monetary analytics

## Requirements

- PHP 8.4+
- Filament v5
- `aiarmada/signals`
