# ArtisanPack UI Ecommerce Kanban (Livewire)

The order kanban board for the [ArtisanPack UI ecommerce engine](https://github.com/ArtisanPack-UI/ecommerce): accessible drag-and-drop boards of orders by sub-status, a board picker, a filter bar, a card detail slide-out, and settings screens for columns, card widgets, routing rules, and automations. Built with Livewire on [`livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components) and [`livewire-drag-and-drop`](https://github.com/ArtisanPack-UI/livewire-drag-and-drop), with real-time sync through Laravel Echo (Reverb or any broadcaster).

> **Status:** in development toward v1.0.0. The build plan is in [`plans/16-ecommerce-kanban-livewire-spec.md`](plans/16-ecommerce-kanban-livewire-spec.md) and the work is tracked in the [v1.0 milestone](https://github.com/ArtisanPack-UI/ecommerce-kanban-livewire/milestone/1).

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 3.6+ or 4
- `artisanpack-ui/ecommerce` 1.1+

## Installation

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire
```

The service provider is auto-discovered and registers the package with the engine's satellite registry.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

MIT. See [LICENSE](LICENSE).
