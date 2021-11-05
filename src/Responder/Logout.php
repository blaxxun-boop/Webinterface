<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Session\Session;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;

class Logout {
    public function logoutUser(Request $request, Session $session) {
		$session->open();
		$session->destroy();

        return redirectTo("/Login");
    }
}
