# ArtisanPack UI Ecommerce Kanban (Livewire) Changelog

## [Unreleased]

## [2.0.0] - 2026-11-23

A major release that moves the board onto the ArtisanPack UI design system through `artisanpack-ui/livewire-ui-components` 3.0. daisyUI is no longer used, and Tailwind CSS is now optional. Every screen is rebuilt from the v2 designs, and the board ships five more languages. The engine contract is unchanged: 2.0 still runs on `artisanpack-ui/ecommerce` 1.2 or later. See [Upgrading](docs/upgrading.md).

### Requirements

- PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4 (unchanged).
- `artisanpack-ui/ecommerce` `^1.2` (unchanged).
- **`artisanpack-ui/livewire-ui-components` `^3.0`** (was `^2.1`).
- **The ArtisanPack UI design system in the host's front-end build:** `@artisanpack-ui/css` `^1` and `@artisanpack-ui/behavior` `^1` (the behavior engine registered as an Alpine plugin), and `@apuiThemeScript` in the layout `<head>`. daisyUI is no longer needed.
- **Tailwind CSS is optional.** The board's views use only the package's `ec-kb-*` classes and the design system's own classes, so the board renders with the design system alone. Hosts that build with Tailwind 4 keep the `@layer` line and may add the board's `@source` lines.
- `@artisanpack-ui/livewire-drag-and-drop` `^2.1` (unchanged).
- If `artisanpack-ui/ecommerce-admin-livewire` is installed, it must be 2.0 or later.

### Breaking changes

