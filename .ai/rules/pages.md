---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Put Livewire page form state and rules in app/Livewire/Forms
Page components are single-file (⚡*.blade.php) and PHPStan only analyses app/. Keep a form's fields and validation rules in a Livewire Form object (app/Livewire/Forms/*Form.php, e.g. VehicleForm) rather than a trait that only an SFC uses — otherwise larastan fails with trait.unused. Bind inputs as wire:model="form.field", and write test assertions against "form.field".
