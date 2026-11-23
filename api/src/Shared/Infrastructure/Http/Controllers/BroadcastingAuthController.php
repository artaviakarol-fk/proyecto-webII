<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

final readonly class BroadcastingAuthController
{
    /**
     * Sign the permission to listen to a private channel.
     *
     * The client of Reverb (Laravel Echo or pusher-js) calls it with the same token of the API, the `socket_id` of
     * its connection and the `channel_name`. The rule of the channel in routes/channels.php decides: the staff of the
     * negocio gets the signature, anybody else 403. The secret of Reverb signs it and never leaves the server.
     *
     * @return array{auth: string}
     */
    public function __invoke(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}
