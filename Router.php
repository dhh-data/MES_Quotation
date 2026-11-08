<?php
/**
 * Routing engine sederhana untuk MES Quotation.
 *
 * Fitur:
 *  - Metode GET dan POST (HEAD diperlakukan sebagai GET).
 *  - Rute dinamis: "/quotations/{id}/edit". Parameter bernama "id" otomatis
 *    hanya cocok dengan angka; parameter lain cocok dengan segmen apa pun
 *    kecuali "/". Pola khusus: "{slug:[a-z0-9-]+}" (tanpa kurung kurawal).
 *  - Handler berupa [Controller::class, 'method'] atau 'Controller@method'.
 *    Nama controller tanpa namespace dicari di App\Controllers.
 *  - Parameter URL dikirim ke method controller berdasarkan NAMA argumen,
 *    dan dikonversi sesuai tipe (int, float, bool).
 *  - Respons 404 (rute tidak ada) dan 405 (metode tidak diizinkan, lengkap
 *    dengan header Allow).
 *
 * Keamanan path: path dibersihkan dari karakter kontrol/NUL, segmen "." dan
 * "..", dan slash ganda sebelum dicocokkan. Handler hanya boleh berasal dari
 * namespace controller, dan hanya method public non-static yang dipanggil.
 *
 * @package App\Core
 */

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    private const MAX_PATH_LENGTH = 2048;

    /**
     * Daftar rute per metode HTTP.
     *
     * @var array<string, list<array{regex: string, class: string, action: string, names: list<string>}>>
     */
    private array $routes = ['GET' => [], 'POST' => []];

    /** Path dasar aplikasi, mis. "/mes-quotation/public" atau "". */
    private string $basePath;

    /** Namespace controller tanpa backslash di akhir. */
    private string $controllerNamespace;

    /** @var (callable(string): void)|null */
    private $notFoundHandler = null;

    /** @var (callable(string, string, string, string): void)|null */
    private $guard = null;

    /**
     * @param string $basePath            Path dasar aplikasi (config app.base_path)
     * @param string $controllerNamespace Namespace controller yang diizinkan
     */
    public function __construct(string $basePath = '', string $controllerNamespace = 'App\\Controllers')
    {
        $trimmed                   = trim($basePath, '/');
        $this->basePath            = $trimmed === '' ? '' : '/' . $trimmed;
        $this->controllerNamespace = trim($controllerNamespace, '\\');
    }

    /**
     * Mendaftarkan rute GET.
     *
     * @param array{0: string, 1: string}|string $handler
     */
    public function get(string $pattern, array|string $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    /**
     * Mendaftarkan rute POST.
     *
     * @param array{0: string, 1: string}|string $handler
     */
    public function post(string $pattern, array|string $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    /**
     * Mendaftarkan rute untuk metode tertentu.
     *
     * @param array{0: string, 1: string}|string $handler
     *
     * @throws InvalidArgumentException Bila metode, pola, atau handler tidak valid.
     */
    public function add(string $method, string $pattern, array|string $handler): self
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            throw new InvalidArgumentException("Metode HTTP tidak didukung: {$method}");
        }

        [$class, $action]    = $this->parseHandler($handler);
        [$regex, $paramNames] = $this->compile($pattern);

        $this->routes[$method][] = [
            'regex'  => $regex,
            'class'  => $class,
            'action' => $action,
            'names'  => $paramNames,
        ];

        return $this;
    }

    /**
     * Mengganti tampilan 404 bawaan.
     *
     * @param callable(string): void $handler Menerima path yang diminta.
     */
    public function setNotFoundHandler(callable $handler): self
    {
        $this->notFoundHandler = $handler;

        return $this;
    }

    /**
     * Memasang penjaga yang dipanggil tepat sebelum controller berjalan, dengan
     * argumen (kelas controller, nama action, metode HTTP, path rute). Penjaga
     * menghentikan request sendiri (redirect/403) bila tidak diizinkan.
     *
     * @param callable(string, string, string, string): void $guard
     */
    public function setGuard(callable $guard): self
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * Mencocokkan request dengan rute lalu memanggil controller.
     *
     * @param string|null $method Bawaan: $_SERVER['REQUEST_METHOD']
     * @param string|null $uri    Bawaan: $_SERVER['REQUEST_URI']
     *
     * @throws RuntimeException Bila handler terdaftar tetapi tidak bisa dipanggil.
     */
    public function dispatch(?string $method = null, ?string $uri = null): void
    {
        $method = strtoupper($method ?? (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $path = $this->resolvePath($uri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($path === null) {
            $this->abort(400, 'Permintaan tidak valid.');
            return;
        }

        if (!isset($this->routes[$method])) {
            header('Allow: ' . implode(', ', array_keys($this->routes)));
            $this->abort(405, 'Metode tidak diizinkan.');
            return;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = [];
                foreach ($route['names'] as $name) {
                    $params[$name] = rawurldecode($matches[$name]);
                }
                if ($this->guard !== null) {
                    ($this->guard)($route['class'], $route['action'], $method, $path);
                }
                $this->invoke($route['class'], $route['action'], $params, $path);
                return;
            }
        }

        // Path ada tetapi untuk metode lain: balas 405, bukan 404.
        $allowed = [];
        foreach ($this->routes as $otherMethod => $list) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($list as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    $allowed[] = $otherMethod;
                    break;
                }
            }
        }
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', $allowed));
            $this->abort(405, 'Metode tidak diizinkan untuk alamat ini.');
            return;
        }

        $this->notFound($path);
    }

    /**
     * Mengirim halaman galat sederhana. Bisa dipanggil dari controller.
     */
    public function abort(int $status, string $message = ''): void
    {
        $titles = [
            400 => 'Permintaan tidak valid',
            404 => 'Halaman tidak ditemukan',
            405 => 'Metode tidak diizinkan',
            500 => 'Terjadi kesalahan',
        ];
        $title = $titles[$status] ?? 'Terjadi kesalahan';

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        $t = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $m = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<title>{$status} {$t}</title>"
            . '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;color:#14231a;background:#f1f5ee}'
            . 'main{text-align:center;padding:24px}h1{font-size:64px;margin:0;color:#0b6a3b}p{margin:8px 0 0}</style></head>'
            . "<body><main><h1>{$status}</h1><p><strong>{$t}</strong></p>"
            . ($m !== '' ? "<p>{$m}</p>" : '') . '</main></body></html>';
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Mengubah handler menjadi pasangan [kelas lengkap, method] yang tervalidasi.
     *
     * @param array{0: string, 1: string}|string $handler
     *
     * @return array{0: string, 1: string}
     */
    private function parseHandler(array|string $handler): array
    {
        if (is_string($handler)) {
            $parts = explode('@', $handler);
            if (count($parts) !== 2) {
                throw new InvalidArgumentException("Format handler harus 'Controller@method': {$handler}");
            }
            [$class, $action] = $parts;
        } else {
            if (count($handler) !== 2) {
                throw new InvalidArgumentException('Handler array harus berisi [kelas, method].');
            }
            [$class, $action] = array_values($handler);
        }

        $class = ltrim((string) $class, '\\');
        if (!str_contains($class, '\\')) {
            $class = $this->controllerNamespace . '\\' . $class;
        }

        $identifier = '[A-Za-z_][A-Za-z0-9_]*';
        if (!preg_match("/^{$identifier}(?:\\\\{$identifier})*$/", $class)
            || !preg_match("/^{$identifier}$/", (string) $action)
            || str_starts_with((string) $action, '__')) {
            throw new InvalidArgumentException("Handler tidak valid: {$class}::{$action}");
        }
        if (!str_starts_with($class, $this->controllerNamespace . '\\')) {
            throw new InvalidArgumentException("Controller harus berada di namespace {$this->controllerNamespace}: {$class}");
        }

        return [$class, (string) $action];
    }

    /**
     * Mengubah pola rute menjadi regular expression.
     *
     * @return array{0: string, 1: list<string>} [regex, daftar nama parameter]
     */
    private function compile(string $pattern): array
    {
        if (str_contains($pattern, '#')) {
            throw new InvalidArgumentException("Pola rute tidak boleh memuat '#': {$pattern}");
        }

        $pattern = '/' . trim($pattern, '/');
        $regex   = '';
        $names   = [];
        $offset  = 0;

        preg_match_all(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(?::([^{}]+))?\}/',
            $pattern,
            $found,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($found as $set) {
            $start = $set[0][1];
            $regex .= preg_quote(substr($pattern, $offset, $start - $offset), '#');

            $name = $set[1][0];
            if (in_array($name, $names, true)) {
                throw new InvalidArgumentException("Nama parameter ganda '{$name}' pada rute: {$pattern}");
            }
            $expr = (isset($set[2]) && $set[2][1] >= 0)
                ? $set[2][0]
                : ($name === 'id' ? '\d+' : '[^/]+');

            $regex   .= '(?P<' . $name . '>' . $expr . ')';
            $names[]  = $name;
            $offset   = $start + strlen($set[0][0]);
        }
        $regex .= preg_quote(substr($pattern, $offset), '#');

        return ['#^' . $regex . '$#D', $names];
    }

    /**
     * Mengambil path rute dari URI: membuang query string, base path, slash
     * ganda dan slash akhir. Mengembalikan null bila URI berbahaya/cacat.
     */
    private function resolvePath(string $uri): ?string
    {
        if (strlen($uri) > self::MAX_PATH_LENGTH) {
            return null;
        }

        // "//quotations" akan dibaca parse_url sebagai host, jadi slash awal diringkas dulu.
        $uri  = preg_replace('#^/{2,}#', '/', $uri) ?? $uri;
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return is_string($path) ? '/' : null;
        }

        // Tolak NUL dan karakter kontrol, juga bila disamarkan dengan %00.
        if (preg_match('/[\x00-\x1F\x7F]/', rawurldecode($path)) === 1) {
            return null;
        }

        $path = preg_replace('#/{2,}#', '/', $path) ?? '/';

        if ($this->basePath !== '') {
            if ($path === $this->basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $this->basePath . '/')) {
                $path = substr($path, strlen($this->basePath));
            } else {
                return null;
            }
        }

        // Tolak segmen titik, termasuk yang di-encode (%2e%2e).
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Membuat controller dan memanggil method dengan argumen dari URL.
     *
     * @param array<string, string> $params
     *
     * @throws RuntimeException
     */
    private function invoke(string $class, string $action, array $params, string $path): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Controller tidak ditemukan: {$class}");
        }

        try {
            $method = new ReflectionMethod($class, $action);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Method tidak ditemukan: {$class}::{$action}", 0, $e);
        }

        if (!$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
            throw new RuntimeException("Method tidak dapat dipanggil sebagai action: {$class}::{$action}");
        }

        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (!array_key_exists($name, $params)) {
                if ($parameter->isDefaultValueAvailable() || $parameter->isOptional()) {
                    continue;
                }
                throw new RuntimeException("Parameter '{$name}' pada {$class}::{$action} tidak ada di pola rute.");
            }

            $value = $params[$name];
            $type  = $parameter->getType();
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                $value = match ($type->getName()) {
                    'int'   => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                    'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                    default => $value,
                };
                if ($value === null) {
                    $this->notFound($path); // nilai tidak cocok dengan tipe argumen
                    return;
                }
            }
            $args[$name] = $value;
        }

        $controller = new $class();
        $controller->{$action}(...$args);
    }

    /**
     * Menjalankan handler 404 khusus atau halaman bawaan.
     */
    private function notFound(string $path): void
    {
        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)($path);
            return;
        }
        $this->abort(404, 'Alamat yang Anda minta tidak ada.');
    }
}
