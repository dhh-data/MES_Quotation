<?php
/**
 * Login, logout, dan ganti password.
 *
 * Rute (lihat public/index.php):
 *   GET  /login      loginForm
 *   POST /login      login
 *   POST /logout     logout
 *   GET  /password   passwordForm
 *   POST /password   passwordUpdate
 *
 * @package App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\UserModel;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::mustChangePassword() ? '/password' : '/quotations');
        }

        $this->view('login_view', ['pageTitle' => 'Masuk'], false);
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $username = $this->post('username');
        $ip       = $this->clientIp();
        $result   = Auth::attempt($username, (string) ($_POST['password'] ?? ''), $ip);

        if (!$result['ok']) {
            $this->flash('error', $result['error'] ?? 'Username atau password salah.');
            $this->flash('old', ['username' => mb_substr($username, 0, 50)]);
            $this->redirect('/login');
        }

        Auth::login($result['user']);
        $this->redirect((int) $result['user']['must_change_password'] === 1 ? '/password' : Auth::pullIntended());
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        $this->flash('success', 'Anda sudah keluar.');
        $this->redirect('/login');
    }

    public function passwordForm(): void
    {
        $this->view('change_password_view', [
            'pageTitle' => 'Ubah Password',
            'forced'    => Auth::mustChangePassword(),
            'minLength' => Auth::config('min_password'),
        ], false);
    }

    public function passwordUpdate(): void
    {
        $this->verifyCsrf();

        $me       = Auth::user();
        $ip       = $this->clientIp();
        $current  = (string) ($_POST['current_password'] ?? '');
        $new      = (string) ($_POST['new_password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');
        $model    = new UserModel();
        $row      = $model->findById((int) $me['id']);
        $username = (string) $me['username'];

        if ($row === null) {
            $this->redirect('/login');
        }
        if (Auth::isLocked($username, $ip)) {
            $this->flash('error', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . Auth::config('window_minutes') . ' menit.');
            $this->redirect('/password');
        }

        $errors = [];
        if (!password_verify($current, (string) $row['password_hash'])) {
            $errors['current_password'] = 'Password saat ini salah.';
            $model->recordFailure($username, $ip);
        }
        foreach ($this->passwordProblems($new, $username) as $problem) {
            $errors['new_password'] = $problem;
            break;
        }
        if ($errors === [] && hash_equals($current, $new)) {
            $errors['new_password'] = 'Password baru harus berbeda dari password saat ini.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Konfirmasi password tidak sama.';
        }

        if ($errors !== []) {
            $this->flash('errors', $errors);
            $this->redirect('/password');
        }

        $model->updatePassword((int) $me['id'], $new, false);
        $model->clearFailures($username, $ip);
        Auth::refresh();
        $this->flash('success', 'Password berhasil diganti.');
        $this->redirect('/quotations');
    }

    // ------------------------------------------------------------------

    /**
     * Daftar masalah pada password baru (kosong bila memenuhi aturan).
     *
     * @return list<string>
     */
    private function passwordProblems(string $password, string $username): array
    {
        $min = Auth::config('min_password');
        $out = [];

        if (strlen($password) > 72) {
            $out[] = 'Password maksimal 72 karakter.';
        }
        if (mb_strlen($password) < $min) {
            $out[] = "Password minimal {$min} karakter.";
        }
        if (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $out[] = 'Password harus memuat huruf dan angka.';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            $out[] = 'Password tidak boleh memuat username.';
        }

        return $out;
    }

    /** Alamat IP langsung dari koneksi (header proxy tidak dipercaya). */
    private function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }
}
