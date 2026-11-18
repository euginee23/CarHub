---
paths:
  - 'resources/views/components/**'
---

# Components

## Clear a #[Computed] value after changing what it is built from
A #[Computed] property is cached for the whole request. If an action reads it (e.g. $this->monthStart) and then changes the property it is derived from ($this->month), the render reuses the stale value and the page shows the previous state. After the change, call unset($this->monthStart). In tests, assert what is rendered (assertSee), not only assertSet. This was the cause of the availability calendar's month arrows lagging one click behind.

## Alpine x-data names must not match Livewire action names
wire:click expressions are evaluated in the Alpine scope first, so an x-data key with the same name as a component action shadows it. For example, x-data="{ open: false }" made wire:click="open(id)" fail with "open is not a function", and bell notifications silently did nothing. Name UI flags distinctly (panelOpen, menuOpen), and check click-through in a browser, because Livewire::test()->call() bypasses this.
