<?php
/**
 * Kesalahan konfigurasi (mis. .env tidak aman). Pesannya tidak pernah memuat
 * rahasia, sehingga aman ditampilkan kepada administrator.
 *
 * @package App\Core
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ConfigException extends RuntimeException
{
}
