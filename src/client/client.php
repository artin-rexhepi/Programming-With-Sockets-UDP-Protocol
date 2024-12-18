<?php
// Client.php
$server_ip = '192.168.1.19'; 
$port = 12345; 

$client_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($client_socket === false) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Client running. Connecting to $server_ip:$port\n";


echo "Enter password if you are admin or editor or type 'guest' to continue as guest: ";
$password = trim(fgets(STDIN));

if (socket_sendto($client_socket, $password, strlen($password), 0, $server_ip, $port) === false) {
    die("Failed to send password: " . socket_strerror(socket_last_error($client_socket)) . "\n");
}

$response = '';
$from = '';
$port_from = 0;
$bytes_received = socket_recvfrom($client_socket, $response, 1024, 0, $from, $port_from);

if ($bytes_received === false) {
    die("Error receiving response: " . socket_strerror(socket_last_error($client_socket)) . "\n");
}

$response = trim($response);
echo "Server response: $response\n";

if (strpos($response, 'Full Access') !== false) {
    echo "Menu: [list, read <file>, write <file> <content>, delete <file>, create <file>, open <file>, exit]\n";
} elseif (strpos($response, 'Edit Access') !== false) {
    echo "Menu: [list, read <file>, write <file> <content>, open <file>, exit]\n";
} elseif (strpos($response, 'Read Only') !== false) {
    echo "Menu: [list, read <file>, exit]\n";
} else {
    echo "Invalid access level. Exiting...\n";
    exit;
}


while (true) {
    echo "Enter command: ";
    $command = trim(fgets(STDIN));

    if ($command === 'exit') {
        break;
    }

    if (socket_sendto($client_socket, $command, strlen($command), 0, $server_ip, $port) === false) {
        echo "Failed to send command: " . socket_strerror(socket_last_error($client_socket)) . "\n";
        continue;
    }

    $response = '';
    $bytes_received = socket_recvfrom($client_socket, $response, 1024, 0, $from, $port_from);

    if ($bytes_received === false) {
        echo "Error receiving response: " . socket_strerror(socket_last_error($client_socket)) . "\n";
        continue;
    }

    $response = trim($response);
    echo "Server response: $response\n";
}

socket_close($client_socket);
?>
