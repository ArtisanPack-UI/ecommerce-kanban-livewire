---
title: Browser Tests
---

# Browser Tests

For contributors. The board has a browser suite (Pest 4's browser plugin on Playwright) that drives the real board in Chromium against a seeded demo board. It covers what unit and Livewire tests cannot: dragging, keyboard moves and their announcements, focus, real assets, and an axe accessibility check on every screen and dialog.

## Running it

```bash
npm ci
npx playwright install chromium
composer test:browser
```

`composer test:browser` builds the workbench assets (`npm run build:browser`) and then runs the browser group (`vendor/bin/pest --group=browser`), so after the first setup it is the only command you need.

`composer test` runs only the Unit and Feature suites (`--testsuite=Unit,Feature`); loading the Browser suite would start Playwright. CI runs the browser suite in its own `browser` job, on Livewire 3 and 4.

## How the harness works

| Piece | Where | Role |
| --- | --- | --- |
| Base test case | `tests/BrowserTestCase.php` | Serves the Testbench app from the workbench `public` directory, loads the workbench assets through the `ap.ecommerceKanbanLivewire.layout.viteEntries` filter, signs in a staff user granted `ecommerce.admin`, and seeds the demo board |
| Demo board | `BrowserTestCase::seedDemoBoard()` | A "Fulfillment" board with the columns To pack, Packing (WIP limit 1), Ready to ship, and Cancelled (an exit column); orders 1001–1004 (1001 tagged `rush`, 1002 `wholesale`, 1004 in Packing); and an automation into Ready to ship. The store is "Demo Store" in USD. |
| Workbench | `tests/Browser/workbench/` | Stands in for a host application: `resources/css/app.css` imports Tailwind 4, adds the board's and the component library's views as `@source`s, and loads daisyUI with the light and dark themes; `resources/js/app.js` imports `@artisanpack-ui/livewire-drag-and-drop`, as the install command tells a host to |
| Build | `package.json`, `vite.config.js` | `npm run build:browser` builds the workbench assets into `tests/Browser/workbench/public/build`. The package itself ships no front-end build; `package.json` exists only for this harness. |
| Accessibility helper | `tests/Browser/Support/Accessibility.php` | `Accessibility::assertAccessible( $page )` runs every axe rule (axe-core is injected into every page by the Pest browser plugin), with `target-size` turned on, and fails on any violation except the known library issues |
| JavaScript helper | `tests/Browser/Support/JavaScript.php` | `JavaScript::assertNoErrors( $page )` |
| Locators | `tests/Browser/Support/Locators.php` | Finds controls the way a person or screen reader does |
| Tests | `tests/Browser/*Test.php` | Journeys, accessibility, keyboard |

The base test case throws if `public/build/manifest.json` is missing. Run `npm run build:browser` first.

Pest's group config in `tests/Pest.php` applies `BrowserTestCase` and the `browser` group to everything in `tests/Browser`, with a 10-second browser timeout.

## What the suites cover

| Suite | Covers |
| --- | --- |
| `JourneysTest` | Dragging a card to another column (and the automation it runs), reordering within a column, moving a card with the keyboard and with "Move to…", the exit-column confirmation, a drop refused at the WIP limit, a move the engine refuses and reverts, filtering by tag and placed date (kept in the URL), opening the slide-out from `?card=`, reordering columns in edit mode, and a move made in another browser arriving by polling |
| `AccessibilityTest` | axe on the picker, the board (light and dark), the board with hidden cards, the slide-out, the "Move to…" and "Add order" dialogs, edit mode and its dialogs, the widget picker, the automations panel, and a phone with the filters open |
| `KeyboardTest` | The skip link, card and column names, keyboard move announcements, card actions and "Move to…" by keyboard, focus returning from the slide-out, column moves by keyboard in edit mode, and focused controls clear of the sticky header |

## Locators

```php
use Tests\Browser\Support\Locators;

Locators::label( 'Column' );                    // the field labelled "Column"
Locators::role( 'Actions for order 1001' );     // a button named "Actions for order 1001"
Locators::role( 'All boards', 'link' );         // a link named "All boards"
Locators::field( 'ec-kb-move-column' );         // an input, select, or textarea whose id contains the key
```

Find fields by label, or with `Locators::field()` by part of their id: the component library prefixes generated ids, so an exact id won't match.

## Writing a test

```php
use Tests\Browser\Support\Accessibility;

it( 'opens a card from the URL', function (): void {
    $page = visit( $this->boardUrl( [ 'card' => $this->orders['1004']->id ] ) )
        ->assertVisible( '[data-ec-kb-detail="' . $this->orders['1004']->id . '"]' )
        ->assertNoJavaScriptErrors();

    Accessibility::assertAccessible( $page );
} );
```

- `$this->board`, `$this->columns[ $label ]`, and `$this->orders[ $number ]` hold the demo board's records; `$this->boardUrl( $query )` builds its URL.
- Focus and announcements settle just after Livewire's update. Check them with `assertScript()`, which retries until it holds.
- Prefer the `data-*` hooks the views carry (`[data-ec-kb-card="…"]`, `[data-ec-kb-cards="…"]`, `[data-ec-kb-column="…"]`, `[data-ec-kb-action="move"]`, `[data-ec-kb-move-submit]`) over classes.
- Assert `assertNoJavaScriptErrors()` on new screens.

## Selectors that never match

The plugin's `keys()` and `click()` wait for their selector with no timeout of their own, so a selector that matches nothing, or a click on a disabled button, can hang the run instead of failing. Use explicit CSS selectors. A bare word is treated as text to find, not a tag: to send keys to the page, use `html > body`, not `body`. If a run hangs, look for a selector or label that changed. CI caps the browser job at 20 minutes, and the test step at 10, so a hang cannot block the pipeline.

## Known library exclusions

`Accessibility::LIBRARY_ISSUES` lists axe failures that come from `livewire-ui-components` markup this package cannot change. Each entry is an axe rule and a CSS selector; a node is ignored only when it, or an ancestor, matches that selector. Remove an entry when the library fixes it, and the suite enforces the rule again. See [Accessibility](Accessibility#known-issues).

## daisyUI pin

`package.json` pins `daisyui` to `~5.0.48`. Later 5.x releases hide `.tab-content`, which leaves the component library's tab panels empty ([livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119)). Keep the pin until that is fixed.
