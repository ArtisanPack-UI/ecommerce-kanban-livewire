---
title: Accessibility
---

# Accessibility

The board targets **WCAG 2.2 AA**. Everything staff do on it (choosing a board, moving cards, filtering, opening a card, editing columns and widgets, and managing automations) is meant to work by keyboard and with a screen reader. This page says what is in place, how it is tested, what is known not to meet the target yet, and how to report a problem.

## What is in place

### Structure and names

- **Columns are labelled regions.** Each column is a `section` with an explicit `role="region"`, named by its heading. Its card list is a group named "Orders in {column}", on read-only boards too.
- **Cards are focusable groups.** Each card is a focusable `role="group"` named by a summary, such as "Order 1042, Jane Doe, 3 items, $124.00". Its actions button is named "Actions for order 1042".
- **The column strip can be scrolled by keyboard.** It is focusable, so the arrow keys scroll it sideways.
- **The filter bar is a search landmark** named "Filter orders". The date fields repeat their label as `aria-label`, since the component library prints it as a legend the input isn't linked to.
- **Icon-only buttons say what they act on**, for example "Collapse Packing", "Move Packing left", "Edit Packing", "Card widgets for Packing". Collapse buttons report `aria-expanded`.

### Never color alone

- **WIP state.** At the limit, the count adds a warning icon, and screen readers hear "1 of 1 cards, WIP limit reached".
- **Widget tones.** A warning, problem, success, or note tone adds an icon and screen-reader text ("Needs attention", "Problem", "Good", "Note").
- **Move state.** A card in flight says "Moving…", and a refused move says "Move undone", in a `role="status"` element.
- **Connection.** The connection indicator says "Live", "Updates every N seconds" (the poll interval), "Offline", or "Not updating automatically" in text.
- **Automation switches.** Each automation's switch is labelled "Active" (its screen-reader name is "Active: " plus the automation, so it starts with the visible label, SC 2.5.3), and the row says "On" or "Off" in text.
- **Column color is decoration.** The user-set column color is only drawn as a top border. Labels never sit on it, so no color a store picks can fail contrast.

### Moving cards

- **Keyboard drag.** Focus a card, press Space or Enter to pick it up, Left and Right to move between columns, Up and Down to move within a column, Home and End to jump to the top or bottom, Space or Enter to drop, and Escape to cancel. The instructions and every step are announced in the user's language, naming the order, the column, and the position: "Order 1002 moved to Packing, position 1 of 2."
- **A single-pointer alternative (SC 2.5.7).** Every card's menu has **Move to…**, a dialog with a column and a position, which makes the same move as a drop.
- **Exit columns confirm first.** A move into a `cancelled`, `refunded`, or `failed` column asks before it changes the order's status.
- **Remote changes are announced.** When someone else moves the card you are dragging, the drag is canceled and an assertive live region says "This card was moved by someone else, so your move was canceled."
- **A failed move goes back.** When a move's request fails (rate limited, server error), the card returns to where it was and says "Move undone".

### Focus

- **Skip link.** The standalone layout's first tab stop is "Skip to content".
- **The card slide-out traps focus** while open, closes with Escape, and returns focus to the card it opened from.
- **Dialogs give focus back.** However a dialog closes (its button, Escape, or a click outside), focus returns to what opened it:
  - "Move to…", the exit-column confirmation, and "Remove from board" return to the card;
  - "Add order" returns to its header button;
  - the column editor and "Remove column" return to the column's button;
  - "Add a column" returns to its button.
- **"Move to…" returns focus to the moved card** after the move.
- **The automation delete confirmation takes focus** when it appears. Escape cancels it without closing the panel, and focus returns to the row's **Delete** button, or moves on after a delete.
- **"Show more" moves focus** to the first card it loaded, so focus isn't lost when the button goes away.
- **Column move buttons keep focus.** In edit mode, "Move left" and "Move right" stay focused after the column moves, so you can press again.
- **Nothing hides under the sticky header (SC 2.4.11).** The standalone layout's header is sticky, and the page has `scroll-padding-top`, so a focused card or control scrolls clear of it.
- **Visible focus.** Cards and card lists draw a focus outline in the primary color.

### Motion, targets, and messages

