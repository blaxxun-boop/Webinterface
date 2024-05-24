<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use Amp\Http\Server\Session\Session;
use function Amp\Http\Server\redirectTo;

class Logout {
    public function logoutUser(Request $request, Session $session) {
		$session->lock();
		$session->destroy();

        return redirectTo("/Login");
    }
}
