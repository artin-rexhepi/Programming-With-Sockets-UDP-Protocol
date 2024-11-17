<?php
// Server.php
$port = 8080; 
$ip_address = '192.168.1.19'; 

$server_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($server_socket === false) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

if (socket_bind($server_socket, $ip_address, $port) === false) {
    die("Binding failed: " . socket_strerror(socket_last_error($server_socket)) . "\n");
}

echo "Server running on UDP at $ip_address:$port\n";

$permissions = []; // List of permissions for clients

while (true) {
    $buf = '';
    $from = '';
    $port_from = 0;

    $bytes_received = socket_recvfrom($server_socket, $buf, 1024, 0, $from, $port_from);
    if ($bytes_received === false) {
        echo "Error receiving message: " . socket_strerror(socket_last_error($server_socket)) . "\n";
        continue;
    }

    $buf = trim($buf);
    if ($buf) {
        echo "Request from $from:$port_from: $buf\n";

        if ($buf === 'kerko_full_access' || $buf === 'kerko_read_only' || $buf === 'kerko_edit') {
            echo "Approve access for $buf (yes/no): ";
            $approval = trim(fgets(STDIN));

            if ($approval === 'yes') {
                $permissions[$from] = $buf;
                $response = "$buf approved.";
            } else {
                $response = "Request denied.";
            }
            socket_sendto($server_socket, $response, strlen($response), 0, $from, $port_from);
        } elseif (isset($permissions[$from])) {
            $command_parts = explode(" ", $buf);
            $command = $command_parts[0];
            $file_name = isset($command_parts[1]) ? $command_parts[1] : '';

            if (!in_array($command, ['read', 'write', 'delete', 'open', 'create'])) {
                $response = "Unknown command.";
            } elseif (!empty($file_name) && in_array($command, ['read', 'write', 'delete', 'open', 'create']) && !file_exists($file_name) && $command !== 'create') {
                $response = "File $file_name does not exist.";
            } else {
                if ($command === 'read') {
                    if ($permissions[$from] === 'kerko_full_access' || $permissions[$from] === 'kerko_read_only' || $permissions[$from] === 'kerko_edit') {
                        $content = file_get_contents($file_name);
                        $response = "Content of $file_name:\n$content";
                    } else {
                        $response = "You do not have permission to read files.";
                    }
                } elseif ($command === 'write') {
                    if ($permissions[$from] === 'kerko_full_access' || $permissions[$from] === 'kerko_edit') {
                        $new_content = implode(" ", array_slice($command_parts, 2));
                        file_put_contents($file_name, $new_content);
                        $response = "New content written to $file_name.";
                    } else {
                        $response = "You do not have permission to write to files.";
                    }
                } elseif ($command === 'delete') {
                    if ($permissions[$from] === 'kerko_full_access') {
                        unlink($file_name);
                        $response = "$file_name deleted.";
                    } else {
                        $response = "You do not have permission to delete files.";
                    }
                } elseif ($command === 'open') {
                    if ($permissions[$from] === 'kerko_full_access') {
                        if (PHP_OS_FAMILY === 'Windows') {
                            exec("start " . escapeshellarg($file_name));
                        } elseif (PHP_OS_FAMILY === 'Linux') {
                            exec("xdg-open " . escapeshellarg($file_name) . " > /dev/null &");
                        } elseif (PHP_OS_FAMILY === 'Darwin') {
                            exec("open " . escapeshellarg($file_name));
                        }
                        $response = "$file_name opened.";
                    } else {
                        $response = "You do not have permission to open files.";
                    }
                } elseif ($command === 'create') {
                    if ($permissions[$from] === 'kerko_full_access') {
                        file_put_contents($file_name, ""); // Create an empty file
                        $response = "$file_name created successfully.";
                    } else {
                        $response = "You do not have permission to create files.";
                    }
                }
            }
            socket_sendto($server_socket, $response, strlen($response), 0, $from, $port_from);
        } else {
            $response = "You do not have approved access. Please request access.";
            socket_sendto($server_socket, $response, strlen($response), 0, $from, $port_from);
        }
    }
}

socket_close($server_socket);
?>
