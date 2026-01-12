<?php

// Lightweight stubs to satisfy static analyzers (Intelephense).
// These do not affect runtime if the real packages are installed via Composer.

namespace Ratchet {
    interface MessageComponentInterface {}
    interface ConnectionInterface {}
}

namespace Ratchet\WebSocket {
    class WsServer {}
}

namespace Ratchet\Http {
    class HttpServer {}
}

namespace Ratchet\Server {
    class IoServer {}
}

namespace React\EventLoop {
    class Loop {}
}

namespace React\Socket {
    class SocketServer {}
}

namespace WebSocket {
    class Client {}
}