- **Reduced motion.** Under `prefers-reduced-motion`, the card pulse while a move is in flight and the card transitions are turned off. Cards never transition `transform`, so a dragged card's floating copy follows the pointer without lag.
- **Target size (SC 2.5.8).** Buttons meet the 24px minimum, and the browser suite runs axe's `target-size` rule on every screen.
- **Toasts** for refused moves, automation results, and rate limits use `role="alert"`.
- **Status messages stay short.** The card slide-out announces only a change to the open order's status or column ("Order 1042 is now Complete in Shipped"), not its whole contents. Empty columns aren't live regions, so a filter change doesn't announce every empty column; instead one status message says how many orders match the filters. The "No boards yet" and "This board has no columns" empty states are status messages.
- **Phones.** Below the `md` breakpoint the filters move into a drawer, and each column takes most of the screen width.
- **Language.** The standalone layout's `<html lang>` follows the application locale.

## How it is tested

The browser suite (see [Browser Tests](Advanced-Browser-Tests)) drives the board in Chromium against a seeded demo board:

- **axe on every screen and dialog.** `tests/Browser/AccessibilityTest.php` runs every axe rule, with `target-size` (WCAG 2.2, 24px) turned on, on:
  - the board picker;
  - the board in light and dark mode, read-only, and with filters that hide cards;
  - the card slide-out;
  - the "Move to…" and "Add order" dialogs;
  - edit mode and its dialogs;
  - the widget picker;
  - the automations panel and its delete confirmation;
  - the board on a phone with the filters open. Any violation fails the build, except the known library issues below.
- **Keyboard.** `tests/Browser/KeyboardTest.php` checks the skip link, the names of cards and columns (and the WIP text), the announcements of a keyboard move by order, column, and position, opening a card's actions and "Move to…" from the keyboard, focus returning to the card when the slide-out closes or a dialog is dismissed with Escape, focus in and out of the automation delete confirmation, focus after "Show more", moving columns by keyboard in edit mode with focus kept, and focused controls staying clear of the sticky header.
- **Journeys.** `tests/Browser/JourneysTest.php` moves a card with the keyboard and with "Move to…", as well as by dragging.

The axe helper is `tests/Browser/Support/Accessibility.php` (`Accessibility::assertAccessible( $page )`).

## Known issues

These come from `artisanpack-ui/livewire-ui-components` markup the board cannot change. They are tracked upstream in [livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117):

- **Choices search inputs.** The search input of `x-artisanpack-choices` (the customer, tags, and products filters) has no label: the field label is a `<legend>` it isn't linked to.
- **Select names.** `x-artisanpack-select` labels with a `<legend>`, so the control itself has no accessible name.
- **Dropdown menus.** `x-artisanpack-dropdown` menus (the card actions and the board switcher) put a `<div>` between the `<ul>` and its `<li>`s, and give items `role="menuitem"` without a `role="menu"` parent.
- **Drawer toggle.** The drawer's `drawer-toggle` checkbox (the slide-out, the automations panel, the phone filters) is unlabelled.
- **Color picker.** The native color input of `x-artisanpack-colorpicker` (the column editor) has no label.

Tracked in [livewire-ui-components#135](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/135):

- **Close buttons.** The icon-only close buttons of the library's modal and drawer have no accessible name.
- **Customer and product filters.** On Livewire 4, the library's `x-artisanpack-choices` keeps its options in its Alpine state, so after a server search changes them the control stops responding and shows no results. The tags filter works around this by being replaced, not morphed, when its options change; the server-searched filters can't, because that would drop the text being typed.

Each is excluded from the automated check by axe rule and selector in `Accessibility::LIBRARY_ISSUES`; a node is ignored only when it, or an ancestor, matches that selector. Some selectors are broader than the library markup they target: the `select-name` exclusion, for example, skips any `<select>` inside a `<fieldset>`, including one the board renders itself. A regression of that same rule inside an excluded selector would not fail the suite, so check new markup there by hand. An exclusion is removed when the library fixes it.

## What your theme and layout need

The board uses daisyUI theme colors (`base-content`, `primary`, `warning`, `error`, and so on). If you use your own theme, keep text colors at 4.5:1 against the backgrounds they sit on, including `base-content` at 70% opacity for secondary text.

If the board renders in your own layout (see [Layouts](Installation-Layouts)), its header, skip link, and scroll padding are yours: provide a skip link to the content, set `<html lang>`, and if your header is sticky or fixed, give the page enough `scroll-padding-top` that a focused card never sits under it.

## Reporting an issue

Open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-kanban-livewire/issues) with:

- the screen and what you were doing;
- the assistive technology, browser, and operating system;
- what you expected and what happened.
