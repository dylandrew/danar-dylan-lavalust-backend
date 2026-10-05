<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    public function register()
    {
        $input = $this->request_body();
        $username = trim((string) ($input['username'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || strlen($username) > 100) {
            $this->api->respond_error('Username is required and must be at most 100 characters.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->api->respond_error('Enter a valid email address.', 422);
        }
        if (strlen($password) < 10) {
            $this->api->respond_error('Password must be at least 10 characters.', 422);
        }

        $existing = $this->db->raw(
            'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->api->respond_error('An account with that username or email already exists.', 409);
        }

        $columns = $this->db->raw('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $user_data = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'is_active' => 1,
        ];

        if (in_array('firstname', $columns, true)) {
            $user_data['firstname'] = $username;
        }
        if (in_array('lastname', $columns, true)) {
            $user_data['lastname'] = '';
        }

        $fields = array_keys($user_data);
        $field_sql = '`' . implode('`, `', $fields) . '`';
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $this->db->raw(
            "INSERT INTO users ({$field_sql}) VALUES ({$placeholders})",
            array_values($user_data)
        );

        $user = ['id' => $this->db->last_id(), 'username' => $username, 'email' => $email];
        $tokens = $this->api->issue_tokens(['id' => $user['id'], 'role' => 'user']);
        $this->api->respond(['user' => $user, 'tokens' => $tokens], 201);
    }

    public function login()
    {
        $input = $this->request_body();
        $identifier = trim((string) ($input['identifier'] ?? $input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $statement = $this->db->raw(
            'SELECT id, username, email, password, role FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1',
            [$identifier, $identifier]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid username/email or password.', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role'],
        ]);
        unset($user['password'], $user['role']);

        $this->api->respond(['user' => $user, 'tokens' => $tokens]);
    }

    public function refresh()
    {
        $input = $this->request_body();
        $refreshToken = (string) ($input['refresh_token'] ?? '');

        if ($refreshToken === '') {
            $this->api->respond_error('Refresh token is required.', 422);
        }

        $this->api->refresh_access_token($refreshToken);
    }

    public function logout()
    {
        $input = $this->request_body();
        $refreshToken = (string) ($input['refresh_token'] ?? '');
        $refreshPayload = $this->api->validate_jwt($refreshToken, 'refresh');

        if (!$refreshPayload) {
            $this->api->respond_error('Invalid refresh token.', 401);
        }

        if ($this->api->get_bearer_token()) {
            $identity = $this->api->require_jwt();
            if ((string) $refreshPayload['sub'] !== (string) $identity['sub']) {
                $this->api->respond_error('Refresh token does not belong to the authenticated user.', 403);
            }
        }

        $this->api->revoke_refresh_token($refreshToken);
        $this->api->respond(['message' => 'Logged out successfully.']);
    }

    public function profile()
    {
        $identity = $this->api->require_jwt();
        $user = $this->db->raw(
            'SELECT id, username, email, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1',
            [$identity['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }

        $this->api->respond(['user' => $user]);
    }

    private function request_body()
    {
        $input = $this->request->json();
        if (!is_array($input)) {
            $this->api->respond_error('Request body must be valid JSON.', 400);
        }
        return $input;
    }
}