---
paths:
  - 'app/Services/Assistant/**'
---

# Assistant

## Chat assistant talks to Claude through CarHubAssistant only
The floating chat (components/marketing/⚡chat-bubble, Livewire) calls CarHubAssistant::reply(), which uses the official anthropic-ai/sdk Client bound in AppServiceProvider (key: services.anthropic.key; model/effort/limits: carhub.assistant). Between chat turns send only plain text (no thinking blocks): Claude Opus 5.5 rejects edited histories that replay thinking. Inside one reply's tool loop, echo every response block back unchanged (CarHubAssistant::echoed) and keep the tools list identical on every request. AssistantTools are read-only lookups over Vehicle::listed(), AvailabilityChecker, and RentalQuote; never add tools that book or change data. In tests, fake the API by binding Anthropic\Client with a Guzzle MockHandler `transporter` (see tests/Feature/Marketing/AssistantTest.php); Http::fake does not intercept the SDK.
