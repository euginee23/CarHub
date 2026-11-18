---
paths:
  - 'resources/views/**'
---

# Views

## $wire.$upload is callback-based in the installed Livewire (4.4)
The current Livewire docs show `await $wire.$upload('prop', file)` returning a promise, but livewire/livewire v4.4.0 only takes callbacks: $upload(name, file, onFinish, onError, onProgress, onCancel), and awaiting it resolves immediately with nothing uploaded. Wrap it in a Promise, as the checkout signing form does, then call the action. Livewire::test()->set() skips this JS path, so check uploads in a browser.

## x-ref on an element with its own x-data is invisible to the parent
Alpine registers an x-ref on the closest x-data root, and an element with its own x-data is its own root. So `<div x-ref="pad" x-data="signaturePad">` puts "pad" on the pad's component, and the parent's `this.$refs.pad` is undefined. To reach a nested component from its parent, give it a data attribute and use `Alpine.$data(this.$root.querySelector('[data-signature-pad]'))`, as the checkout signing form does. Always test the real button in a browser: calling the child's methods from the console skips this path.

## $wire.$interceptMessage's remover throws in Livewire 4.4
In livewire/livewire 4.4.0, calling the function returned by `$wire.$interceptMessage(...)` throws "messageInterceptorCallbacksByComponent.delete is not a function" (the store is a WeakBag). Register the interceptor once in Alpine's init() and return early inside it when it shouldn't act (e.g. `if (! this.saving) return;`), never in a try/finally that removes it. Also, a 419 or network failure on `$upload`'s first request (_startUpload) never calls its error callback, so use onError/onFailure to give up the wait, and call preventDefault() on a 419 to replace Livewire's "This page has expired" confirm with your own message.
