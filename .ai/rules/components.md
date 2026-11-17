---
paths:
  - 'resources/views/components/**'
---

# Components

## Clear a #[Computed] value after changing what it is built from
A #[Computed] property is cached for the whole request. If an action reads it (e.g. $this->monthStart) and then changes the property it is derived from ($this->month), the render reuses the stale value and the page shows the previous state. After the change, call unset($this->monthStart). In tests, assert what is rendered (assertSee), not only assertSet. This was the cause of the availability calendar's month arrows lagging one click behind.
