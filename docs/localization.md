---
title: Localization
menu_order: 55
---

# Localization

Every staff-facing string on the board goes through Laravel's translation functions, including the drag-and-drop instructions and announcements. The package ships JSON catalogues for nine languages.

## Shipped languages

| Locale | File |
| --- | --- |
| English | `lang/en.json` |
| Spanish | `lang/es.json` |
| French | `lang/fr.json` |
| German | `lang/de.json` |
| Brazilian Portuguese | `lang/pt_BR.json` |
| Japanese | `lang/ja.json` |
| Simplified Chinese | `lang/zh_CN.json` |
| Italian | `lang/it.json` |
| Arabic | `lang/ar.json` |

The catalogues are loaded with `loadJsonTranslationsFrom()`, so strings are keyed by their English text. They load in the service provider's `register()`, so the board's strings (and your overrides) are translated even when another provider calls `__()` while it boots. The board follows the application locale (`app()->getLocale()`), and the standalone layout's `<html lang>` and `dir` follow it too.

## Overriding strings

Publish the catalogues:

```bash
php artisan vendor:publish --tag=ecommerce-kanban-lang
```

They are copied to `lang/vendor/ecommerce-kanban/`. The package loads that directory as well as its own, so you can change individual strings there. An override there applies from the start of the request, including during other providers' `boot()`.

Every package's JSON catalogue loads into one shared namespace. When you override a string the engine or another ArtisanPack UI package also uses (such as "Cancel" or "Save"), override it the same way for every package that uses it.

## Drag-and-drop announcements

The keyboard instructions and move announcements are translated strings with placeholders that `livewire-drag-and-drop` fills in the browser:

| Placeholder | Filled with |
| --- | --- |
| `{item}` | The card ("Order 1002") or, in edit mode, the column |
| `{container}` | The column, or the board in edit mode |
| `{position}`, `{total}` | The position in the column, and how many cards it holds |

For example, "{item} moved to {container}, position {position} of {total}." Keep every `{placeholder}` when you translate these.

## Plurals

Counts use `trans_choice()` with a singular and a plural segment:

```php
trans_choice( ':count card|:count cards', $count, [ 'count' => $count ] );
```

Translations keep the same `:placeholders` as the key, and the segments the locale's plural rules need. Laravel picks the segment by those rules: in French and Brazilian Portuguese, 0 is singular; in English, Spanish, German, and Italian it is plural. Japanese and Chinese have one form, so their catalogues keep a single segment. Arabic has six (zero, one, two, few, many, other), so its catalogue has six segments.

## Engine strings

Order, payment, and fulfillment status names are in this package's catalogues. Widget labels and values, automation trigger labels, and money come from the engine, so they follow the engine's translations and formatting. Column labels, sub-status names, board names, and descriptions are store data: they show as the store entered them.

## Right-to-left languages

The standalone layout sets `dir="rtl"` on `<html>` for right-to-left locales (Arabic, Hebrew, Persian, Urdu, and others), and the board's styles and the design system's directional icons mirror with it. Columns read right to left, and "Move left" and "Move right" in edit mode follow the visual direction.

The board also sets `dir="rtl"` on its own root for those locales (`KanbanStyles::textDirection()`), so its columns, mirrored icons, "Move left" / "Move right" labels, and keyboard drag direction stay right in any layout. Its dialogs and drawers render at the end of the page, outside the board, and follow the page's direction, so a layout of your own, or an admin layout, should still set `dir="rtl"` on `<html>` for those locales. See [Layouts](installation/layouts.md).

Known issue: in right-to-left, dragging a column header with a pointer in edit mode drops it at the start of the board (a `livewire-drag-and-drop` issue). The keyboard and the "Move left" / "Move right" buttons work.

## Translation lint

The test suite runs the engine's translation lint, which fails when a user-facing string is not wrapped in a translation function or a key is missing from a catalogue. Run the same check locally:

```bash
vendor/bin/testbench ecommerce:lint:translations --no-engine --path=src --path=resources --lang=lang
```

It scans `src/` and `resources/` (Blade included) and compares against every catalogue in `lang/`. `--no-engine` leaves the engine's own sources out. The suite (`tests/Feature/TranslationsTest.php`) also checks that every locale has every English key, that translations keep their placeholders, that English is its own translation, and that plural strings give a form for 0, 1, and many.

When you add a string, add it to all four catalogues.

## Adding a language

Copy `lang/en.json` to `lang/vendor/ecommerce-kanban/{locale}.json` in your application and translate the values. Keep every `:placeholder`, `{placeholder}`, and `|` segment. Consider contributing it back.
