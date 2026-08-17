<?php

namespace Whilesmart\Integrations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Integrations\Models\Integration;

/**
 * A webhook the provider sent about a connection, passed on by the vault.
 *
 * The body is whatever that provider sends, so this package records it and
 * hands it on rather than interpreting it. A host listens to turn it into
 * whatever the provider's events mean in its own domain. The provider's own
 * headers come along because that is where several of them put the event name.
 */
class ProviderWebhookForwarded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  The envelope the vault delivered.
     * @param  array<string, mixed>  $body  What the provider itself sent.
     * @param  array<string, mixed>  $headers  The provider's headers, as forwarded.
     */
    public function __construct(
        public Integration $integration,
        public array $payload,
        public array $body,
        public array $headers = [],
    ) {
    }
}
