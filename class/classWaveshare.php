<?php

/*
  (c) 2025 Nima Ghassemi Nejad

  v 1.0  05.07.2025 - initial release
 */

class WaveshareClient {
    private string $host;
    private int $port;
    private int $timeOut;

    private array $dataBlock;
    private $socket;
    private $log;
    
    public function __construct(callable $logFunction, string $host, int $port, int $timeOut) {
        $this->host = $host;
        $this->port = $port;
        $this->timeOut = $timeOut;

        $this->dataBlock = [];
        $this->socket = false;
        $this->log = $logFunction;
    }

    function __destruct() {
        if ($this->socket !== false) 
            socket_close($this->socket);
    }

    public function connect () : bool {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
            return false;
        }
        socket_set_nonblock($this->socket);
        @socket_connect($this->socket, $this->host, $this->port); // Non-blocking connect

        return true;
    }

    public function hexDump(array $bytes, int $width = 16) : string {
        $lines = [];
        $count = count($bytes);
        for ($i = 0; $i < $count; $i += $width) {
            $chunk = array_slice($bytes, $i, $width);
            $hex = '';
            $ascii = '';
            foreach ($chunk as $byte) {
                $hex .= sprintf('%02x ', $byte);
                $ascii .= ($byte >= 32 && $byte <= 126) ? chr($byte) : '.';
            }
            $lines[] = sprintf("%08x  %-48s |%s|", $i, $hex, $ascii);
        }
        return implode("\n", $lines);
    }
    
    public function readFromSocket (int $timeout = 0) : ?string {
        if ($timeout > 0) {
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $timeout,  // Seconds
                'usec' => 0   // Microseconds
            ]);
        }

        $data = false;
        try {
            $data = @socket_read($this->socket, 2048, PHP_BINARY_READ);
        }  catch (Throwable $t) {
            ($this->log)("Error on read. Socket probably disconnected.");
            return null;
        }
        
        if ($data === false) {
            $error = socket_last_error($this->socket);
            if ($error != SOCKET_EAGAIN && $error != SOCKET_EWOULDBLOCK) {
                ($this->log)("Failed to read from socket: " . socket_strerror($error));
                return null;
            }
        }
        return $data;
    }
    
    public function readDataAsBinaryArray() : ?array {
        $response = null;

        $write = $except = null;
        
        $read = [$this->socket];
        if (socket_select($read, $write, $except, $this->timeOut) > 0) {
            $response = $this->readFromSocket ();
            if ($response != null) {
                $response = unpack('C*', $response);
            }
        }
        return $response;
    }

    public function readDataAsString() : ?string {
        $response = null;

        $write = $except = null;
        
        $read = [$this->socket];
        if (socket_select($read, $write, $except, $this->timeOut) > 0) {
            $response = $this->readFromSocket ();
        }
        return $response;
    }

    private function writeToSocket (string $data) : bool {
        $totalWritten = 0;
        $length = strlen($data);

        try {
            while ($totalWritten < $length) {
                $written = socket_write($this->socket, substr($data, $totalWritten), $length - $totalWritten);
                if ($written === false) {
                    $error = socket_last_error($this->socket);
                    ($this->log)("Failed to write to socket:" . socket_strerror($error));
                    return false;
                }
                $totalWritten += $written;
            }
            return true;
        } catch (Throwable $e) {
            ($this->log)("writeToSocket failed:" . $e->getMessage());
        }
        return false;
    }
    
    public function writeArrayToSocket(array $data): bool {
        $binaryString = pack('C*', ...$data);
        return ($this->writeToSocket ($binaryString));
    }

    public function writeStringToSocket(string $data): bool {
        return ($this->writeToSocket ($data));
    }
}
?>
