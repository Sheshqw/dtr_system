<?php

namespace App\Services;

/**
 * ZKTeco/GRANDING TCP implementation using PHP socket extension.
 * More reliable than stream functions on Windows.
 */
class ZktecoService
{
    private const CMD_CONNECT      = 1000;
    private const CMD_EXIT         = 1001;
    private const CMD_ACK_OK       = 2000;
    private const CMD_ATT_LOG_RRQ  = 13;
    private const CMD_DATA         = 1500;
    private const CMD_PREPARE_DATA = 1501;
    private const CMD_FREE_DATA    = 1502;
    private const USHRT_MAX        = 65535;
    private const TCP_MAGIC        = "\x50\x50\x82\x7d";

    private string $ip;
    private int    $port;
    private int    $timeout;
    private        $sock      = null;
    private int    $sessionId = 0;
    private int    $replyId   = 0;

    public function __construct()
    {
        $this->ip      = config('biometric.ip');
        $this->port    = (int) config('biometric.port', 4370);
        $this->timeout = max(15, (int) config('biometric.timeout', 15));
    }

    public function getIp(): string { return $this->ip; }
    public function getPort(): int  { return $this->port; }

    public function isReachable(): bool
    {
        $sock = @fsockopen($this->ip, $this->port, $errno, $errstr, 5);
        if ($sock) { fclose($sock); return true; }
        return false;
    }

