# Contacting

Contact methods, social profiles and immutable snapshots are separate owned features under `AIArmada\Contacting\Features`. Use the concrete create, update and primary-selection Actions in the owning feature. Use the public `HasContactMethods` and `HasSocialProfiles` concerns for host model integration. Apply explicit owner context for all tenant writes. Never expose private contact details without an explicit visibility decision. Snapshots are append-only and omit loaded relationship payloads.
