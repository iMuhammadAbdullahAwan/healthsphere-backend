<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use React\EventLoop\Loop;
use React\Socket\SocketServer as Reactor;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;
use React\Socket\SecureServer;
use App\Libraries\NotificationSocket;

class NotificationServer extends BaseCommand
{
    protected $group = 'HealthSphere';
    protected $name = 'notification:server';
    protected $description = 'Start the WebSocket notification server';
    protected $usage = 'notification:server [options]';
    protected $options = [
        '--port' => 'Port number (default: 8084)',
        '--host' => 'Host address (default: 0.0.0.0)',
    ];

    public function run(array $params)
    {
        if (!is_cli()) {
            CLI::error("Server is only CLI enabled.");
            return;
        }

        $loop = Loop::get();

        $notificationSocket = new NotificationSocket($loop);

        $webSocket = new WsServer($notificationSocket);
        $httpServer = new HttpServer($webSocket);

        /**
         * Production
         */
        // $port = 8084;
        // $contextOptions = [
        //     'local_cert'        => '/home/apphealthsphere/public_html/api/secure/fullchain.pem',
        //     'local_pk'          => '/home/apphealthsphere/public_html/api/secure/privkey.pem',
        //     'allow_self_signed' => false,
        //     'verify_peer'       => false
        // ];

        // $socket = new Reactor('0.0.0.0:' . $port, [], $loop);
        // $secureServer = new SecureServer($socket, $loop, $contextOptions);
        // $secureServer->on('error', function ($e) {
        //     log_message("error", $e->getMessage());
        // });
        // $server = new IoServer($httpServer, $secureServer, $loop);
        // print($server);


        // CLI::write("WebSocket server running on port " . $port . " ...", 'green');
        // $loop->run();

        /**
         * Development
         */
        $port = 8084;
        $socket = new Reactor('0.0.0.0:' . $port, [], $loop);
        $server = new IoServer($httpServer, $socket, $loop);
        CLI::write("WebSocket server running on port " . $port . " ...", 'green');
        $loop->run();
    }
}