    /**
     * Debug: return raw hex of what the device sends after CMD_CONNECT.
     * Useful for diagnosing protocol issues.
     */
    public function debugRawResponse(): array
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! $sock) {
            return ['error' => 'Cannot create socket: ' . socket_strerror(socket_last_error())];
        }

        socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);

        if (! socket_connect($sock, $this->ip, $this->port)) {
            $err = socket_strerror(socket_last_error($sock));
            socket_close($sock);
            return ['error' => "TCP connect failed: $err"];
        }

        // Step 1 — read any banner the device sends immediately on connect
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        $banner = @socket_read($sock, 256, PHP_BINARY_READ);

        // Step 2 — send CMD_CONNECT
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
        $this->sessionId = 0;
        $this->replyId   = self::USHRT_MAX - 1;
        $packet          = $this->buildPacket(self::CMD_CONNECT, '');
        socket_write($sock, $packet, strlen($packet));

        // Step 3 — read response
        $resp = @socket_read($sock, 256, PHP_BINARY_READ);
        socket_close($sock);

        return [
            'step1_banner_hex'  => $banner ? bin2hex($banner) : 'nothing',
            'step1_banner_len'  => $banner ? strlen($banner) : 0,
            'step2_sent_hex'    => bin2hex($packet),
            'step2_sent_bytes'  => strlen($packet),
            'step3_resp_hex'    => $resp ? bin2hex($resp) : 'nothing',
            'step3_resp_bytes'  => $resp ? strlen($resp) : 0,
        ];
    }

    /**
     * Pull all attendance logs from the device.
     *
     * @throws \RuntimeException
     */
    public function getAttendanceLogs(): array
    {
        $this->openSocket();

        try {
            $this->handshake();
            $raw = $this->downloadAttendance();
        } finally {
            $this->closeSocket();
        }

        return $this->parseRecords($raw);
    }

    // ── Socket ──────────────────────────────────────────────────────────────

    private function openSocket(): void
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! $sock) {
            throw new \RuntimeException('Cannot create TCP socket: ' . socket_strerror(socket_last_error()));
        }

        $tv = ['sec' => $this->timeout, 'usec' => 0];
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $tv);
        socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, $tv);

        if (! socket_connect($sock, $this->ip, $this->port)) {
            $err = socket_strerror(socket_last_error($sock));
            socket_close($sock);
            throw new \RuntimeException(
                "Cannot connect to device at {$this->ip}:{$this->port} — {$err}. " .
                "Ensure the device is on and the LAN cable is connected."
            );
        }

        $this->sock      = $sock;
        $this->sessionId = 0;
        $this->replyId   = self::USHRT_MAX - 1;
    }

    private function closeSocket(): void
    {
        if ($this->sock) {
            $pkt = $this->buildPacket(self::CMD_EXIT, '');
            @socket_write($this->sock, $pkt, strlen($pkt));
            @socket_close($this->sock);
            $this->sock = null;
        }
    }

    // ── Handshake ───────────────────────────────────────────────────────────

    private function handshake(): void
    {
        // Some devices send a banner/greeting when TCP connects — read and discard it
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        @socket_read($this->sock, 256, PHP_BINARY_READ);
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $resp = $this->sendCmd(self::CMD_CONNECT, '');

        if (strlen($resp) < 8) {
            throw new \RuntimeException(
                "Device at {$this->ip} did not respond to CMD_CONNECT (got " . strlen($resp) . " bytes). " .
                "Check COMM → PC Connection → Comm Key = 0 on the device."
            );
        }

        $h               = unpack('v4', substr($resp, 0, 8));
        $this->sessionId = $h[3];
        $this->replyId   = ($h[4] + 1) & self::USHRT_MAX;

        if ($this->sessionId === 0) {
            throw new \RuntimeException(
                "Device rejected connection (session_id=0). " .
                "Check COMM → PC Connection → Comm Key = 0 on the device."
            );
        }
    }

    // ── Attendance ──────────────────────────────────────────────────────────

    private function downloadAttendance(): string
    {
        $resp = $this->sendCmd(self::CMD_ATT_LOG_RRQ, '');
        if (strlen($resp) < 8) return '';

        $h = unpack('v4', substr($resp, 0, 8));

        if ($h[1] === self::CMD_ACK_OK) {
            return substr($resp, 8);
        }

        if ($h[1] === self::CMD_PREPARE_DATA) {
            return $this->readChunks();
        }

        return substr($resp, 8);
    }

    private function readChunks(): string
    {
        $buffer = '';

        for ($i = 0; $i < 200; $i++) {
            $raw = $this->readResponse();
            if ($raw === '') break;

            $h = unpack('v4', substr($raw, 0, 8));

            if ($h[1] === self::CMD_DATA) {
                $this->replyId = ($h[4] + 1) & self::USHRT_MAX;
                $buffer       .= substr($raw, 8);
            } elseif ($h[1] === self::CMD_ACK_OK) {
                break;
            } else {
                break;
            }
        }

        $pkt = $this->buildPacket(self::CMD_FREE_DATA, '');
        @socket_write($this->sock, $pkt, strlen($pkt));

        return $buffer;
    }

    // ── I/O ─────────────────────────────────────────────────────────────────

    private function sendCmd(int $command, string $data): string
    {
        $pkt = $this->buildPacket($command, $data);
        socket_write($this->sock, $pkt, strlen($pkt));
        return $this->readResponse();
    }

    /**
     * Build a ZKTeco TCP packet:
     *   [magic 4B] [payload_size 2B LE] [00 00] [command 2B] [checksum 2B] [session 2B] [reply 2B] [data]
     */
    private function buildPacket(int $command, string $data): string
    {
        $zk = pack('vvvv', $command, 0, $this->sessionId, $this->replyId) . $data;

        $sum = 0;
        foreach (unpack('C*', $zk) as $b) { $sum += $b; }
        $checksum = $sum & self::USHRT_MAX;

        $zk = pack('vvvv', $command, $checksum, $this->sessionId, $this->replyId) . $data;

        return self::TCP_MAGIC . pack('v', strlen($zk)) . "\x00\x00" . $zk;
    }

    /**
     * Read one response packet. Handles both TCP-wrapped and raw ZK responses.
     */
    private function readResponse(): string
    {
        if (! $this->sock) return '';

        // Read first 8 bytes
        $header = $this->readBytes(8);
        if (strlen($header) < 8) return '';

        // TCP magic prefix → strip 8-byte TCP header, read ZK payload
        if (substr($header, 0, 4) === self::TCP_MAGIC) {
            $size    = unpack('v', substr($header, 4, 2))[1];
            $payload = $size > 0 ? $this->readBytes($size) : '';
        } else {
            // Raw ZK response (no TCP wrapper) — the 8 bytes IS the payload
            $payload = $header;
            // Try to read any extra data
            $extra = @socket_read($this->sock, 4096, PHP_BINARY_READ);
            if ($extra) $payload .= $extra;
        }

        if (strlen($payload) >= 8) {
            $h             = unpack('v4', substr($payload, 0, 8));
            $this->replyId = ($h[4] + 1) & self::USHRT_MAX;
        }

        return $payload;
    }

    /**
     * Read exactly $n bytes from the socket, retrying until done or timeout.
     */
    private function readBytes(int $n): string
    {
        $data  = '';
        $start = time();

        while (strlen($data) < $n && (time() - $start) < $this->timeout) {
            $chunk = @socket_read($this->sock, $n - strlen($data), PHP_BINARY_READ);
            if ($chunk === false || $chunk === '') {
                usleep(5000);
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }

    // ── Parsing ─────────────────────────────────────────────────────────────

    private function parseRecords(string $raw): array
    {
        if (strlen($raw) > 4) {
            $count = unpack('V', substr($raw, 0, 4))[1];
            if ($count > 0 && ($count * 40 + 4) === strlen($raw)) {
                $raw = substr($raw, 4);
            }
        }

        $records = [];

        while (strlen($raw) >= 40) {
            $block = substr($raw, 0, 40);
            $raw   = substr($raw, 40);

            $u         = unpack('H78', substr($block, 0, 39));
            $hex       = $u[1];
            $id        = trim(str_replace(chr(0), '', hex2bin(substr($hex, 8, 18))));
            $state     = hexdec(substr($hex, 56, 2));
            $timeInt   = hexdec($this->reverseHex(substr($hex, 58, 8)));
            $timestamp = $this->decodeTime($timeInt);

            if ($id === '' || $timestamp === '') continue;

            $records[] = ['id' => $id, 'timestamp' => $timestamp, 'state' => $state];
        }

        return $records;
    }

    private function decodeTime(int $t): string
    {
        if ($t <= 0) return '';
        $second = $t % 60;     $t = intdiv($t, 60);
        $minute = $t % 60;     $t = intdiv($t, 60);
        $hour   = $t % 24;     $t = intdiv($t, 24);
        $day    = $t % 31 + 1; $t = intdiv($t, 31);
        $month  = $t % 12 + 1; $t = intdiv($t, 12);
        $year   = $t + 2000;
        if ($year < 2000 || $year > 2100) return '';
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    private function reverseHex(string $hex): string
    {
        return implode('', array_reverse(str_split($hex, 2)));
    }
}