- **livewire-ui-components 3.0 and the design system replace daisyUI** (#43). Run `php artisan livewire-ui-components:install --assets=bundler`, or add `@artisanpack-ui/css` and `@artisanpack-ui/behavior` by hand: `@import '@artisanpack-ui/css';` in the main stylesheet, the behavior engine registered as an Alpine plugin, and `@apuiThemeScript` before `@vite`. With Tailwind, put `@layer theme, base, apui, components, utilities;` first, before `@import 'tailwindcss';`. Remove the `@plugin "daisyui"` block and the `~5.0` daisyUI pin if nothing else uses them. `php artisan ecommerce-kanban:install` prints every line, with the Tailwind `@source` lines marked optional.
- **Layouts need `<x-artisanpack-toaster />`** instead of `<x-artisanpack-toast />`. In livewire-ui-components 3.0 `toast` is a single static toast, so a layout that keeps it shows no board toasts at all. Custom layouts also need `@apuiThemeScript`.
- **Every view is rebuilt** (#44–#56). Markup, CSS classes, and component usage changed in every package view: the card, column, WIP badge, connection status, empty state, generic widget, filter bar, board, move dialogs, edit mode and column dialogs, card slide-out, automations panel, widget picker, board picker, and standalone layout. The board's own styles moved into `resources/css/kanban.css`, inlined once per page by the new `ecommerce-kanban::partials.styles` (with the Vite CSP nonce; the board includes it itself), and use `ec-kb-*` classes on the design system's tokens instead of Tailwind/daisyUI utilities. **Views published from 1.x no longer match. Delete `resources/views/vendor/ecommerce-kanban` and publish again if you still need overrides.**
- **Toasts are plain text.** livewire-ui-components 3.0's toaster renders text, not HTML. The board no longer escapes toast titles and descriptions itself, and the automation-failure toast no longer carries an "Open order :number" link.
- **`WidgetViews::TONES` entries no longer have a `class` key** (the daisyUI `badge-*` class), and `WidgetViews::tone()` returns `{ color, icon, text }` only. It now takes an optional widget key. The `danger` tone icon is `o-x-circle` (was `o-exclamation-circle`).
- **Choice controls read the 3.0 option shape.** Options from `ap.ecommerceKanbanLivewire.filters` and the board's own lists are mapped from `id`/`name`/`hint` to `value`/`label`/`description` by the new `Support\ControlOptions`. The 2.x keys still work, and 3.0 keys win.
- **`cardClasses` classes are no longer Tailwind/daisyUI color utilities by default.** daisyUI colors such as `border-l-warning` are gone. Return your own class names and style them with the design tokens (`var(--apui-color-warning)`), or use Tailwind utilities if your app builds with Tailwind.
- **`ap.ecommerce.kanban.cardQuery` is now a visibility boundary.** A card the host's or a satellite's `cardQuery` filter hides was only left out of the column lists in 1.x; it could still be moved, removed, or opened by id. 2.0 enforces the filter on every card path through the new `Queries\VisibleCards` (read with the filter bar cleared): drag moves, reorders and "Move to…" are refused (`not-on-board`), "Remove from board" is refused, the slide-out doesn't open it (from the card, an `ec-kb-open-card` event, or `?card=`), and it is left out of the Customer, Products, and Tags filter options, the widget picker's preview, the board picker's `cards_count`, and the count "Show more" uses. Column totals and WIP badges still count hidden cards, because the engine enforces WIP limits against every card, so the numbers show but no hidden card's content does. A filter that narrows cards unconditionally (for example by warehouse) now hides those cards everywhere; a filter that only reads `$filters->extra` is unaffected. See [Filters](docs/usage/filters.md#restricting-which-cards-people-see).
- **Translation strings changed.** Removed: ":board: :column", "Editing the board. Drag column headers or use the arrows to reorder columns; changes save as you make them.", "Open order :number", "Order :number — :email", "This board is switched off. Only people who can edit it see it.", and "Toggle dark mode" (the livewire-ui-components 3.0 theme toggle brings its own label). Several German strings changed to match the engine ("Fulfillment" → "Abwicklung", "Tax" → "USt.", "You are not allowed to do that." → "Das dürfen Sie nicht."), and the German catalogue now uses "Sie" throughout. Re-check published overrides in `lang/vendor/ecommerce-kanban`.

### Added

- Brazilian Portuguese (`pt_BR`), Japanese (`ja`), Simplified Chinese (`zh_CN`), Italian (`it`) and Arabic (`ar`) catalogues, with locale plural rules (one form for ja/zh_CN, six for ar) (#34–#38).
- Right-to-left support: the standalone layout sets `dir="rtl"` for RTL locales (ar, he, fa, ur, …), and the board's styles and the design system's directional icons mirror with it.
- `php artisan ecommerce-kanban:install` prints the design-system setup (the component library's installer, or the npm packages, stylesheet lines, Alpine plugin lines and the `@apuiThemeScript` reminder) before the board's own lines.
- Filter bar: a "More filters" row for placed dates, payment and fulfillment status, stale cards and satellite controls, which opens by itself when any of them is set and shows a count. "N of M orders shown". Customer and product comboboxes search on the server as you type (#47).
- Cards and the slide-out show payment and fulfillment status as tone badges (`Statuses::tone()`), tags in the info color, and tone icons so status is never shown by color alone (#44, #48, #49).
- The card slide-out's "Recent activity" is a Timeline with an icon and color per event type (`CardDetailPresenter::MARKERS`) (#49).
- "Add order" results and the "after a card" list in the move dialog show the customer's name, plus the total and placed date for "Add order" (#54, #55).
- Empty states for first use (no boards), a switched-off board and empty columns (`x-artisanpack-ec-kb-empty` gains a `firstUse` prop) (#45, #53).
- Collapsed columns show as a narrow vertical strip with the label and count. Column color is a dot in the header (#46).
- The widget picker's preview says which card it uses (#51).
- The standalone layout's brand tile uses the design's kanban glyph (#52, #59).
- `Support\ControlOptions`, `Support\KanbanStyles`, `Statuses::tone()`, `CardDetailPresenter::marker()`, `WidgetViews::TAG_KEYS`.
- Documentation: [Upgrading](docs/upgrading.md), a [PHP API](docs/reference/php-api.md) reference, and notes on Content Security Policy and on `ap.ecommerce.kanban.cardQuery` as a visibility boundary.

### Changed

- Every screen rebuilt on livewire-ui-components 3.0 from the v2 designs (#33, #42–#56): Dialog for the move, confirm, add-order and column dialogs; Drawer for the slide-out, automations panel and phone filters; DropdownMenu for card actions and the board switcher; Combobox, Select, RadioGroup, DatePicker, ColorPicker, Toggle, Timeline, PageHeader, Navbar and Toaster elsewhere.
- The rate-limit notice dispatches the design system's `toast` browser event (`{ title, description, color: 'warning' }`).
- `ThrottleKanbanMutations` treats the new `customerSearch` and `productSearch` updates as reads.
- The browser workbench builds Tailwind with the design system instead of daisyUI. The axe exclusions now cover only livewire-ui-components#164 (Drawer `<aside role="dialog">`) and #168 (ColorPicker label).
- Documentation moved to the org docs standard: Overview, FAQ, Troubleshooting, a Reference section (Hooks and Commands moved from `extending/hooks-reference.md` and `advanced/artisan-commands.md`, with redirects), and browser-test docs moved to CONTRIBUTING.md (#40).
- CI and the docs lint also run on pushes to `release/**` branches.
- The package reports version `2.0.0` to the engine's satellite registry (`EcommerceKanbanLivewireServiceProvider::VERSION`).
- Card action keys are unique: the first action per key wins, and a satellite action keyed `open`, `move`, or `remove` can't replace the built-in one (`ActionList::normalize()` takes a `$preferred` map).
- Icon names from livewire-ui-components 3.0's icon registry (its Lucide set) work for column, sub-status, widget, and action icons. A name in both the registry and Heroicons renders the registry icon (`Icons::name()`).
- "Show more" stops at 500 cards per column (`Board::MAX_CARDS_PER_COLUMN`).
- The slide-out's "Recent activity" names boards by name, and only boards the viewer may view; others, and deleted boards, show as "Added to another board" / "Removed from another board" (`CardDetailPresenter::timeline()` takes the viewer).
- The customer filter offers and searches only customers of cards the viewer may see (see the `cardQuery` change above). Board viewers with `kanbanBoard.view` still see those customers' emails.
- `BoardsQuery::visibleTo()` takes `$withCounts`, so callers that don't show counts skip the count queries.
- `Board::dragMessages()` takes a `$kind` (`cards`, `columns`, or `widgets`).
- The install command prints the board's Tailwind `@source` lines as an optional step, for apps that use Tailwind.
- Fewer repeated queries per request: the board's filters are built once while their values don't change, the automations panel and widget picker read their board once per request, and the slide-out eager-loads sub-statuses (its query budget drops from 18 to 17).
- The board sets `dir="rtl"` on its own root for right-to-left locales (`KanbanStyles::textDirection()`, `KanbanStyles::RTL_LANGUAGES`), so it lays out and drags right to left in host layouts that don't set `dir`. Its dialogs and drawers still follow the page's `dir`.
- The board adds its own stylesheet to the page's `<head>` through Livewire's `@assets` (once per page, lazy boards included), so a page that embeds `<livewire:artisanpack-ecommerce-kanban-board>` needs no `partials.styles` include.
- The "Move to…" dialog's description reads "Choose a column and where the order goes in it." 

### Fixed

- JSON translations load in `register()` instead of `boot()`, so a provider that calls `__()` while booting before this one no longer leaves every board string in English (#39, #41).
- German strings shared with the engine now match the engine's wording (#41).
- **Rate limiting:** Livewire event dispatches whose listener only reads (opening a card, "Move to…", the "Remove from board" confirmation, opening the widget picker or automations panel, and the board's real-time and poll refreshes) no longer count against `ecommerce.admin.mutate` (`ThrottleKanbanMutations::READ_EVENTS`). A satellite's own events still count.
- An exit-column move of an order that isn't on the board is refused straight away (`not-on-board`) instead of first asking to confirm, and the confirmation no longer shows the number of an order from another board.
- The card slide-out, automations panel, and widget picker answer 404 on a switched-off board for viewers who can't edit it, as the board itself does.
- Adding an order that is already on the board (from "Add order" or the slide-out's "Add to another board") shows an info toast, "Order :number is already on :board.", instead of a misleading "added" toast. "Add to another board" refuses a column from a different board instead of quietly using the entry column.
- The slide-out's timeline no longer names boards the viewer can't view.
- A test now fails when `en.json` has a key no source file uses, or the source uses a key missing from it.
- Card actions with both a URL and an event open the URL in a new tab on Ctrl, Cmd, Shift, or Alt-click again, as in 1.0 (Cmd-click on macOS, where Ctrl-click opens the context menu); a plain click dispatches the event.
- **Right to left:** keyboard drag moves cards and edit-mode columns in the reading direction, and the "Move undone" icon mirrors.
- **Accessibility:**
  - Widget-picker rows keep list semantics instead of becoming `role="button"` around form controls.
  - Drag instructions and announcements in the widget picker and for edit-mode columns are translated and name widgets and columns instead of cards (`Board::dragMessages( $kind )`).
  - Connection changes ("Live", "Offline") and "Add order" results (including none) are announced through persistent status regions, and "Live" no longer shows before Alpine starts.
  - Cards no longer carry their own live region for "Moving…" / "Move undone".
  - The moving-card state keeps full opacity, so its text stays at WCAG AA contrast, and shows a dashed edge, a sunken surface and a spinner.
  - New styles for a held card and for allowed and refused drop targets; the drop indicator uses the primary token instead of a hard-coded blue.
  - Card action triggers keep a 24px minimum target in compact density.
  - Widget-row errors in the widget picker are alerts tied to their control, and focus moves to the first one after a failed save.
  - The widget picker's preview card is no longer a tab stop and ignores board events.
- Single-card and single-column refreshes no longer render tooltip and menu ids that duplicate ones already on the board.
- An automation toggle goes back to its real state after a refused or failed request.
- The Customer and Products filter comboboxes keep searching and accept a pick after the first search on Livewire 4 (worked around a livewire-ui-components 3.0 Combobox re-init).
- The browser test suite no longer hangs.
- A card that the `cardQuery` filter hides while "Move to…", the exit-column confirmation, or the remove confirmation is open closes that dialog instead of naming it, and a confirmed removal of it is refused.
- Polling refreshes a column when only hidden cards joined or left it, so its total and WIP badge stay current.
- Satellite filter-control options may use livewire-ui-components 3.0's `value` / `label` keys as well as `id` / `name`.
- Switching an automation on or off no longer fails after saving when a board-change listener throws; the error is reported instead.
- A card action chosen with a long modified press still opens its URL in a new tab: the modifiers come from the press that chose the item.

### Known issues

- In right-to-left locales, dragging a column header with a pointer in edit mode drops it at the start of the board (a livewire-drag-and-drop issue). Keyboard moves and the "Move left" / "Move right" buttons work.

## [1.0.0] - 2026-10-29

The first release of the Livewire order board for the ArtisanPack UI ecommerce
engine. Orders show as cards in columns by sub-status and move by drag-and-drop,
keyboard, or a "Move to…" dialog. The package also covers filtering, a card
slide-out, adding and removing orders, board-native column and card-widget
editing, an automations panel, and real-time sync. Every change goes through
the engine's services.

### Requirements

- PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4.
- `artisanpack-ui/ecommerce` `^1.2`: manual card order, the filterable card query, board events, card refresh, widget config, and the column service (ArtisanPack-UI/ecommerce#198–#203).
- `artisanpack-ui/livewire-ui-components` `^2.1`, `artisanpack-ui/core` `^1.0`, `artisanpack-ui/hooks` `^1.2`, and `@artisanpack-ui/livewire-drag-and-drop` `^2.1` on the front end.

### Added

- Package scaffold: service provider, config, and `SatelliteRegistry` registration.
- Board routes (`artisanpack.ecommerce.kanban.boards.index` / `.show`), screen controller, layout resolver (config → cms-framework → ecommerce admin → standalone), standalone layout, rate-limited Livewire updates, and the "Kanban" admin menu entry (#2).
- `php artisan ecommerce-kanban:install`: publishes the config, prints the Tailwind and drag-and-drop lines, and checks the engine, boards, and broadcasting (#3).
- Board picker that returns to the last board, plus a board switcher in the board header (#4).
- Board view: columns with counts, WIP indicators, collapse (remembered per board), and "Show more" paging (#5).
- Cards and widget rendering with tone, icon, tooltip, and link; the `ap.ecommerceKanbanLivewire.widgetView` and `ap.ecommerceKanbanLivewire.cardActions` filters; and the card actions menu (#6).
- Drag cards between columns through `KanbanBoardService::move()`: optimistic drops that revert with a translated toast when the engine refuses, a client-side WIP-limit veto, and a confirmation before a move into an exit column (cancelled, refunded, failed) (#7).
- Reorder cards within a column by drag, kept in the engine's manual card order (#8).
- "Move to…" dialog (column + top / bottom / after a card) for every card, and translated drag-and-drop keyboard instructions and announcements (#9).
- Filter bar with search, customer, tags, products, placed date range, stale cards, and payment and fulfillment status, kept in the URL; filtered and real column counts; a drawer on small screens; and the `ap.ecommerceKanbanLivewire.filters` filter for satellite controls (#10).
- Card slide-out (`artisanpack-ecommerce-kanban-card-detail`) with the order summary, its other boards, recent activity, "Move to…", and "Open in admin"; `?card={order}` opens it (#11).
- Add and remove orders on a board (#12): "Add order" in the board header searches orders that aren't on the board and assigns one to a chosen column or the engine's entry column; "Remove from board" (card menu and slide-out) asks first and runs once per action token; the slide-out adds the order to another board. Engine refusals show as toasts.
- Real-time board sync (#13): with `realtime.enabled`, Laravel Echo, and the engine's `kanban.broadcast`, the board listens for `kanbanBoardEvent` on `private-ecommerce.kanban.board.{board}`, ignores the echo of the user's own in-flight move, cancels a drag whose card moved elsewhere, and leaves the channel on navigation; otherwise it polls every `realtime.poll_interval` seconds (paused while dragging or when the tab is hidden; 0 turns it off). `x-artisanpack-ec-kb-connection` shows live / polling / offline.
- Live card refresh (#14): `card-updated` events re-render just the affected cards (batched, so a card re-renders once) and the slide-out when it shows one of them.
- "Edit board" mode (#15) with `kanbanBoard.update`: drag column headers or use "Move left / right" to reorder, edit a column's label, color, icon, and WIP limit with the engine's validation messages inline, add a column for a sub-status not on the board (grouped by system status), remove a column (its cards moved to a chosen column first; once per action token), and an "Advanced settings" link to the admin's board screen.
- Card widget picker (`artisanpack-ecommerce-kanban-widget-picker`, #16) for each column and the board default: registered widgets with label and provider, add / remove / reorder (drag or up / down), settings forms from each widget's `configSchema()`, which list applies, and a live preview on a card from the board.
- Automations panel (`artisanpack-ecommerce-kanban-automations`, #17) for the whole board and per column ("into this column" / "out of this column"): trigger label, from → to, a conditions summary, and whether it is on. With `kanbanBoard.update`, switch an automation on or off and delete it (confirmed, once per action token); "Add" and "Edit" link to the admin's automation forms when the admin is installed. Column headers show how many active automations fire into the column.
- Automation feedback after a move (#18): one toast per move naming the automations that ran, or an error naming each failed trigger with a link to the order's slide-out. Read from the timeline entries the move wrote, or from the engine's `automation-fired` / `automation-failed` broadcasts while the board is live; only the person who moved the card sees it.
- Extension surface (#19), listed in `Support\Hooks`: the new `ap.ecommerceKanbanLivewire.boardToolbar` (header buttons) and `ap.ecommerceKanbanLivewire.cardClasses` (card CSS classes) filters, and the `ap.ecommerceKanbanLivewire.boardRendered` and `ap.ecommerceKanbanLivewire.cardMoveFailed` actions.
- Spanish, French, and German translations for every new string, and tests that every catalogue has every key and placeholder and that plural strings resolve for 0, 1, and many (#21).
- Browser test suite (#23): a workbench (`package.json`, `vite.config.js`, `npm run build:browser`, `composer test:browser`) with a seeded demo board; journeys for dragging a card between columns with the automation firing, reordering within a column, keyboard moves, "Move to…", the exit-column confirmation, WIP refusals, filtering by tag and date, `?card=`, reordering columns in edit mode, and a move made in another browser; axe checks on every screen and dialog (light, dark, phone); and a CI `browser` job for Livewire 3 and 4.
- Documentation in `docs/` and a new README with screenshots (#24).
- The card slide-out describes the engine's automation timeline entries ("Automation ran: …", "Automation failed: …", "Order field … updated") in every catalogue.
- Cards read through the engine's `KanbanCardQuery`, in their manual order.
- A card action may have both a `url` and an `event`: a plain click dispatches the event and a modified click follows the link. "Open" opens the slide-out in place.
- Large boards (#20):
  - Columns load as they scroll into view, and collapsed columns never query.
  - A drop re-renders only its two columns, real-time card events only the columns they name, and polls only the columns whose cards changed.
  - Board load, column paging, card re-renders, polls, and the slide-out have fixed query budgets; a 1,000-card board loads in 24 queries.
- WCAG 2.2 AA (#22):
  - Cards are focusable groups, and columns are labelled regions.
  - Neutral widget badges use an outline in the text color (dark-mode contrast).
  - The date filters are labelled, and the card pulse and transitions stop under reduced motion.
  - The standalone layout's sticky header has scroll padding so focus isn't hidden under it.
  - The "Filters" toggle only shows on small screens.
  - The tags filter is replaced rather than morphed when its options load (livewire-ui-components#135).
- Rate limiting that counts only changes: page loads, polls, lazy column loads, real-time refreshes, automation feedback, filtering, searching, and opening or closing dialogs don't count against `ecommerce.admin.mutate`. The read lists only apply to this package's components.

### Fixed

Found in the 1.0.0 release-readiness review (#31) and fixed before release:

- **Removing columns:**
  - When the engine won't move a removed column's cards to the chosen column (for example, an illegal status change), the dialog says why instead of failing with a server error.
  - Moving them into an exit column (cancelled, refunded, failed) needs an extra confirmation, as a drop does.
- **Rate limiting:** the rate limiter reads the Livewire update from the current request. As persistent middleware it receives a copy without the request body, so before this fix every board update counted, reads included.
- **Failed requests:** a drop or column reorder whose request fails (rate limited, server error) goes back where it was, and a move that never gets an answer is put back after 20 seconds.
- **Access:**
  - A switched-off board opens only for people who can edit it, with a notice, and is never remembered as the last board.
  - The widget picker needs `kanbanBoard.update` to open and edit, however it is opened.
  - The slide-out's "Other boards" lists only boards the viewer can see.
  - The filter option lists are locked to the server.
  - The rate-limit toast escapes its text.
- **Accessibility:**
  - The automation switch is labelled "Active" and sets the state rather than flipping it.
  - The automation delete confirmation takes focus and gives it back.
  - Every dialog returns focus to what opened it, however it is closed (Escape and backdrop included).
  - The slide-out announces only status and column changes; empty columns are no longer live regions, and the board announces how many orders match the filters.
  - Read-only card lists are named groups, and "Show more" moves focus to the first new card.
  - The connection status shows the real poll interval ("Updates every 30 seconds").
- **Smaller fixes:**
  - "After a card" in "Move to…" says when its list is cut short at 50 cards.
  - Widget tone badge classes are written out in full for Tailwind.
  - `textarea` widget settings render as a textarea.
